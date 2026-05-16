<template>
  <span class="inline-flex items-center" @mouseenter="onEnter" @mouseleave="onLeave">
    <!-- Trigger -->
    <button
      ref="triggerRef"
      type="button"
      class="w-4 h-4 rounded-full bg-gray-200 hover:bg-primary/20 text-gray-500 hover:text-primary transition-colors flex items-center justify-center flex-shrink-0 focus:outline-none"
      :aria-label="title"
    >
      <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
      </svg>
    </button>

    <!--
      Teleport to body so the tooltip is a direct child of <body>.
      This escapes ALL overflow:hidden, transform, and stacking contexts
      from ancestor elements. The fixed positioning then works relative
      to the true viewport.
    -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="show"
          :style="tooltipStyle"
          class="fixed w-56 pointer-events-none"
          style="z-index: 99999;"
        >
          <!-- Arrow (above or below depending on placement) -->
          <div v-if="placement === 'top'" class="flex justify-center">
            <div class="w-2.5 h-2.5 bg-primary-dark rotate-45 mb-[-6px] rounded-sm"></div>
          </div>

          <div class="bg-primary-dark text-white text-xs rounded-xl px-3.5 py-3 shadow-2xl leading-relaxed">
            <p v-if="title" class="font-semibold mb-1">{{ title }}</p>
            <p class="text-white/85">{{ text }}</p>
          </div>

          <!-- Arrow pointing up when tooltip is below trigger -->
          <div v-if="placement === 'bottom'" class="flex justify-center">
            <div class="w-2.5 h-2.5 bg-primary-dark rotate-45 mt-[-6px] rounded-sm"></div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </span>
</template>

<script setup>
import { ref, nextTick } from 'vue';

defineProps({
  title: { type: String, default: '' },
  text:  { type: String, required: true },
});

const show        = ref(false);
const triggerRef  = ref(null);
const tooltipStyle = ref({});
const placement   = ref('top');

const GAP = 10;      // px gap between trigger and tooltip
const TIP_W = 224;   // w-56 = 224px

const onEnter = async () => {
  show.value = true;
  await nextTick();

  if (!triggerRef.value) return;

  const rect = triggerRef.value.getBoundingClientRect();
  const vw   = window.innerWidth;
  const vh   = window.innerHeight;

  // Horizontal: centre on trigger, clamp to viewport
  let left = rect.left + rect.width / 2 - TIP_W / 2;
  if (left < 8)            left = 8;
  if (left + TIP_W > vw - 8) left = vw - TIP_W - 8;

  // Vertical: prefer above, fall back to below if not enough space
  // Estimate tooltip height (~70px) for space check
  const estimatedH = 70;
  if (rect.top - GAP - estimatedH > 8) {
    // Enough room above — place above trigger
    placement.value = 'top';
    tooltipStyle.value = {
      left:      `${left}px`,
      top:       `${rect.top - GAP}px`,
      transform: 'translateY(-100%)',
    };
  } else {
    // Not enough room above — place below trigger
    placement.value = 'bottom';
    tooltipStyle.value = {
      left: `${left}px`,
      top:  `${rect.bottom + GAP}px`,
    };
  }
};

const onLeave = () => {
  show.value = false;
};
</script>
