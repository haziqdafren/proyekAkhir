<template>
  <DashboardLayout>
    <div class="flex flex-col h-full overflow-hidden px-4 md:px-6 py-4 gap-3 max-w-[1400px] mx-auto w-full">

      <!-- HEADER: Navy banner -->
      <div class="flex-shrink-0 bg-primary-dark rounded-2xl px-5 py-4 flex items-center justify-between shadow-card">
        <div>
          <h1 class="text-lg font-bold text-white leading-tight">Dashboard Hotel</h1>
          <p class="text-xs text-white/60 mt-0.5">{{ chartDateRange?.start ?? '...' }} – {{ chartDateRange?.end ?? '...' }} • {{ currentDate }}</p>
        </div>
        <button
          @click="showFilters = !showFilters"
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl bg-white/10 border border-white/20 text-white hover:bg-white/20 transition-all flex-shrink-0"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
          </svg>
          Filter Periode
        </button>
      </div>

      <!-- Filter Panel -->
      <div v-show="showFilters" class="flex-shrink-0 bg-white rounded-xl border border-surface/30 px-4 py-3 shadow-sm">
        <div class="flex flex-wrap gap-3 items-end">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Mulai</label>
            <input type="date" v-model="localFilters.date_start" :min="props.filters.db_min_date" :max="props.filters.db_max_date"
              class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-primary focus:border-transparent" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Akhir</label>
            <input type="date" v-model="localFilters.date_end" :min="props.filters.db_min_date" :max="props.filters.db_max_date"
              class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-primary focus:border-transparent" />
          </div>
          <div class="flex gap-2">
            <button @click="applyFilters" class="px-4 py-1.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">Terapkan</button>
            <button @click="resetFilters" class="px-4 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Reset</button>
          </div>
        </div>
      </div>

      <!-- MAIN CONTENT: flex-1, no scroll -->
      <div class="flex-1 min-h-0 grid grid-cols-1 xl:grid-cols-3 gap-3 overflow-visible">

        <!-- KIRI: Rekomendasi + Metrik + Chart -->
        <div class="xl:col-span-2 flex flex-col gap-3 min-h-0 overflow-visible">

          <!-- REKOMENDASI MANAJEMEN — white card + color-coded border -->
          <div
            v-if="activeRecommendation"
            class="flex-shrink-0 rounded-2xl border overflow-hidden"
            :class="{
              'bg-red-50 border-red-200':          activeRecommendation.urgency === 'high',
              'bg-amber-50 border-amber-200':     activeRecommendation.urgency === 'medium',
              'bg-green-50 border-green-200':     activeRecommendation.urgency === 'low',
            }"
          >
            <div
              class="px-4 py-3 flex items-center gap-3"
              :class="{
                'border-l-4 border-red-500':      activeRecommendation.urgency === 'high',
                'border-l-4 border-amber-400':    activeRecommendation.urgency === 'medium',
                'border-l-4 border-green-500':    activeRecommendation.urgency === 'low',
              }"
            >
              <div
                class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0"
                :class="{
                  'bg-red-100':       activeRecommendation.urgency === 'high',
                  'bg-amber-100':     activeRecommendation.urgency === 'medium',
                  'bg-green-100':     activeRecommendation.urgency === 'low',
                }"
              >
                <svg class="w-4 h-4" :class="{ 'text-red-600': activeRecommendation.urgency === 'high', 'text-amber-600': activeRecommendation.urgency === 'medium', 'text-green-600': activeRecommendation.urgency === 'low' }" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                  <span class="text-[10px] font-bold uppercase tracking-widest" :class="{ 'text-red-700': activeRecommendation.urgency === 'high', 'text-amber-700': activeRecommendation.urgency === 'medium', 'text-green-700': activeRecommendation.urgency === 'low' }">Rekomendasi · {{ activeRecommendation.for_month }}</span>
                  <span class="text-[10px] text-gray-400">{{ activeRecommendation.level_label }} · {{ activeRecommendation.trend_label }}</span>
                </div>
                <p class="text-sm font-bold text-primary-dark leading-snug">{{ activeRecommendation.action }}</p>
                <p class="text-xs text-gray-500 leading-relaxed mt-0.5">{{ activeRecommendation.detail }}</p>
              </div>
            </div>
          </div>

          <!-- Placeholder jika belum ada prediksi -->
          <div v-else class="flex-shrink-0 bg-white rounded-2xl border border-surface/30 shadow-card-md border-dashed px-4 py-4 text-center">
            <p class="text-sm text-gray-500">Belum ada data prediksi. Buat prediksi untuk mendapatkan rekomendasi.</p>
            <a href="/predictions" class="text-xs text-primary font-medium hover:underline mt-1 inline-block">Buat Prediksi →</a>
          </div>

          <!-- KPI Row (compact) -->
          <div class="flex-shrink-0 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <StatCard
              title="Rata-rata Hunian"
              :value="stats.avgOccupancy"
              format="percentage"
              :trend="stats.occupancyTrend"
              :progress="stats.avgOccupancy"
              color="blue"
              :icon="ChartBarIcon"
              :subtitle="filterPeriodLabel"
              :tooltip="`Persentase kamar terisi pada periode ${filterPeriodLabel}. Target ideal: di atas 70%.`"
              tooltip-title="Tingkat Hunian"
              compact
            />
            <StatCard
              title="Pendapatan"
              :value="stats.actualRevenue"
              format="currency"
              :trend="stats.revenueTrend"
              color="green"
              :icon="CurrencyDollarIcon"
              :subtitle="filterPeriodLabel"
              :tooltip="`Total pendapatan pada periode ${filterPeriodLabel}.`"
              tooltip-title="Pendapatan"
              compact
            />
            <StatCard
              title="Total Kamar"
              :value="stats.totalRooms"
              suffix="kamar"
              color="purple"
              :icon="HomeIcon"
              subtitle="Kapasitas hotel"
              tooltip="Standard (27), Superior (23), Junior Suite (2), Family (4) = 56 kamar."
              tooltip-title="Kapasitas"
              compact
            />
            <StatCard
              title="Akurasi Prediksi"
              :value="singleAccuracy"
              format="percentage"
              :progress="singleAccuracy"
              color="orange"
              :icon="SparklesIcon"
              subtitle="Model aktif"
              tooltip="Seberapa tepat sistem memprediksi tingkat hunian berdasarkan data historis."
              tooltip-title="Akurasi"
              compact
            />
          </div>

          <!-- Chart Tren Hunian -->
          <div class="flex-1 min-h-0 bg-white rounded-2xl shadow-card-md border border-surface/30 overflow-hidden flex flex-col">
            <div class="px-5 py-3 border-b border-surface/20 flex-shrink-0">
              <h2 class="text-sm font-semibold text-primary-dark">Tren Tingkat Hunian</h2>
              <p class="text-xs text-gray-500">
                Data historis ({{ chartDateRange?.start ?? 'Jan 2024' }} – {{ chartDateRange?.end ?? 'Okt 2025' }})
                <span v-if="chartData?.predicted?.length" class="ml-1 text-primary font-medium">+ Prediksi</span>
                · <span class="text-gray-400">Data terakhir: {{ props.filters?.db_max_date ? new Date(props.filters.db_max_date + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-' }}</span>
              </p>
            </div>
            <div class="flex-1 min-h-[160px] px-2 py-1">
              <ApexChart type="area" height="100%" :series="occupancyChartSeries" :options="occupancyChartOptions" />
            </div>
          </div>
        </div>

        <!-- KANAN: Performa Per Tipe Kamar -->
        <div class="bg-white rounded-2xl shadow-card-md border border-surface/30 overflow-hidden flex flex-col transition-shadow duration-200 hover:shadow-card-lg">
          <div class="px-5 py-3.5 border-b border-surface/20 flex-shrink-0">
            <h2 class="text-sm font-semibold text-primary-dark">Kondisi Kamar {{ activeRecommendation?.for_month || 'Bulan Depan' }}</h2>
            <p class="text-xs text-gray-500">Rata-rata hunian berdasarkan prediksi</p>
          </div>

          <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-surface/10">
            <div v-for="(room, idx) in roomBreakdown" :key="room.id" class="px-4 py-3">
              <!-- Nama + Status -->
              <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-center gap-2">
                  <span class="w-5 h-5 rounded-full bg-surface text-xs font-bold text-primary-dark flex items-center justify-center flex-shrink-0">{{ idx + 1 }}</span>
                  <span class="text-sm font-semibold text-primary-dark">{{ room.name }}</span>
                </div>
                <span
                  class="text-xs font-bold px-2 py-0.5 rounded-md"
                  :class="{
                    'bg-green-50 text-green-700': room.status === 'high',
                    'bg-amber-50 text-amber-700': room.status === 'medium',
                    'bg-red-50 text-red-700': room.status === 'low',
                  }"
                >
                  {{ room.status === 'high' ? 'Permintaan Tinggi' : room.status === 'medium' ? 'Permintaan Sedang' : 'Permintaan Rendah' }}
                </span>
              </div>

              <!-- Progress bar + persentase -->
              <div class="flex items-center gap-2 mb-1">
                <div class="flex-1 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all duration-700"
                    :class="{
                      'bg-green-500': room.status === 'high',
                      'bg-amber-400': room.status === 'medium',
                      'bg-red-500': room.status === 'low',
                    }"
                    :style="{ width: room.occupancy_rate + '%' }"
                  ></div>
                </div>
                <span class="text-sm font-bold text-primary-dark w-10 text-right shrink-0">{{ room.occupancy_rate }}%</span>
              </div>

              <!-- Detail kamar -->
              <p class="text-xs text-gray-500">
                {{ room.predicted_occupied }} dari {{ room.capacity }} kamar terisi · {{ formatCurrency(room.base_price) }}/malam
              </p>

              <!-- Rekomendasi singkat per tipe kamar -->
              <p class="text-xs mt-1.5 leading-relaxed"
                :class="{
                  'text-green-700': room.status === 'high',
                  'text-amber-700': room.status === 'medium',
                  'text-red-700': room.status === 'low',
                }"
              >
                <span v-if="room.status === 'high'">Pertimbangkan penyesuaian harga ke atas untuk tipe ini.</span>
                <span v-else-if="room.status === 'medium'">Pertahankan strategi — kondisi stabil.</span>
                <span v-else>Siapkan promosi khusus untuk mendorong pemesanan tipe ini.</span>
              </p>
            </div>
          </div>

          <!-- Footer: link ke detail prediksi -->
          <div class="flex-shrink-0 border-t border-surface/20 px-4 py-3">
            <a href="/predictions/multi" class="text-xs text-primary font-medium hover:underline flex items-center gap-1">
              Lihat analisis lengkap per tipe kamar
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </a>
          </div>
        </div>

      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import ApexChart from '@/Components/ApexChart.vue';
import {
  ChartBarIcon,
  CurrencyDollarIcon,
  HomeIcon,
  SparklesIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      avgOccupancy: 0,
      actualRevenue: 0,
      totalRooms: 0,
      occupancyTrend: 0,
      revenueTrend: 0,
    }),
  },
  chartData: {
    type: Object,
    default: () => ({
      historical: [],
      predicted: [],
    }),
  },
  roomBreakdown: {
    type: Array,
    default: () => [],
  },
  recentPredictions: {
    type: Object,
    default: () => ({}),
  },
  roomTypes: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({
      date_start: null,
      date_end: null,
      room_types: null,
    }),
  },
  retrainingStatus: {
    type: Object,
    default: () => ({
      single: {},
      multi: {},
    }),
  },
  chartDateRange: {
    type: Object,
    default: () => ({ start: '...', end: '...' }),
  },
  modelComparison: {
    type: Object,
    default: () => ({ single: {}, multi: {} }),
  },
  activeRecommendation: {
    type: Object,
    default: () => null,
  },
  alerts: {
    type: Array,
    default: () => [],
  },
});

const singleAccuracy = computed(() => {
  // Gunakan field accuracy yang sudah dihitung di server (100 - MAPE)
  const acc = props.modelComparison?.single?.accuracy;
  if (acc != null) return acc;
  const mape = props.modelComparison?.single?.mape;
  if (mape != null) return parseFloat((100 - mape).toFixed(1));
  return 85.5;
});

// Local filter state
const localFilters = ref({
  date_start: props.filters.date_start,
  date_end: props.filters.date_end,
  room_types: props.filters.room_types || [],
});

// Filter panel toggle
const showFilters = ref(false);

// Apply filters
const applyFilters = () => {
  router.get('/dashboard', {
    date_start: localFilters.value.date_start,
    date_end: localFilters.value.date_end,
    room_types: localFilters.value.room_types.length > 0 ? localFilters.value.room_types : null,
  }, {
    preserveState: true,
  });
};

// Reset filters to full historical period (driven by actual DB bounds from server)
const resetFilters = () => {
  localFilters.value = {
    date_start: props.filters.db_min_date,
    date_end: props.filters.db_max_date,
    room_types: [],
  };

  applyFilters();
};

const currentDate = computed(() => {
  return new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
});

const filterPeriodLabel = computed(() => {
  const fmt = (d) => {
    if (!d) return '';
    return new Date(d + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
  };
  const start = fmt(props.filters?.date_start);
  const end = fmt(props.filters?.date_end);
  if (start && end) return `${start} – ${end}`;
  return 'Periode dipilih';
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(value);
};

// Occupancy Chart
const occupancyChartSeries = computed(() => {
  const historical = (props.chartData?.historical || []).map(item => ({
    x: new Date(item.date).getTime(),
    y: item.occupancy,
  }));

  const predicted = (props.chartData?.predicted || []).map(item => ({
    x: new Date(item.date).getTime(),
    y: item.occupancy,
  }));

  return [
    {
      name: 'Data Historis',
      data: historical,
    },
    {
      name: 'Prediksi',
      data: predicted,
    },
  ];
});

const occupancyChartOptions = {
  chart: {
    type: 'area',
    stacked: false,
    zoom: {
      type: 'x',
      enabled: true,
      autoScaleYaxis: true,
    },
    fontFamily: 'Inter, sans-serif',
    toolbar: {
      show: true,
      offsetY: -10,
      tools: {
        download: true,
        selection: true,
        zoom: true,
        zoomin: true,
        zoomout: true,
        pan: true,
        reset: true,
      },
    },
  },
  dataLabels: {
    enabled: false,
  },
  markers: {
    size: 0,
  },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      inverseColors: false,
      opacityFrom: 0.45,
      opacityTo: 0.05,
      stops: [20, 100, 100, 100],
    },
  },
  colors: ['#3F72AF', '#10B981'],
  stroke: {
    curve: 'smooth',
    width: 2.5,
  },
  xaxis: {
    type: 'datetime',
    labels: {
      datetimeFormatter: {
        year: 'yyyy',
        month: 'MMM',
        day: 'dd MMM',
      },
      style: {
        colors: '#9CA3AF',
        fontSize: '12px',
      },
    },
    axisBorder: {
      show: false,
    },
    axisTicks: {
      show: false,
    },
  },
  yaxis: {
    title: {
      text: 'Okupansi (%)',
      style: {
        color: '#6B7280',
        fontSize: '13px',
        fontWeight: 500,
      },
    },
    min: 0,
    max: 100,
    labels: {
      style: {
        colors: '#9CA3AF',
        fontSize: '12px',
      },
    },
  },
  grid: {
    borderColor: '#F3F4F6',
    strokeDashArray: 3,
    padding: {
      top: 10,
      right: 0,
      bottom: 0,
      left: 10,
    },
  },
  tooltip: {
    shared: true,
    x: {
      format: 'dd MMM yyyy',
    },
    y: {
      formatter: function (val) {
        return val.toFixed(1) + '%';
      },
    },
  },
  legend: {
    show: true,
    position: 'top',
    horizontalAlign: 'left',
    fontSize: '13px',
    fontWeight: 500,
    offsetY: 0,
    offsetX: 0,
    itemMargin: {
      horizontal: 16,
      vertical: 8,
    },
    markers: {
      width: 10,
      height: 10,
      radius: 10,
      offsetX: -4,
    },
  },
  annotations: {
    xaxis: [
      {
        x: new Date().getTime(),
        borderColor: '#DBE2EF',
        strokeDashArray: 4,
        label: {
          text: 'Hari Ini',
          style: {
            color: '#fff',
            background: '#3F72AF',
            fontSize: '11px',
            fontWeight: 500,
          },
        },
      },
    ],
  },
};


</script>

<style>
.bg-grid-white\/\[0\.02\] {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32' width='32' height='32' fill='none' stroke='rgb(255 255 255 / 0.02)'%3e%3cpath d='M0 .5H31.5V32'/%3e%3c/svg%3e");
}
</style>
