# 🧪 Testing Guide - Local Development

Step-by-step guide untuk testing dashboard di Mac Anda.

## 📋 Prerequisites (Already Installed ✅)

- ✅ PHP 8.1+
- ✅ Composer
- ✅ Python 3.8+
- ✅ pip3

## 🚀 Quick Start (3 Steps)

### Step 1: Pull Latest Code

```bash
# Di terminal Mac Anda
cd ~/path/to/proyekAkhir
git pull origin claude/python-final-project-011CUxH3cwKAa4VHh9cdAXb3
```

### Step 2: Run Setup (One-Time)

```bash
# Di project root
./setup_local.sh
```

Script ini akan:
- ✅ Install Laravel dependencies (composer install)
- ✅ Setup .env file
- ✅ Generate Laravel key
- ✅ Create Python virtual environment
- ✅ Install Python packages (Flask, TensorFlow, dll)
- ✅ Check model files

**⏱️ Estimated time:** 2-3 minutes

### Step 3: Start Dashboard

```bash
# Di project root
./start_dashboard.sh
```

Script ini akan:
- ✅ Start Flask API on port 5000
- ✅ Start Laravel on port 8000
- ✅ Display URLs dan PIDs

**Access:** http://localhost:8000/dashboard

---

## 📱 Testing the Dashboard

### 1. Check ML Service Status

When you open the dashboard, you should see:
- ✅ **Green alert**: "ML Service: Running and ready for predictions"
- ❌ **Red alert**: Flask API not running (check terminal)

### 2. View Metrics Cards

Top row should show:
- **Model Accuracy**: 74.83%
- **MAPE Error**: 25.17%
- **Best Room Type**: STD
- **Model Size**: 12.0K

### 3. View Historical Chart

Should display:
- Line chart with 2 years of data
- 4 lines (STD, SPR, FMY, JS) in different colors
- Smooth transitions

### 4. Generate Prediction

**Steps:**
1. Select months ahead (try: **3 months**)
2. Check all 4 room types (✓ STD, SPR, FMY, JS)
3. Click **"Generate Prediction"** button
4. Loading overlay should appear
5. After 1-2 seconds, results appear below

**Expected Results:**
- ✅ New chart with predicted occupancy
- ✅ Summary table with values per month
- ✅ Success toast message

### 5. Try Different Scenarios

Test these combinations:
- **1 month** prediction, all rooms
- **6 months** prediction, only STD + SPR
- **12 months** prediction, all rooms

---

## 🔍 Detailed Testing Checklist

### ✅ Frontend Tests

- [ ] Dashboard loads without errors
- [ ] All 4 metric cards show values
- [ ] ML Service status is green
- [ ] Historical chart displays correctly
- [ ] Form inputs work (dropdown, checkboxes)
- [ ] Submit button is clickable
- [ ] Loading overlay shows during prediction
- [ ] Charts are responsive (resize browser)
- [ ] No console errors (F12 → Console tab)

### ✅ Prediction Tests

- [ ] 1-month prediction works
- [ ] 3-month prediction works
- [ ] 6-month prediction works
- [ ] 12-month prediction works
- [ ] Single room type prediction works
- [ ] Multiple room types work
- [ ] Prediction chart renders correctly
- [ ] Summary table populates with data
- [ ] Values look reasonable (0-100%)

### ✅ Error Handling Tests

- [ ] What happens if Flask API is not running?
  - Should show red alert "ML Service not reachable"
- [ ] What happens if no room type selected?
  - Should show warning "Select at least one room type"
- [ ] Network errors are handled gracefully

### ✅ Backend Tests

Test Flask API directly:

```bash
# Health check
curl http://localhost:5000/api/health

# Historical data
curl http://localhost:5000/api/historical

# Metrics
curl http://localhost:5000/api/metrics

# Prediction (POST)
curl -X POST http://localhost:5000/api/predict \
  -H "Content-Type: application/json" \
  -d '{"months_ahead": 3, "room_types": ["STD", "SPR"]}'
```

Expected responses:
- ✅ `200 OK` status
- ✅ JSON response with `"success": true`
- ✅ Data in expected format

---

## 🛠️ Manual Testing (Alternative)

If automatic scripts don't work:

### Terminal 1: Start Flask API

```bash
cd flask_api

# Create venv (first time only)
python3 -m venv venv

# Activate venv
source venv/bin/activate  # Mac/Linux
# or on Windows:
# . venv/Scripts/activate

# Install dependencies (first time only)
pip install -r requirements.txt

# Start Flask
python app.py
```

**Expected output:**
```
🚀 Initializing Flask API...
✓ Model loaded successfully
✓ Scalers loaded successfully
✓ Historical data loaded
🌐 Starting Flask API server...
📍 Endpoint: http://localhost:5000/api
```

### Terminal 2: Start Laravel

```bash
# In project root
php artisan serve
```

**Expected output:**
```
Starting Laravel development server: http://127.0.0.1:8000
```

### Browser

Open: http://localhost:8000/dashboard

---

## 📊 Expected Performance

### Load Times
- Dashboard initial load: < 2 seconds
- Historical data load: < 1 second
- Prediction generation: 1-3 seconds

### Browser Console
- No errors in Console (F12)
- No warnings about CORS
- Network requests should show `200 OK`

---

## 🐛 Troubleshooting

### Problem: Flask API won't start

**Check:**
```bash
# Python version
python3 --version  # Should be 3.8+

# TensorFlow compatibility
cd flask_api
source venv/bin/activate
python -c "import tensorflow; print(tensorflow.__version__)"
```

**Solution:**
```bash
# Reinstall dependencies
cd flask_api
rm -rf venv
python3 -m venv venv
source venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
```

### Problem: Laravel shows errors

**Check:**
```bash
# PHP version
php --version  # Should be 8.1+

# Laravel dependencies
composer install

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Problem: Model files not found

**Verify files exist:**
```bash
ls -lh models/checkpoints/best_model_optimized.h5
ls -lh models/scaler_X_optimized.pkl
ls -lh models/scaler_y_optimized.pkl
ls -lh monthly_enhanced_features.csv
```

**If missing:**
- Model files should be in repository
- Pull latest code: `git pull origin claude/python-final-project-011CUxH3cwKAa4VHh9cdAXb3`

### Problem: Port already in use

**Check ports:**
```bash
# Check if port 5000 is in use
lsof -i :5000

# Check if port 8000 is in use
lsof -i :8000
```

**Kill process:**
```bash
kill -9 <PID>
```

### Problem: CORS errors

**Solution:**
- Flask CORS already enabled in `flask_api/app.py`
- If issue persists, check browser console for specific error

---

## 📹 Testing Flow Example

```
1. Open http://localhost:8000/dashboard
   → Green status: "ML Service Running" ✓

2. View Historical Chart
   → See 2 years of occupancy trends ✓

3. Click "Generate Prediction"
   → Select: 3 months, All rooms
   → Click button ✓

4. Loading overlay appears
   → "Processing..." for 1-2 seconds ✓

5. Results appear
   → New chart with 3-month forecast ✓
   → Table with breakdown per month ✓
   → Toast: "Prediction generated successfully!" ✓

6. Try different scenario
   → 6 months, only STD + SPR
   → Works perfectly ✓

✅ TEST PASSED!
```

---

## 🎯 Success Criteria

Dashboard is working correctly if:

1. ✅ ML Service shows green status
2. ✅ Historical chart displays with data
3. ✅ Metrics cards show accurate values
4. ✅ Prediction generates within 3 seconds
5. ✅ Results chart and table display correctly
6. ✅ No console errors
7. ✅ UI is responsive and smooth

---

## 📸 Screenshots for Verification

Take screenshots of:
1. Dashboard homepage with green status
2. Historical chart filled with data
3. Prediction results with chart and table
4. Browser console (no errors)

These can be used for:
- Documentation
- Presentation slides
- Proposal (BAB 4)

---

## 🚀 Next Steps After Testing

1. **Take screenshots** for presentation
2. **Note any bugs** or improvements needed
3. **Test on different browsers** (Chrome, Safari, Firefox)
4. **Prepare demo** for presentation
5. **Update proposal** with screenshots

---

## 💡 Tips

- Keep both terminals open during testing
- Check Flask terminal for ML service logs
- Check Laravel terminal for web server logs
- Use browser DevTools (F12) to debug
- Test incrementally (one feature at a time)

---

**Happy Testing! 🎉**

If you encounter issues, check:
1. Both services are running (Flask + Laravel)
2. No port conflicts
3. Model files exist
4. Python dependencies installed
5. Laravel dependencies installed

For questions, refer to README_DASHBOARD.md
