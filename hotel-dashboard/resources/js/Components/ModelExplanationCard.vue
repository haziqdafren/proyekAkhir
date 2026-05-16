<template>
  <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-3xl border-2 border-blue-200 p-8 mb-8">
    <div class="flex items-start gap-4">
      <!-- Icon -->
      <div class="flex-shrink-0">
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
      </div>

      <div class="flex-1">
        <!-- Title -->
        <h3 class="text-2xl font-bold text-gray-900 mb-2">
          {{ title }}
        </h3>
        <p class="text-lg text-gray-700 mb-6">
          {{ description }}
        </p>

        <!-- Grid Layout -->
        <div class="grid md:grid-cols-2 gap-6 mb-6">
          <!-- Advantages -->
          <div class="bg-white/80 backdrop-blur rounded-2xl p-5 border border-green-200">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <h4 class="font-semibold text-gray-900">Kelebihan</h4>
            </div>
            <ul class="space-y-2">
              <li v-for="(advantage, index) in advantages" :key="index" class="flex items-start gap-2 text-sm text-gray-700">
                <span class="text-green-500 mt-0.5">•</span>
                <span>{{ advantage }}</span>
              </li>
            </ul>
          </div>

          <!-- Limitations -->
          <div class="bg-white/80 backdrop-blur rounded-2xl p-5 border border-orange-200">
            <div class="flex items-center gap-2 mb-3">
              <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
              </div>
              <h4 class="font-semibold text-gray-900">Keterbatasan</h4>
            </div>
            <ul class="space-y-2">
              <li v-for="(limitation, index) in limitations" :key="index" class="flex items-start gap-2 text-sm text-gray-700">
                <span class="text-orange-500 mt-0.5">•</span>
                <span>{{ limitation }}</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Accuracy Badge -->
        <div class="flex items-center gap-4 mb-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <span class="text-sm font-medium text-gray-700">Tingkat Akurasi</span>
              <div class="group relative">
                <svg class="w-4 h-4 text-gray-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block w-64 p-3 bg-gray-900 text-white text-xs rounded-lg z-10">
                  Tingkat akurasi menunjukkan seberapa tepat sistem memprediksi tingkat hunian. Semakin tinggi nilainya, semakin akurat prediksinya.
                  <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-gray-900"></div>
                </div>
              </div>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-1000"
                :class="accuracyColor"
                :style="{ width: `${accuracyValue}%` }"
              ></div>
            </div>
            <div class="flex justify-between items-center mt-1">
              <span class="text-xs text-gray-600">Akurasi: {{ accuracyValue }}%</span>
              <span class="text-xs font-semibold" :class="accuracyTextColor">{{ accuracyLabel }}</span>
            </div>
          </div>
          <div v-if="isRecommended" class="flex-shrink-0">
            <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-yellow-400 to-orange-400 text-white text-sm font-bold shadow-lg">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
              </svg>
              Direkomendasikan
            </span>
          </div>
        </div>

        <!-- When to Use -->
        <div class="bg-white/80 backdrop-blur rounded-2xl p-5 border border-blue-200">
          <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
              <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
              </svg>
            </div>
            <h4 class="font-semibold text-gray-900">💡 Kapan Menggunakan?</h4>
          </div>
          <ul class="space-y-2">
            <li v-for="(useCase, index) in useCases" :key="index" class="flex items-start gap-2 text-sm text-gray-700">
              <span class="text-blue-500 mt-0.5">→</span>
              <span>{{ useCase }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  advantages: {
    type: Array,
    required: true,
  },
  limitations: {
    type: Array,
    required: true,
  },
  useCases: {
    type: Array,
    required: true,
  },
  mape: {
    type: Number,
    default: null,
  },
  accuracy: {
    type: Number,
    default: null,
  },
  isRecommended: {
    type: Boolean,
    default: false,
  },
});

const accuracyValue = computed(() => {
  if (props.accuracy !== null) return props.accuracy;
  if (props.mape !== null) return Math.round((100 - props.mape) * 10) / 10;
  return 0;
});

const accuracyColor = computed(() => {
  if (accuracyValue.value >= 75) return 'bg-gradient-to-r from-green-500 to-emerald-600';
  if (accuracyValue.value >= 65) return 'bg-gradient-to-r from-yellow-500 to-orange-500';
  return 'bg-gradient-to-r from-red-500 to-rose-600';
});

const accuracyTextColor = computed(() => {
  if (accuracyValue.value >= 75) return 'text-green-600';
  if (accuracyValue.value >= 65) return 'text-yellow-600';
  return 'text-red-600';
});

const accuracyLabel = computed(() => {
  if (accuracyValue.value >= 75) return 'Sangat Baik ⭐';
  if (accuracyValue.value >= 65) return 'Baik';
  return 'Cukup';
});
</script>
