<script setup>
import { ref } from 'vue';

const props = defineProps({
  term: {
    type: String,
    required: true,
  },
  placement: {
    type: String,
    default: 'top', // top, bottom, left, right
  },
});

const showTooltip = ref(false);

// ML Glossary - comprehensive explanations
const glossary = {
  'MAPE': {
    title: 'MAPE (Mean Absolute Percentage Error)',
    description: 'Mengukur akurasi prediksi dalam persentase. Semakin rendah, semakin akurat.',
    explanation: 'MAPE 10% berarti prediksi rata-rata meleset 10% dari nilai sebenarnya. Target: <15% (sangat baik), 15-20% (baik), >20% (perlu perbaikan).',
    formula: 'MAPE = (1/n) × Σ|Aktual - Prediksi| / |Aktual| × 100%',
  },
  'R²': {
    title: 'R² (R-Squared / Coefficient of Determination)',
    description: 'Mengukur seberapa baik model menjelaskan variasi data. Nilai 0-1.',
    explanation: 'R² = 0.90 berarti model menjelaskan 90% variasi okupansi. R² = 1.0 adalah sempurna (jarang tercapai). Target: >0.80 (baik), >0.90 (sangat baik).',
    formula: 'R² = 1 - (SS_res / SS_tot)',
  },
  'RMSE': {
    title: 'RMSE (Root Mean Squared Error)',
    description: 'Mengukur rata-rata kesalahan prediksi dalam satuan asli (jumlah kamar).',
    explanation: 'RMSE = 5 berarti prediksi rata-rata meleset 5 kamar dari aktual. Sensitif terhadap outlier (kesalahan besar). Semakin rendah semakin baik.',
    formula: 'RMSE = √[(1/n) × Σ(Aktual - Prediksi)²]',
  },
  'LSTM': {
    title: 'LSTM (Long Short-Term Memory)',
    description: 'Jenis neural network untuk data time series yang dapat "mengingat" pola jangka panjang.',
    explanation: 'LSTM sangat cocok untuk prediksi okupansi karena dapat menangkap pola musiman, tren tahunan, dan momentum booking yang kompleks.',
    formula: 'Architecture: Input → LSTM Layers → Dense → Output',
  },
  'Confidence': {
    title: 'Confidence Level (Tingkat Kepercayaan)',
    description: 'Seberapa yakin model dengan prediksinya, dalam persentase.',
    explanation: 'Confidence 85% berarti model cukup yakin. Dihitung dari stabilitas prediksi dan kualitas data historis. >80% = tinggi, 60-80% = sedang, <60% = rendah.',
    formula: 'Based on prediction variance and R² score',
  },
  'Occupancy Rate': {
    title: 'Occupancy Rate (Tingkat Okupansi)',
    description: 'Persentase kamar yang terisi dibanding total kamar tersedia.',
    explanation: 'Occupancy Rate = (Kamar Terisi / Total Kamar) × 100%. Metrik utama kesuksesan hotel. Target industri: 65-75%.',
    formula: 'Occupancy = (Rooms Occupied / Total Rooms) × 100%',
  },
  'Champion Model': {
    title: 'Champion Model (Model Unggulan)',
    description: 'Model ML terbaik yang sedang digunakan untuk prediksi production.',
    explanation: 'Dipilih otomatis berdasarkan metrik terbaik (MAPE terendah, R² tertinggi). Challenger models dilatih dan dibandingkan sebelum promosi.',
    formula: 'Promoted when: MAPE_new < MAPE_current && 0 < R² < 1',
  },
  'Retraining': {
    title: 'Model Retraining (Pelatihan Ulang)',
    description: 'Proses melatih ulang model dengan data terbaru untuk menjaga akurasi.',
    explanation: 'Dilakukan setiap 6 bulan atau saat ada 3+ bulan data baru. Mencegah model drift (penurunan akurasi karena perubahan pola).',
    formula: 'Triggered when: new_data_months ≥ 3 OR months_since_training ≥ 6',
  },
  'Single-Output': {
    title: 'Single-Output Model',
    description: 'Model yang memprediksi total okupansi hotel secara keseluruhan.',
    explanation: 'Lebih sederhana dan cepat. Cocok untuk overview cepat dan perencanaan kapasitas total. Output: 1 nilai prediksi.',
    formula: 'Input: [6 months × 15 features] → Output: 1 value (total occupancy)',
  },
  'Multi-Output': {
    title: 'Multi-Output Model',
    description: 'Model yang memprediksi okupansi per tipe kamar (STD, SPR, FMY, JS).',
    explanation: 'Lebih detail dan akurat untuk analisis per tipe kamar. Membantu pricing strategy dan inventory management. Output: 4 nilai prediksi.',
    formula: 'Input: [6 months × 15 features] → Output: 4 values (STD, SPR, FMY, JS)',
  },
  'Sliding Window': {
    title: 'Sliding Window Projection',
    description: 'Teknik prediksi yang menggunakan data 6 bulan terakhir untuk prediksi berikutnya.',
    explanation: 'Window "bergeser" setiap bulan. Bulan baru masuk, bulan terlama keluar. Menjaga model tetap fokus pada pola terkini.',
    formula: 'Window: [t-5, t-4, t-3, t-2, t-1, t] → Predict: t+1',
  },
};

const tooltipContent = glossary[props.term] || {
  title: props.term,
  description: 'Penjelasan belum tersedia untuk istilah ini.',
  explanation: '',
  formula: '',
};
</script>

<template>
  <div class="inline-flex items-center relative">
    <!-- Help Icon -->
    <button
      @mouseenter="showTooltip = true"
      @mouseleave="showTooltip = false"
      @focus="showTooltip = true"
      @blur="showTooltip = false"
      class="ml-1.5 text-gray-400 hover:text-blue-600 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 rounded-full"
      type="button"
      :aria-label="`Informasi tentang ${term}`"
    >
      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
      </svg>
    </button>

    <!-- Tooltip Popup -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0 translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-1"
    >
      <div
        v-show="showTooltip"
        :class="[
          'absolute z-50 w-80 px-4 py-3 text-sm bg-gray-900 text-white rounded-xl shadow-2xl',
          placement === 'top' ? 'bottom-full mb-2 left-1/2 -translate-x-1/2' : '',
          placement === 'bottom' ? 'top-full mt-2 left-1/2 -translate-x-1/2' : '',
          placement === 'left' ? 'right-full mr-2 top-1/2 -translate-y-1/2' : '',
          placement === 'right' ? 'left-full ml-2 top-1/2 -translate-y-1/2' : '',
        ]"
        role="tooltip"
      >
        <!-- Arrow -->
        <div
          :class="[
            'absolute w-2 h-2 bg-gray-900 transform rotate-45',
            placement === 'top' ? 'bottom-[-4px] left-1/2 -translate-x-1/2' : '',
            placement === 'bottom' ? 'top-[-4px] left-1/2 -translate-x-1/2' : '',
            placement === 'left' ? 'right-[-4px] top-1/2 -translate-y-1/2' : '',
            placement === 'right' ? 'left-[-4px] top-1/2 -translate-y-1/2' : '',
          ]"
        ></div>

        <!-- Content -->
        <div class="relative">
          <div class="flex items-start gap-2 mb-2">
            <svg class="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
            <div class="flex-1">
              <h4 class="font-semibold text-blue-300 mb-1">{{ tooltipContent.title }}</h4>
              <p class="text-gray-200 text-xs leading-relaxed mb-2">
                {{ tooltipContent.description }}
              </p>

              <p v-if="tooltipContent.explanation" class="text-gray-300 text-xs leading-relaxed mb-2">
                💡 {{ tooltipContent.explanation }}
              </p>

              <p v-if="tooltipContent.formula" class="text-blue-200 text-xs font-mono bg-gray-800/50 px-2 py-1 rounded border border-gray-700">
                {{ tooltipContent.formula }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
/* Ensure tooltip is always visible and above other content */
[role="tooltip"] {
  pointer-events: none;
}
</style>
