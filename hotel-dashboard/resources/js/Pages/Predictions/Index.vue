<template>
  <DashboardLayout>
    <div class="p-8 space-y-6 max-w-[1200px] mx-auto">

      <!-- Welcome Header -->
      <div class="relative bg-gradient-to-br from-primary to-primary-dark rounded-3xl shadow-md overflow-hidden">
        <div class="absolute inset-0 bg-grid-white/[0.02] bg-[size:32px_32px]"></div>
        <div class="absolute right-0 top-0 -mt-8 -mr-20 h-72 w-72 rounded-full bg-white/5 blur-3xl"></div>

        <div class="relative px-10 py-12">
          <h1 class="text-3xl font-semibold text-white mb-2">Generate Prediksi Okupansi</h1>
          <p class="text-white/80 text-base max-w-2xl">
            Pilih model prediksi untuk membuat forecast okupansi hotel Anda
          </p>
        </div>
      </div>

      <!-- Main Action Cards: Generate Single vs Multi -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Single Output Card -->
        <div class="bg-white rounded-3xl border-2 border-surface/30 p-8 hover:border-primary hover:shadow-xl transition-all group">
          <div class="flex items-start justify-between mb-6">
            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
            </div>
            <span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-bold rounded-lg">
              MAPE {{ singleModelInfo?.mape ?? 'N/A' }}%
            </span>
          </div>

          <h2 class="text-2xl font-bold text-primary-dark mb-2">Single Output</h2>
          <p class="text-gray-600 mb-6 text-sm">
            Prediksi total okupansi hotel secara keseluruhan untuk perencanaan revenue dan staffing
          </p>

          <div class="space-y-3 mb-6">
            <div class="flex items-start gap-2 text-sm">
              <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-gray-700">Akurasi tinggi (MAPE {{ singleModelInfo?.mape ?? 'N/A' }}%)</span>
            </div>
            <div class="flex items-start gap-2 text-sm">
              <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-gray-700">Cocok untuk laporan manajemen</span>
            </div>
            <div class="flex items-start gap-2 text-sm">
              <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-gray-700">Prediksi cepat dan sederhana</span>
            </div>
          </div>

          <button
            @click="openGenerateModal('single')"
            class="w-full px-6 py-3 bg-gradient-to-br from-primary to-primary-dark text-white rounded-2xl font-semibold hover:shadow-lg transition-all flex items-center justify-center gap-2"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            Generate Single Output
          </button>

          <Link
            href="/predictions/single"
            class="mt-3 w-full px-6 py-2.5 border-2 border-primary text-primary rounded-2xl font-semibold hover:bg-primary hover:text-white transition-all flex items-center justify-center gap-2 text-sm"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            Lihat History
          </Link>
        </div>

        <!-- Multi Output Card -->
        <div class="bg-white rounded-3xl border-2 border-surface/30 p-8 hover:border-purple-600 hover:shadow-xl transition-all group">
          <div class="flex items-start justify-between mb-6">
            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl flex items-center justify-center">
              <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-3zM14 16a1 1 0 011-1h4a1 1 0 011 1v3a1 1 0 01-1 1h-4a1 1 0 01-1-1v-3z" />
              </svg>
            </div>
            <span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-bold rounded-lg">
              MAPE {{ multiModelInfo?.mape ?? 'N/A' }}%
            </span>
          </div>

          <h2 class="text-2xl font-bold text-primary-dark mb-2">Multi Output</h2>
          <p class="text-gray-600 mb-6 text-sm">
            Prediksi okupansi per tipe kamar untuk strategi pricing dan alokasi kamar yang optimal
          </p>

          <div class="space-y-3 mb-6">
            <div class="flex items-start gap-2 text-sm">
              <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-gray-700">Detail per tipe kamar (4 rooms)</span>
            </div>
            <div class="flex items-start gap-2 text-sm">
              <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-gray-700">Optimal untuk pricing strategy</span>
            </div>
            <div class="flex items-start gap-2 text-sm">
              <svg class="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span class="text-gray-700">Analisis mendalam per segmen</span>
            </div>
          </div>

          <button
            @click="openGenerateModal('multi')"
            class="w-full px-6 py-3 bg-gradient-to-br from-purple-600 to-purple-700 text-white rounded-2xl font-semibold hover:shadow-lg transition-all flex items-center justify-center gap-2"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            Generate Multi Output
          </button>

          <Link
            href="/predictions/multi"
            class="mt-3 w-full px-6 py-2.5 border-2 border-purple-600 text-purple-600 rounded-2xl font-semibold hover:bg-purple-600 hover:text-white transition-all flex items-center justify-center gap-2 text-sm"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            Lihat History
          </Link>
        </div>

      </div>

      <!-- Recent Activity (Optional - Small Section) -->
      <div v-if="recentPredictions && recentPredictions.length > 0" class="bg-white rounded-3xl border border-surface/30 p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-primary-dark">Prediksi Terbaru</h3>
          <span class="text-sm text-gray-500">{{ recentPredictions.length }} prediksi terakhir</span>
        </div>

        <div class="space-y-2">
          <div
            v-for="prediction in recentPredictions.slice(0, 5)"
            :key="prediction.id"
            class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition-colors"
          >
            <div class="flex items-center gap-3">
              <span
                class="px-2.5 py-1 rounded-lg text-xs font-bold"
                :class="prediction.model_type === 'single' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
              >
                {{ prediction.model_type === 'single' ? 'Single' : 'Multi' }}
              </span>
              <div>
                <p class="text-sm font-semibold text-gray-900">
                  {{ formatMonth(prediction.predicted_for_date) }}
                </p>
                <p class="text-xs text-gray-500">
                  {{ prediction.room_type?.name || 'Total Hotel' }}
                </p>
              </div>
            </div>

            <div class="flex items-center gap-4">
              <div class="text-right">
                <p class="text-lg font-bold text-primary">{{ Number(prediction.predicted_occupancy_rate || 0).toFixed(1) }}%</p>
                <p class="text-xs text-gray-500">{{ formatDate(prediction.created_at) }}</p>
              </div>
              <Link
                :href="prediction.model_type === 'single' ? '/predictions/single' : '/predictions/multi'"
                class="px-3 py-2 text-primary hover:bg-primary hover:text-white rounded-xl transition-all text-xs font-medium border border-primary"
              >
                Detail
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
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-200">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-xl font-bold text-primary-dark">Generate Prediksi</h2>
              <p class="text-sm text-gray-500 mt-1">
                {{ selectedModelType === 'single' ? 'Single Output' : 'Multi Output' }}
              </p>
            </div>
            <button
              @click="showGenerateModal = false"
              class="w-10 h-10 rounded-xl hover:bg-gray-100 flex items-center justify-center transition-colors"
            >
              <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Modal Body -->
        <form @submit.prevent="generatePrediction" class="p-6 space-y-6">
          <!-- Month Selection -->
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Pilih Bulan</label>
            <input
              v-model="selectedMonth"
              type="month"
              :min="minMonth"
              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"
              required
            />
            <p class="text-xs text-gray-500 mt-2">Pilih bulan untuk prediksi okupansi</p>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center gap-3">
            <button
              type="submit"
              :disabled="generating"
              :class="selectedModelType === 'single' ? 'bg-primary hover:bg-primary-dark' : 'bg-purple-600 hover:bg-purple-700'"
              class="flex-1 px-6 py-3 text-white rounded-xl font-semibold transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              <svg v-if="!generating" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
              <svg v-else class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              {{ generating ? 'Generating...' : 'Generate' }}
            </button>
            <button
              type="button"
              @click="showGenerateModal = false"
              class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition-all"
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
      title="Generating Prediction"
      :message="selectedModelType === 'single' ? 'Model LSTM sedang menganalisis data historis dan membuat prediksi okupansi total hotel...' : 'Model LSTM sedang menganalisis data dan membuat prediksi untuk setiap tipe kamar...'"
    />
  </DashboardLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import LoadingModal from '@/Components/LoadingModal.vue';

const props = defineProps({
  allPredictions: Array,
  predictionsByMonth: Array,
  stats: Object,
  roomTypes: Array,
  singleModelInfo: Object,
  multiModelInfo: Object,
});

const selectedModelType = ref('single');
const selectedMonth = ref('');
const generating = ref(false);
const showGenerateModal = ref(false);

// Get recent predictions (last 5)
const recentPredictions = computed(() => {
  if (!props.allPredictions) return [];
  return props.allPredictions.slice(0, 5);
});

// Set minimum month to next month
const minMonth = computed(() => {
  const nextMonth = new Date();
  nextMonth.setMonth(nextMonth.getMonth() + 1);
  return nextMonth.toISOString().slice(0, 7);
});

const formatMonth = (dateString) => {
  return new Date(dateString).toLocaleDateString('id-ID', {
    month: 'long',
    year: 'numeric',
  });
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
};

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

  router.post(endpoint, {
    predict_for_month: selectedMonth.value + '-01',
  }, {
    preserveScroll: true,
    onFinish: () => {
      generating.value = false;
    },
    onSuccess: () => {
      // Redirect to appropriate page after generation
      const redirectUrl = selectedModelType.value === 'single'
        ? '/predictions/single'
        : '/predictions/multi';

      router.visit(redirectUrl);
    },
  });
};
</script>
