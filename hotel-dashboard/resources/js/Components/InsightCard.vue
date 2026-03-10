<template>
  <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl border-2 border-blue-200 p-6">
    <div class="flex items-start gap-4">
      <!-- Icon -->
      <div class="flex-shrink-0">
        <div
          class="w-12 h-12 rounded-xl flex items-center justify-center"
          :class="iconBgClass"
        >
          <svg class="w-6 h-6" :class="iconColorClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              :d="iconPath"
            />
          </svg>
        </div>
      </div>

      <div class="flex-1 min-w-0">
        <!-- Title -->
        <h3 class="text-base font-semibold text-gray-900 mb-1">
          {{ title }}
        </h3>

        <!-- Description -->
        <p class="text-sm text-gray-700 mb-3 leading-relaxed">
          {{ description }}
        </p>

        <!-- Actions/Recommendations -->
        <div v-if="actions && actions.length > 0" class="space-y-2">
          <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Rekomendasi:</p>
          <ul class="space-y-1.5">
            <li
              v-for="(action, index) in actions"
              :key="index"
              class="flex items-start gap-2 text-sm text-gray-700"
            >
              <span class="text-blue-500 mt-0.5 flex-shrink-0">→</span>
              <span>{{ action }}</span>
            </li>
          </ul>
        </div>

        <!-- Status Badge -->
        <div v-if="status" class="mt-3">
          <span
            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold"
            :class="statusClass"
          >
            <span
              class="w-2 h-2 rounded-full mr-1.5"
              :class="statusDotClass"
            ></span>
            {{ statusText }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  type: {
    type: String,
    default: 'info', // info, success, warning, danger
  },
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  actions: {
    type: Array,
    default: () => [],
  },
  status: {
    type: String,
    default: null, // low, medium, high
  },
});

const iconBgClass = computed(() => {
  const classes = {
    info: 'bg-blue-100',
    success: 'bg-green-100',
    warning: 'bg-yellow-100',
    danger: 'bg-red-100',
  };
  return classes[props.type] || classes.info;
});

const iconColorClass = computed(() => {
  const classes = {
    info: 'text-blue-600',
    success: 'text-green-600',
    warning: 'text-yellow-600',
    danger: 'text-red-600',
  };
  return classes[props.type] || classes.info;
});

const iconPath = computed(() => {
  const paths = {
    info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    danger: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  };
  return paths[props.type] || paths.info;
});

const statusClass = computed(() => {
  const classes = {
    low: 'bg-red-100 text-red-700',
    medium: 'bg-yellow-100 text-yellow-700',
    high: 'bg-green-100 text-green-700',
  };
  return classes[props.status] || '';
});

const statusDotClass = computed(() => {
  const classes = {
    low: 'bg-red-500',
    medium: 'bg-yellow-500',
    high: 'bg-green-500',
  };
  return classes[props.status] || '';
});

const statusText = computed(() => {
  const texts = {
    low: 'Perlu Perhatian',
    medium: 'Stabil',
    high: 'Sangat Baik',
  };
  return texts[props.status] || '';
});
</script>
