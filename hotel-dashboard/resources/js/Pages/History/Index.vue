<template>
  <DashboardLayout>
    <div class="px-6 py-6 max-w-[1400px] mx-auto w-full space-y-5">

      <!-- Page Header with top filters (controls charts) -->
      <div class="bg-primary-dark rounded-2xl px-6 py-4 shadow-card flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-lg font-bold text-white leading-tight">Riwayat Data</h1>
          <p class="text-sm text-white/60 mt-0.5">Data historis hotel<span v-if="dbDateRange"> · {{ dbDateRange }}</span></p>
        </div>
        <div class="flex flex-wrap gap-2">
          <select v-model="localFilters.years_back" @change="applyFilters"
            class="px-3 py-2 text-sm font-medium rounded-xl bg-white/10 border border-white/20 text-white focus:outline-none">
            <option value="1" class="text-gray-900 bg-white">1 Tahun Terakhir</option>
            <option value="2" class="text-gray-900 bg-white">2 Tahun Terakhir</option>
            <option value="3" class="text-gray-900 bg-white">3 Tahun Terakhir</option>
            <option value="5" class="text-gray-900 bg-white">5 Tahun Terakhir</option>
          </select>
          <select v-model="localFilters.room_type" @change="applyFilters"
            class="px-3 py-2 text-sm font-medium rounded-xl bg-white/10 border border-white/20 text-white focus:outline-none">
            <option value="all" class="text-gray-900 bg-white">Semua Tipe Kamar</option>
            <option v-for="rt in roomTypes" :key="rt.id" :value="rt.id" class="text-gray-900 bg-white">{{ rt.name }}</option>
          </select>
        </div>
      </div>

      <!-- KPI Row -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-surface/30 shadow-card-md p-5">
          <div class="flex items-start justify-between mb-3">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Rata-rata Hunian</p>
            <span class="text-xs font-bold px-2 py-0.5 rounded-lg"
              :class="stats.avgOccupancy >= 55 ? 'bg-green-100 text-green-700' : stats.avgOccupancy >= 40 ? 'bg-amber-100 text-amber-700' : 'bg-red-50 text-red-600'">
              {{ stats.avgOccupancy >= 55 ? 'Tinggi' : stats.avgOccupancy >= 40 ? 'Sedang' : 'Rendah' }}
            </span>
          </div>
          <p class="text-4xl font-black text-primary-dark leading-none mb-2">{{ stats.avgOccupancy }}<span class="text-xl font-bold text-gray-400">%</span></p>
          <div class="w-full bg-gray-100 rounded-full h-1.5 mb-2">
            <div class="h-1.5 rounded-full"
              :class="stats.avgOccupancy >= 55 ? 'bg-green-500' : stats.avgOccupancy >= 40 ? 'bg-amber-400' : 'bg-primary'"
              :style="{ width: Math.min(stats.avgOccupancy, 100) + '%' }"></div>
          </div>
          <p class="text-xs text-gray-400">Min {{ stats.minOccupancy }}% · Maks {{ stats.maxOccupancy }}%</p>
        </div>

        <div class="bg-white rounded-2xl border border-surface/30 shadow-card-md p-5">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Total Pendapatan</p>
          <p class="text-4xl font-black text-primary-dark leading-none mb-1">{{ formatCurrencyShort(stats.totalRevenue) }}</p>
          <p class="text-xs text-gray-400 mb-3">Akumulasi dari data upload (data lama tidak tersedia)</p>
          <div class="pt-3 border-t border-surface/20">
            <p class="text-xs text-gray-400 mb-0.5">Rata-rata per hari</p>
            <p class="text-xl font-bold text-primary-dark">{{ formatCurrencyShort(stats.avgRevenue) }}</p>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-surface/30 shadow-card-md p-5">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Data Tercatat</p>
          <p class="text-4xl font-black text-primary-dark leading-none mb-1">{{ stats.totalRecords }}</p>
          <p class="text-xs text-gray-400">hari operasional</p>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="!hasData" class="bg-white rounded-2xl shadow-card-md border border-surface/20 p-12 text-center">
        <h3 class="text-lg font-semibold text-primary-dark mb-2">Tidak Ada Data</h3>
        <p class="text-sm text-gray-500 mb-5">Tidak ada data historis untuk filter yang dipilih.</p>
        <button @click="resetFilters" class="px-5 py-2.5 bg-primary text-white text-sm font-medium rounded-xl hover:bg-primary-dark transition-colors">Reset Filter</button>
      </div>

      <template v-if="hasData">

        <!-- Occupancy Trend Chart (time-series, controlled by top filter) -->
        <div class="bg-white rounded-2xl shadow-card-md border border-surface/30 overflow-hidden">
          <div class="px-5 py-4 border-b border-surface/20 flex items-center justify-between">
            <div>
              <h2 class="text-sm font-bold text-primary-dark">Tren Tingkat Hunian</h2>
              <p class="text-xs text-gray-400 mt-0.5">Rata-rata hunian bulanan per tipe kamar</p>
            </div>
            <div v-if="isLoading" class="w-4 h-4 border-2 border-primary/30 border-t-primary rounded-full animate-spin"></div>
          </div>
          <div v-if="isLoading" class="px-5 py-5">
            <div class="h-[280px] animate-pulse rounded-xl bg-gray-100"></div>
          </div>
          <div v-else class="px-4 py-3">
            <ApexChart type="line" :height="280" :series="chartData.occupancy" :options="occupancyChartOptions" />
          </div>
        </div>

        <!-- Revenue Trend Chart (time-series, controlled by top filter) -->
        <div class="bg-white rounded-2xl shadow-card-md border border-surface/30 overflow-hidden">
          <div class="px-5 py-4 border-b border-surface/20">
            <h2 class="text-sm font-bold text-primary-dark">Tren Pendapatan</h2>
            <p class="text-xs text-gray-400 mt-0.5">Total pendapatan bulanan per tipe kamar</p>
          </div>
          <div v-if="isLoading" class="px-5 py-5">
            <div class="h-[280px] animate-pulse rounded-xl bg-gray-100"></div>
          </div>
          <div v-else class="px-4 py-3">
            <ApexChart type="area" :height="280" :series="chartData.revenue" :options="revenueChartOptions" />
          </div>
        </div>

        <!-- ═══════════════════════════════════════════════════
             YEAR-ON-YEAR COMPARISON TABLE
             Separate from charts — has its own year dropdowns
        ════════════════════════════════════════════════════ -->
        <div class="bg-white rounded-2xl shadow-card-md border border-surface/30 overflow-hidden">

          <!-- Section header with year pickers -->
          <div class="px-5 py-4 border-b border-surface/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
              <h2 class="text-sm font-bold text-primary-dark">Perbandingan Tahun ke Tahun</h2>
              <p class="text-xs text-gray-400 mt-0.5">Hunian &amp; pendapatan per bulan (Jan–Des) — bandingkan 2 tahun</p>
            </div>
            <!-- Year pickers — independent from top filter -->
            <div class="flex items-center gap-2">
              <div class="flex items-center gap-1.5 bg-primary/5 border border-primary/20 rounded-xl px-3 py-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-primary flex-shrink-0"></span>
                <select v-model="year1" @change="applyYearFilter"
                  class="text-sm font-bold text-primary bg-transparent border-none focus:outline-none cursor-pointer">
                  <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                </select>
              </div>
              <span class="text-xs font-bold text-gray-400">vs</span>
              <div class="flex items-center gap-1.5 bg-emerald-50 border border-emerald-200 rounded-xl px-3 py-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 flex-shrink-0"></span>
                <select v-model="year2" @change="applyYearFilter"
                  class="text-sm font-bold text-emerald-700 bg-transparent border-none focus:outline-none cursor-pointer">
                  <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Column headers legend -->
          <div class="px-5 py-3 bg-gray-50/60 border-b border-surface/20 flex items-center gap-6">
            <div class="flex items-center gap-1.5">
              <span class="w-3 h-3 rounded bg-primary flex-shrink-0"></span>
              <span class="text-xs font-bold text-primary">Tahun {{ year1 }}</span>
            </div>
            <div class="flex items-center gap-1.5">
              <span class="w-3 h-3 rounded bg-emerald-500 flex-shrink-0"></span>
              <span class="text-xs font-bold text-emerald-600">Tahun {{ year2 }}</span>
            </div>
            <span class="text-xs text-gray-400 ml-auto">▲ = {{ year1 }} lebih baik &nbsp;·&nbsp; ▼ = {{ year2 }} lebih baik</span>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr class="bg-gray-50/50">
                  <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Bulan</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-primary uppercase tracking-wide">Hunian {{ year1 }}</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-emerald-600 uppercase tracking-wide">Hunian {{ year2 }}</th>
                  <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Selisih Hunian</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-primary uppercase tracking-wide border-l border-surface/30">Pendapatan {{ year1 }}</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-emerald-600 uppercase tracking-wide">Pendapatan {{ year2 }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-surface/20">
                <tr v-for="(row, i) in tableRows" :key="i"
                  class="hover:bg-blue-50/20 transition-colors"
                  :class="{ 'bg-surface/10': i % 2 === 0 }">

                  <td class="px-5 py-3 whitespace-nowrap text-sm font-bold text-primary-dark">{{ row.month }}</td>

                  <!-- Year1 occupancy + badge -->
                  <td class="px-4 py-3 whitespace-nowrap text-right">
                    <template v-if="row.occ1 !== null">
                      <span class="text-sm font-bold mr-1"
                        :class="row.occ1 >= 55 ? 'text-green-600' : row.occ1 >= 40 ? 'text-amber-600' : 'text-red-500'">
                        {{ row.occ1 }}%
                      </span>
                      <span class="text-xs px-1.5 py-0.5 rounded font-semibold"
                        :class="row.occ1 >= 55 ? 'bg-green-50 text-green-600' : row.occ1 >= 40 ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-500'">
                        {{ row.occ1 >= 55 ? 'Tinggi' : row.occ1 >= 40 ? 'Sedang' : 'Rendah' }}
                      </span>
                    </template>
                    <span v-else class="text-xs text-gray-300">—</span>
                  </td>

                  <!-- Year2 occupancy + badge -->
                  <td class="px-4 py-3 whitespace-nowrap text-right">
                    <template v-if="row.occ2 !== null">
                      <span class="text-sm font-bold mr-1"
                        :class="row.occ2 >= 55 ? 'text-green-600' : row.occ2 >= 40 ? 'text-amber-600' : 'text-red-500'">
                        {{ row.occ2 }}%
                      </span>
                      <span class="text-xs px-1.5 py-0.5 rounded font-semibold"
                        :class="row.occ2 >= 55 ? 'bg-green-50 text-green-600' : row.occ2 >= 40 ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-500'">
                        {{ row.occ2 >= 55 ? 'Tinggi' : row.occ2 >= 40 ? 'Sedang' : 'Rendah' }}
                      </span>
                    </template>
                    <span v-else class="text-xs text-gray-300">—</span>
                  </td>

                  <!-- Diff -->
                  <td class="px-4 py-3 whitespace-nowrap text-center">
                    <span v-if="row.diff !== null"
                      class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full"
                      :class="row.diff > 0 ? 'bg-primary/10 text-primary' : row.diff < 0 ? 'bg-red-50 text-red-500' : 'bg-gray-100 text-gray-400'">
                      <span>{{ row.diff > 0 ? '▲' : row.diff < 0 ? '▼' : '=' }}</span>
                      {{ row.diff > 0 ? '+' : '' }}{{ row.diff }}%
                    </span>
                    <span v-else class="text-xs text-gray-300">—</span>
                  </td>

                  <!-- Year1 revenue -->
                  <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-700 border-l border-surface/30">
                    <span v-if="row.rev1 !== null">{{ formatCurrencyShort(row.rev1) }}</span>
                    <span v-else class="text-xs text-gray-300">—</span>
                  </td>

                  <!-- Year2 revenue -->
                  <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-700">
                    <span v-if="row.rev2 !== null">{{ formatCurrencyShort(row.rev2) }}</span>
                    <span v-else class="text-xs text-gray-300">—</span>
                  </td>
                </tr>
              </tbody>

              <!-- Footer: annual totals -->
              <tfoot class="border-t-2 border-primary/20 bg-primary-dark/5">
                <tr>
                  <td class="px-5 py-3 text-xs font-bold text-primary-dark uppercase tracking-wide">Rata-rata / Total</td>
                  <td class="px-4 py-3 text-right text-sm font-bold text-primary">{{ avgOcc1 !== null ? avgOcc1 + '%' : '—' }}</td>
                  <td class="px-4 py-3 text-right text-sm font-bold text-emerald-600">{{ avgOcc2 !== null ? avgOcc2 + '%' : '—' }}</td>
                  <td class="px-4 py-3 text-center">
                    <span v-if="avgDiff !== null"
                      class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full"
                      :class="avgDiff > 0 ? 'bg-primary/10 text-primary' : avgDiff < 0 ? 'bg-red-50 text-red-500' : 'bg-gray-100 text-gray-400'">
                      {{ avgDiff > 0 ? '▲' : avgDiff < 0 ? '▼' : '●' }}
                      {{ avgDiff > 0 ? '+' : '' }}{{ avgDiff }}% rata-rata
                    </span>
                  </td>
                  <td class="px-4 py-3 text-right text-sm font-bold text-primary border-l border-surface/30">{{ totalRev1 !== null ? formatCurrencyShort(totalRev1) : '—' }}</td>
                  <td class="px-4 py-3 text-right text-sm font-bold text-emerald-600">{{ totalRev2 !== null ? formatCurrencyShort(totalRev2) : '—' }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

      </template>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import ApexChart from '@/Components/ApexChart.vue';

const props = defineProps({
  historicalData: Array,
  stats: Object,
  chartData: Object,
  performanceByRoomType: Array,
  monthlyComparison: Array,
  insights: Array,
  roomTypes: Array,
  yearComparison: Object,
  availableYears: Array,
  filters: Object,
});

// ─── Top filter state (controls monthly trend charts) ──
const localFilters = ref({
  years_back: props.filters?.years_back ?? 2,
  room_type:  props.filters?.room_type  ?? 'all',
});

// ─── Year comparison state (controls table only) ──────
const year1 = ref(props.filters?.year1 ?? props.availableYears?.[0] ?? new Date().getFullYear());
const year2 = ref(props.filters?.year2 ?? props.availableYears?.[1] ?? (new Date().getFullYear() - 1));

const isLoading = ref(false);

// ─── Computed helpers ─────────────────────────────────
const dbDateRange = computed(() => {
  const min = props.filters?.db_min_date;
  const max = props.filters?.db_max_date;
  if (!min || !max) return '';
  const fmt = d => new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
  return `${fmt(min)} – ${fmt(max)}`;
});

const hasData = computed(() => Array.isArray(props.historicalData) && props.historicalData.length > 0);

// ─── Monthly chart options ────────────────────────────
const ROOM_COLORS = ['#3F72AF', '#10B981', '#F59E0B', '#F43F5E'];

const BASE_CHART = {
  fontFamily: 'Inter, sans-serif',
  toolbar: { show: false },
  zoom: { enabled: false },
  animations: { enabled: true, speed: 500 },
};

const occupancyChartOptions = {
  chart: { ...BASE_CHART, type: 'line' },
  colors: ROOM_COLORS,
  stroke: { curve: 'smooth', width: 2.5 },
  markers: { size: 4, hover: { size: 6 } },
  xaxis: {
    type: 'datetime',
    labels: {
      datetimeFormatter: { year: 'yyyy', month: "MMM 'yy" },
      style: { colors: '#9CA3AF', fontSize: '11px' },
    },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    title: { text: 'Hunian (%)', style: { color: '#6B7280', fontSize: '12px', fontWeight: 500 } },
    min: 0, max: 100,
    labels: { style: { colors: '#9CA3AF', fontSize: '12px' }, formatter: v => v + '%' },
  },
  annotations: {
    yaxis: [
      { y: 55, borderColor: '#10B981', borderWidth: 1, strokeDashArray: 5, label: { text: 'Tinggi ≥55%', style: { color: '#10B981', fontSize: '10px', background: 'transparent', padding: { left: 4 } }, position: 'left', offsetX: 8 } },
      { y: 40, borderColor: '#F59E0B', borderWidth: 1, strokeDashArray: 5, label: { text: 'Sedang ≥40%', style: { color: '#F59E0B', fontSize: '10px', background: 'transparent', padding: { left: 4 } }, position: 'left', offsetX: 8 } },
    ],
  },
  grid: { borderColor: '#F3F4F6', strokeDashArray: 3, padding: { top: 10, right: 20, bottom: 0, left: 10 } },
  legend: { show: true, position: 'top', horizontalAlign: 'left', fontSize: '12px', fontWeight: 500, itemMargin: { horizontal: 16, vertical: 8 }, markers: { width: 10, height: 10, radius: 10 } },
  tooltip: { shared: true, x: { format: "MMM yyyy" }, y: { formatter: v => (v ?? 0).toFixed(1) + '%' } },
};

const revenueChartOptions = {
  chart: { ...BASE_CHART, type: 'area' },
  colors: ROOM_COLORS,
  stroke: { curve: 'smooth', width: 2.5 },
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [20, 100] } },
  markers: { size: 3, hover: { size: 5 } },
  xaxis: {
    type: 'datetime',
    labels: {
      datetimeFormatter: { year: 'yyyy', month: "MMM 'yy" },
      style: { colors: '#9CA3AF', fontSize: '11px' },
    },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    title: { text: 'Pendapatan (Rp)', style: { color: '#6B7280', fontSize: '12px', fontWeight: 500 } },
    labels: { style: { colors: '#9CA3AF', fontSize: '12px' }, formatter: v => v ? formatCurrencyShort(v) : '—' },
  },
  grid: { borderColor: '#F3F4F6', strokeDashArray: 3, padding: { top: 10, right: 20, bottom: 0, left: 10 } },
  legend: { show: true, position: 'top', horizontalAlign: 'left', fontSize: '12px', fontWeight: 500, itemMargin: { horizontal: 16, vertical: 8 }, markers: { width: 10, height: 10, radius: 10 } },
  tooltip: { shared: true, x: { format: "MMM yyyy" }, y: { formatter: v => v ? formatCurrency(v) : 'Tidak ada data' } },
};

// ─── Year comparison table ────────────────────────────
const MONTHS_FULL = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

const getYD = (year, key) => {
  const yc = props.yearComparison ?? {};
  const entry = yc[year] ?? yc[String(year)] ?? null;
  if (!entry) return Array(12).fill(null);
  return (entry[key] ?? []).map(v => v ?? null);
};

const tableRows = computed(() => {
  const occ1 = getYD(year1.value, 'occupancy');
  const occ2 = getYD(year2.value, 'occupancy');
  const rev1 = getYD(year1.value, 'revenue');
  const rev2 = getYD(year2.value, 'revenue');
  return MONTHS_FULL.map((month, i) => {
    const o1 = occ1[i];
    const o2 = occ2[i];
    return {
      month,
      occ1: o1,
      occ2: o2,
      diff: (o1 !== null && o2 !== null) ? Math.round((o1 - o2) * 10) / 10 : null,
      rev1: rev1[i],
      rev2: rev2[i],
    };
  });
});

const avgOcc1 = computed(() => {
  const vals = tableRows.value.map(r => r.occ1).filter(v => v !== null);
  return vals.length ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length * 10) / 10 : null;
});
const avgOcc2 = computed(() => {
  const vals = tableRows.value.map(r => r.occ2).filter(v => v !== null);
  return vals.length ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length * 10) / 10 : null;
});
const avgDiff = computed(() => {
  if (avgOcc1.value === null || avgOcc2.value === null) return null;
  return Math.round((avgOcc1.value - avgOcc2.value) * 10) / 10;
});
const totalRev1 = computed(() => {
  const vals = tableRows.value.map(r => r.rev1).filter(v => v !== null);
  return vals.length ? vals.reduce((a, b) => a + b, 0) : null;
});
const totalRev2 = computed(() => {
  const vals = tableRows.value.map(r => r.rev2).filter(v => v !== null);
  return vals.length ? vals.reduce((a, b) => a + b, 0) : null;
});

// ─── Navigation ───────────────────────────────────────
const go = () => {
  isLoading.value = true;
  router.get('/history', {
    years_back: localFilters.value.years_back,
    room_type:  localFilters.value.room_type,
    year1: year1.value,
    year2: year2.value,
  }, { preserveState: true, preserveScroll: true, onFinish: () => { isLoading.value = false; } });
};

const applyFilters    = go;
const applyYearFilter = go;

const resetFilters = () => {
  localFilters.value = { years_back: 2, room_type: 'all' };
  go();
};

// ─── Formatters ───────────────────────────────────────
const formatCurrency = v =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(v);

const formatCurrencyShort = v => {
  if (v === null || v === undefined || v === 0) return '—';
  if (v >= 1_000_000_000) return `Rp ${(v / 1_000_000_000).toFixed(2)} M`;
  if (v >= 1_000_000) return `Rp ${(v / 1_000_000).toFixed(1)} Jt`;
  return formatCurrency(v);
};
</script>
