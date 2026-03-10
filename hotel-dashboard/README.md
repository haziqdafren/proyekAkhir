# Dharma Hotel LSTM Forecasting System

A machine learning-powered occupancy prediction system built for final year project at **Politeknik Caltex Riau** (TA 2025/2026), forecasting hotel room occupancy rates using LSTM neural networks across 4 room types at Dharma Utama Hotel Pekanbaru — with an intelligent champion model selection system and real-time retraining capabilities.

**Author**: Mohamad Haziq Dafren (2355301119)  
**Institution**: Teknik Informatika, Politeknik Caltex Riau  
**Project Type**: Machine Learning Final Year Project  

---

## 📊 The System Pipeline

```
Excel Upload → Laravel Queue → Data Parsing → Database Storage → ML API (Flask)
                                                                        ↓
User Dashboard ← Laravel Backend ← Champion Model ← LSTM Training (TensorFlow/Keras)
       ↓                                                    ↓
   Predictions                                    Model Comparison & Auto-Promotion
```

---

## 🎯 Project Overview

This system predicts hotel occupancy rates for **Dharma Utama Hotel Pekanbaru** using historical data from **2021-2025**. The hotel has **56 total rooms** across **4 room types**:
- **Standard (STD)**
- **Superior (SPR)**
- **Junior Suite (JS)**
- **Family (FMY)**

### Two Prediction Models:

1. **Single Output Model** — Predicts aggregate hotel occupancy (all rooms combined)
2. **Multi Output Model** — Predicts occupancy for each room type separately

Both models use **LSTM** (Long Short-Term Memory) neural networks, a type of recurrent neural network excellent for time-series forecasting due to its ability to remember long-term patterns in sequential data.

---

## 🔬 The Machine Learning Pipeline

### Step 1 — Data Collection & Storage

**Source**: Real historical occupancy data from Dharma Utama Hotel (2021-2025)  
**Format**: Excel files with daily occupancy records per room type  
**Storage**: SQLite database with normalized schema  

The raw Excel files contain daily records showing:
- Date
- Rooms occupied per type (STD, SPR, JS, FMY)
- Total occupancy rate
- Revenue metrics

### Step 2 — ETL with Laravel Queue

When a manager uploads an Excel file, the system:

1. **Validates** the file format and structure
2. **Parses** each Excel sheet (using PhpSpreadsheet) — one sheet typically represents one day/period
3. **Extracts** occupancy metrics for all 4 room types
4. **Inserts** into `historical_occupancy_data` table
5. **Triggers** automatic model retraining via background queue job

**Key Backend Files**:
- `app/Jobs/ProcessDataUploadJob.php` — Handles Excel parsing and database insertion
- `app/Jobs/RetrainModelJob.php` — Triggers ML API to train new models

### Step 3 — Feature Engineering

Before training, the system constructs a **15-dimensional feature vector** for each month from the historical data:

#### Features (Engineered from Raw Data):

| # | Feature | Description | Calculation |
|---|---------|-------------|-------------|
| 0 | Kamar_Terjual | Total rooms sold (normalized) | `total_occupied / 56` |
| 1 | Okupansi_Rate | Current month occupancy rate | `avg_occupancy / 100` |
| 2 | occ_momentum | Acceleration of occupancy change | `(diff1 - diff2) / 100` |
| 3 | occ_trend | Month-to-month change | `(current - previous) / 100` |
| 4 | occ_rolling_mean_3 | 3-month rolling average | `mean(last_3_months) / 100` |
| 5 | occ_yoy | Year-over-year change | `(current - last_year_same_month) / 100` |
| 6 | std_proportion | Standard room proportion | `std_occupied / total_occupied` |
| 7 | is_increasing | Binary trend indicator | `1 if trend > 0 else 0` |
| 8 | is_peak_season | Peak month indicator | `1 if month in [6,7,12] else 0` |
| 9 | js_proportion | Junior Suite proportion | `js_occupied / total_occupied` |
| 10 | fmy_proportion | Family room proportion | `fmy_occupied / total_occupied` |
| 11 | spr_proportion | Superior room proportion | `spr_occupied / total_occupied` |
| 12 | occ_rolling_mean_6 | 6-month rolling average | `mean(last_6_months) / 100` |
| 13 | occ_rolling_std_3 | 3-month standard deviation | `std(last_3_months)` |
| 14 | occ_lag_1 | Previous month occupancy | `previous_month_occ / 100` |

**Implementation**: `ml-api/train_model.py` lines 192-297

### Step 4 — LSTM Model Architecture

#### Single Output Model (Aggregate Occupancy)

```python
Sequential([
    LSTM(64 units, return_sequences=True, input_shape=(6, 15)),
    Dropout(0.3),
    LSTM(32 units),
    Dropout(0.3),
    Dense(16, activation='relu'),
    Dense(1, activation='linear')  # Output: total occupancy rate
])
```

**Architecture Breakdown**:
- **Input**: 6 months of historical data, each month with 15 features
- **Layer 1**: LSTM with 64 memory units, processes temporal patterns
- **Dropout**: 30% to prevent overfitting
- **Layer 2**: LSTM with 32 units, refines patterns
- **Dense Layer**: 16 neurons with ReLU activation for non-linearity
- **Output**: Single value (0-1 range) representing occupancy rate

**Parameters**: ~15,000 trainable parameters

#### Multi Output Model (Per Room Type)

```python
Sequential([
    LSTM(32 units, return_sequences=True, input_shape=(6, 15)),
    Dropout(0.3),
    LSTM(16 units),
    Dropout(0.3),
    Dense(8, activation='relu'),
    Dense(4, activation='linear')  # Output: [STD, SPR, FMY, JS] rates
])
```

**Architecture Breakdown**:
- Smaller architecture (32→16 units) compared to single output
- **Output**: 4 values representing occupancy for each room type
- More complex task (4 predictions) with more data points

**Parameters**: ~6,000 trainable parameters

### Step 5 — Training Process

**Configuration**:
```python
optimizer = Adam(learning_rate=0.001)
loss = 'mse'  # Mean Squared Error
batch_size = 4
max_epochs = 100
validation_split = 0.2  # 80% train, 20% validation
early_stopping = EarlyStopping(monitor='val_loss', patience=10)
```

**Training Flow**:
1. Load historical data and create sequences (6 months → predict next month)
2. Split data: 80% training, 20% validation
3. Train LSTM model with backpropagation through time (BPTT)
4. Monitor validation loss — if no improvement for 10 epochs, stop early
5. Restore best weights based on lowest validation loss

**Implementation**: `ml-api/train_model.py` lines 17-108

### Step 6 — Model Evaluation & Metrics

After training, the system calculates three key metrics:

#### 1. MAPE (Mean Absolute Percentage Error)
```python
MAPE = mean(|actual - predicted| / actual) × 100%
```
- **Interpretation**: Lower is better
- **Current Performance**:
  - Single Output: **4.06%** (excellent — 95.94% accurate)
  - Multi Output: **15.95%** (good — 84.05% accurate)

#### 2. RMSE (Root Mean Squared Error)
```python
RMSE = sqrt(mean((actual - predicted)²))
```
- Penalizes larger errors more heavily
- Measured in same units as target (occupancy rate)

#### 3. R² Score (Coefficient of Determination)
```python
R² = 1 - (SS_res / SS_tot)
```
- **Range**: -∞ to 1
- **Current Status**: Negative values (indicates model is underperforming baseline)
- **Note**: This is a known issue being addressed in future iterations

**Implementation**: `ml-api/train_model.py` lines 332-369

### Step 7 — Champion Model System

The system implements an **automatic champion model selection** mechanism:

```
                  ┌─────────────────────┐
                  │   New Model Trained  │
                  └──────────┬───────────┘
                             │
                    ┌────────▼─────────┐
                    │  Calculate MAPE  │
                    └────────┬─────────┘
                             │
              ┌──────────────▼───────────────┐
              │  Compare with Champion MAPE  │
              └──────────────┬───────────────┘
                             │
                ┌────────────▼────────────┐
                │  New MAPE < Champion?   │
                └─────┬──────────┬────────┘
                      │          │
                   YES│          │NO
                      │          │
            ┌─────────▼──┐    ┌──▼──────────────┐
            │  Promote    │    │  Keep Old       │
            │  New Model  │    │  Champion       │
            └─────────────┘    └─────────────────┘
```

**Logic** (`app/Jobs/RetrainModelJob.php`):
```php
if ($newModelMAPE < $currentChampionMAPE) {
    // Demote old champion
    $oldChampion->update(['is_champion' => false]);
    
    // Promote new model
    $newModel->update(['is_champion' => true]);
    
    // Save as champion.keras for predictions
    copy($newModelPath, storage_path('app/models/{type}/champion.keras'));
}
```

**Quality Targets** (planned for implementation):
- Single Output: MAPE must be < **20%**
- Multi Output: MAPE must be < **30%**
- R² Score: Must be > **0** (positive correlation)

### Step 8 — Making Predictions

When a user requests a prediction:

1. **Load champion model** from `storage/app/models/{type}/champion.keras`
2. **Prepare input sequence** — last 6 months of occupancy data with 15 features each
3. **Run model.predict()** — LSTM processes the sequence
4. **Denormalize output** — convert (0-1) range back to percentages
5. **Display results** — show predicted occupancy rates with visualization

**Implementation**: `ml-api/app.py` (Flask API endpoints)

---

## 🏗️ Backend Architecture

### System Components

```
┌──────────────────────────────────────────────────────────────┐
│                         USER INTERFACE                        │
│                    (Vue.js + Inertia.js)                      │
└────────────────────────────┬─────────────────────────────────┘
                             │ HTTP Requests
┌────────────────────────────▼─────────────────────────────────┐
│                      LARAVEL BACKEND                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │ Controllers  │  │    Models    │  │  Queue Jobs  │       │
│  │              │  │              │  │              │       │
│  │ - Dashboard  │  │ - Historical │  │ - ParseData  │       │
│  │ - DataUpload │  │   Occupancy  │  │ - RetrainML  │       │
│  │ - Prediction │  │ - ModelVer   │  │              │       │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘       │
│         │                  │                  │               │
│         └─────────┬────────┴──────────────────┘               │
│                   │                                           │
│         ┌─────────▼──────────┐                                │
│         │  SQLite Database   │                                │
│         │  - 2,616 records   │                                │
│         │  - 2021-2025 data  │                                │
│         └────────────────────┘                                │
└────────────────────────────┬─────────────────────────────────┘
                             │ HTTP API Call
┌────────────────────────────▼─────────────────────────────────┐
│                    PYTHON ML API (Flask)                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │   app.py     │  │ train_model  │  │  load_data   │       │
│  │              │  │              │  │              │       │
│  │ - /retrain   │  │ - LSTM       │  │ - CSV parse  │       │
│  │ - /predict   │  │ - Feature    │  │ - Sequence   │       │
│  │ - /health    │  │   Engineer   │  │   creation   │       │
│  └──────────────┘  └──────────────┘  └──────────────┘       │
│                                                                │
│  TensorFlow / Keras / NumPy / Pandas                          │
└────────────────────────────────────────────────────────────┘
```

---

## 📁 Project Structure

```
hotel-dashboard/
├── app/
│   ├── Http/Controllers/
│   │   ├── DashboardController.php          # Dashboard view
│   │   ├── DataUploadController.php         # Upload handling
│   │   └── PredictionController.php         # Prediction interface
│   ├── Jobs/
│   │   ├── ProcessDataUploadJob.php         # Excel parsing
│   │   └── RetrainModelJob.php              # ML training orchestration
│   └── Models/
│       ├── HistoricalOccupancyData.php      # Daily occupancy records
│       ├── ModelVersion.php                 # Model versioning
│       └── TrainingUpload.php               # Upload tracking
│
├── ml-api/                                   # Python Flask ML service
│   ├── app.py                                # Flask server (port 5000)
│   ├── train_model.py                        # LSTM training logic
│   ├── load_data.py                          # Data loading utilities
│   └── requirements.txt                      # Python dependencies
│
├── resources/js/Pages/
│   ├── Dashboard.vue                         # Main dashboard
│   ├── DataUpload/Index.vue                  # Upload interface
│   └── Predictions/
│       ├── SingleOutput.vue                  # Aggregate predictions
│       └── MultiOutput.vue                   # Per-room-type predictions
│
├── database/
│   ├── database.sqlite                       # SQLite database (0.54 MB)
│   └── migrations/                           # Schema migrations
│
├── storage/app/
│   ├── models/                               # Trained LSTM models
│   │   ├── single/champion.keras
│   │   └── multi/champion.keras
│   └── uploads/training/                     # Uploaded Excel files
│
└── README.md                                 # This file
```

---

## 🔧 Tech Stack

| Layer | Technologies |
|-------|-------------|
| **Backend Framework** | Laravel 11 (PHP 8.3) |
| **Database** | SQLite (production: MySQL/PostgreSQL) |
| **Queue System** | Laravel Queue (database driver) |
| **Excel Processing** | PhpSpreadsheet |
| **Frontend** | Vue.js 3, Inertia.js, Tailwind CSS |
| **Charts** | Chart.js |
| **ML Framework** | TensorFlow 2.x, Keras |
| **ML API** | Flask (Python 3.13) |
| **Data Processing** | NumPy, Pandas |
| **Metrics** | scikit-learn |
| **Model Type** | LSTM (Long Short-Term Memory) |

---

## 🚀 Running the System

### Prerequisites

```bash
# PHP & Composer
php >= 8.3
composer

# Node.js & npm
node >= 18
npm

# Python & pip
python >= 3.10
pip
```

### Installation

```bash
# 1. Clone repository
git clone <repository-url>
cd hotel-dashboard

# 2. Install PHP dependencies
composer install

# 3. Install Node dependencies
npm install

# 4. Create Python virtual environment
python3 -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

# 5. Install Python dependencies
pip install -r ml-api/requirements.txt

# 6. Set up environment
cp .env.example .env
php artisan key:generate

# 7. Run migrations
php artisan migrate

# 8. Build frontend
npm run build
```

### Starting the System

You need **3 terminal windows**:

**Terminal 1 — Laravel Server**
```bash
php artisan serve
# Access: http://127.0.0.1:8000
```

**Terminal 2 — Queue Worker**
```bash
php -d memory_limit=512M artisan queue:work --tries=3 --timeout=600 --verbose
```

**Terminal 3 — ML API**
```bash
source venv/bin/activate
python3 ml-api/app.py
# Runs on: http://127.0.0.1:5000
```

### Login Credentials

```
Email: manager@dharmahotel.com
Password: password
```

---

## 📈 Current Performance

### Champion Models (as of Feb 2026)

| Model Type | MAPE | R² Score | RMSE | Status |
|------------|------|----------|------|--------|
| **Single Output** | **4.06%** | -0.16 | 0.047 | ✅ Champion |
| **Multi Output** | **15.95%** | -2.02 | 0.092 | ✅ Champion |

**Interpretation**:
- **Single Model**: 95.94% accuracy in predicting aggregate occupancy (excellent!)
- **Multi Model**: 84.05% accuracy for per-room-type predictions (good!)
- **R² Issue**: Negative R² indicates model performs worse than baseline mean predictor — planned for optimization

### Training Dataset
- **Total Records**: 2,616 daily occupancy entries
- **Date Range**: 2021-01-01 to 2025-10-31
- **Room Types**: 4 (STD, SPR, JS, FMY)
- **Total Room Capacity**: 56 rooms

---

## 🎯 Key Features

1. **Automatic Data Processing** — Upload Excel, system handles everything
2. **Intelligent Model Management** — Champion model auto-selection based on MAPE
3. **Dual Prediction Modes** — Single (aggregate) and Multi (per room type)
4. **Real-time Dashboard** — Current champion metrics and training history
5. **Professional UI** — Enterprise-grade design without AI-generated feel

---

## 🔮 Future Enhancements

### Phase 2 (Planned)

1. **Quality Threshold Validation** — Reject models that don't meet standards (MAPE < 20%/30%, R² > 0)
2. **R² Score Optimization** — Hyperparameter tuning, batch normalization, bidirectional LSTM
3. **Advanced Metrics** — MAE display, confidence intervals, feature importance
4. **Model Explainability** — SHAP values, attention visualization
5. **Production Readiness** — MySQL migration, A/B testing, rollback capability

---

## 🐛 Known Issues

### 1. Negative R² Scores
**Status**: Known issue, planned for fix  
**Impact**: Models still perform well based on MAPE  
**Cause**: Small validation set, possible overfitting, non-stationary data  

**Planned Solutions**:
- Increase training data
- Add regularization (L1/L2)
- Implement cross-validation
- More aggressive feature normalization

### 2. Queue Worker Stops After Inactivity
**Workaround**: Restart queue worker manually  
**Solution**: Implement Supervisor (process monitor) for production

---

## 📝 License & Academic Use

This project is developed as a final year project for **Politeknik Caltex Riau**.

**Academic Integrity**: If you use this project as a reference for your own academic work, please cite appropriately and ensure compliance with your institution's academic honesty policies.

---

## 📞 Contact

**Developer**: Mohamad Haziq Dafren  
**Student ID**: 2355301119  
**Institution**: Teknik Informatika, Politeknik Caltex Riau  
**Academic Year**: 2025/2026  

---

## 🙏 Acknowledgments

- **Dharma Utama Hotel Pekanbaru** for providing real historical occupancy data
- **Politeknik Caltex Riau** for academic support and resources
- **TensorFlow/Keras Team** for the excellent deep learning framework
- **Laravel Community** for comprehensive documentation

---

**Final Year Project — Machine Learning**  
**Politeknik Caltex Riau | TA 2025/2026**
