<template>
  <div class="bg-white rounded-2xl p-6 border border-surface/40 shadow-sm">
    <!-- Performance Badge + Month -->
    <div class="flex items-center justify-between mb-4">
      <span
        class="inline-flex items-center px-3 py-1.5 rounded-xl text-sm font-bold"
        :class="getPerformanceBadgeClass(insights.performance.color)"
      >
        {{ insights.performance.level }}
      </span>
      <span class="text-sm text-gray-500 font-medium">{{ monthName }}</span>
    </div>

    <!-- Simple Interpretation -->
    <p class="text-sm text-gray-700 mb-5 leading-relaxed bg-surface/30 rounded-xl px-4 py-3">
      {{ insights.interpretation }}
    </p>

    <!-- Key Metrics Grid -->
    <div class="grid grid-cols-2 gap-3 mb-4">
      <!-- Avg Rooms per Day -->
      <div class="bg-primary/5 border border-primary/15 rounded-xl p-3">
        <div class="text-xs text-gray-500 mb-1 font-medium">Rata-rata Per Hari</div>
        <div class="text-xl font-bold text-primary-dark">{{ insights.avg_rooms_per_day }} kamar</div>
      </div>

      <!-- Estimated Revenue -->
      <div class="bg-primary/5 border border-primary/15 rounded-xl p-3">
        <div class="text-xs text-gray-500 mb-1 font-medium">Estimasi Revenue</div>
        <div class="text-lg font-bold text-primary-dark">{{ insights.estimated_revenue_formatted }}</div>
      </div>
    </div>

    <!-- Staffing Recommendation -->
    <div class="rounded-xl border border-surface/60 p-3.5 mb-3 bg-white">
      <div class="flex items-center gap-2 mb-1.5">
        <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <span class="text-xs font-semibold text-primary-dark">Kebutuhan Staff: {{ insights.staffing.level }}</span>
      </div>
      <p class="text-xs text-gray-600 leading-relaxed">{{ insights.staffing.description }}</p>
    </div>

    <!-- Marketing Action -->
    <div
      class="rounded-xl border p-3.5 bg-white"
      :class="getMarketingBorderClass(insights.marketing.urgency)"
    >
      <div class="flex items-center gap-2 mb-1.5">
        <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
        </svg>
        <span class="text-xs font-semibold text-primary-dark">Aksi Marketing: {{ insights.marketing.action }}</span>
      </div>
      <p class="text-xs text-gray-600 leading-relaxed">{{ insights.marketing.description }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  insights: Object,
  monthName: String,
});

const getPerformanceBadgeClass = (color) => {
  const map = {
    'primary':       'bg-primary/10 text-primary',
    'primary-light': 'bg-primary/10 text-primary',
    'yellow':        'bg-yellow-100 text-yellow-800',
    'red':           'bg-red-50 text-red-700',
  };
  return map[color] || 'bg-surface text-primary-dark';
};

// Marketing urgency → left border accent only (keeps card white/clean)
const getMarketingBorderClass = (urgency) => {
  switch (urgency) {
    case 'urgent': return 'border-l-4 border-l-red-400 border-surface/40';
    case 'high':   return 'border-l-4 border-l-yellow-400 border-surface/40';
    case 'medium': return 'border-l-4 border-l-primary/50 border-surface/40';
    default:       return 'border-l-4 border-l-primary/30 border-surface/40';
  }
};
</script>
