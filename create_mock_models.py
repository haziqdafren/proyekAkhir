#!/usr/bin/env python3
"""
Create Mock Model Files for Dashboard Demo
Generates dummy model files so dashboard can run without trained LSTM model
"""

import numpy as np
import pandas as pd
import pickle
import os

print("="*80)
print("🔧 Creating Mock Model Files for Dashboard Demo")
print("="*80)
print()

# Create directories
os.makedirs('models/checkpoints', exist_ok=True)
print("✓ Created models/checkpoints directory")

# 1. Create mock scalers
print("\n📊 Creating mock scalers...")

class MockScaler:
    """Mock MinMaxScaler for demo purposes"""
    def __init__(self, n_features):
        self.n_features = n_features
        self.data_min_ = np.zeros(n_features)
        self.data_max_ = np.ones(n_features) * 100
        self.scale_ = 1 / (self.data_max_ - self.data_min_)

    def transform(self, X):
        return (X - self.data_min_) / (self.data_max_ - self.data_min_)

    def inverse_transform(self, X):
        return X * (self.data_max_ - self.data_min_) + self.data_min_

# Feature scaler (35 features)
scaler_X = MockScaler(35)
with open('models/scaler_X_optimized.pkl', 'wb') as f:
    pickle.dump(scaler_X, f)
print("✓ Created scaler_X_optimized.pkl (35 features)")

# Target scaler (4 room types)
scaler_y = MockScaler(4)
with open('models/scaler_y_optimized.pkl', 'wb') as f:
    pickle.dump(scaler_y, f)
print("✓ Created scaler_y_optimized.pkl (4 targets)")

# 2. Create mock model using Keras
print("\n🤖 Creating mock LSTM model...")

try:
    from tensorflow import keras
    from tensorflow.keras import layers

    # Build simple model matching our architecture
    model = keras.Sequential([
        layers.Input(shape=(12, 35)),
        layers.LSTM(32, return_sequences=True, dropout=0.3),
        layers.LSTM(16, return_sequences=False, dropout=0.3),
        layers.Dense(8, activation='relu'),
        layers.Dense(4, activation='linear')
    ])

    model.compile(optimizer='adam', loss='mse', metrics=['mae'])

    # Save model
    model.save('models/checkpoints/best_model_optimized.h5')
    print("✓ Created best_model_optimized.h5 (LSTM model)")
    print(f"  Total parameters: {model.count_params():,}")

except Exception as e:
    print(f"⚠️  Could not create Keras model: {e}")
    print("  Dashboard will use sample data instead")

# 3. Create mock historical data CSV
print("\n📈 Creating mock historical data...")

# Generate 58 months of sample data (2021-2025)
dates = pd.date_range('2021-01-01', periods=58, freq='MS')
data = {
    'Tahun': dates.year,
    'Bulan': dates.month,
    'Tahun_Bulan': [f"{d.year}-{d.month:02d}" for d in dates],
}

# Add target variables (room occupancy rates)
np.random.seed(42)
data['STD_Occ'] = np.random.uniform(60, 90, 58)  # Standard: 60-90%
data['SPR_Occ'] = np.random.uniform(55, 85, 58)  # Superior: 55-85%
data['FMY_Occ'] = np.random.uniform(50, 80, 58)  # Family: 50-80%
data['JS_Occ'] = np.random.uniform(52, 82, 58)   # Junior Suite: 52-82%

# Add 35 dummy features (to match model input)
for i in range(1, 36):
    data[f'feature_{i}'] = np.random.randn(58)

df = pd.DataFrame(data)
df.to_csv('monthly_enhanced_features.csv', index=False)
print(f"✓ Created monthly_enhanced_features.csv ({len(df)} months, {len(df.columns)} columns)")

# 4. Create evaluation report JSON
print("\n📊 Creating evaluation report...")

evaluation_report = {
    "overall_metrics": {
        "MAPE": 25.17,
        "MAE": 37.25,
        "RMSE": 41.80,
        "R²": -0.3689
    },
    "per_room_metrics": {
        "STD": {"MAPE": 19.18, "MAE": 95.15, "RMSE": 100.29, "R²": -0.4146},
        "SPR": {"MAPE": 20.28, "MAE": 41.91, "RMSE": 51.47, "R²": -0.5676},
        "FMY": {"MAPE": 31.79, "MAE": 7.26, "RMSE": 9.93, "R²": -0.2024},
        "JS": {"MAPE": 29.41, "MAE": 4.69, "RMSE": 5.51, "R²": -0.2909}
    },
    "baseline_comparison": {
        "baseline_mape": 42.78,
        "optimized_mape": 25.17,
        "improvement": 17.61,
        "reduction_percentage": 41.17
    },
    "test_set_info": {
        "n_samples": 10,
        "n_features": 35,
        "sequence_length": 12
    }
}

import json
with open('models/evaluation_report_optimized.json', 'w') as f:
    json.dump(evaluation_report, f, indent=2)
print("✓ Created evaluation_report_optimized.json")

# Summary
print()
print("="*80)
print("✅ Mock Model Files Created Successfully!")
print("="*80)
print()
print("📁 Files created:")
print("  ✓ models/scaler_X_optimized.pkl")
print("  ✓ models/scaler_y_optimized.pkl")
print("  ✓ models/checkpoints/best_model_optimized.h5")
print("  ✓ models/evaluation_report_optimized.json")
print("  ✓ monthly_enhanced_features.csv")
print()
print("⚠️  NOTE: These are MOCK files for demo purposes")
print("   Dashboard will work, but predictions use sample data")
print()
print("🚀 Next step: Run ./start_dashboard.sh")
print("="*80)
