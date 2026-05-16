<template>
  <div v-if="comparisons && comparisons.length > 0" class="bg-white rounded-2xl shadow-sm border border-surface/30 overflow-hidden">
    <div class="px-5 py-3.5 border-b border-surface/20 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-primary-dark">Perbandingan Antar Bulan</h2>
      <span class="text-xs text-gray-400">{{ comparisons.length }} perbandingan</span>
    </div>

    <div class="divide-y divide-surface/20">
      <div
        v-for="(comparison, index) in comparisons"
        :key="index"
        class="flex items-center gap-4 px-5 py-3"
      >
        <!-- From month -->
        <div class="flex-shrink-0 w-24 text-right">
          <p class="text-[10px] text-gray-400">Dari</p>
          <p class="text-xs font-semibold text-gray-600">{{ comparison.from_month }}</p>
          <p class="text-sm font-bold text-primary-dark">{{ comparison.prev_occupancy }}%</p>
        </div>

        <!-- Arrow + change badge -->
        <div class="flex-shrink-0 flex flex-col items-center gap-1">
          <svg
            v-if="comparison.trend === 'up'"
            class="w-5 h-5 text-green-500"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
          </svg>
          <svg
            v-else-if="comparison.trend === 'down'"
            class="w-5 h-5 text-primary"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
          </svg>
          <svg
            v-else
            class="w-5 h-5 text-gray-400"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" />
          </svg>
          <span
            class="text-[10px] font-bold px-1.5 py-0.5 rounded"
            :class="{
              'bg-green-100 text-green-700':     comparison.trend === 'up',
              'bg-primary/10 text-primary-dark': comparison.trend === 'down',
              'bg-gray-100 text-gray-600':       comparison.trend === 'flat',
            }"
          >{{ comparison.change > 0 ? '+' : '' }}{{ comparison.change }}%</span>
        </div>

        <!-- To month -->
        <div class="flex-shrink-0 w-24">
          <p class="text-[10px] text-gray-400">Ke</p>
          <p class="text-xs font-semibold text-gray-600">{{ comparison.to_month }}</p>
          <p class="text-sm font-bold text-primary-dark">{{ comparison.current_occupancy }}%</p>
        </div>

        <!-- Interpretation -->
        <div class="flex-1 min-w-0 border-l border-surface/30 pl-4">
          <p class="text-xs text-gray-600 leading-relaxed line-clamp-2">{{ comparison.interpretation }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  comparisons: Array,
});
</script>
