<template>
  <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-6 border-2 border-blue-200">
    <!-- Performance Badge -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2">
        <span class="text-2xl">{{ insights.performance.icon }}</span>
        <span class="text-lg font-semibold" :class="`text-${insights.performance.color}-700`">
          {{ insights.performance.level }}
        </span>
      </div>
      <span class="text-sm text-gray-600">{{ monthName }}</span>
    </div>

    <!-- Simple Interpretation -->
    <p class="text-sm text-gray-700 mb-4 leading-relaxed">
      {{ insights.interpretation }}
    </p>

    <!-- Key Metrics Grid -->
    <div class="grid grid-cols-2 gap-3 mb-4">
      <!-- Avg Rooms per Day -->
      <div class="bg-white/80 rounded-xl p-3">
        <div class="text-xs text-gray-500 mb-1">Rata-rata Per Hari</div>
        <div class="text-xl font-bold text-primary-dark">{{ insights.avg_rooms_per_day }} kamar</div>
      </div>

      <!-- Estimated Revenue -->
      <div class="bg-white/80 rounded-xl p-3">
        <div class="text-xs text-gray-500 mb-1">Estimasi Revenue</div>
        <div class="text-lg font-bold text-green-600">{{ insights.estimated_revenue_formatted }}</div>
      </div>
    </div>

    <!-- Staffing Recommendation -->
    <div class="bg-white/80 rounded-xl p-3 mb-3">
      <div class="flex items-center gap-2 mb-1">
        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <span class="text-xs font-semibold text-gray-700">Kebutuhan Staff: {{ insights.staffing.level }}</span>
      </div>
      <p class="text-xs text-gray-600">{{ insights.staffing.description }}</p>
    </div>

    <!-- Marketing Action -->
    <div class="bg-white/80 rounded-xl p-3" :class="getMarketingUrgencyClass(insights.marketing.urgency)">
      <div class="flex items-center gap-2 mb-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
        </svg>
        <span class="text-xs font-semibold">Aksi Marketing: {{ insights.marketing.action }}</span>
      </div>
      <p class="text-xs">{{ insights.marketing.description }}</p>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  insights: Object,
  monthName: String,
});

const getMarketingUrgencyClass = (urgency) => {
  switch (urgency) {
    case 'urgent':
      return 'border-l-4 border-red-500';
    case 'high':
      return 'border-l-4 border-orange-500';
    case 'medium':
      return 'border-l-4 border-yellow-500';
    default:
      return 'border-l-4 border-green-500';
  }
};
</script>
