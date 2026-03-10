"""
Flask ML API Server for Hotel Occupancy Prediction
Provides endpoints for model training, retraining, and predictions.
"""

import os
import sys
import json
import time
import shutil
from datetime import datetime
from pathlib import Path

from flask import Flask, request, jsonify
from flask_cors import CORS
import numpy as np

# Suppress TensorFlow logging
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'

app = Flask(__name__)
CORS(app)

# Configuration
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
        model_type = data.get('model_type', 'single')
        test_mode = data.get('test_mode', False)
        training_data = data.get('training_data', [])
        
        # Import TensorFlow here to avoid startup delay
        import tensorflow as tf
        from train_model import train_lstm_model, evaluate_model
        
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
        model_type = data.get('model_type', 'single')
        features = data.get('features')
        custom_model_path = data.get('model_path')
        
        if features is None:
            return jsonify({'success': False, 'error': 'Missing features'}), 400
        
        # Determine model path
        if custom_model_path:
            model_path = Path(custom_model_path)
        else:
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
        model_type = data.get('model_type', 'single')
        version = data.get('version')
        
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
    Used for testing the pipeline without actual training.
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
    
    # Generate realistic random metrics
    # Single output baseline: MAPE 17.18%, R² 0.4208
    # Multi output baseline: MAPE 32.39%, R² 0.214
    if model_type == 'single':
        base_mape = 17.18
        base_r2 = 0.4208
    else:
        base_mape = 32.39
        base_r2 = 0.214
    
    # Add some randomness (±15% variation)
    mape_variation = random.uniform(-0.15, 0.15)
    r2_variation = random.uniform(-0.1, 0.1)
    
    return {
        'success': True,
        'metrics': {
            'mape': round(base_mape * (1 + mape_variation), 2),
            'r2_score': round(min(1.0, max(0.0, base_r2 + r2_variation)), 4),
            'rmse': round(random.uniform(0.08, 0.18), 4),
        },
        'simulated': True
    }


if __name__ == '__main__':
    print(f"Models directory: {MODELS_DIR}")
    print(f"Starting Flask ML API server on http://127.0.0.1:5000")
    app.run(host='127.0.0.1', port=5000, debug=True)
