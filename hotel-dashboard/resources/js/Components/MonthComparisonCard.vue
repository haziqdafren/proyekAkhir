<template>
  <div v-if="comparisons && comparisons.length > 0" class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden mb-8">
    <div class="px-8 py-6 border-b border-surface/30">
      <h2 class="text-xl font-semibold text-primary-dark">Perbandingan Antar Bulan</h2>
      <p class="text-sm text-gray-500 mt-1">Tren okupansi dari waktu ke waktu</p>
    </div>

    <div class="p-8">
      <div class="space-y-4">
        <div
          v-for="(comparison, index) in comparisons"
          :key="index"
          class="flex items-center justify-between p-5 rounded-2xl border-2 transition-all hover:shadow-md"
          :class="getTrendBorderClass(comparison.trend)"
        >
          <!-- Month Names -->
          <div class="flex items-center gap-4">
            <div class="text-center">
              <div class="text-xs text-gray-500 mb-1">Dari</div>
              <div class="font-semibold text-gray-700">{{ comparison.from_month }}</div>
              <div class="text-sm text-primary-dark font-bold">{{ comparison.prev_occupancy }}%</div>
            </div>

            <!-- Arrow -->
            <div>
              <svg
                v-if="comparison.trend === 'up'"
                class="w-8 h-8 text-green-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
              <svg
                v-else-if="comparison.trend === 'down'"
                class="w-8 h-8 text-red-500"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
              </svg>
              <svg
                v-else
                class="w-8 h-8 text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" />
              </svg>
            </div>

            <div class="text-center">
              <div class="text-xs text-gray-500 mb-1">Ke</div>
              <div class="font-semibold text-gray-700">{{ comparison.to_month }}</div>
              <div class="text-sm text-primary-dark font-bold">{{ comparison.current_occupancy }}%</div>
            </div>
          </div>

          <!-- Change Info -->
          <div class="flex-1 ml-6">
            <div class="flex items-center gap-3 mb-2">
              <span
                class="inline-flex items-center px-3 py-1.5 rounded-xl text-sm font-bold"
                :class="getTrendBadgeClass(comparison.trend)"
              >
                {{ comparison.change > 0 ? '+' : '' }}{{ comparison.change }}%
                <span class="ml-1 text-xs">({{ comparison.change_percent > 0 ? '+' : '' }}{{ comparison.change_percent }}%)</span>
              </span>
            </div>
            <p class="text-sm text-gray-600">{{ comparison.interpretation }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  comparisons: Array,
});

const getTrendBorderClass = (trend) => {
  switch (trend) {
    case 'up':
      return 'border-green-200 bg-green-50/30';
    case 'down':
      return 'border-red-200 bg-red-50/30';
    default:
      return 'border-gray-200 bg-gray-50/30';
  }
};

const getTrendBadgeClass = (trend) => {
  switch (trend) {
    case 'up':
      return 'bg-green-100 text-green-700';
    case 'down':
      return 'bg-red-100 text-red-700';
    default:
      return 'bg-gray-100 text-gray-700';
  }
};
</script>
