<template>
  <div class="group relative inline-flex items-center">
    <!-- Trigger Element -->
    <slot name="trigger">
      <svg class="w-4 h-4 text-gray-400 cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    </slot>

    <!-- Tooltip Content -->
    <div
      class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block w-64 p-3 bg-gray-900 text-white text-xs rounded-lg z-50 shadow-xl"
      :class="[
        position === 'top' ? 'bottom-full mb-2' : '',
        position === 'bottom' ? 'top-full mt-2' : '',
        position === 'left' ? 'right-full mr-2 top-1/2 -translate-y-1/2 translate-x-0' : '',
        position === 'right' ? 'left-full ml-2 top-1/2 -translate-y-1/2 translate-x-0' : '',
      ]"
    >
      <!-- Title -->
      <div v-if="title" class="font-semibold mb-1 text-white">{{ title }}</div>

      <!-- Content -->
      <div class="text-gray-200 leading-relaxed">
        <slot>{{ content }}</slot>
      </div>

      <!-- Arrow -->
      <div
        class="absolute left-1/2 -translate-x-1/2 border-4 border-transparent"
        :class="[
          position === 'top' ? 'top-full -mt-1 border-t-gray-900' : '',
          position === 'bottom' ? 'bottom-full -mb-1 border-b-gray-900' : '',
          position === 'left' ? 'left-full -ml-1 border-l-gray-900 top-1/2 -translate-y-1/2' : '',
          position === 'right' ? 'right-full -mr-1 border-r-gray-900 top-1/2 -translate-y-1/2' : '',
        ]"
      ></div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  content: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    default: '',
  },
  position: {
    type: String,
    default: 'top', // top, bottom, left, right
  },
});
</script>
