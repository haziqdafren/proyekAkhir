"""
Flask ML API Server for Hotel Occupancy Prediction
Provides endpoints for model training, retraining, and predictions.
"""

import os
import re
import sys
import json
import time
import shutil
from datetime import datetime
from pathlib import Path

from functools import wraps
from flask import Flask, request, jsonify
from flask_cors import CORS
import numpy as np

# Suppress TensorFlow logging
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'

app = Flask(__name__)

# CORS — only allow requests from the Laravel app (localhost in dev, set ML_ALLOWED_ORIGIN in prod)
_allowed_origin = os.getenv('ML_ALLOWED_ORIGIN', 'http://localhost:8000')
CORS(app, resources={
    r"/api/*": {
        "origins": [_allowed_origin, 'http://127.0.0.1:8000'],
        "methods": ["GET", "POST"],
        "allow_headers": ["Content-Type", "X-API-Key"],
    }
})

# Configuration
app.config['MAX_CONTENT_LENGTH'] = 50 * 1024 * 1024  # 50MB request size limit

# ── API key authentication ─────────────────────────────────────────────────
_API_KEY = os.getenv('ML_API_KEY', '')

def require_api_key(f):
    """Decorator: reject requests that don't supply the correct X-API-Key header."""
    @wraps(f)
    def decorated(*args, **kwargs):
        # Skip auth in development if no key is configured
        if _API_KEY and request.headers.get('X-API-Key') != _API_KEY:
            return jsonify({'success': False, 'error': 'Unauthorized'}), 401
        return f(*args, **kwargs)
    return decorated


_VALID_MODEL_TYPES = {'single', 'multi'}
_VALID_VERSION_RE  = re.compile(r'^\d+\.\d+\.\d+$')

def validate_model_type(model_type: str):
    """Return (model_type, error_response) — error_response is None when valid (#4)."""
    if model_type not in _VALID_MODEL_TYPES:
        return None, jsonify({'success': False, 'error': f'Invalid model_type: {model_type!r}. Must be "single" or "multi"'}), 400
    return model_type, None

def validate_version(version: str):
    """Return (version, error_response) — error_response is None when valid (#4)."""
    if not version or not _VALID_VERSION_RE.match(str(version)):
        return None, jsonify({'success': False, 'error': 'Invalid version format. Expected x.y.z'}), 400
    return version, None

@app.errorhandler(413)
def request_entity_too_large(error):
    """Handle request size limit exceeded"""
    return jsonify({
        'success': False,
        'error': 'Request too large. Maximum size is 50MB.',
        'error_type': 'PayloadTooLarge'
    }), 413

BASE_DIR = Path(__file__).parent.parent
MODELS_DIR = BASE_DIR / 'storage' / 'app' / 'models'
ORIGINAL_MODELS_DIR = BASE_DIR.parent  # /proyekAkhir directory
VENV_PYTHON = BASE_DIR / 'venv' / 'bin' / 'python3'

# Ensure directories exist
(MODELS_DIR / 'single').mkdir(parents=True, exist_ok=True)
(MODELS_DIR / 'multi').mkdir(parents=True, exist_ok=True)


@app.route('/api/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'ok',
        'timestamp': datetime.now().isoformat(),
        'models_dir': str(MODELS_DIR),
        'single_champion_exists': (MODELS_DIR / 'single' / 'champion.keras').exists(),
        'multi_champion_exists': (MODELS_DIR / 'multi' / 'champion.keras').exists(),
    })


@app.route('/api/retrain', methods=['POST'])
@require_api_key
def retrain_model():
    """
    Retrain a model with new data.
    
    Request body:
    {
        "model_type": "single" or "multi",
        "training_data": [...],  # Optional, for actual training
        "test_mode": true/false,  # If true, uses existing data
        "incremental": true/false
    }
    
    Response:
    {
        "success": true/false,
        "model_path": "path/to/new/model.keras",
        "metrics": {"mape": ..., "r2_score": ..., "rmse": ...},
        "metadata": {...}
    }
    """
    try:
        data = request.get_json()
        model_type, err = validate_model_type(data.get('model_type', 'single'))
        if err:
            return err
        test_mode = data.get('test_mode', False)
        training_data = data.get('training_data', [])

        # Security: test_mode (simulate) only allowed in development
        # Production environment check via FLASK_ENV or APP_ENV
        is_production = os.getenv('FLASK_ENV') == 'production' or os.getenv('APP_ENV') == 'production'
        if test_mode and is_production:
            return jsonify({
                'success': False,
                'error': 'test_mode is not allowed in production environment'
            }), 403

        # Import TensorFlow here to avoid startup delay
        import tensorflow as tf
        from train_model import train_lstm_model
        
        # Generate version number
        version = generate_next_version(model_type)
        model_filename = f'v{version}.keras'
        model_path = MODELS_DIR / model_type / model_filename
        
        start_time = time.time()
        
        if test_mode:
            # Test mode: copy existing model and simulate training
            result = simulate_training(model_type, model_path)
        else:
            # Real training with provided data
            result = train_lstm_model(
                training_data=training_data,
                model_type=model_type,
                output_path=str(model_path)
            )
        
        training_duration = time.time() - start_time
        
        if not result['success']:
            return jsonify(result), 400
        
        return jsonify({
            'success': True,
            'version': version,
            'model_path': str(model_path),
            'metrics': result['metrics'],
            'metadata': {
                'training_duration_seconds': round(training_duration, 2),
                'trained_samples': len(training_data) if training_data else 0,
                'test_mode': test_mode,
                'timestamp': datetime.now().isoformat(),
            }
        })
        
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e),
            'error_type': type(e).__name__
        }), 500


@app.route('/api/predict', methods=['POST'])
@require_api_key
def predict():
    """
    Make prediction using champion model.
    
    Request body:
    {
        "model_type": "single" or "multi",
        "features": [[...], [...], ...],  # Shape: (6, 15)
        "model_path": "optional/path/to/model.keras"
    }
    """
    try:
        data = request.get_json()
        model_type, err = validate_model_type(data.get('model_type', 'single'))
        if err:
            return err
        features = data.get('features')

        if features is None:
            return jsonify({'success': False, 'error': 'Missing features'}), 400

        # Validate shape (6, 15) before reshape
        if (not isinstance(features, list) or len(features) != 6
                or not all(isinstance(m, list) and len(m) == 15 for m in features)):
            return jsonify({'success': False, 'error': 'features must be shape (6, 15)'}), 400

        # Always use champion — no custom_model_path to prevent path traversal
        model_path = MODELS_DIR / model_type / 'champion.keras'
        
        if not model_path.exists():
            return jsonify({
                'success': False, 
                'error': f'Model not found: {model_path}'
            }), 404
        
        # Load and predict
        import tensorflow as tf
        model = tf.keras.models.load_model(str(model_path))
        
        features_array = np.array(features).reshape(1, 6, 15)
        prediction = model.predict(features_array, verbose=0)
        
        if model_type == 'single':
            result = {
                'success': True,
                'model_type': 'single',
                'model_path': str(model_path),
                'prediction': {
                    'normalized_occupancy': float(prediction[0][0]),
                    'confidence': {'note': 'Champion model prediction'}
                }
            }
        else:
            # Multi-output: 4 values for STD, SPR, FMY, JS
            room_types = ['STD', 'SPR', 'FMY', 'JS']
            predictions = {}
            for i, rt in enumerate(room_types):
                predictions[rt] = {
                    'normalized_occupancy': float(prediction[0][i]) if i < len(prediction[0]) else 0.0
                }
            result = {
                'success': True,
                'model_type': 'multi',
                'model_path': str(model_path),
                'predictions': predictions
            }
        
        return jsonify(result)
        
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e),
            'error_type': type(e).__name__
        }), 500


@app.route('/api/copy-champion', methods=['POST'])
@require_api_key
def copy_champion():
    """
    Copy a specific model version to champion.
    
    Request body:
    {
        "model_type": "single" or "multi",
        "version": "1.0.0"
    }
    """
    try:
        data = request.get_json()
        model_type, err = validate_model_type(data.get('model_type', 'single'))
        if err:
            return err
        version, err = validate_version(data.get('version'))
        if err:
            return err

        source_path = MODELS_DIR / model_type / f'v{version}.keras'
        champion_path = MODELS_DIR / model_type / 'champion.keras'
        
        if not source_path.exists():
            return jsonify({
                'success': False,
                'error': f'Model version not found: {source_path}'
            }), 404
        
        shutil.copy2(source_path, champion_path)
        
        return jsonify({
            'success': True,
            'message': f'Model v{version} is now champion for {model_type}',
            'champion_path': str(champion_path)
        })
        
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500


@app.route('/api/init-models', methods=['POST'])
@require_api_key
def init_models():
    """
    Initialize model storage by copying original models as v1.0.0 and champion.
    This should be called once during setup.
    """
    try:
        results = {}
        
        # Single output model
        single_source = ORIGINAL_MODELS_DIR / 'single_output' / 'lstm_single_final.keras'
        if single_source.exists():
            single_v1 = MODELS_DIR / 'single' / 'v1.0.0.keras'
            single_champion = MODELS_DIR / 'single' / 'champion.keras'
            shutil.copy2(single_source, single_v1)
            shutil.copy2(single_source, single_champion)
            results['single'] = {
                'success': True,
                'source': str(single_source),
                'v1_path': str(single_v1),
                'champion_path': str(single_champion)
            }
        else:
            results['single'] = {'success': False, 'error': f'Source not found: {single_source}'}
        
        # Multi output model
        multi_source = ORIGINAL_MODELS_DIR / 'multi_output' / 'lstm_multi_final.keras'
        if multi_source.exists():
            multi_v1 = MODELS_DIR / 'multi' / 'v1.0.0.keras'
            multi_champion = MODELS_DIR / 'multi' / 'champion.keras'
            shutil.copy2(multi_source, multi_v1)
            shutil.copy2(multi_source, multi_champion)
            results['multi'] = {
                'success': True,
                'source': str(multi_source),
                'v1_path': str(multi_v1),
                'champion_path': str(multi_champion)
            }
        else:
            results['multi'] = {'success': False, 'error': f'Source not found: {multi_source}'}
        
        return jsonify({
            'success': all(r.get('success', False) for r in results.values()),
            'results': results
        })
        
    except Exception as e:
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500


def generate_next_version(model_type: str) -> str:
    """Generate next version number based on existing models"""
    model_dir = MODELS_DIR / model_type
    existing_versions = []
    
    for f in model_dir.glob('v*.keras'):
        try:
            version_str = f.stem[1:]  # Remove 'v' prefix
            parts = version_str.split('.')
            if len(parts) >= 2:
                existing_versions.append((int(parts[0]), int(parts[1])))
        except ValueError:
            continue
    
    if not existing_versions:
        return '1.0.0'
    
    # Get highest version and increment minor
    latest = max(existing_versions)
    return f'{latest[0]}.{latest[1] + 1}.0'


def simulate_training(model_type: str, output_path: Path) -> dict:
    """
    Simulate training by copying existing champion and generating random metrics.
    Includes a realistic delay (simulated epochs) so the demo feels like real training.
    Used for testing the pipeline without running actual LSTM training.
    """
    import random

    champion_path = MODELS_DIR / model_type / 'champion.keras'

    # If champion exists, copy it
    if champion_path.exists():
        shutil.copy2(champion_path, output_path)
    else:
        # If no champion, copy from original
        if model_type == 'single':
            source = ORIGINAL_MODELS_DIR / 'single_output' / 'lstm_single_final.keras'
        else:
            source = ORIGINAL_MODELS_DIR / 'multi_output' / 'lstm_multi_final.keras'

        if source.exists():
            shutil.copy2(source, output_path)
        else:
            return {'success': False, 'error': 'No source model found for simulation'}

    # Simulate realistic epoch-by-epoch training time.
    # Real training on 11 months of data stops at ~20-40 epochs (patience=10).
    # We simulate ~25 epochs at ~1s each = ~25s total — believable for a CPU run.
    simulated_epochs = random.randint(20, 35)
    epoch_time = random.uniform(0.6, 1.2)  # seconds per epoch (CPU speed)
    time.sleep(simulated_epochs * epoch_time)

    # Generate realistic random metrics based on current champion (if exists)
    try:
        metadata_path = MODELS_DIR / model_type / 'champion_metadata.json'
        if metadata_path.exists():
            with open(metadata_path, 'r') as f:
                champion_meta = json.load(f)
                base_mape = champion_meta.get('mape', 20.0)
                base_r2 = champion_meta.get('r2_score', 0.3)
        else:
            if model_type == 'single':
                base_mape = 17.18
                base_r2 = 0.4208
            else:
                base_mape = 32.39
                base_r2 = 0.214
    except:
        base_mape = 20.0
        base_r2 = 0.3

    # Add some randomness (±10% variation)
    mape_variation = random.uniform(-0.10, 0.10)
    r2_variation = random.uniform(-0.08, 0.08)

    return {
        'success': True,
        'metrics': {
            'mape': round(base_mape * (1 + mape_variation), 2),
            'r2_score': round(min(1.0, max(0.0, base_r2 + r2_variation)), 4),
            'rmse': round(random.uniform(0.08, 0.18), 4),
        },
        'simulated': True,
        'epochs_trained': simulated_epochs,
    }


if __name__ == '__main__':
    print(f"Models directory: {MODELS_DIR}")
    print(f"Starting Flask ML API server on http://127.0.0.1:5000")
    app.run(host='127.0.0.1', port=5000, debug=True)
