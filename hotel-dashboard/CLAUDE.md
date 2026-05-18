# CLAUDE.md — Dharma Hotel LSTM Forecasting System

Project-specific instructions for Claude Code. Read this before doing anything.

---

## Project Overview

**Author**: Mohamad Haziq Dafren (2355301119)  
**Institution**: Teknik Informatika, Politeknik Caltex Riau  
**Stack**: Laravel 11 + Inertia.js + Vue 3 + Flask (Python/TensorFlow) + SQLite  
**Hotel**: Dharma Utama Hotel Pekanbaru — 56 rooms across 4 types (STD/SPR/FMY/JS)

Two LSTM prediction models:
- **Single Output** — predicts aggregate hotel occupancy (Kamar_Terjual overall)
- **Multi Output** — predicts occupancy per room type (STD, SPR, FMY, JS separately)

---

## Running the Application

### Two servers must be running simultaneously:

#### 1. Laravel (PHP dev server) — port 8000
```bash
cd /Users/haziqdafren/proyekAkhir/hotel-dashboard
php artisan serve
```

#### 2. Flask ML API — port 5000
**CRITICAL: Always use the venv Python, never system Python.**
```bash
cd /Users/haziqdafren/proyekAkhir/hotel-dashboard/ml-api
nohup /Users/haziqdafren/proyekAkhir/hotel-dashboard/venv/bin/python app.py > /tmp/flask.log 2>&1 &
```
To check Flask is running:
```bash
curl http://127.0.0.1:5000/api/health
tail -f /tmp/flask.log
```
To kill Flask:
```bash
pkill -f "venv/bin/python app.py"
```

#### 3. Queue Worker (optional — for background jobs)
```bash
php artisan queue:work
```
Queue is set to `sync` in `.env` so jobs run inline by default. Only needed if changed to `database`.

#### 4. Vite (frontend dev, optional)
```bash
npm run dev
```
Only needed when actively editing Vue/CSS files.

---

## Login Credentials

- **URL**: http://localhost:8000
- **Email**: admin@hotel.com
- **Password**: (check `.env` / seeder — not committed to git)

---

## Project Structure

```
hotel-dashboard/
├── app/
│   ├── Console/Commands/
│   │   ├── GenerateMonthlyPredictions.php     # Artisan: generate multi-output predictions
│   │   └── GenerateSingleOutputPredictions.php # Artisan: generate single-output predictions
│   ├── Http/Controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── DataUploadController.php           # Handles guest Excel uploads
│   │   ├── ExportController.php               # Excel/PDF export
│   │   ├── HistoryController.php              # Historical data + charts
│   │   └── PredictionController.php           # Prediction CRUD + trigger
│   ├── Jobs/
│   │   ├── ProcessDataUploadJob.php           # Parses uploaded Excel → DB
│   │   └── RetrainModelJob.php                # Triggers Flask retraining
│   ├── Models/
│   │   ├── HistoricalOccupancyData.php        # Main data table
│   │   ├── ModelVersion.php                   # Model version tracking
│   │   └── RoomType.php
│   └── Services/
│       ├── ExcelParserService.php             # Parses daily guest Excel sheets
│       ├── FeatureEngineeringService.php
│       ├── HistoricalDataAggregationService.php
│       ├── MLPredictionService.php            # Calls Flask API
│       ├── ModelRetrainingService.php
│       └── OccupancyCalculationService.php    # Weighted occupancy calculator
├── ml-api/
│   ├── app.py                                 # Flask API server (port 5000)
│   ├── train_model.py                         # LSTM training logic
│   └── requirements.txt
├── storage/app/
│   ├── models/
│   │   ├── single/
│   │   │   ├── champion.keras                 # Active single model (v1.0.0-thesis)
│   │   │   ├── champion_backup_thesis.keras   # Backup of original thesis model
│   │   │   └── v1.x.x.keras                  # Retrained versions
│   │   └── multi/
│   │       ├── champion.keras                 # Active multi model (v1.0.0-thesis)
│   │       ├── champion_backup_thesis.keras   # Backup of original thesis model
│   │       └── v1.x.x.keras                  # Retrained versions
│   ├── private/uploads/training/              # Uploaded guest Excel files
│   └── 2021_2025_Clean.xlsx                   # Original historical data (original scale, has Revenue)
├── venv/                                      # Python virtual environment (DO NOT delete)
│   └── bin/python                             # Always use this Python, not system Python
└── database/
    └── database.sqlite                        # SQLite database
```

---

## Database

- **Type**: SQLite
- **File**: `database/database.sqlite`
- **Key tables**:
  - `historical_occupancy_data` — daily records per room type (date, room_type_id, occupancy_rate, revenue, rooms_occupied, rooms_available)
  - `model_versions` — LSTM model version history with MAPE/R2 metrics
  - `predictions` — stored predictions per month
  - `room_types` — STD (id=1), SPR (id=2), FMY (id=3), JS (id=4), each 14 rooms

### Data Range
- **2021-01-01 to 2026-01**: fully populated occupancy + revenue
- Revenue source: `storage/app/2021_2025_Clean.xlsx` (original SQL-derived daily data)
- Revenue for Aug/Sep/Nov/Dec 2025 and Jan 2026: from uploaded guest Excel files
- Revenue was split evenly across room_type rows per day (daily totals from Clean.xlsx ÷ 4 rows)

### Useful Tinker Queries
```bash
# Check revenue by month
php artisan tinker --execute="DB::table('historical_occupancy_data')->select(DB::raw('strftime(\"%Y-%m\", date) as ym'), DB::raw('SUM(revenue) as rev'))->groupBy('ym')->orderBy('ym','desc')->limit(12)->get()->each(fn(\$r) => print(\$r->ym.' | Rp '.number_format(\$r->rev).PHP_EOL));"

# Count records
php artisan tinker --execute="echo DB::table('historical_occupancy_data')->count();"

# Check model versions
php artisan tinker --execute="DB::table('model_versions')->get()->each(fn(\$r) => print(\$r->version.' | '.\$r->model_type.' | mape:'.\$r->mape.PHP_EOL));"
```

---

## ML Models

### Champion Model System
- Each model type (single/multi) has a `champion.keras` file — this is the active model used for predictions
- `v1.0.0.keras` = the original thesis model (protected, never overwrite)
- `champion_backup_thesis.keras` = backup copy of v1.0.0 thesis model

### CRITICAL: Single Model Must Use Thesis Champion
The single output model champion MUST be `v1.0.0-thesis`. If retraining accidentally promotes a worse model:
```bash
cp storage/app/models/single/champion_backup_thesis.keras storage/app/models/single/champion.keras
```

### Model Metrics (Thesis Baseline)
- **Single Output**: MAPE ~20%, trained on aggregate hotel occupancy
- **Multi Output**: per room type, higher MAPE expected

### Flask API Endpoints
- `GET /api/health` — health check
- `POST /api/predict/single` — single output prediction
- `POST /api/predict/multi` — multi output prediction
- `POST /api/retrain` — trigger retraining with new data
- `GET /api/models` — list model versions

### Python Virtual Environment
```bash
# Always activate before running Python scripts
source /Users/haziqdafren/proyekAkhir/hotel-dashboard/venv/bin/activate

# Or call directly
/Users/haziqdafren/proyekAkhir/hotel-dashboard/venv/bin/python script.py
```

---

## Data Upload Flow

1. User uploads monthly guest Excel file via UI (Data Upload page)
2. `DataUploadController` stores file in `storage/app/private/uploads/training/`
3. `ProcessDataUploadJob` dispatches → `ExcelParserService::parseForValidation()`
4. Excel is parsed sheet-by-sheet (one sheet per day), extracting:
   - Room number → room type (STD/SPR/FMY/JS)
   - Occupied (OC=1) → count rooms + sum prices (revenue)
   - Occupancy rate = occupied / total rooms per type
5. Results saved to `historical_occupancy_data` via `updateOrCreate`

### Guest Excel Format
- Sheets named by date: `01 AGUS 2025`, `02 AGUS 25`, etc.
- Row 4: date header (`HARI : JUMAT`, ` `, `2025-08-01`)
- Row 5: column headers (`RM`, `OC`, `P`, `NAME GUEST`, ...)
- Data rows: room 100–400, col 0=room#, col 1=OC, col 2=persons, col 10 (approx)=type, col 13=price

### Manual Re-processing a File
```php
// In tinker if a file was uploaded but not processed
$service = app(App\Services\ExcelParserService::class);
$result = $service->parseForValidation('path/to/file.xls');
// Then loop result and updateOrCreate records
```

---

## Occupancy Color Coding

Colors are derived DIRECTLY from occupancy rate — NOT from backend `urgency` field:

| Rate | Level | Color |
|------|-------|-------|
| ≥ 55% | Hunian Tinggi | Green |
| 40–54% | Hunian Sedang | Yellow |
| < 40% | Hunian Rendah | Red |

In Vue, always derive color from `predicted_occupancy_rate` or `occupancy_rate`, never from backend `urgency`.

---

## Revenue Display Rules

- Revenue `null` or `0` → display as `—` (dash), never `Rp 0`
- Outlier cap: revenue > Rp 50,000,000 per row is excluded from stats (data entry errors)
- `formatCurrencyShort()` in Vue returns `—` for null/0/undefined

---

## Key Business Rules

- Hotel has exactly **56 rooms**: STD=14, SPR=14, FMY=14, JS=14
- Peak season: July, August, December
- Low season: January, February
- Occupancy ≥ 55% = good performance for this hotel
- MAPE ≤ 25% is acceptable for thesis-level accuracy

---

## Common Issues & Fixes

### Flask won't start / ModuleNotFoundError
```bash
# Wrong — system Python doesn't have flask/tensorflow
python app.py

# Correct — use venv
/Users/haziqdafren/proyekAkhir/hotel-dashboard/venv/bin/python app.py
```

### Predictions show wrong colors (red when should be green)
Backend `urgency` field = action urgency (marketing/ops recommendation), NOT occupancy level.
Always compute display color from occupancy rate in Vue frontend.

### Revenue shows — for all months
Run the revenue backfill script using `storage/app/2021_2025_Clean.xlsx` (original scale data with Revenue column in Rupiah).

### Single model predicting wrong (too high/low)
Check if `champion.keras` got overwritten by retraining. Restore:
```bash
cp storage/app/models/single/champion_backup_thesis.keras storage/app/models/single/champion.keras
```

### Retraining finishes in 3–10 seconds (normal)
This is expected on CPU (MacBook). Colab uses GPU which is 10–50× faster. Early stopping triggers at best epoch (~7), which is correct behavior.

---

## Environment Variables (`.env` — not committed)

Key variables to set up on fresh install:
```
APP_KEY=base64:...              # Generate with: php artisan key:generate
DB_CONNECTION=sqlite
SESSION_DRIVER=database
QUEUE_CONNECTION=sync
ML_FLASK_API_URL=http://127.0.0.1:5000
ML_API_KEY=your-secret-key      # Must match what Flask expects
```

---

## Fresh Install Steps (after git clone)

```bash
# 1. Install PHP dependencies
composer install

# 2. Install JS dependencies
npm install

# 3. Copy env and generate app key
cp .env.example .env
php artisan key:generate

# 4. Update ML_PYTHON_PATH in .env to your local venv path, e.g.:
#    ML_PYTHON_PATH="/path/to/hotel-dashboard/venv/bin/python"

# 5. Run migrations + seed (creates DB and room types)
php artisan migrate --seed

# 6. Create storage symlink
php artisan storage:link

# 7. Backfill historical revenue from the included Clean.xlsx
#    (Run this AFTER migrate --seed, otherwise no records to update)
php artisan tinker --execute="
\$revenueMap = [];
\$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
\$spreadsheet = \$reader->load(storage_path('app/2021_2025_Clean.xlsx'));
\$rows = \$spreadsheet->getActiveSheet()->toArray();
array_shift(\$rows); // remove header
foreach (\$rows as \$row) {
    if (empty(\$row[0])) continue;
    \$date = \Carbon\Carbon::parse(\$row[0])->format('Y-m-d');
    \$revenue = (float) \$row[6];
    if (\$revenue > 50000000) \$revenue = 50000000; // cap outliers
    \$revenueMap[\$date] = (int) \$revenue;
}
\$updated = 0;
foreach (\$revenueMap as \$date => \$rev) {
    \$rows = DB::table('historical_occupancy_data')->where('date', \$date)->count();
    if (\$rows > 0) {
        DB::table('historical_occupancy_data')->where('date', \$date)->update(['revenue' => intval(\$rev / \$rows)]);
        \$updated++;
    }
}
echo 'Updated ' . \$updated . ' dates with revenue.' . PHP_EOL;
"

# 8. Set up Python venv
python3 -m venv venv
source venv/bin/activate
pip install -r ml-api/requirements.txt

# 9. Build frontend
npm run build

# 10. Start servers (two separate terminals)
php artisan serve
# Terminal 2:
cd ml-api && /path/to/venv/bin/python app.py
```

> **Note**: `database/database.sqlite` is NOT in git (contains real hotel data).
> After fresh install you get seeded dummy data. The `storage/app/models/` champion
> keras files ARE included — predictions will work immediately after setup.

---

## GitHub Push Checklist

Before pushing, ensure these are NOT committed:
- `.env` (contains secrets)
- `database/database.sqlite` (has real hotel data)
- `storage/app/private/` (uploaded guest files)
- `venv/` (Python venv — too large, install from requirements.txt)
- `public/build/` (generated by npm run build)
- `vendor/` (generated by composer install)
- `node_modules/`

These are all covered by `.gitignore`.

Large binary files to handle carefully:
- `storage/app/models/*/champion.keras` — include in git (needed for predictions)
- `storage/app/models/*/champion_backup_thesis.keras` — include (thesis safety backup)
- `storage/app/2021_2025_Clean.xlsx` — include (needed for revenue backfill)
