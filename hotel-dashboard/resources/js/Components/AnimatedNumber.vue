<template>
  <span>{{ displayValue }}</span>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
  value: {
    type: [Number, String],
    required: true,
  },
  duration: {
    type: Number,
    default: 1000,
  },
  format: {
    type: String,
    default: 'number', // 'number', 'currency', 'percentage'
  },
});

const displayValue = ref('0');
let animationFrame = null;

const formatNumber = (num) => {
  const value = typeof num === 'string' ? parseFloat(num) : num;

  switch (props.format) {
    case 'currency':
      // For large numbers (> 1 billion), use abbreviated format
      if (value >= 1000000000) {
        const billions = value / 1000000000;
        return `Rp ${billions.toFixed(2)} M`;
      }
      // For medium numbers (> 1 million), use abbreviated format
      else if (value >= 1000000) {
        const millions = value / 1000000;
        return `Rp ${millions.toFixed(1)} Jt`;
      }
      // For smaller numbers, use full format
      return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(value);
    case 'percentage':
      return `${value.toFixed(1)}%`;
    default:
      return new Intl.NumberFormat('id-ID').format(value);
  }
};

const animateValue = (start, end, duration) => {
  const startTime = performance.now();
  const startValue = parseFloat(start) || 0;
  const endValue = parseFloat(end) || 0;
  const diff = endValue - startValue;

  const step = (currentTime) => {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);

    // Easing function (ease-out cubic)
    const easeOut = 1 - Math.pow(1 - progress, 3);
    const current = startValue + diff * easeOut;

    displayValue.value = formatNumber(current);

    if (progress < 1) {
      animationFrame = requestAnimationFrame(step);
    } else {
      displayValue.value = formatNumber(endValue);
    }
  };

  if (animationFrame) {
    cancelAnimationFrame(animationFrame);
  }

  animationFrame = requestAnimationFrame(step);
};

watch(
  () => props.value,
  (newValue, oldValue) => {
    animateValue(oldValue || 0, newValue, props.duration);
  }
);

onMounted(() => {
  animateValue(0, props.value, props.duration);
});
</script>
