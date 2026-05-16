<template>
  <div v-if="totalPages > 1" class="flex items-center justify-between gap-4 flex-wrap">
    <!-- Count label -->
    <p class="text-xs text-gray-400">
      Menampilkan <span class="font-semibold text-gray-600">{{ rangeStart }}–{{ rangeEnd }}</span> dari <span class="font-semibold text-gray-600">{{ total }}</span> {{ itemLabel }}
    </p>

    <!-- Page controls -->
    <div class="flex items-center gap-1">
      <!-- Prev -->
      <button
        @click="$emit('change', currentPage - 1)"
        :disabled="currentPage === 1"
        class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-medium transition-all disabled:opacity-30 disabled:cursor-not-allowed"
        :class="currentPage === 1 ? 'text-gray-300' : 'text-gray-600 hover:bg-surface hover:text-primary-dark'"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>

      <!-- Page numbers -->
      <template v-for="p in visiblePages" :key="p">
        <span v-if="p === '...'" class="w-8 h-8 flex items-center justify-center text-xs text-gray-400">···</span>
        <button
          v-else
          @click="$emit('change', p)"
          class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-medium transition-all"
          :class="p === currentPage
            ? 'bg-primary text-white font-bold shadow-sm'
            : 'text-gray-600 hover:bg-surface hover:text-primary-dark'"
        >{{ p }}</button>
      </template>

      <!-- Next -->
      <button
        @click="$emit('change', currentPage + 1)"
        :disabled="currentPage === totalPages"
        class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-medium transition-all disabled:opacity-30 disabled:cursor-not-allowed"
        :class="currentPage === totalPages ? 'text-gray-300' : 'text-gray-600 hover:bg-surface hover:text-primary-dark'"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  currentPage: { type: Number, required: true },
  totalPages: { type: Number, required: true },
  total: { type: Number, required: true },
  perPage: { type: Number, required: true },
  itemLabel: { type: String, default: 'item' },
});

defineEmits(['change']);

const rangeStart = computed(() => (props.currentPage - 1) * props.perPage + 1);
const rangeEnd = computed(() => Math.min(props.currentPage * props.perPage, props.total));

// Show at most 7 page buttons with ellipsis
const visiblePages = computed(() => {
  const total = props.totalPages;
  const cur = props.currentPage;
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);

  const pages = [];
  if (cur <= 4) {
    pages.push(1, 2, 3, 4, 5, '...', total);
  } else if (cur >= total - 3) {
    pages.push(1, '...', total - 4, total - 3, total - 2, total - 1, total);
  } else {
    pages.push(1, '...', cur - 1, cur, cur + 1, '...', total);
  }
  return pages;
});
</script>
