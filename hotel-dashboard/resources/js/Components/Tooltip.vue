<template>
  <div class="inline-flex items-center" @mouseenter="onEnter" @mouseleave="onLeave">
    <!-- Trigger: slot or default icon -->
    <div ref="triggerRef" class="cursor-help">
      <slot name="trigger">
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </slot>
    </div>

    <!-- Teleport to body — escapes all stacking contexts and overflow:hidden -->
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
          class="fixed w-64 pointer-events-none"
          style="z-index: 99999;"
        >
          <!-- Arrow pointing down (tooltip above trigger) -->
          <div v-if="placementAbove" class="flex" :style="arrowOffset">
            <div class="w-2.5 h-2.5 bg-gray-900 rotate-45 mb-[-5px] rounded-sm"></div>
          </div>

          <div class="bg-gray-900 text-white text-xs rounded-xl px-3.5 py-3 shadow-2xl leading-relaxed">
            <p v-if="title" class="font-semibold mb-1 text-white">{{ title }}</p>
            <p class="text-gray-200 leading-relaxed">{{ content }}</p>
            <slot />
          </div>

          <!-- Arrow pointing up (tooltip below trigger) -->
          <div v-if="!placementAbove" class="flex" :style="arrowOffset">
            <div class="w-2.5 h-2.5 bg-gray-900 rotate-45 mt-[-5px] rounded-sm"></div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, nextTick } from 'vue';

defineProps({
  content: { type: String, default: '' },
  title:   { type: String, default: '' },
  position: { type: String, default: 'top' }, // kept for API compat, logic auto-detects
});

const show         = ref(false);
const triggerRef   = ref(null);
const tooltipStyle = ref({});
const arrowOffset  = ref({});
const placementAbove = ref(true);

const TIP_W = 256;  // w-64
const GAP   = 10;

const onEnter = async () => {
  show.value = true;
  await nextTick();

  if (!triggerRef.value) return;

  const rect = triggerRef.value.getBoundingClientRect();
  const vw   = window.innerWidth;

  // Horizontal: centre on trigger, clamp to viewport
  let left = rect.left + rect.width / 2 - TIP_W / 2;
  if (left < 8)              left = 8;
  if (left + TIP_W > vw - 8) left = vw - TIP_W - 8;

  // Arrow position relative to tooltip left edge
  const arrowLeft = Math.max(10, Math.min(rect.left + rect.width / 2 - left - 5, TIP_W - 20));
  arrowOffset.value = { paddingLeft: `${arrowLeft}px` };

  // Vertical: prefer above, fall back to below
  const estimatedH = 70;
  if (rect.top - GAP - estimatedH > 8) {
    placementAbove.value = true;
    tooltipStyle.value = {
      left:      `${left}px`,
      top:       `${rect.top - GAP}px`,
      transform: 'translateY(-100%)',
    };
  } else {
    placementAbove.value = false;
    tooltipStyle.value = {
      left: `${left}px`,
      top:  `${rect.bottom + GAP}px`,
    };
  }
};

const onLeave = () => { show.value = false; };
</script>
