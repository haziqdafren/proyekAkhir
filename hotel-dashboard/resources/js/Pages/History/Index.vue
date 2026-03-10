<template>
  <DashboardLayout>
    <div class="p-8 space-y-8 max-w-[1600px] mx-auto">
      <!-- Header with Filters -->
      <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
        <div class="px-8 py-6 border-b border-surface/30">
          <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
            <div>
              <h1 class="text-2xl font-semibold text-primary-dark">Riwayat Data</h1>
              <p class="text-sm text-gray-500 mt-1.5">Analisis data okupansi historis hotel</p>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
              <select
                v-model="filters.date_range"
                @change="applyFilters"
                class="px-4 py-2.5 text-sm font-medium border border-surface rounded-2xl bg-background focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"
              >
                <option value="30">30 Hari Terakhir</option>
                <option value="60">60 Hari Terakhir</option>
                <option value="90">90 Hari Terakhir</option>
                <option value="180">6 Bulan Terakhir</option>
                <option value="365">1 Tahun Terakhir</option>
              </select>

              <select
                v-model="filters.room_type"
                @change="applyFilters"
                class="px-4 py-2.5 text-sm font-medium border border-surface rounded-2xl bg-background focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"
              >
                <option value="all">Semua Tipe Kamar</option>
                <option v-for="roomType in roomTypes" :key="roomType.id" :value="roomType.id">
                  {{ roomType.name }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 divide-y md:divide-y-0 md:divide-x divide-surface/30">
          <div class="px-8 py-6">
            <div class="space-y-3">
              <div class="flex items-center space-x-2">
                <div class="p-2 bg-primary/10 rounded-xl">
                  <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                  </svg>
                </div>
                <p class="text-xs text-gray-500 font-medium">Rata-rata</p>
              </div>
              <p class="text-2xl font-semibold text-primary-dark">{{ stats.avgOccupancy }}%</p>
            </div>
          </div>

          <div class="px-8 py-6">
            <div class="space-y-3">
              <div class="flex items-center space-x-2">
                <div class="p-2 bg-green-50 rounded-xl">
                  <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                  </svg>
                </div>
                <p class="text-xs text-gray-500 font-medium">Maksimum</p>
              </div>
              <p class="text-2xl font-semibold text-primary-dark">{{ stats.maxOccupancy }}%</p>
            </div>
          </div>

          <div class="px-8 py-6">
            <div class="space-y-3">
              <div class="flex items-center space-x-2">
                <div class="p-2 bg-orange-50 rounded-xl">
                  <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                  </svg>
                </div>
                <p class="text-xs text-gray-500 font-medium">Minimum</p>
              </div>
              <p class="text-2xl font-semibold text-primary-dark">{{ stats.minOccupancy }}%</p>
            </div>
          </div>

          <div class="px-8 py-6">
            <div class="space-y-3">
              <div class="flex items-center space-x-2">
                <div class="p-2 bg-purple-50 rounded-xl">
                  <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <p class="text-xs text-gray-500 font-medium">Total Revenue</p>
              </div>
              <p class="text-2xl font-semibold text-primary-dark">{{ formatCurrencyShort(stats.totalRevenue) }}</p>
            </div>
          </div>

          <div class="px-8 py-6">
            <div class="space-y-3">
              <div class="flex items-center space-x-2">
                <div class="p-2 bg-indigo-50 rounded-xl">
                  <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                  </svg>
                </div>
                <p class="text-xs text-gray-500 font-medium">Avg. Harian</p>
              </div>
              <p class="text-2xl font-semibold text-primary-dark">{{ formatCurrencyShort(stats.avgRevenue) }}</p>
            </div>
          </div>

          <div class="px-8 py-6">
            <div class="space-y-3">
              <div class="flex items-center space-x-2">
                <div class="p-2 bg-blue-50 rounded-xl">
                  <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <p class="text-xs text-gray-500 font-medium">Total Data</p>
              </div>
              <p class="text-2xl font-semibold text-primary-dark">{{ stats.totalRecords }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <!-- Occupancy Trend Chart -->
        <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
          <div class="px-8 pt-8 pb-6 border-b border-surface/20">
            <div class="space-y-2.5">
              <h2 class="text-xl font-semibold text-primary-dark">Trend Okupansi Historis</h2>
              <p class="text-sm text-gray-500 leading-relaxed">Pergerakan okupansi per tipe kamar</p>
            </div>
          </div>

          <div class="px-8 py-6">
            <ApexChart
              type="line"
              :height="380"
              :series="chartData.occupancy"
              :options="occupancyChartOptions"
            />
          </div>
        </div>

        <!-- Revenue Trend Chart -->
        <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
          <div class="px-8 pt-8 pb-6 border-b border-surface/20">
            <div class="space-y-2.5">
              <h2 class="text-xl font-semibold text-primary-dark">Trend Pendapatan</h2>
              <p class="text-sm text-gray-500 leading-relaxed">Revenue per tipe kamar</p>
            </div>
          </div>

          <div class="px-8 py-6">
            <ApexChart
              type="area"
              :height="380"
              :series="chartData.revenue"
              :options="revenueChartOptions"
            />
          </div>
        </div>
      </div>

      <!-- Performance by Room Type -->
      <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
        <div class="px-8 py-6 border-b border-surface/30">
          <h2 class="text-xl font-semibold text-primary-dark">Performa Per Tipe Kamar</h2>
          <p class="text-sm text-gray-500 mt-1">Ringkasan statistik periode terpilih</p>
        </div>

        <div class="p-8">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div
              v-for="room in performanceByRoomType"
              :key="room.id"
              class="relative p-6 rounded-2xl border border-surface/40 hover:border-primary/30 transition-all duration-300 hover:shadow-md"
            >
              <div class="flex items-start justify-between mb-6">
                <div class="flex items-center space-x-4">
                  <div :class="getRoomColorClass(room.color, 'bg')" class="p-4 rounded-2xl">
                    <svg :class="getRoomColorClass(room.color, 'text')" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                  </div>
                  <div>
                    <h3 class="text-lg font-semibold text-primary-dark">{{ room.name }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ room.totalDays }} hari data</p>
                  </div>
                </div>

                <span
                  class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-semibold"
                  :class="{
                    'bg-green-50 text-green-700': room.performance === 'excellent',
                    'bg-blue-50 text-blue-700': room.performance === 'good',
                    'bg-yellow-50 text-yellow-700': room.performance === 'fair',
                    'bg-red-50 text-red-700': room.performance === 'poor',
                  }"
                >
                  {{ getPerformanceLabel(room.performance) }}
                </span>
              </div>

              <div class="grid grid-cols-3 gap-4">
                <div class="space-y-1.5">
                  <p class="text-xs text-gray-500 font-medium">Rata-rata</p>
                  <p class="text-xl font-semibold text-primary-dark">{{ room.avgOccupancy }}%</p>
                </div>
                <div class="space-y-1.5">
                  <p class="text-xs text-gray-500 font-medium">Maksimum</p>
                  <p class="text-xl font-semibold text-green-600">{{ room.maxOccupancy }}%</p>
                </div>
                <div class="space-y-1.5">
                  <p class="text-xs text-gray-500 font-medium">Minimum</p>
                  <p class="text-xl font-semibold text-orange-600">{{ room.minOccupancy }}%</p>
                </div>
              </div>

              <div class="mt-6 pt-6 border-t border-surface/30 grid grid-cols-2 gap-4">
                <div>
                  <p class="text-xs text-gray-500 font-medium mb-1.5">Total Revenue</p>
                  <p class="text-lg font-semibold text-primary-dark">{{ formatCurrencyShort(room.totalRevenue) }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-500 font-medium mb-1.5">Avg. Harian</p>
                  <p class="text-lg font-semibold text-primary-dark">{{ formatCurrencyShort(room.avgRevenue) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Insights -->
      <div v-if="insights.length > 0" class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
        <div class="px-8 py-6 border-b border-surface/30">
          <h2 class="text-xl font-semibold text-primary-dark">Insight & Analisis</h2>
          <p class="text-sm text-gray-500 mt-1">Temuan penting dari data historis</p>
        </div>

        <div class="p-8">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div
              v-for="(insight, index) in insights"
              :key="index"
              class="relative p-6 rounded-2xl border-2 transition-all duration-300 hover:shadow-md"
              :class="{
                'border-yellow-200 bg-yellow-50/50': insight.type === 'warning',
                'border-blue-200 bg-blue-50/50': insight.type === 'info',
                'border-green-200 bg-green-50/50': insight.type === 'success',
              }"
            >
              <div class="flex items-start space-x-4">
                <div
                  class="flex-shrink-0 p-3 rounded-2xl"
                  :class="{
                    'bg-yellow-100': insight.type === 'warning',
                    'bg-blue-100': insight.type === 'info',
                    'bg-green-100': insight.type === 'success',
                  }"
                >
                  <svg
                    class="w-6 h-6"
                    :class="{
                      'text-yellow-600': insight.type === 'warning',
                      'text-blue-600': insight.type === 'info',
                      'text-green-600': insight.type === 'success',
                    }"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                  >
                    <path v-if="insight.icon === 'trophy'" stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    <path v-else-if="insight.icon === 'alert'" stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    <path v-else-if="insight.icon === 'check'" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    <path v-else-if="insight.icon === 'lightbulb'" stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <h4 class="text-base font-semibold text-primary-dark mb-1.5">{{ insight.title }}</h4>
                  <p class="text-sm text-gray-600 leading-relaxed">{{ insight.description }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Monthly Comparison -->
      <div v-if="monthlyComparison.length > 0" class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
        <div class="px-8 py-6 border-b border-surface/30">
          <h2 class="text-xl font-semibold text-primary-dark">Perbandingan Bulanan</h2>
          <p class="text-sm text-gray-500 mt-1">Ringkasan per bulan</p>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="bg-background/50">
              <tr>
                <th class="px-8 py-5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Bulan</th>
                <th class="px-6 py-5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Rata-rata Okupansi</th>
                <th class="px-6 py-5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Revenue</th>
                <th class="px-6 py-5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Hari Operasional</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-surface/20">
              <tr
                v-for="(month, index) in monthlyComparison"
                :key="index"
                class="hover:bg-background/30 transition-colors duration-150"
              >
                <td class="px-8 py-6 whitespace-nowrap">
                  <span class="text-sm font-semibold text-primary-dark">{{ month.month }}</span>
                </td>
                <td class="px-6 py-6 whitespace-nowrap text-center">
                  <span class="text-sm font-bold text-primary-dark">{{ month.avgOccupancy }}%</span>
                </td>
                <td class="px-6 py-6 whitespace-nowrap text-center">
                  <span class="text-sm font-medium text-gray-700">{{ formatCurrency(month.totalRevenue) }}</span>
                </td>
                <td class="px-6 py-6 whitespace-nowrap text-center">
                  <span class="text-sm font-medium text-gray-700">{{ month.totalDays }} hari</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
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
  filters: Object,
});

const filters = ref({
  date_range: props.filters.date_range,
  room_type: props.filters.room_type,
});

const applyFilters = () => {
  router.get('/history', {
    date_range: filters.value.date_range,
    room_type: filters.value.room_type,
  }, {
    preserveState: true,
    preserveScroll: true,
  });
};

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(value);
};

const formatCurrencyShort = (value) => {
  if (value >= 1000000000) {
    return `Rp ${(value / 1000000000).toFixed(2)} M`;
  } else if (value >= 1000000) {
    return `Rp ${(value / 1000000).toFixed(1)} Jt`;
  }
  return formatCurrency(value);
};

const getRoomColorClass = (color, type) => {
  const colorMap = {
    primary: { bg: 'bg-primary/10', text: 'text-primary' },
    green: { bg: 'bg-green-50', text: 'text-green-600' },
    purple: { bg: 'bg-purple-50', text: 'text-purple-600' },
    orange: { bg: 'bg-orange-50', text: 'text-orange-600' },
  };
  return colorMap[color]?.[type] || colorMap.primary[type];
};

const getPerformanceLabel = (performance) => {
  const labels = {
    excellent: 'Sangat Baik',
    good: 'Baik',
    fair: 'Cukup',
    poor: 'Rendah',
  };
  return labels[performance] || 'N/A';
};

// Chart options
const occupancyChartOptions = {
  chart: {
    type: 'line',
    fontFamily: 'Inter, sans-serif',
    toolbar: {
      show: true,
      offsetY: -10,
    },
  },
  colors: ['#3F72AF', '#10B981', '#9333EA', '#F59E0B'],
  stroke: {
    curve: 'smooth',
    width: 2.5,
  },
  markers: {
    size: 0,
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
      right: 10,
      bottom: 0,
      left: 10,
    },
  },
  legend: {
    show: true,
    position: 'top',
    horizontalAlign: 'left',
    fontSize: '13px',
    fontWeight: 500,
    itemMargin: {
      horizontal: 16,
      vertical: 8,
    },
    markers: {
      width: 10,
      height: 10,
      radius: 10,
    },
  },
  tooltip: {
    shared: true,
    x: {
      format: 'dd MMM yyyy',
    },
    y: {
      formatter: (val) => val.toFixed(1) + '%',
    },
  },
};

const revenueChartOptions = {
  ...occupancyChartOptions,
  chart: {
    ...occupancyChartOptions.chart,
    type: 'area',
  },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.45,
      opacityTo: 0.05,
      stops: [20, 100],
    },
  },
  yaxis: {
    title: {
      text: 'Revenue (Rp)',
      style: {
        color: '#6B7280',
        fontSize: '13px',
        fontWeight: 500,
      },
    },
    labels: {
      style: {
        colors: '#9CA3AF',
        fontSize: '12px',
      },
      formatter: (val) => formatCurrencyShort(val),
    },
  },
  tooltip: {
    shared: true,
    x: {
      format: 'dd MMM yyyy',
    },
    y: {
      formatter: (val) => formatCurrency(val),
    },
  },
};
</script>
