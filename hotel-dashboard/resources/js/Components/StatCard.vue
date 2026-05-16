<template>
  <div
    class="relative bg-white shadow-card-md hover:shadow-card-lg border border-surface/30 transition-all duration-300 cursor-pointer group"
    :class="compact ? 'rounded-xl' : 'rounded-3xl'"
    @click="$emit('click')"
  >
    <!-- Content -->
    <div class="relative" :class="compact ? 'p-4' : 'p-8'">
      <div class="flex items-start justify-between" :class="compact ? 'mb-3' : 'mb-8'">
        <div :class="[iconBgClass, compact ? 'p-2.5 rounded-xl' : 'p-4 rounded-2xl']" class="shadow-sm group-hover:scale-105 transition-transform duration-300">
          <component :is="icon" :class="[iconColorClass, compact ? 'w-5 h-5' : 'w-7 h-7']" stroke-width="1.5" />
        </div>

        <!-- Trend Badge -->
        <div v-if="trend" class="flex items-center gap-1.5 rounded-lg text-xs font-semibold" :class="[trendClass, compact ? 'px-2 py-1' : 'px-4 py-2.5 rounded-xl text-sm']">
          <svg v-if="trend > 0" :class="compact ? 'w-3 h-3' : 'w-4 h-4'" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" />
          </svg>
          <svg v-else :class="compact ? 'w-3 h-3' : 'w-4 h-4'" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M12 13a1 1 0 100 2h5a1 1 0 001-1V9a1 1 0 10-2 0v2.586l-4.293-4.293a1 1 0 00-1.414 0L8 9.586 3.707 5.293a1 1 0 00-1.414 1.414l5 5a1 1 0 001.414 0L11 9.414 14.586 13H12z" clip-rule="evenodd" />
          </svg>
          <span>{{ Math.abs(trend) }}%</span>
        </div>
      </div>

      <div :class="compact ? 'space-y-1.5' : 'space-y-4'">
        <div class="flex items-center gap-2">
          <p class="text-xs font-medium text-gray-500 leading-relaxed">{{ title }}</p>
          <Tooltip v-if="tooltip" :content="tooltip" :title="tooltipTitle" />
        </div>
        <div class="flex items-baseline gap-2">
          <h3 class="font-semibold text-primary-dark tracking-tight leading-none" :class="compact ? 'text-2xl' : 'text-4xl'">
            <AnimatedNumber :value="value" :duration="1500" :format="format" />
          </h3>
          <span v-if="suffix" class="font-medium text-gray-400" :class="compact ? 'text-sm' : 'text-lg pb-1'">{{ suffix }}</span>
        </div>
        <p v-if="subtitle" class="text-xs text-gray-400 leading-relaxed">{{ subtitle }}</p>
      </div>

      <!-- Progress Bar (optional) -->
      <div v-if="progress !== undefined" :class="compact ? 'mt-3' : 'mt-6'">
        <div class="flex items-center justify-between mb-1.5">
          <span class="text-xs font-medium text-gray-500">Progres</span>
          <span class="text-xs font-semibold text-primary-dark">{{ progress }}%</span>
        </div>
        <div class="w-full bg-surface/50 rounded-full overflow-hidden" :class="compact ? 'h-1.5' : 'h-2.5'">
          <div
            :class="progressBarClass"
            class="h-full rounded-full transition-all duration-1000 ease-out"
            :style="{ width: animatedProgress + '%' }"
          ></div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import AnimatedNumber from './AnimatedNumber.vue';
import Tooltip from './Tooltip.vue';

const props = defineProps({
  title: String,
  value: [Number, String],
  format: {
    type: String,
    default: 'number',
  },
  suffix: String,
  subtitle: String,
  icon: [Object, Function],
  color: {
    type: String,
    default: 'primary',
  },
  trend: Number,
  progress: Number,
  tooltip: String,
  tooltipTitle: String,
  compact: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['click']);

const animatedProgress = ref(0);

const colorMap = {
  primary: {
    iconBg: 'bg-primary/10',
    iconColor: 'text-primary',
    progressBar: 'bg-primary',
  },
  blue: {
    iconBg: 'bg-blue-50',
    iconColor: 'text-blue-600',
    progressBar: 'bg-blue-500',
  },
  green: {
    iconBg: 'bg-green-50',
    iconColor: 'text-green-600',
    progressBar: 'bg-green-500',
  },
  purple: {
    iconBg: 'bg-purple-50',
    iconColor: 'text-purple-600',
    progressBar: 'bg-purple-500',
  },
  orange: {
    iconBg: 'bg-orange-50',
    iconColor: 'text-orange-600',
    progressBar: 'bg-orange-500',
  },
  indigo: {
    iconBg: 'bg-surface/50',
    iconColor: 'text-primary',
    progressBar: 'bg-primary',
  },
};

const iconBgClass = computed(() => colorMap[props.color]?.iconBg || colorMap.primary.iconBg);
const iconColorClass = computed(() => colorMap[props.color]?.iconColor || colorMap.primary.iconColor);
const progressBarClass = computed(() => colorMap[props.color]?.progressBar || colorMap.primary.progressBar);

const trendClass = computed(() => {
  if (props.trend > 0) {
    return 'bg-green-50 text-green-600';
  } else if (props.trend < 0) {
    return 'bg-primary/10 text-primary-dark';
  }
  return 'bg-gray-50 text-gray-600';
});

onMounted(() => {
  if (props.progress !== undefined) {
    setTimeout(() => {
      animatedProgress.value = props.progress;
    }, 100);
  }
});
</script>
