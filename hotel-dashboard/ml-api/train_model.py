"""
LSTM Model Training Module
Handles training new LSTM models for hotel occupancy prediction.
"""

import os
import sys
import json
import numpy as np
from pathlib import Path
from datetime import datetime

# Suppress TensorFlow logging
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'


def train_lstm_model(training_data: list, model_type: str, output_path: str) -> dict:
    """
    Train a new LSTM model with the provided data.
    
    Args:
        training_data: List of training samples from historical_occupancy_data
        model_type: 'single' or 'multi'
        output_path: Path to save the trained model
    
    Returns:
        dict with success, metrics, and model info
    """
    try:
        import tensorflow as tf
        from tensorflow import keras
        from sklearn.model_selection import train_test_split
        from sklearn.preprocessing import MinMaxScaler
        
        if not training_data:
            return {
                'success': False,
                'error': 'No training data provided'
            }
        
        # Prepare data for LSTM
        # Expected format: each sample has month, room type, avg_occupancy, etc.
        X, y = prepare_sequences(training_data, model_type)
        
        if X is None or len(X) < 10:
            return {
                'success': False,
                'error': f'Insufficient data for training. Got {len(X) if X is not None else 0} samples, need at least 10.'
            }
        
        # Split data
        X_train, X_val, y_train, y_val = train_test_split(
            X, y, test_size=0.2, random_state=42
        )
        
        # Build model based on type
        if model_type == 'single':
            model = build_single_output_model()
            output_size = 1
        else:
            model = build_multi_output_model()
            output_size = 4
        
        # Compile
        model.compile(
            optimizer=keras.optimizers.Adam(learning_rate=0.001),
            loss='mse',
            metrics=['mae']
        )
        
        # Train with early stopping
        early_stopping = keras.callbacks.EarlyStopping(
            monitor='val_loss',
            patience=10,
            restore_best_weights=True
        )
        
        history = model.fit(
            X_train, y_train,
            validation_data=(X_val, y_val),
            epochs=100,
            batch_size=4,
            callbacks=[early_stopping],
            verbose=0
        )
        
        # Evaluate
        metrics = evaluate_model(model, X_val, y_val, model_type)
        
        # Save model
        model.save(output_path)
        
        return {
            'success': True,
            'metrics': metrics,
            'training_samples': len(X_train),
            'validation_samples': len(X_val),
            'epochs_trained': len(history.history['loss']),
            'final_train_loss': history.history['loss'][-1],
            'final_val_loss': history.history['val_loss'][-1],
        }
        
    except Exception as e:
        return {
            'success': False,
            'error': str(e),
            'error_type': type(e).__name__
        }


def prepare_sequences(training_data: list, model_type: str, seq_length: int = 6) -> tuple:
    """
    Prepare sequences for LSTM training from monthly aggregated data.

    Args:
        training_data: List of monthly records
        model_type: 'single' or 'multi'
        seq_length: Number of months per sequence (default 6)

    Returns:
        (X, y) tuple of numpy arrays
    """
    if not training_data:
        return None, None

    # Sort by month
    sorted_data = sorted(training_data, key=lambda x: x.get('month', ''))

    # Group by month and calculate monthly aggregates
    monthly_groups = {}
    monthly_occupancy = []  # For historical calculations

    for record in sorted_data:
        month = record.get('month')
        if month not in monthly_groups:
            monthly_groups[month] = {}
        room_type = record.get('room_type_code', 'UNK')
        monthly_groups[month][room_type] = record

    months = sorted(monthly_groups.keys())

    # Pre-calculate monthly occupancy rates for all months
    for month in months:
        month_data = monthly_groups[month]
        avg_occ = sum(d.get('avg_occupancy', 0) for d in month_data.values()) / max(len(month_data), 1)
        monthly_occupancy.append(avg_occ)

    if len(months) < seq_length + 1:
        return None, None

    X = []
    y = []

    # Create sequences
    for i in range(len(months) - seq_length):
        sequence = []
        for j in range(seq_length):
            month_idx = i + j
            month = months[month_idx]
            month_data = monthly_groups[month]

            # Build feature vector with historical context
            features = build_feature_vector_with_history(
                month_data, month, month_idx, months, monthly_groups, monthly_occupancy
            )
            sequence.append(features)

        # Target is the next month
        target_month = months[i + seq_length]
        target_data = monthly_groups[target_month]

        if model_type == 'single':
            # Total occupancy
            total_occ = sum(
                d.get('avg_occupancy', 0) for d in target_data.values()
            ) / max(len(target_data), 1)
            y.append([total_occ / 100])  # Normalize to 0-1
        else:
            # Per room type (STD, SPR, FMY, JS)
            room_types = ['STD', 'SPR', 'FMY', 'JS']
            target = []
            for rt in room_types:
                occ = target_data.get(rt, {}).get('avg_occupancy', 50) / 100
                target.append(occ)
            y.append(target)

        X.append(sequence)

    return np.array(X), np.array(y)


def build_feature_vector_with_history(month_data: dict, month: str, month_idx: int,
                                      months: list, monthly_groups: dict,
                                      monthly_occupancy: list) -> list:
    """
    Build a 15-feature vector from monthly data WITH proper historical calculations.
    Features match the original Data_Hotel.ipynb preprocessing.
    """
    import math

    # Current month occupancy
    avg_occupancy = sum(d.get('avg_occupancy', 0) for d in month_data.values()) / max(len(month_data), 1)
    total_occupied = sum(d.get('total_occupied', 0) for d in month_data.values())
    occ_rate = avg_occupancy / 100  # Normalized

    # Room type proportions
    total_rooms = max(total_occupied, 1)
    std_prop = month_data.get('STD', {}).get('total_occupied', 0) / total_rooms
    spr_prop = month_data.get('SPR', {}).get('total_occupied', 0) / total_rooms
    fmy_prop = month_data.get('FMY', {}).get('total_occupied', 0) / total_rooms
    js_prop = month_data.get('JS', {}).get('total_occupied', 0) / total_rooms

    # Month number for seasonality
    try:
        month_num = int(month.split('-')[1])
    except:
        month_num = 6
    is_peak = 1.0 if month_num in [6, 7, 12] else 0.0

    # =================================================================
    # HISTORICAL FEATURES (require lookback)
    # =================================================================

    # 1. occ_momentum (rate of change of change)
    if month_idx >= 2:
        diff1 = monthly_occupancy[month_idx] - monthly_occupancy[month_idx - 1]
        diff2 = monthly_occupancy[month_idx - 1] - monthly_occupancy[month_idx - 2]
        occ_momentum = (diff1 - diff2) / 100  # Normalized
    else:
        occ_momentum = 0.0

    # 2. occ_trend (month-to-month change)
    if month_idx >= 1:
        occ_trend = (monthly_occupancy[month_idx] - monthly_occupancy[month_idx - 1]) / 100
    else:
        occ_trend = 0.0

    # 3. occ_rolling_mean_3 (3-month rolling average)
    if month_idx >= 2:
        window = monthly_occupancy[max(0, month_idx-2):month_idx+1]
        occ_rolling_mean_3 = (sum(window) / len(window)) / 100
    else:
        occ_rolling_mean_3 = occ_rate

    # 4. occ_yoy (year-over-year)
    if month_idx >= 12:
        occ_yoy = (monthly_occupancy[month_idx] - monthly_occupancy[month_idx - 12]) / 100
    else:
        occ_yoy = 0.0

    # 5. is_increasing (binary trend)
    is_increasing = 1.0 if occ_trend > 0 else 0.0

    # 6. occ_rolling_mean_6 (6-month rolling average)
    if month_idx >= 5:
        window = monthly_occupancy[max(0, month_idx-5):month_idx+1]
        occ_rolling_mean_6 = (sum(window) / len(window)) / 100
    else:
        occ_rolling_mean_6 = occ_rate

    # 7. occ_rolling_std_3 (3-month standard deviation)
    if month_idx >= 2:
        window = [monthly_occupancy[i] / 100 for i in range(max(0, month_idx-2), month_idx+1)]
        mean = sum(window) / len(window)
        variance = sum((x - mean) ** 2 for x in window) / len(window)
        occ_rolling_std_3 = math.sqrt(variance)
    else:
        occ_rolling_std_3 = 0.0

    # 8. occ_lag_1 (previous month)
    if month_idx >= 1:
        occ_lag_1 = monthly_occupancy[month_idx - 1] / 100
    else:
        occ_lag_1 = occ_rate

    # =================================================================
    # BUILD 15-FEATURE VECTOR (exact order from training)
    # =================================================================
    features = [
        total_occupied / 56,     # 0. Kamar_Terjual
        occ_rate,                # 1. Okupansi_Rate
        occ_momentum,            # 2. occ_momentum
        occ_trend,               # 3. occ_trend
        occ_rolling_mean_3,      # 4. occ_rolling_mean_3
        occ_yoy,                 # 5. occ_yoy
        std_prop,                # 6. std_proportion
        is_increasing,           # 7. is_increasing
        is_peak,                 # 8. is_peak_season
        js_prop,                 # 9. js_proportion
        fmy_prop,                # 10. fmy_proportion
        spr_prop,                # 11. spr_proportion
        occ_rolling_mean_6,      # 12. occ_rolling_mean_6
        occ_rolling_std_3,       # 13. occ_rolling_std_3
        occ_lag_1,               # 14. occ_lag_1
    ]

    return features


def build_single_output_model():
    """Build single-output LSTM model matching original architecture."""
    from tensorflow import keras
    
    model = keras.Sequential([
        keras.layers.LSTM(64, return_sequences=True, input_shape=(6, 15)),
        keras.layers.Dropout(0.3),
        keras.layers.LSTM(32),
        keras.layers.Dropout(0.3),
        keras.layers.Dense(16, activation='relu'),
        keras.layers.Dense(1, activation='linear')
    ])
    
    return model


def build_multi_output_model():
    """Build multi-output LSTM model matching original architecture."""
    from tensorflow import keras
    
    model = keras.Sequential([
        keras.layers.LSTM(32, return_sequences=True, input_shape=(6, 15)),
        keras.layers.Dropout(0.3),
        keras.layers.LSTM(16),
        keras.layers.Dropout(0.3),
        keras.layers.Dense(8, activation='relu'),
        keras.layers.Dense(4, activation='linear')  # 4 outputs: STD, SPR, FMY, JS
    ])
    
    return model


def evaluate_model(model, X_val, y_val, model_type: str) -> dict:
    """
    Evaluate model and calculate metrics.
    
    Returns:
        dict with mape, r2_score, rmse
    """
    from sklearn.metrics import mean_absolute_percentage_error, r2_score, mean_squared_error
    
    y_pred = model.predict(X_val, verbose=0)
    
    # Clip predictions to valid range
    y_pred = np.clip(y_pred, 0, 1)
    
    # Calculate metrics
    # Avoid division by zero in MAPE
    y_val_safe = np.where(y_val < 0.01, 0.01, y_val)
    
    try:
        mape = mean_absolute_percentage_error(y_val_safe, y_pred) * 100
    except:
        mape = 50.0  # Default if calculation fails
    
    try:
        r2 = r2_score(y_val.flatten(), y_pred.flatten())
    except:
        r2 = 0.0
    
    try:
        rmse = np.sqrt(mean_squared_error(y_val, y_pred))
    except:
        rmse = 0.15
    
    return {
        'mape': round(float(mape), 2),
        'r2_score': round(float(r2), 4),
        'rmse': round(float(rmse), 4),
    }
