# 🏨 Hotel Dharma Utama - Occupancy Prediction Dashboard

Dashboard untuk prediksi tingkat okupansi hotel menggunakan LSTM Neural Network.

## 📋 Tech Stack

- **Frontend**: Laravel 10 + Blade Templates + Bootstrap 5
- **Backend Web**: Laravel (PHP)
- **ML API**: Flask (Python)
- **Model**: LSTM Multi-Output (TensorFlow/Keras)
- **Charts**: Chart.js

## 🏗️ Architecture

```
Browser → Laravel (UI) → Flask API → LSTM Model → Predictions
```

## 📁 Project Structure

```
proyekAkhir/
├── app/
│   └── Http/Controllers/
│       └── PredictionController.php    # Laravel controller
├── routes/
│   └── web.php                         # Laravel routes
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php          # Main layout
│       └── prediction/
│           └── dashboard.blade.php     # Dashboard view
├── flask_api/
│   ├── app.py                         # Flask API application
│   └── requirements.txt               # Python dependencies
├── models/
│   ├── best_model_optimized.h5       # Trained LSTM model
│   ├── scaler_X_optimized.pkl        # Feature scaler
│   └── scaler_y_optimized.pkl        # Target scaler
└── monthly_enhanced_features.csv      # Historical data
```

## 🚀 Installation & Setup

### 1. Prerequisites

**PHP & Laravel:**
- PHP 8.1+
- Composer
- Laravel 10+

**Python:**
- Python 3.8+
- pip

### 2. Install Laravel Dependencies

```bash
composer install
```

### 3. Setup Laravel Environment

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure .env file
# Set APP_URL, database, etc.
```

### 4. Install Python Dependencies

```bash
cd flask_api
pip install -r requirements.txt
cd ..
```

## 🎯 Running the Dashboard

### Option A: Run Both Services Separately

**Terminal 1 - Start Flask API (ML Service):**
```bash
cd flask_api
python app.py
```

Flask API will start on `http://localhost:5000`

**Terminal 2 - Start Laravel Server:**
```bash
php artisan serve
```

Laravel will start on `http://localhost:8000`

**Access Dashboard:**
Open browser: `http://localhost:8000/dashboard`

---

### Option B: Run with Startup Script (Recommended)

```bash
# Make script executable
chmod +x start_dashboard.sh

# Run
./start_dashboard.sh
```

This will start both Flask API and Laravel server automatically.

## 📊 API Endpoints

### Flask API (ML Service)

Base URL: `http://localhost:5000/api`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/health` | Check ML service status |
| GET | `/historical` | Get historical occupancy data |
| POST | `/predict` | Generate occupancy predictions |
| GET | `/metrics` | Get model performance metrics |

### Laravel Routes

Base URL: `http://localhost:8000`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard` | Main dashboard page |
| GET | `/dashboard/api/historical` | Proxy to Flask historical data |
| POST | `/dashboard/api/predict` | Proxy to Flask prediction |
| GET | `/dashboard/api/metrics` | Proxy to Flask metrics |
| GET | `/dashboard/api/health` | Check ML service health |

## 🔧 Configuration

### Flask API Configuration

Edit `flask_api/app.py`:

```python
# Model paths
MODEL_PATH = '../models/checkpoints/best_model_optimized.h5'
SCALER_X_PATH = '../models/scaler_X_optimized.pkl'
SCALER_Y_PATH = '../models/scaler_y_optimized.pkl'
DATA_PATH = '../monthly_enhanced_features.csv'

# Server configuration
app.run(host='0.0.0.0', port=5000, debug=True)
```

### Laravel Configuration

Edit `app/Http/Controllers/PredictionController.php`:

```php
// Flask API URL
private $flaskApiUrl = 'http://localhost:5000/api';
```

## 📈 Features

### 1. Model Performance Metrics
- Overall Accuracy: **74.83%**
- MAPE: **25.17%**
- Per-room type breakdown

### 2. Historical Data Visualization
- Interactive line charts
- 2-year historical trend
- All 4 room types (STD, SPR, FMY, JS)

### 3. Prediction Generation
- Predict 1-12 months ahead
- Select specific room types
- Real-time predictions

### 4. Prediction Results
- Visual charts for predicted occupancy
- Summary table with detailed breakdown
- Period-by-period comparison

## 🎨 Dashboard Screenshots

### Main Dashboard
![Dashboard](docs/screenshots/dashboard.png)

### Prediction Results
![Prediction](docs/screenshots/prediction.png)

## 🧪 Testing

### Test Flask API Directly

```bash
# Health check
curl http://localhost:5000/api/health

# Get historical data
curl http://localhost:5000/api/historical

# Generate prediction
curl -X POST http://localhost:5000/api/predict \
  -H "Content-Type: application/json" \
  -d '{"months_ahead": 3, "room_types": ["STD", "SPR", "FMY", "JS"]}'

# Get metrics
curl http://localhost:5000/api/metrics
```

### Test via Laravel Dashboard

1. Open browser: `http://localhost:8000/dashboard`
2. Check ML Service status (green alert)
3. View historical chart
4. Generate prediction (select months and room types)
5. View results chart and table

## 🐛 Troubleshooting

### Issue: ML Service Not Reachable (Red Alert)

**Solution:**
1. Make sure Flask API is running (`cd flask_api && python app.py`)
2. Check if port 5000 is available
3. Check Flask console for errors

### Issue: Model Not Loaded

**Solution:**
1. Verify model file exists: `models/checkpoints/best_model_optimized.h5`
2. Verify scalers exist: `models/scaler_*_optimized.pkl`
3. Check Flask console logs for specific error

### Issue: Historical Data Not Showing

**Solution:**
1. Verify data file exists: `monthly_enhanced_features.csv`
2. Check CSV format and columns
3. Dashboard will use sample data if CSV not found

### Issue: Prediction Failed

**Solution:**
1. Check Flask API logs for error details
2. Verify model and scalers loaded successfully
3. Check network connection between Laravel and Flask

### Issue: Laravel Routes Not Found

**Solution:**
```bash
# Clear and rebuild routes cache
php artisan route:clear
php artisan route:cache
php artisan config:clear
```

## 📝 Model Information

### LSTM Architecture
- **Input**: (12 timesteps, 35 features)
- **LSTM Layer 1**: 32 units, dropout 0.3
- **LSTM Layer 2**: 16 units, dropout 0.3
- **Dense Layer**: 8 units, ReLU
- **Output**: 4 units (STD, SPR, FMY, JS)
- **Total Parameters**: 12,012

### Performance Metrics
- **Overall MAPE**: 25.17% ✅ (Target: ≤35%)
- **Overall Accuracy**: 74.83%
- **Improvement**: 41.17% reduction from baseline

### Per Room Type Performance
| Room Type | MAPE | Accuracy |
|-----------|------|----------|
| Standard (STD) | 19.18% | 80.82% |
| Superior (SPR) | 20.28% | 79.72% |
| Family (FMY) | 31.79% | 68.21% |
| Junior Suite (JS) | 29.41% | 70.59% |

## 🚢 Deployment

### For Production

1. **Disable Debug Mode**
   - Flask: Set `debug=False`
   - Laravel: Set `APP_DEBUG=false` in `.env`

2. **Use Production Server**
   - Flask: Use Gunicorn or uWSGI
   - Laravel: Use Apache/Nginx

3. **Environment Variables**
   - Store sensitive config in `.env`
   - Never commit `.env` to git

4. **HTTPS**
   - Use SSL certificates
   - Update CORS settings

### Docker Deployment (Optional)

```bash
# Build containers
docker-compose up --build

# Access dashboard
http://localhost:8000/dashboard
```

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Flask Documentation](https://flask.palletsprojects.com/)
- [TensorFlow Documentation](https://www.tensorflow.org/)
- [Chart.js Documentation](https://www.chartjs.org/)

## 👥 Credits

- **Dashboard Framework**: Argon Dashboard by Creative Tim
- **ML Model**: Custom LSTM implementation
- **Hotel**: Hotel Dharma Utama

## 📄 License

This project is for educational purposes.

---

**Made with ❤️ for Hotel Dharma Utama**
