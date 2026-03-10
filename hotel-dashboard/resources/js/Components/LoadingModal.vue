<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
      >
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-gray-900/75 backdrop-blur-sm transition-opacity"></div>

        <!-- Modal Content -->
        <div class="flex min-h-full items-center justify-center p-4">
          <div class="relative transform overflow-hidden rounded-3xl bg-white px-8 py-12 text-center shadow-2xl transition-all sm:w-full sm:max-w-lg">
            <!-- Animated Spinner -->
            <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center">
              <div class="relative">
                <!-- Outer ring -->
                <div class="h-20 w-20 rounded-full border-4 border-primary/20"></div>
                <!-- Spinning ring -->
                <div class="absolute top-0 left-0 h-20 w-20 animate-spin rounded-full border-4 border-transparent border-t-primary border-l-primary"></div>
                <!-- Inner dot -->
                <div class="absolute top-1/2 left-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary animate-pulse"></div>
              </div>
            </div>

            <!-- Title -->
            <h3 class="text-2xl font-semibold text-primary-dark mb-3" id="modal-title">
              {{ title }}
            </h3>

            <!-- Message -->
            <p class="text-gray-600 mb-6">
              {{ message }}
            </p>

            <!-- Slot for additional content -->
            <div v-if="$slots.content">
              <slot name="content"></slot>
            </div>

            <!-- Progress dots (only show if no custom content) -->
            <div v-else class="flex items-center justify-center gap-2">
              <div class="h-2 w-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0s"></div>
              <div class="h-2 w-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.2s"></div>
              <div class="h-2 w-2 rounded-full bg-primary animate-bounce" style="animation-delay: 0.4s"></div>
            </div>

            <!-- Tips (only show if no custom content) -->
            <p v-if="!$slots.content" class="mt-6 text-xs text-gray-400">
              {{ tip }}
            </p>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: 'Sedang Memproses',
  },
  message: {
    type: String,
    default: 'Mohon tunggu sebentar...',
  },
});

const tips = [
  'Model LSTM sedang menganalisis data historis...',
  'Proses prediksi membutuhkan waktu beberapa detik...',
  'Sedang menghitung pola okupansi...',
  'Menggunakan 6 bulan data terakhir untuk prediksi...',
];

const tip = computed(() => {
  return tips[Math.floor(Math.random() * tips.length)];
});
</script>
