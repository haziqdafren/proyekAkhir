<template>
  <DashboardLayout>
    <div class="px-6 py-6 space-y-5 max-w-[1400px] mx-auto">
      <!-- Page Header -->
      <div class="bg-primary-dark rounded-2xl px-6 py-4 shadow-card">
        <div class="flex items-center justify-between gap-4 mb-2">
          <div class="flex items-center gap-3">
            <Link href="/predictions" class="text-white/60 hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </Link>
            <div>
              <h1 class="text-lg font-bold text-white leading-tight">Prediksi Per Tipe Kamar</h1>
              <p class="text-xs text-white/60 mt-0.5">Hunian masing-masing tipe kamar</p>
            </div>
          </div>
          <span class="px-3 py-1.5 rounded-xl text-xs font-bold bg-white/10 text-white border border-white/20 flex-shrink-0">
            Akurasi: {{ modelAccuracy }}%
          </span>
        </div>
        <!-- Room capacity pills -->
        <div class="flex flex-wrap gap-2">
          <div v-for="(info, code) in roomTypeInfo" :key="code" class="inline-flex items-center gap-1.5 bg-white/10 border border-white/20 text-white text-xs font-medium px-2.5 py-1 rounded-lg">
            <span class="font-semibold">{{ info.fullName }}</span>
            <span class="text-[10px] text-white/60">{{ info.capacity }} kamar</span>
          </div>
        </div>
      </div>

      <!-- Month-to-Month Comparison -->
      <MonthComparisonCard :comparisons="comparisons" />

      <!-- Grouped Predictions by Month -->
      <div v-if="groupedPredictions.length > 0">
        <div class="flex items-center justify-between px-1 mb-3">
          <h2 class="text-sm font-semibold text-primary-dark">Prediksi & Rekomendasi Per Tipe Kamar</h2>
          <p class="text-xs text-gray-400">{{ totalMonths }} bulan tersimpan</p>
        </div>
        <div class="space-y-6">
        <div v-for="group in paginatedGroups" :key="group.month" :id="`month-${group.month.replace(/\s/g, '-')}`">

          <!-- Month header — sticky within scroll -->
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-3">
              <div class="w-1 h-5 rounded-full bg-primary"></div>
              <h2 class="text-sm font-bold text-primary-dark">{{ group.month }}</h2>
              <span class="text-xs text-gray-400 bg-surface/40 px-2 py-0.5 rounded-md">{{ group.predictions.length }} tipe kamar</span>
            </div>
            <button
              @click="confirmDelete(group)"
              class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-primary-dark transition-colors px-2.5 py-1.5 rounded-lg hover:bg-gray-50 border border-transparent hover:border-surface/40"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              Hapus Bulan Ini
            </button>
          </div>

          <!-- Room type cards — 2-column grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="prediction in group.predictions"
              :key="prediction.id"
              class="bg-white rounded-2xl shadow-card-md overflow-hidden flex flex-col border border-surface/30"
              :class="{
                'border-l-green-400': getRoomUrgency(prediction) === 'low',
                'border-l-amber-400': getRoomUrgency(prediction) === 'medium',
                'border-l-red-500':   getRoomUrgency(prediction) === 'high',
              }"
              style="border-left-width: 4px;"
            >

              <div class="p-4 flex flex-col flex-1">
                <!-- Room name + status -->
                <div class="flex items-center justify-between mb-3">
                  <div>
                    <p class="text-sm font-bold text-primary-dark">{{ prediction.room_type?.name }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ roomCapacities[prediction.room_type?.code] }} kamar tersedia</p>
                  </div>
                  <span
                    class="text-xs font-bold px-2.5 py-1 rounded-lg"
                    :class="{
                      'bg-green-100 text-green-700': getRoomUrgency(prediction) === 'low',
                      'bg-amber-100 text-amber-700': getRoomUrgency(prediction) === 'medium',
                      'bg-red-100 text-red-700':     getRoomUrgency(prediction) === 'high',
                    }"
                  >{{ getRoomLevelLabel(prediction) }}</span>
                </div>

                <!-- Occupancy + bar -->
                <div class="flex items-end gap-4 mb-3">
                  <div class="flex-shrink-0">
                    <p class="text-4xl font-black text-primary-dark leading-none">
                      {{ Number(prediction.predicted_occupancy_rate || 0).toFixed(1) }}<span class="text-xl font-bold text-gray-300">%</span>
                    </p>
                    <p class="text-xs text-gray-400 mt-1">
                      {{ prediction.insights?.avg_rooms_per_day || Math.round((roomCapacities[prediction.room_type?.code] || 0) * prediction.predicted_occupancy_rate / 100) }} kamar/hari
                    </p>
                  </div>
                  <div class="flex-1 pb-1">
                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden mb-1">
                      <div
                        class="h-full rounded-full transition-all duration-700"
                        :class="{
                          'bg-green-500': getRoomUrgency(prediction) === 'low',
                          'bg-amber-400': getRoomUrgency(prediction) === 'medium',
                          'bg-red-500':   getRoomUrgency(prediction) === 'high',
                        }"
                        :style="{ width: `${prediction.predicted_occupancy_rate}%` }"
                      ></div>
                    </div>
                    <div class="flex justify-between">
                      <p class="text-[10px] text-gray-400">0%</p>
                      <p class="text-[10px] text-gray-400">100%</p>
                    </div>
                    <p v-if="prediction.insights?.estimated_revenue_formatted" class="text-xs font-semibold text-primary-dark mt-1">
                      {{ prediction.insights.estimated_revenue_formatted }}
                    </p>
                  </div>
                </div>

                <!-- Recommendation block — full width, clearly readable -->
                <div
                  v-if="prediction.insights?.yield_recommendation || prediction.insights?.interpretation"
                  class="rounded-xl p-3.5 mt-auto"
                  :class="{
                    'bg-green-50': getRoomUrgency(prediction) === 'low',
                    'bg-amber-50': getRoomUrgency(prediction) === 'medium',
                    'bg-red-50':   getRoomUrgency(prediction) === 'high',
                  }"
                >
                  <p class="text-[10px] font-bold uppercase tracking-widest mb-1.5"
                    :class="{
                      'text-green-600': getRoomUrgency(prediction) === 'low',
                      'text-amber-600': getRoomUrgency(prediction) === 'medium',
                      'text-red-600':   getRoomUrgency(prediction) === 'high',
                    }"
                  >Rekomendasi</p>
                  <p class="text-sm font-semibold text-gray-900 leading-snug mb-1.5"
                    v-if="prediction.insights?.yield_recommendation"
                  >{{ prediction.insights.yield_recommendation.action }}</p>
                  <p v-if="prediction.insights?.yield_recommendation?.detail"
                    class="text-xs text-gray-600 leading-relaxed">{{ prediction.insights.yield_recommendation.detail }}</p>
                  <p v-else-if="prediction.insights?.interpretation"
                    class="text-xs text-gray-600 leading-relaxed">{{ prediction.insights.interpretation }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        </div><!-- end space-y-6 -->

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="mt-2 bg-white rounded-2xl border border-surface/20 px-5 py-3 shadow-sm">
          <Pagination
            :current-page="currentPage"
            :total-pages="totalPages"
            :total="totalMonths"
            :per-page="MONTHS_PER_PAGE"
            item-label="bulan prediksi"
            @change="onPageChange"
          />
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="bg-white rounded-2xl shadow-card-md border border-surface/30 p-10 text-center">
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
            <div class="w-9 h-9 rounded-xl bg-primary-dark flex items-center justify-center shadow-md">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <div>
              <h2 class="text-sm font-semibold text-primary-dark text-left">Tambah Prediksi Bulan Berikutnya</h2>
              <p class="text-xs text-gray-500 text-left">Prediksi untuk 4 tipe kamar sekaligus</p>
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
              <p class="text-xs text-gray-500 mt-2">Prediksi untuk semua 4 tipe kamar ({{ new Date(minDate).toLocaleDateString('id-ID', {month:'long',year:'numeric'}) }} – {{ new Date(maxDate).toLocaleDateString('id-ID', {month:'long',year:'numeric'}) }})</p>

              <!-- Warning if month already has prediction -->
              <div v-if="monthAlreadyPredicted" class="mt-3 flex items-start gap-2 p-3 bg-yellow-50 border border-yellow-200 rounded-xl">
                <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="flex-1">
                  <p class="text-sm font-medium text-yellow-800">Prediksi sudah ada untuk bulan ini</p>
                  <p class="text-xs text-yellow-700 mt-1">Membuat prediksi baru akan <strong>memperbarui</strong> semua prediksi tipe kamar dengan nilai terbaru</p>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-4">
              <button
                type="submit"
                :disabled="generating"
                :class="monthAlreadyPredicted ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-primary-dark hover:bg-primary'"
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
      message="Sistem prediksi sedang menganalisis data dan membuat perkiraan hunian per tipe kamar..."
    />

    <!-- Delete Confirmation Modal -->
    <ConfirmModal
      :show="showDeleteConfirm"
      title="Hapus Prediksi?"
      :message="`Apakah Anda yakin ingin menghapus semua prediksi (${predictionToDelete?.count} tipe kamar) untuk ${predictionToDelete?.monthName}? Tindakan ini tidak dapat dibatalkan.`"
      confirm-text="Ya, Hapus Semua"
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
  modelInfo: Object,
  roomTypes: Array,
  roomCapacities: Object,
  dbMaxDate: String,
});

// Akurasi = 100 - MAPE
const modelAccuracy = computed(() => {
  if (props.modelInfo?.accuracy != null) return props.modelInfo.accuracy;
  if (props.modelInfo?.mape != null) return parseFloat((100 - props.modelInfo.mape).toFixed(1));
  return 'N/A';
});

// Full room type names + capacities for the legend strip
const roomTypeInfo = computed(() => {
  const fullNames = {
    STD: 'Standard',
    SPR: 'Superior',
    FMY: 'Family',
    JS:  'Junior Suite',
  };
  const result = {};
  Object.entries(props.roomCapacities || {}).forEach(([code, capacity]) => {
    result[code] = { fullName: fullNames[code] || code, capacity };
  });
  return result;
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
const showDeleteConfirm = ref(false);
const predictionToDelete = ref(null);
const showGenerateForm = ref(false);
const activeMonth = ref('');

// Pagination — paginate by month groups, 4 months per page
const MONTHS_PER_PAGE = 4;
const currentPage = ref(1);
const totalMonths = computed(() => groupedPredictions.value.length);
const totalPages = computed(() => Math.ceil(totalMonths.value / MONTHS_PER_PAGE));
const paginatedGroups = computed(() => {
  const start = (currentPage.value - 1) * MONTHS_PER_PAGE;
  return groupedPredictions.value.slice(start, start + MONTHS_PER_PAGE);
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

// Check if selected month already has predictions
const monthAlreadyPredicted = computed(() => {
  if (!selectedMonth.value || !props.recentPredictions) return false;

  return props.recentPredictions.some(pred => {
    const ym = (pred.raw_date || pred.predicted_for_date || '').substring(0, 7); // "YYYY-MM"
    const [y, m] = ym.split('-').map(Number);
    return y === selectedMonth.value.year && (m - 1) === selectedMonth.value.month;
  });
});

// Group predictions by month
const groupedPredictions = computed(() => {
  const groups = {};
  
  props.recentPredictions.forEach(pred => {
    const monthKey = formatMonth(pred.raw_date || pred.predicted_for_date);
    if (!groups[monthKey]) {
      groups[monthKey] = {
        month: monthKey,
        created_at: pred.created_at,
        predictions: []
      };
    }
    groups[monthKey].predictions.push(pred);
  });

  return Object.values(groups);
});

const generatePrediction = () => {
  if (!selectedMonth.value) return;

  generating.value = true;

  // Convert month picker format to YYYY-MM-DD
  const year = selectedMonth.value.year;
  const month = String(selectedMonth.value.month + 1).padStart(2, '0'); // Convert from 0-indexed to 1-indexed
  const formattedDate = `${year}-${month}-01`;

  router.post('/predictions/generate-multi', {
    predict_for_month: formattedDate,
  }, {
    preserveScroll: true,
    onFinish: () => {
      generating.value = false;
    },
  });
};

const confirmDelete = (group) => {
  // Get year and month from first prediction in group
  const firstPred = group.predictions[0];
  const ym = (firstPred.raw_date || firstPred.predicted_for_date || '').substring(0, 7);
  const [predYear, predMonth] = ym.split('-').map(Number);

  predictionToDelete.value = {
    year: predYear,
    month: predMonth,
    monthName: group.month,
    count: group.predictions.length,
  };
  showDeleteConfirm.value = true;
};

const deletePrediction = () => {
  if (!predictionToDelete.value) return;

  router.delete(`/predictions/month/${predictionToDelete.value.year}/${predictionToDelete.value.month}/multi`, {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteConfirm.value = false;
      predictionToDelete.value = null;
    },
  });
};

const scrollToMonth = (month) => {
  activeMonth.value = month;
  const id = `month-${month.replace(/\s/g, '-')}`;
  const el = document.getElementById(id);
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const getRoomUrgency = (prediction) => {
  const rate = prediction.predicted_occupancy_rate;
  if (rate >= 55) return 'low';      // green  — Hunian Tinggi
  if (rate >= 40) return 'medium';   // yellow — Hunian Sedang
  return 'high';                     // red    — Hunian Rendah
};

const getRoomLevelLabel = (prediction) => {
  return prediction.insights?.yield_recommendation?.level_label
    || (prediction.predicted_occupancy_rate >= 55 ? 'Hunian Tinggi'
      : prediction.predicted_occupancy_rate >= 40 ? 'Hunian Sedang' : 'Hunian Rendah');
};

const getRoomTrendLabel = (prediction) => {
  return prediction.insights?.yield_recommendation?.trend_label || 'Tren Stabil';
};

const formatMonth = (dateString) => {
  // Extract YYYY-MM to avoid UTC timezone shift on midnight local-time dates
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

const getRoomDotColor = (code) => {
  const colors = {
    'STD': 'bg-primary',
    'SPR': 'bg-primary-dark',
    'FMY': 'bg-primary-light',
    'JS':  'bg-surface',
  };
  return colors[code] || 'bg-primary';
};
</script>
