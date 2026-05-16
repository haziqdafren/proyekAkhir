<template>
  <DashboardLayout>
    <!-- No-scroll: fits one screen -->
    <div class="flex flex-col h-full overflow-hidden px-6 py-6 max-w-[1400px] mx-auto w-full gap-4">

      <!-- Header -->
      <div class="flex-shrink-0 bg-primary-dark rounded-2xl px-6 py-4 shadow-card">
        <h1 class="text-lg font-bold text-white leading-tight">Prediksi Tingkat Hunian</h1>
        <p class="text-sm text-white/60 mt-0.5">Pilih jenis prediksi yang ingin dibuat atau dilihat</p>
      </div>

      <!-- Two prediction cards -->
      <div class="flex-1 min-h-0 grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Prediksi Total Hotel -->
        <div class="bg-white rounded-2xl border border-surface/30 shadow-card-md hover:border-primary/40 hover:shadow-card-lg transition-shadow duration-200 flex flex-col overflow-hidden">
          <!-- Accent line -->
          <div class="h-0.5 bg-primary rounded-t-2xl"></div>
          <div class="p-5 flex flex-col flex-1">
          <!-- Icon + Akurasi -->
          <div class="flex items-start justify-between mb-3">
            <div class="w-11 h-11 bg-primary rounded-xl flex items-center justify-center shadow-sm">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <span class="px-3 py-1 bg-primary/10 text-primary text-xs font-bold rounded-lg">
              Akurasi: {{ singleAccuracy }}%
            </span>
          </div>

          <!-- Title + desc -->
          <div class="flex-1">
            <h2 class="text-base font-bold text-primary-dark mb-1.5">Prediksi Total Hotel</h2>
            <p class="text-sm text-gray-500 leading-relaxed">
              Perkiraan tingkat hunian keseluruhan hotel. Cocok untuk perencanaan pendapatan dan keputusan manajemen umum.
            </p>
          </div>

          <!-- Actions -->
          <div class="mt-4 flex flex-col gap-2">
            <button
              @click="openGenerateModal('single')"
              class="w-full px-5 py-3 bg-primary hover:bg-primary-dark text-white rounded-xl font-semibold transition-all flex items-center justify-center gap-2 text-sm"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
              Buat Prediksi
            </button>
            <Link
              href="/predictions/single"
              class="w-full px-5 py-2.5 border-2 border-primary/30 text-primary hover:border-primary rounded-xl font-semibold transition-all flex items-center justify-center gap-2 text-sm"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
              Lihat Hasil Prediksi
            </Link>
          </div>
          </div>
        </div>

        <!-- Prediksi Per Tipe Kamar -->
        <div class="bg-white rounded-2xl border border-surface/30 shadow-card-md hover:border-primary/40 hover:shadow-card-lg transition-shadow duration-200 flex flex-col overflow-hidden">
          <!-- Accent line -->
          <div class="h-0.5 bg-primary-dark rounded-t-2xl"></div>
          <div class="p-5 flex flex-col flex-1">
          <!-- Icon + Akurasi -->
          <div class="flex items-start justify-between mb-3">
            <div class="w-11 h-11 bg-primary-dark rounded-xl flex items-center justify-center shadow-sm">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-3zM14 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1h-4a1 1 0 01-1-1v-3z" />
              </svg>
            </div>
            <span class="px-3 py-1 bg-primary-dark/10 text-primary-dark text-xs font-bold rounded-lg">
              Akurasi: {{ multiAccuracy }}%
            </span>
          </div>

          <!-- Title + desc -->
          <div class="flex-1">
            <h2 class="text-base font-bold text-primary-dark mb-1.5">Prediksi Per Tipe Kamar</h2>
            <p class="text-sm text-gray-500 leading-relaxed">
              Perkiraan tingkat hunian untuk Standard, Superior, Family, dan Junior Suite. Berguna untuk strategi harga dan promosi per segmen.
            </p>
          </div>

          <!-- Actions -->
          <div class="mt-4 flex flex-col gap-2">
            <button
              @click="openGenerateModal('multi')"
              class="w-full px-5 py-3 bg-primary-dark hover:bg-primary text-white rounded-xl font-semibold transition-all flex items-center justify-center gap-2 text-sm"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
              Buat Prediksi
            </button>
            <Link
              href="/predictions/multi"
              class="w-full px-5 py-2.5 border-2 border-primary-dark/30 text-primary-dark hover:border-primary-dark rounded-xl font-semibold transition-all flex items-center justify-center gap-2 text-sm"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
              Lihat Hasil Prediksi
            </Link>
          </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Generate Modal -->
    <div
      v-if="showGenerateModal"
      class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4"
      @click.self="showGenerateModal = false"
    >
      <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <div>
            <h2 class="text-lg font-bold text-primary-dark">Buat Prediksi</h2>
            <p class="text-sm text-gray-500 mt-0.5">
              {{ selectedModelType === 'single' ? 'Prediksi Total Hotel' : 'Prediksi Per Tipe Kamar' }}
            </p>
          </div>
          <button
            @click="showGenerateModal = false"
            class="w-9 h-9 rounded-xl hover:bg-gray-100 flex items-center justify-center transition-colors"
          >
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="generatePrediction" class="p-6 space-y-5">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Pilih Bulan</label>
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
              class="w-full"
            >
              <template #dp-input="{ value }">
                <input
                  type="text"
                  :value="value"
                  readonly
                  class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer bg-white text-sm"
                  placeholder="Pilih bulan"
                  required
                />
              </template>
            </Datepicker>
            <p class="text-xs text-gray-400 mt-1.5">Rentang tersedia: {{ predictionDateHint }}</p>
          </div>

          <div class="flex gap-3">
            <button
              type="submit"
              :disabled="generating"
              :class="selectedModelType === 'single' ? 'bg-primary hover:bg-primary-dark' : 'bg-primary-dark hover:bg-primary'"
              class="flex-1 px-5 py-3 text-white rounded-xl font-semibold transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 text-sm"
            >
              <svg v-if="!generating" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
              <svg v-else class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              {{ generating ? 'Memproses...' : 'Buat Prediksi' }}
            </button>
            <button
              type="button"
              @click="showGenerateModal = false"
              class="px-5 py-3 border-2 border-gray-200 text-gray-600 rounded-xl font-semibold hover:bg-gray-50 transition-all text-sm"
            >
              Batal
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Loading Modal -->
    <LoadingModal
      :show="generating"
      title="Membuat Prediksi"
      :message="selectedModelType === 'single' ? 'Sistem sedang menganalisis data dan membuat prediksi hunian total hotel...' : 'Sistem sedang menganalisis data dan membuat prediksi hunian per tipe kamar...'"
    />
  </DashboardLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import LoadingModal from '@/Components/LoadingModal.vue';
import { VueDatePicker as Datepicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

const props = defineProps({
  allPredictions: Array,
  predictionsByMonth: Array,
  stats: Object,
  roomTypes: Array,
  singleModelInfo: Object,
  multiModelInfo: Object,
  dbMaxDate: String,
});

const selectedModelType = ref('single');
const generating = ref(false);
const showGenerateModal = ref(false);

const singleAccuracy = computed(() => {
  if (props.singleModelInfo?.accuracy != null) return props.singleModelInfo.accuracy;
  if (props.singleModelInfo?.mape != null) return parseFloat((100 - props.singleModelInfo.mape).toFixed(1));
  return 'N/A';
});

const multiAccuracy = computed(() => {
  if (props.multiModelInfo?.accuracy != null) return props.multiModelInfo.accuracy;
  if (props.multiModelInfo?.mape != null) return parseFloat((100 - props.multiModelInfo.mape).toFixed(1));
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

const minDate = computed(() => firstPredictableDate.value);

const maxDate = computed(() => {
  const d = new Date(firstPredictableDate.value);
  d.setMonth(d.getMonth() + 13);
  return d;
});

const predictionDateHint = computed(() => {
  const fmt = (d) => d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
  return `${fmt(minDate.value)} – ${fmt(maxDate.value)}`;
});

const openGenerateModal = (modelType) => {
  selectedModelType.value = modelType;
  showGenerateModal.value = true;
};

const generatePrediction = () => {
  if (!selectedMonth.value) return;
  generating.value = true;

  const endpoint = selectedModelType.value === 'single'
    ? '/predictions/generate-single'
    : '/predictions/generate-multi';

  const year = selectedMonth.value.year;
  const month = String(selectedMonth.value.month + 1).padStart(2, '0');
  const formattedDate = `${year}-${month}-01`;

  router.post(endpoint, { predict_for_month: formattedDate }, {
    preserveScroll: true,
    onFinish: () => { generating.value = false; },
    onSuccess: () => {
      router.visit(selectedModelType.value === 'single' ? '/predictions/single' : '/predictions/multi');
    },
  });
};
</script>
