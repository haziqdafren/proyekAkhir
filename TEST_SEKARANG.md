# ✅ Testing Dashboard - Langkah Demi Langkah

Ikuti checklist ini di Mac Anda untuk test dashboard.

---

## 📋 Checklist Setup (Sekali Saja)

### ☐ Step 1: Clone Repository

Buka Terminal di Mac, lalu jalankan:

```bash
cd ~/Documents
git clone https://github.com/haziqdafren/proyekAkhir.git
cd proyekAkhir
git checkout claude/python-final-project-011CUxH3cwKAa4VHh9cdAXb3
git pull
```

**✅ Berhasil jika:** Anda lihat folder `proyekAkhir` di Documents

---

### ☐ Step 2: Install Dependencies

```bash
composer install
```

**⏱️ Tunggu:** ~1-2 menit (download Laravel packages)

**✅ Berhasil jika:** Muncul "Generating optimized autoload files"

---

### ☐ Step 3: Setup Environment

```bash
cp .env.example .env
php artisan key:generate
```

**✅ Berhasil jika:** Muncul "Application key set successfully"

---

## 🚀 Checklist Run Dashboard

### ☐ Step 4: Start Laravel

```bash
php artisan serve
```

**✅ Berhasil jika:** Muncul:
```
Laravel development server started: http://127.0.0.1:8000
```

**⚠️ JANGAN CLOSE TERMINAL INI!** Biarkan tetap running.

---

### ☐ Step 5: Buka Dashboard

Buka browser (Chrome/Safari), ketik:

```
http://localhost:8000/dashboard
```

**✅ Berhasil jika:**
- Dashboard muncul dengan title "Prediksi Tingkat Okupansi Hotel"
- Ada 4 kartu metrics di atas
- Ada banner biru "Demo Mode"
- Ada chart historical data

**❌ Jika error:** Lihat Troubleshooting di bawah

---

## 🧪 Checklist Testing Features

### ☐ Test 1: View Historical Chart

**Action:** Scroll ke chart "Historical Occupancy Trend"

**✅ Check:**
- [ ] Chart tampil dengan 4 colored lines
- [ ] Ada legend: STD, SPR, FMY, JS
- [ ] Hover di chart → tooltip muncul
- [ ] Klik legend → line hide/show

---

### ☐ Test 2: Generate Prediction 3 Bulan

**Action:**
1. Di form sebelah kiri, pilih "3 Bulan"
2. Pastikan semua 4 checkboxes ter-check
3. Klik button "Generate Prediction"

**✅ Check:**
- [ ] Loading overlay muncul (~1-2 detik)
- [ ] Toast "Success!" muncul di pojok
- [ ] Prediction chart muncul di bawah historical chart
- [ ] Table muncul dengan 3 rows (Dec, Jan, Feb)
- [ ] Semua values dalam range 40-95%

---

### ☐ Test 3: Generate Prediction 12 Bulan

**Action:**
1. Ganti dropdown ke "12 Bulan"
2. Klik "Generate Prediction" lagi

**✅ Check:**
- [ ] Loading overlay muncul
- [ ] Chart updates dengan 12 data points
- [ ] Table shows 12 rows
- [ ] Values look realistic (40-95%)

---

### ☐ Test 4: Selective Room Types

**Action:**
1. Uncheck "FMY" dan "JS"
2. Hanya STD dan SPR yang checked
3. Pilih "6 Bulan"
4. Klik "Generate Prediction"

**✅ Check:**
- [ ] Chart shows only 2 lines (blue, green)
- [ ] Table shows "-" di kolom FMY dan JS
- [ ] Average calculated dari 2 room types saja

---

### ☐ Test 5: Validation

**Action:**
1. Uncheck SEMUA room types
2. Klik "Generate Prediction"

**✅ Check:**
- [ ] Toast warning muncul: "Please select at least one room type"
- [ ] Tidak ada prediction generated
- [ ] Form tetap di state yang sama

---

### ☐ Test 6: Responsive Design

**Action:** Resize browser window (kecilkan)

**✅ Check:**
- [ ] Layout berubah ke mobile view
- [ ] Form dan chart jadi stack vertical
- [ ] Semua masih readable
- [ ] Chart masih interactive

---

## 📸 Checklist Screenshots

Ambil screenshots untuk proposal:

### ☐ Screenshot 1: Dashboard Overview
- [ ] Full page view
- [ ] All 4 metric cards visible
- [ ] Historical chart visible
- [ ] Form visible

**Save as:** `dashboard_overview.png`

---

### ☐ Screenshot 2: Historical Chart
- [ ] Zoom ke chart area
- [ ] All 4 room types visible with different colors
- [ ] Legend visible

**Save as:** `historical_chart.png`

---

### ☐ Screenshot 3: Prediction 3 Months
- [ ] Form showing "3 Bulan" selected
- [ ] Prediction chart showing 3 months
- [ ] Table with 3 rows

**Save as:** `prediction_3months.png`

---

### ☐ Screenshot 4: Prediction 12 Months
- [ ] Prediction chart dengan 12 months
- [ ] Table dengan 12 rows
- [ ] Seasonal pattern visible di chart

**Save as:** `prediction_12months.png`

---

### ☐ Screenshot 5: Mobile View
- [ ] Browser window di-resize ke narrow
- [ ] Mobile responsive layout
- [ ] Vertical stack

**Save as:** `dashboard_mobile.png`

---

## 🐛 Troubleshooting

### ❌ Error: "Target class [PredictionController] does not exist"

**Fix:**
```bash
composer dump-autoload
php artisan config:clear
php artisan route:clear
```

Lalu restart:
```bash
php artisan serve
```

---

### ❌ Error: "No application encryption key"

**Fix:**
```bash
php artisan key:generate
```

---

### ❌ Port 8000 already in use

**Check:**
```bash
lsof -i :8000
```

**Kill process:**
```bash
kill -9 <PID>
```

Atau gunakan port lain:
```bash
php artisan serve --port=8080
```

Lalu buka: `http://localhost:8080/dashboard`

---

### ❌ Chart tidak muncul

**Fix:**
1. Buka Developer Tools (F12 atau Cmd+Option+I)
2. Lihat Console tab
3. Check ada error atau tidak

**Biasanya fix dengan:**
```bash
# Hard refresh
Cmd + Shift + R (Mac)
Ctrl + Shift + R (Windows)
```

---

### ❌ Composer command not found

**Fix:** Install Composer dulu

```bash
# Check if installed
composer --version

# If not, install from:
# https://getcomposer.org/download/
```

---

### ❌ PHP version too old

**Check:**
```bash
php --version
```

**Requirement:** PHP 8.1 atau lebih baru

**Fix:** Upgrade PHP via Homebrew:
```bash
brew install php@8.2
```

---

## ✅ Success Criteria

Dashboard berhasil jika:

- [x] Bisa buka http://localhost:8000/dashboard
- [x] Dashboard loads tanpa error
- [x] 4 metric cards tampil dengan values
- [x] Historical chart tampil dengan 4 lines
- [x] Generate prediction works (test beberapa scenarios)
- [x] Prediction chart dan table muncul
- [x] Toast notifications work
- [x] Loading overlay work
- [x] Responsive di mobile view
- [x] No errors di browser console

**Jika semua ✅ → Dashboard READY untuk presentasi!** 🎉

---

## 📞 Jika Masih Stuck

Check dokumentasi lengkap:
- `QUICK_START_FRONTEND.md` - Quick start guide
- `FRONTEND_DEMO_READY.md` - Detailed documentation
- `README_DASHBOARD.md` - Full README

Atau lihat Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

---

## 🎯 Next: Prepare Presentasi

Setelah testing berhasil:

1. ✅ Screenshots sudah diambil
2. ✅ Practice demo flow (6 menit)
3. ✅ Prepare Q&A answers
4. ✅ Insert screenshots ke proposal
5. ✅ Ready untuk show ke dosen!

---

**Good luck! 🚀**
