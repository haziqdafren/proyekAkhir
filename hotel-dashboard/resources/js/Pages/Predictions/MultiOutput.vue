<template>
  <DashboardLayout>
    <div class="p-8 space-y-8 max-w-[1600px] mx-auto">
      <!-- Header with Compact Model Info -->
      <div class="flex items-center justify-between">
        <div class="flex-1">
          <div class="flex items-center gap-3 mb-2">
            <h1 class="text-3xl font-bold text-primary-dark">Prediksi per Tipe Kamar</h1>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-gradient-to-r from-purple-500 to-indigo-500 text-white text-xs font-bold shadow-md">
              MAPE {{ modelInfo?.mape ?? 'N/A' }}%
            </span>
          </div>
          <div class="flex items-center gap-2 text-gray-600">
            <span class="text-sm">Model LSTM Multi-Output • Prediksi okupansi per tipe kamar</span>
            <button
              @click="showModelInfo = !showModelInfo"
              class="inline-flex items-center gap-1 text-xs text-primary hover:text-primary-dark font-medium"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Info Model
            </button>
          </div>

          <!-- Compact Model Info - Collapsible -->
          <div v-show="showModelInfo" class="mt-3 p-4 bg-purple-50 border border-purple-200 rounded-xl text-sm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <p class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                  <span class="inline-block w-1.5 h-1.5 bg-green-600 rounded-full"></span>
                  Cocok untuk:
                </p>
                <ul class="text-gray-700 space-y-1 text-xs pl-3.5">
                  <li>• Pricing & promo per tipe kamar</li>
                  <li>• Marketing targeted per segment</li>
                </ul>
              </div>
              <div>
                <p class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                  <span class="inline-block w-1.5 h-1.5 bg-amber-600 rounded-full"></span>
                  Keterbatasan:
                </p>
                <ul class="text-gray-700 space-y-1 text-xs pl-3.5">
                  <li>• Akurasi lebih rendah dari Single Output</li>
                  <li>• Analisis lebih kompleks</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <Link href="/predictions" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-2xl font-medium transition-colors">
          ← Kembali
        </Link>
      </div>

      <!-- Room Capacities Info -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div v-for="(capacity, code) in roomCapacities" :key="code" class="bg-white rounded-2xl border border-surface/30 p-4">
          <p class="text-xs text-gray-500 font-medium uppercase">{{ code }}</p>
          <p class="text-2xl font-bold text-primary-dark mt-1">{{ capacity }}</p>
          <p class="text-xs text-gray-500 mt-0.5">kamar</p>
        </div>
      </div>

      <!-- Month-to-Month Comparison -->
      <MonthComparisonCard :comparisons="comparisons" />

      <!-- Grouped Predictions by Month -->
      <div v-if="groupedPredictions.length > 0" class="space-y-6">
        <div v-for="group in groupedPredictions" :key="group.month" class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
          <!-- Month Header -->
          <div class="px-8 py-6 bg-gradient-to-r from-primary/5 to-purple-50 border-b border-surface/30">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-xl font-semibold text-primary-dark">{{ group.month }}</h3>
                <p class="text-sm text-gray-500 mt-1">Klik baris untuk lihat rekomendasi • Dibuat: {{ formatDate(group.created_at) }}</p>
              </div>
              <button
                @click="confirmDelete(group)"
                class="inline-flex items-center px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-xl transition-colors"
                title="Hapus semua prediksi bulan ini"
              >
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Hapus
              </button>
            </div>
          </div>

          <!-- Hybrid Table -->
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-8"></th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tipe Kamar</th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Okupansi</th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <template v-for="prediction in group.predictions" :key="prediction.id">
                  <!-- Main Row (Clickable) -->
                  <tr
                    @click="toggleExpand(prediction.id)"
                    class="hover:bg-purple-50/50 transition-colors cursor-pointer"
                    :class="{ 'bg-purple-50/30': expandedRows.has(prediction.id) }"
                  >
                    <!-- Expand Icon -->
                    <td class="px-6 py-4">
                      <svg
                        class="w-5 h-5 text-gray-400 transition-transform"
                        :class="{ 'rotate-90': expandedRows.has(prediction.id) }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                      </svg>
                    </td>

                    <!-- Room Type -->
                    <td class="px-6 py-4">
                      <div class="flex items-center gap-3">
                        <div class="w-3 h-3 rounded-full" :class="getRoomDotColor(prediction.room_type?.code)"></div>
                        <div>
                          <div class="font-semibold text-primary-dark">{{ prediction.room_type?.name }}</div>
                          <div class="text-xs text-gray-500">{{ roomCapacities[prediction.room_type?.code] }} kamar</div>
                        </div>
                      </div>
                    </td>

                    <!-- Occupancy -->
                    <td class="px-6 py-4">
                      <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-primary-dark">{{ Number(prediction.predicted_occupancy_rate || 0).toFixed(1) }}%</span>
                        <span class="text-sm text-gray-500">({{ prediction.insights?.avg_rooms_per_day || Math.round(roomCapacities[prediction.room_type?.code] * prediction.predicted_occupancy_rate / 100) }} kamar/hari)</span>
                      </div>
                      <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                        <div
                          class="h-1.5 rounded-full transition-all"
                          :class="getRoomColorClass(prediction.room_type?.code)"
                          :style="{ width: `${prediction.predicted_occupancy_rate}%` }"
                        ></div>
                      </div>
                    </td>

                    <!-- Status -->
                    <td class="px-6 py-4">
                      <div v-if="prediction.insights" class="flex items-center gap-2">
                        <span class="text-xl">{{ prediction.insights.performance.icon }}</span>
                        <div>
                          <div class="font-semibold text-sm" :class="`text-${prediction.insights.performance.color}-700`">
                            {{ prediction.insights.performance.level }}
                          </div>
                          <div class="text-xs text-gray-500">Confidence: {{ Number(prediction.confidence_level || 0).toFixed(1) }}%</div>
                        </div>
                      </div>
                      <span v-else class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold"
                        :class="getConfidenceClass(prediction.confidence_level)">
                        {{ Number(prediction.confidence_level || 0).toFixed(1) }}%
                      </span>
                    </td>
                  </tr>

                  <!-- Expanded Row (Insights) -->
                  <tr v-if="expandedRows.has(prediction.id)" class="bg-gradient-to-br from-purple-50 to-indigo-50">
                    <td colspan="4" class="px-6 py-6">
                      <div class="max-w-5xl mx-auto">
                        <PredictionInsightCard
                          v-if="prediction.insights"
                          :insights="prediction.insights"
                          :month-name="`${group.month} - ${prediction.room_type?.name}`"
                        />
                        <div v-else class="text-center text-gray-500 py-4">
                          Loading insights...
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="bg-white rounded-3xl shadow-sm border border-surface/30 p-12 text-center">
        <div class="max-w-md mx-auto">
          <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Belum Ada Prediksi</h3>
          <p class="text-gray-500">Gunakan form di bawah untuk membuat prediksi pertama Anda</p>
        </div>
      </div>

      <!-- Generate New Prediction - Collapsible (at bottom) -->
      <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
        <button
          @click="showGenerateForm = !showGenerateForm"
          class="w-full px-8 py-6 flex items-center justify-between hover:bg-gray-50 transition-colors"
        >
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-semibold text-primary-dark text-left">Generate Prediksi Baru</h2>
              <p class="text-sm text-gray-500 text-left">Buat prediksi untuk semua 4 tipe kamar</p>
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

        <div v-show="showGenerateForm" class="px-8 pb-8 pt-2 border-t border-surface/20">
          <form @submit.prevent="generatePrediction" class="space-y-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Bulan untuk Prediksi</label>
              <input
                v-model="selectedMonth"
                type="month"
                :min="minMonth"
                class="w-full md:w-96 px-4 py-3 border border-surface rounded-2xl focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"
                required
              />
              <p class="text-xs text-gray-500 mt-2">Prediksi akan dibuat untuk semua 4 tipe kamar</p>

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
                :class="monthAlreadyPredicted ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-purple-600 hover:bg-purple-700'"
                class="px-6 py-3 text-white rounded-2xl font-semibold transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
              >
                <svg v-if="!generating" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <svg v-else class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                {{ generating ? 'Generating...' : (monthAlreadyPredicted ? 'Perbarui Prediksi' : 'Generate Prediksi') }}
              </button>

              <div v-if="$page.props.flash?.success" class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 rounded-lg">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <p class="text-green-700 font-medium text-sm">{{ $page.props.flash.success }}</p>
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
      title="Generating Predictions"
      message="Model LSTM sedang menganalisis data dan membuat prediksi untuk setiap tipe kamar..."
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
import PredictionInsightCard from '@/Components/PredictionInsightCard.vue';
import MonthComparisonCard from '@/Components/MonthComparisonCard.vue';

const props = defineProps({
  recentPredictions: Array,
  comparisons: Array,
  modelInfo: Object,
  roomTypes: Array,
  roomCapacities: Object,
});

const selectedMonth = ref('');
const generating = ref(false);
const showDeleteConfirm = ref(false);
const predictionToDelete = ref(null);
const expandedRows = ref(new Set());
const showModelInfo = ref(false);
const showGenerateForm = ref(false);

// Set minimum month to next month
const minMonth = computed(() => {
  const nextMonth = new Date();
  nextMonth.setMonth(nextMonth.getMonth() + 1);
  return nextMonth.toISOString().slice(0, 7);
});

// Check if selected month already has predictions
const monthAlreadyPredicted = computed(() => {
  if (!selectedMonth.value || !props.recentPredictions) return false;

  return props.recentPredictions.some(pred => {
    const predDate = new Date(pred.predicted_for_date);
    const selectedDate = new Date(selectedMonth.value + '-01');
    return predDate.getFullYear() === selectedDate.getFullYear() &&
           predDate.getMonth() === selectedDate.getMonth();
  });
});

// Group predictions by month
const groupedPredictions = computed(() => {
  const groups = {};
  
  props.recentPredictions.forEach(pred => {
    const monthKey = formatMonth(pred.predicted_for_date);
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

  router.post('/predictions/generate-multi', {
    predict_for_month: selectedMonth.value + '-01',
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
  const predDate = new Date(firstPred.predicted_for_date);

  predictionToDelete.value = {
    year: predDate.getFullYear(),
    month: predDate.getMonth() + 1,
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

const toggleExpand = (id) => {
  if (expandedRows.value.has(id)) {
    expandedRows.value.delete(id);
  } else {
    expandedRows.value.add(id);
  }
};

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
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getConfidenceClass = (confidence) => {
  if (confidence >= 75) return 'bg-green-100 text-green-700';
  if (confidence >= 60) return 'bg-yellow-100 text-yellow-700';
  return 'bg-red-100 text-red-700';
};

const getRoomColorClass = (code) => {
  const colors = {
    'STD': 'bg-primary',
    'SPR': 'bg-green-500',
    'FMY': 'bg-purple-500',
    'JS': 'bg-orange-500',
  };
  return colors[code] || 'bg-primary';
};

const getRoomBorderClass = (code) => {
  const borders = {
    'STD': 'border-primary/30 hover:border-primary',
    'SPR': 'border-green-300 hover:border-green-500',
    'FMY': 'border-purple-300 hover:border-purple-500',
    'JS': 'border-orange-300 hover:border-orange-500',
  };
  return borders[code] || 'border-primary/30';
};

const getRoomDotColor = (code) => {
  const colors = {
    'STD': 'bg-primary',
    'SPR': 'bg-green-500',
    'FMY': 'bg-purple-500',
    'JS': 'bg-orange-500',
  };
  return colors[code] || 'bg-primary';
};
</script>
