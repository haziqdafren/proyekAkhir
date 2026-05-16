<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Prediksi Hunian — Hotel Dharma</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: DejaVu Sans, Arial, sans-serif;
  font-size: 9.5pt;
  color: #1a2e3d;
  background: #fff;
}

.header {
  background: #2e5266;
  color: #fff;
  padding: 20px 24px 16px;
  margin-bottom: 16px;
}
.header .hotel    { font-size: 18pt; font-weight: bold; letter-spacing: 0.5px; }
.header .subtitle { font-size: 10pt; margin-top: 2px; opacity: 0.85; }
.header .meta     { margin-top: 10px; font-size: 8pt; opacity: 0.7; }

.summary-bar {
  display: table;
  width: 100%;
  border-collapse: separate;
  border-spacing: 8px;
  margin: 0 -8px 16px;
}
.summary-cell {
  display: table-cell;
  background: #f5f7fa;
  border: 1px solid #d6e4ec;
  border-top: 3px solid #2e5266;
  border-radius: 3px;
  padding: 10px 12px;
  text-align: center;
  width: 25%;
}
.summary-cell .s-label { font-size: 7.5pt; color: #6b7280; margin-bottom: 4px; }
.summary-cell .s-value { font-size: 15pt; font-weight: bold; color: #2e5266; line-height: 1; }
.summary-cell .s-sub   { font-size: 7pt; color: #9ca3af; margin-top: 2px; }

.month-block  { margin-bottom: 14px; }
.month-title  {
  background: #2e5266;
  color: #fff;
  padding: 7px 12px;
  font-size: 10pt;
  font-weight: bold;
  border-radius: 3px 3px 0 0;
}
.month-body {
  border: 1px solid #d6e4ec;
  border-top: none;
  border-radius: 0 0 3px 3px;
  overflow: hidden;
}

table.data { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
table.data th {
  background: #4a7fa5;
  color: #fff;
  padding: 6px 10px;
  text-align: left;
  font-size: 8pt;
}
table.data th.r { text-align: right; }
table.data th.c { text-align: center; }
table.data td   { padding: 7px 10px; border-bottom: 1px solid #e9eff3; vertical-align: top; }
table.data tr:last-child td  { border-bottom: none; }
table.data tr:nth-child(even) td { background: #f8fafc; }
table.data td.r { text-align: right; }
table.data td.c { text-align: center; }

.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8pt; font-weight: bold; }
.badge-high { background: #d1fae5; color: #065f46; }
.badge-mid  { background: #dbeafe; color: #1e40af; }
.badge-low  { background: #fee2e2; color: #991b1b; }

/* Recommendation box inside table cell */
.rec-box { padding: 4px 6px; border-radius: 3px; font-size: 7.5pt; }
.rec-high   { background: #fef9c3; border-left: 2px solid #ca8a04; }
.rec-medium { background: #fef3c7; border-left: 2px solid #f59e0b; }
.rec-low    { background: #d1fae5; border-left: 2px solid #10b981; }
.rec-none   { background: #f0f4f9; border-left: 2px solid #9ca3af; }
.rec-action { font-weight: bold; color: #1a2e3d; margin-bottom: 2px; }
.rec-detail { color: #4b5563; font-style: italic; }

.alert { padding: 7px 10px; border-radius: 3px; font-size: 8pt; margin-bottom: 8px; border-left: 3px solid; }
.alert-warn { background: #fef3c7; border-color: #f59e0b; color: #78350f; }
.alert-ok   { background: #d1fae5; border-color: #10b981; color: #065f46; }

.footer {
  margin-top: 16px;
  padding-top: 8px;
  border-top: 1px solid #d6e4ec;
  font-size: 7.5pt;
  color: #9ca3af;
  text-align: center;
}

.page-break { page-break-before: always; }
</style>
</head>
<body>

@php
  use Carbon\Carbon;
  use App\Models\RoomType;
  use App\Models\HistoricalOccupancyData;
  use App\Models\Prediction;
  use App\Services\RecommendationService;
  use App\Services\OccupancyCalculationService;

  $roomTypes  = RoomType::where('is_active', true)->get();
  $totalRooms = $roomTypes->sum('total_rooms');
  $recService = app(RecommendationService::class);
  $occService = app(OccupancyCalculationService::class);

  // Helper: get previous month occupancy (same logic as PredictionController)
  $getPrevOcc = function ($p) use ($occService, $roomTypes) {
      $prevMonth = Carbon::parse($p->getRawOriginal('predicted_for_date'))->subMonth();
      $prev = Prediction::where('model_type', $p->model_type)
          ->whereYear('predicted_for_date', $prevMonth->year)
          ->whereMonth('predicted_for_date', $prevMonth->month)
          ->when($p->room_type_id, fn($q) => $q->where('room_type_id', $p->room_type_id))
          ->orderByDesc('created_at')->first();
      if ($prev) return (float) $prev->predicted_occupancy_rate;
      $hist = HistoricalOccupancyData::whereYear('date', $prevMonth->year)
          ->whereMonth('date', $prevMonth->month)
          ->when($p->room_type_id, fn($q) => $q->where('room_type_id', $p->room_type_id))
          ->get();
      if ($hist->isNotEmpty()) return (float) $occService->calculateWeightedOccupancy($hist, $roomTypes);
      return 0.0;
  };

  // Enrich each prediction row
  $enriched = $predictions->map(function ($p) use ($totalRooms, $recService, $getPrevOcc) {
      $occ      = (float) $p->predicted_occupancy_rate;
      $capacity = $p->roomType ? $p->roomType->total_rooms : $totalRooms;
      $revenue  = (float) $p->predicted_revenue;
      $rooms    = (int)   $p->predicted_rooms_occupied;
      $label    = $p->roomType ? $p->roomType->name : 'Keseluruhan Hotel';
      $isSingle = $p->model_type === 'single';

      // Badge (status)
      [$badge, $badgeClass] = match(true) {
          $occ >= 55 => ['Tinggi', 'badge-high'],
          $occ >= 40 => ['Sedang', 'badge-mid'],
          default    => ['Rendah', 'badge-low'],
      };

      // Full yield recommendation — same as prediction pages
      $prevOcc = $getPrevOcc($p);
      $rec     = $recService->getRecommendation($occ, $prevOcc);

      // Urgency → CSS class
      $recClass = match($rec['urgency']) {
          'high'   => 'rec-high',
          'medium' => 'rec-medium',
          'low'    => 'rec-low',
          default  => 'rec-none',
      };

      $monthKey   = Carbon::parse($p->predicted_for_date)->format('Y-m');
      $monthLabel = Carbon::parse($p->predicted_for_date)->isoFormat('MMMM YYYY');

      return compact('p','occ','capacity','revenue','rooms','label','isSingle',
                     'badge','badgeClass','rec','recClass','monthKey','monthLabel');
  });

  $grouped    = $enriched->groupBy('monthLabel');
  $avgOcc     = $enriched->isNotEmpty() ? round($enriched->avg('occ'), 1) : 0;
  $totalRev   = $enriched->sum('revenue');
  $highCount  = $enriched->where('occ', '>=', 55)->count();
  $lowCount   = $enriched->where('occ', '<', 40)->count();
  $periods    = $grouped->count();

  // For summary: count distinct months (not rows) with high/low
  $monthAvgs = $grouped->map(fn($rows) => round($rows->avg('occ'), 1));
  $highMonthsCnt = $monthAvgs->filter(fn($v) => $v >= 55)->count();
  $lowMonthsCnt  = $monthAvgs->filter(fn($v) => $v < 40)->count();
@endphp

<!-- Header -->
<div class="header">
  <div class="hotel">Hotel Dharma</div>
  <div class="subtitle">Laporan Prediksi Tingkat Hunian</div>
  <div class="meta">
    Dibuat: {{ Carbon::now()->isoFormat('D MMMM YYYY, HH:mm') }} WIB
    &nbsp;&nbsp;·&nbsp;&nbsp;
    {{ $periods }} bulan diprediksi
  </div>
</div>

<!-- Summary bar -->
<div class="summary-bar">
  <div class="summary-cell">
    <div class="s-label">Rata-rata Hunian</div>
    <div class="s-value">{{ $avgOcc }}%</div>
    <div class="s-sub">seluruh periode</div>
  </div>
  <div class="summary-cell">
    <div class="s-label">Hunian Tinggi (≥ 55%)</div>
    <div class="s-value">{{ $highMonthsCnt }} bulan</div>
    <div class="s-sub">hunian ramai, potensi naikkan harga</div>
  </div>
  <div class="summary-cell">
    <div class="s-label">Perlu Promosi (< 40%)</div>
    <div class="s-value">{{ $lowMonthsCnt }} bulan</div>
    <div class="s-sub">hunian rendah, perlu tindakan</div>
  </div>
  <div class="summary-cell">
    <div class="s-label">Est. Total Pendapatan</div>
    <div class="s-value" style="font-size:11pt;">Rp {{ number_format($totalRev/1000000, 1, ',', '.') }}jt</div>
    <div class="s-sub">seluruh periode</div>
  </div>
</div>

<!-- Alerts -->
@if($lowMonthsCnt > 0)
<div class="alert alert-warn">
  <strong>Perhatian:</strong> {{ $lowMonthsCnt }} dari {{ $periods }} bulan diprediksi memiliki hunian rendah (&lt; 40%). Segera rencanakan promosi dan penyesuaian harga untuk bulan-bulan tersebut.
</div>
@endif
@if($highMonthsCnt > 0)
<div class="alert alert-ok">
  <strong>Peluang:</strong> {{ $highMonthsCnt }} dari {{ $periods }} bulan diprediksi memiliki hunian tinggi (≥ 55%). Pertimbangkan penyesuaian harga untuk memaksimalkan pendapatan.
</div>
@endif

<!-- Per-month blocks -->
@foreach($grouped as $monthLabel => $monthData)
@php
  $monthAvgOcc   = round($monthData->avg('occ'), 1);
  $monthTotalRev = $monthData->sum('revenue');
@endphp

<div class="month-block">
  <div class="month-title">
    {{ $monthLabel }}
    &nbsp;&nbsp;
    <span style="font-size:8.5pt; font-weight:normal; opacity:0.85;">
      Rata-rata hunian: <strong>{{ $monthAvgOcc }}%</strong>
      &nbsp;·&nbsp;
      Est. pendapatan: <strong>Rp {{ number_format($monthTotalRev, 0, ',', '.') }}</strong>
    </span>
  </div>

  <div class="month-body">
    <table class="data">
      <thead>
        <tr>
          <th style="width:16%">Kamar / Segmen</th>
          <th class="c" style="width:11%">Tingkat Hunian</th>
          <th class="c" style="width:12%">Kamar Terisi/Hari</th>
          <th class="r" style="width:14%">Est. Pendapatan</th>
          <th class="c" style="width:9%">Status</th>
          <th style="width:38%">Rekomendasi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($monthData as $row)
        <tr>
          <td><strong>{{ $row['label'] }}</strong></td>
          <td class="c"><strong>{{ number_format($row['occ'], 1) }}%</strong></td>
          <td class="c">{{ $row['rooms'] }} / {{ $row['capacity'] }} kamar</td>
          <td class="r">Rp {{ number_format($row['revenue'], 0, ',', '.') }}</td>
          <td class="c"><span class="badge {{ $row['badgeClass'] }}">{{ $row['badge'] }}</span></td>
          <td>
            <div class="rec-box {{ $row['recClass'] }}">
              <div class="rec-action">{{ $row['rec']['action'] }}</div>
              <div class="rec-detail">{{ $row['rec']['detail'] }}</div>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endforeach

<!-- Footer -->
<div class="footer">
  Hotel Dharma — Laporan ini dibuat otomatis oleh sistem. Bersifat konfidensial dan hanya untuk keperluan internal manajemen.
</div>

</body>
</html>
