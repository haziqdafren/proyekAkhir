<template>
  <DashboardLayout>
    <div class="px-6 py-6 space-y-5 max-w-[1400px] mx-auto">
      <!-- Page Header -->
      <div class="bg-primary-dark rounded-2xl px-6 py-4 shadow-card flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <Link href="/predictions" class="text-white/60 hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </Link>
          <div>
            <h1 class="text-lg font-bold text-white leading-tight">Prediksi Total Hotel</h1>
            <p class="text-xs text-white/60 mt-0.5">Keseluruhan hotel ({{ totalRooms || 56 }} kamar)</p>
          </div>
        </div>
        <span class="px-3 py-1.5 rounded-xl text-xs font-bold bg-white/10 text-white border border-white/20 flex-shrink-0">
          Akurasi: {{ modelAccuracy }}%
        </span>
      </div>

      <!-- Month-to-Month Comparison -->
      <MonthComparisonCard :comparisons="comparisons" />

      <!-- Daftar Prediksi & Rekomendasi -->
      <div v-if="recentPredictions.length > 0">
        <div class="flex items-center justify-between px-1 mb-3">
          <h2 class="text-sm font-semibold text-primary-dark">Prediksi & Rekomendasi</h2>
          <p class="text-xs text-gray-400">{{ recentPredictions.length }} bulan tersimpan</p>
        </div>

        <!-- One card = one month -->
        <div class="space-y-4">
          <div
            v-for="prediction in paginatedPredictions"
            :key="prediction.id"
            :id="`pred-${prediction.id}`"
            class="bg-white rounded-2xl shadow-card-md overflow-hidden border border-surface/30"
            :class="{
              'border-l-green-400': getUrgency(prediction) === 'low',
              'border-l-amber-400': getUrgency(prediction) === 'medium',
              'border-l-red-500':   getUrgency(prediction) === 'high',
            }"
            style="border-left-width: 4px;"
          >

            <div class="p-5">
              <!-- Month + status row -->
              <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                  <p class="text-base font-bold text-primary-dark">{{ formatMonth(prediction.raw_date || prediction.predicted_for_date) }}</p>
                  <span
                    class="text-xs font-bold px-2.5 py-1 rounded-lg"
                    :class="{
                      'bg-green-100 text-green-700': getUrgency(prediction) === 'low',
                      'bg-amber-100 text-amber-700': getUrgency(prediction) === 'medium',
                      'bg-red-100 text-red-700':     getUrgency(prediction) === 'high',
                    }"
                  >{{ getLevelLabel(prediction) }}</span>
                </div>
                <button
                  @click="confirmDelete(prediction)"
                  class="p-1.5 text-gray-400 hover:text-red-500 transition-colors rounded-lg hover:bg-gray-50"
                  title="Hapus prediksi"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>

              <!-- Metrics + Recommendation: 2 columns -->
              <div class="flex flex-col md:flex-row gap-5">

                <!-- LEFT: Occupancy metrics -->
                <div class="flex-shrink-0 md:w-48">
                  <p class="text-5xl font-black text-primary-dark leading-none">
                    {{ Number(prediction.predicted_occupancy_rate || 0).toFixed(1) }}<span class="text-2xl font-bold text-primary-dark/40">%</span>
                  </p>
                  <p class="text-xs text-gray-400 mt-1.5 mb-3">{{ prediction.insights?.avg_rooms_per_day || calculateRooms(prediction.predicted_occupancy_rate) }} kamar/hari rata-rata</p>
                  <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden mb-1.5">
                    <div
                      class="h-full rounded-full transition-all duration-700"
                      :class="{
                        'bg-green-500': getUrgency(prediction) === 'low',
                        'bg-amber-400': getUrgency(prediction) === 'medium',
                        'bg-red-500':   getUrgency(prediction) === 'high',
                      }"
                      :style="{ width: `${prediction.predicted_occupancy_rate}%` }"
                    ></div>
                  </div>
                  <div class="flex items-center justify-between">
                    <p class="text-xs text-gray-400">{{ getTrendLabel(prediction) }}</p>
                    <p class="text-xs text-gray-400">Target 70%</p>
                  </div>
                  <p v-if="prediction.insights?.estimated_revenue_formatted" class="text-sm font-bold text-primary-dark mt-3">
                    {{ prediction.insights.estimated_revenue_formatted }}
                    <span class="text-xs font-normal text-gray-400 ml-1">est. pendapatan</span>
                  </p>
                </div>

                <!-- Divider -->
                <div class="hidden md:block w-px bg-surface/40 flex-shrink-0"></div>
                <div class="block md:hidden h-px bg-surface/40"></div>

                <!-- RIGHT: Recommendation -->
                <div class="flex-1 min-w-0">
                  <div
                    v-if="prediction.insights?.yield_recommendation || prediction.insights?.interpretation"
                    class="h-full rounded-xl p-4"
                    :class="{
                      'bg-green-50': getUrgency(prediction) === 'low',
                      'bg-amber-50': getUrgency(prediction) === 'medium',
                      'bg-red-50':   getUrgency(prediction) === 'high',
                    }"
                  >
                    <p class="text-[10px] font-bold uppercase tracking-widest mb-2"
                      :class="{
                        'text-green-600': getUrgency(prediction) === 'low',
                        'text-amber-600': getUrgency(prediction) === 'medium',
                        'text-red-600':   getUrgency(prediction) === 'high',
                      }"
                    >Rekomendasi Manajemen</p>
                    <p class="text-sm font-bold text-gray-900 leading-snug mb-2"
                      v-if="prediction.insights?.yield_recommendation"
                    >{{ prediction.insights.yield_recommendation.action }}</p>
                    <p v-if="prediction.insights?.yield_recommendation?.detail"
                      class="text-sm text-gray-600 leading-relaxed">{{ prediction.insights.yield_recommendation.detail }}</p>
                    <p v-else-if="prediction.insights?.interpretation"
                      class="text-sm text-gray-600 leading-relaxed">{{ prediction.insights.interpretation }}</p>
                  </div>
                  <div v-else class="h-full rounded-xl p-4 bg-gray-50 flex items-center justify-center">
                    <p class="text-sm text-gray-400">Tidak ada rekomendasi tersedia</p>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="mt-4 bg-white rounded-2xl border border-surface/20 px-5 py-3 shadow-sm">
          <Pagination
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="totalPredictions"
            :per-page="PER_PAGE"
            item-label="bulan prediksi"
            @change="onPageChange"
          />
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="bg-white rounded-2xl shadow-card-md border border-surface/20 p-10 text-center">
        <div class="max-w-md mx-auto">
          <div class="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-primary/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Belum Ada Prediksi</h3>
          <p class="text-gray-500">Gunakan form di bawah untuk membuat prediksi pertama Anda</p>
        </div>
      </div>

      <!-- Generate New Prediction - Collapsible (at bottom) -->
      <div class="bg-white rounded-2xl shadow-card-md border border-surface/20 overflow-hidden">
        <button
          @click="showGenerateForm = !showGenerateForm"
          class="w-full px-6 py-4 flex items-center justify-between bg-surface/20 hover:bg-surface/40 transition-colors"
        >
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-primary flex items-center justify-center shadow-md">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <div>
              <h2 class="text-sm font-semibold text-primary-dark text-left">Tambah Prediksi Bulan Berikutnya</h2>
              <p class="text-xs text-gray-500 text-left">Pilih bulan dan buat perkiraan tingkat hunian</p>
            </div>
          </div>
          <svg
            class="w-5 h-5 text-gray-400 transition-transform duration-200"
            :class="{ 'rotate-180': showGenerateForm }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <div v-show="showGenerateForm" class="px-6 pb-6 pt-3 border-t border-surface/20">
          <form @submit.prevent="generatePrediction" class="space-y-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Bulan untuk Prediksi</label>
              <Datepicker
                v-model="selectedMonth"
                month-picker
                :min-date="minDate"
                :max-date="maxDate"
                :enable-time-picker="false"
                auto-apply
                :clearable="false"
                :format="(date) => `${['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][date.month]} ${date.year}`"
                placeholder="Pilih bulan"
                class="w-full md:w-96"
              >
                <template #dp-input="{ value }">
                  <input
                    type="text"
                    :value="value"
                    readonly
                    class="w-full px-4 py-3 border border-surface rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer bg-white"
                    placeholder="Pilih bulan"
                    required
                  />
                </template>
              </Datepicker>
              <p class="text-xs text-gray-500 mt-2">Pilih bulan untuk prediksi ({{ new Date(minDate).toLocaleDateString('id-ID', {month:'long',year:'numeric'}) }} – {{ new Date(maxDate).toLocaleDateString('id-ID', {month:'long',year:'numeric'}) }})</p>

              <!-- Warning if month already has prediction -->
              <div v-if="monthAlreadyPredicted" class="mt-3 flex items-start gap-2 p-3 bg-yellow-50 border border-yellow-200 rounded-xl">
                <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="flex-1">
                  <p class="text-sm font-medium text-yellow-800">Prediksi sudah ada untuk bulan ini</p>
                  <p class="text-xs text-yellow-700 mt-1">Membuat prediksi baru akan <strong>memperbarui</strong> prediksi yang sudah ada dengan nilai terbaru</p>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-4">
              <button
                type="submit"
                :disabled="generating"
                :class="monthAlreadyPredicted ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-primary hover:bg-primary-dark'"
                class="px-6 py-3 text-white rounded-2xl font-semibold transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
              >
                <svg v-if="!generating" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <svg v-else class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                {{ generating ? 'Memproses...' : (monthAlreadyPredicted ? 'Perbarui Prediksi' : 'Buat Prediksi') }}
              </button>

              <div v-if="$page.props.flash?.success" class="flex items-center gap-2 px-4 py-3 bg-primary/5 border border-primary/20 rounded-lg">
                <svg class="w-5 h-5 text-primary flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <p class="text-primary font-medium text-sm">{{ $page.props.flash.success }}</p>
              </div>
              <div v-if="$page.props.errors?.error" class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 rounded-lg">
                <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <p class="text-red-700 font-medium text-sm">{{ $page.props.errors.error }}</p>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Loading Modal -->
    <LoadingModal
      :show="generating"
      title="Membuat Prediksi"
      message="Sistem prediksi sedang menganalisis data historis dan membuat perkiraan hunian..."
    />

    <!-- Delete Confirmation Modal -->
    <ConfirmModal
      :show="showDeleteConfirm"
      title="Hapus Prediksi?"
      :message="`Apakah Anda yakin ingin menghapus prediksi untuk ${predictionToDelete?.monthName}? Tindakan ini tidak dapat dibatalkan.`"
      confirm-text="Ya, Hapus"
      cancel-text="Batal"
      type="danger"
      @confirm="deletePrediction"
      @close="showDeleteConfirm = false"
    />
  </DashboardLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import LoadingModal from '@/Components/LoadingModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import MonthComparisonCard from '@/Components/MonthComparisonCard.vue';
import Pagination from '@/Components/Pagination.vue';
import { VueDatePicker as Datepicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

const props = defineProps({
  recentPredictions: Array,
  comparisons: Array,
  totalRooms: Number,
  modelInfo: Object,
  roomTypes: Array,
  dbMaxDate: String,
});

// Akurasi = 100 - MAPE, gunakan field accuracy jika tersedia
const modelAccuracy = computed(() => {
  if (props.modelInfo?.accuracy != null) return props.modelInfo.accuracy;
  if (props.modelInfo?.mape != null) return parseFloat((100 - props.modelInfo.mape).toFixed(1));
  return 'N/A';
});

const firstPredictableDate = computed(() => {
  if (props.dbMaxDate) {
    const d = new Date(props.dbMaxDate + 'T00:00:00');
    d.setMonth(d.getMonth() + 1);
    d.setDate(1);
    return d;
  }
  const d = new Date();
  d.setDate(1);
  d.setMonth(d.getMonth() + 1);
  return d;
});

const selectedMonth = ref(
  (() => {
    if (props.dbMaxDate) {
      const d = new Date(props.dbMaxDate + 'T00:00:00');
      return { month: (d.getMonth() + 1) % 12, year: d.getMonth() === 11 ? d.getFullYear() + 1 : d.getFullYear() };
    }
    const d = new Date();
    return { month: (d.getMonth() + 1) % 12, year: d.getFullYear() };
  })()
);

const generating = ref(false);
const activePredictionId = ref(null);
const showDeleteConfirm = ref(false);
const predictionToDelete = ref(null);
const showGenerateForm = ref(false);

// Pagination
const PER_PAGE = 6;
const currentPage = ref(1);
const totalPredictions = computed(() => props.recentPredictions?.length ?? 0);
const totalPages = computed(() => Math.ceil(totalPredictions.value / PER_PAGE));
const paginatedPredictions = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE;
  return (props.recentPredictions ?? []).slice(start, start + PER_PAGE);
});
const onPageChange = (page) => {
  currentPage.value = page;
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

const minDate = computed(() => firstPredictableDate.value);

const maxDate = computed(() => {
  const d = new Date(firstPredictableDate.value);
  d.setMonth(d.getMonth() + 13);
  return d;
});

// Check if selected month already has a prediction
const monthAlreadyPredicted = computed(() => {
  if (!selectedMonth.value || !props.recentPredictions) return false;

  return props.recentPredictions.some(pred => {
    const ym = (pred.raw_date || pred.predicted_for_date || '').substring(0, 7);
    const [y, m] = ym.split('-').map(Number);
    return y === selectedMonth.value.year && (m - 1) === selectedMonth.value.month;
  });
});

const generatePrediction = () => {
  if (!selectedMonth.value) return;

  generating.value = true;

  // Convert month picker format to YYYY-MM-DD
  const year = selectedMonth.value.year;
  const month = String(selectedMonth.value.month + 1).padStart(2, '0'); // Convert from 0-indexed to 1-indexed
  const formattedDate = `${year}-${month}-01`;

  router.post('/predictions/generate-single', {
    predict_for_month: formattedDate,
  }, {
    preserveScroll: true,
    onFinish: () => {
      generating.value = false;
    },
  });
};

const scrollToPrediction = (prediction) => {
  activePredictionId.value = prediction.id;
  const el = document.getElementById(`pred-${prediction.id}`);
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const confirmDelete = (prediction) => {
  const ym = (prediction.raw_date || prediction.predicted_for_date || '').substring(0, 7);
  const [predYear, predMonth] = ym.split('-').map(Number);
  predictionToDelete.value = {
    id: prediction.id,
    year: predYear,
    month: predMonth,
    monthName: formatMonth(prediction.raw_date || prediction.predicted_for_date),
  };
  showDeleteConfirm.value = true;
};

const deletePrediction = () => {
  if (!predictionToDelete.value) return;

  router.delete(`/predictions/month/${predictionToDelete.value.year}/${predictionToDelete.value.month}/single`, {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteConfirm.value = false;
      predictionToDelete.value = null;
    },
  });
};

const getUrgency = (prediction) => {
  const rate = prediction.predicted_occupancy_rate;
  if (rate >= 55) return 'low';      // green  — Hunian Tinggi
  if (rate >= 40) return 'medium';   // yellow — Hunian Sedang
  return 'high';                     // red    — Hunian Rendah
};

const getLevelLabel = (prediction) => {
  return prediction.insights?.yield_recommendation?.level_label
    || (prediction.predicted_occupancy_rate >= 55 ? 'Hunian Tinggi'
      : prediction.predicted_occupancy_rate >= 40 ? 'Hunian Sedang' : 'Hunian Rendah');
};

const getTrendLabel = (prediction) => {
  return prediction.insights?.yield_recommendation?.trend_label || 'Tren Stabil';
};

const formatMonth = (dateString) => {
  // Extract YYYY-MM from raw_date or ISO string to avoid UTC timezone shift
  const ym = (dateString || '').substring(0, 7); // "YYYY-MM"
  const [year, month] = ym.split('-').map(Number);
  if (!year || !month) return dateString;
  return new Date(year, month - 1, 1).toLocaleDateString('id-ID', {
    month: 'long',
    year: 'numeric',
  });
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const calculateRooms = (occupancyRate) => {
  return Math.round((occupancyRate / 100) * (props.totalRooms || 56));
};

const getConfidenceClass = (confidence) => {
  if (confidence >= 75) return 'bg-primary/10 text-primary';
  if (confidence >= 60) return 'bg-yellow-100 text-yellow-700';
  return 'bg-primary/10 text-primary-dark';
};

// Map backend color tokens to Tailwind classes (no dynamic class strings — Tailwind purges them)
const getPerformanceLevelClass = (color) => {
  const map = {
    'primary':       'text-primary',
    'primary-light': 'text-primary',
    'yellow':        'text-yellow-700',
    'red':           'text-primary',
  };
  return map[color] || 'text-primary-dark';
};
</script>
