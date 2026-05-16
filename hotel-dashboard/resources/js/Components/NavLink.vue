<template>
  <Link
    :href="href"
    class="group flex items-center gap-3 text-sm font-medium rounded-xl transition-all duration-200 relative"
    :class="[
      collapsed ? 'px-2 py-2.5 justify-center' : 'px-3 py-2.5',
      active
        ? 'bg-white/15 text-white'
        : 'text-white/55 hover:bg-white/10 hover:text-white'
    ]"
    :title="collapsed ? label : ''"
  >
    <!-- Active left indicator -->
    <span
      v-if="active && !collapsed"
      class="absolute left-0 top-1/2 -translate-y-1/2 w-0.5 h-5 bg-white rounded-r-full"
    ></span>

    <!-- Icon -->
    <svg
      class="flex-shrink-0 transition-colors"
      :class="[
        collapsed ? 'w-5 h-5' : 'w-4 h-4 ',
        active ? 'text-white' : 'text-white/45 group-hover:text-white'
      ]"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
      stroke-width="1.8"
    >
      <path v-if="href === '/dashboard'"        stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
      <path v-else-if="href === '/predictions'" stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
      <path v-else-if="href === '/history'"     stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      <path v-else-if="href === '/export'"      stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      <path v-else-if="href === '/data-upload'" stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
    </svg>

    <slot />
  </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  href: String,
  active: Boolean,
  collapsed: {
    type: Boolean,
    default: false,
  },
});

const labelMap = {
  '/dashboard':   'Dashboard',
  '/predictions': 'Prediksi',
  '/history':     'Riwayat Data',
  '/export':      'Ekspor Laporan',
  '/data-upload': 'Kelola Data',
};

const label = labelMap[props.href] ?? '';
</script>
