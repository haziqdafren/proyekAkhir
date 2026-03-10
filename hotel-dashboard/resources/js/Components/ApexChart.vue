<template>
  <div ref="chartRef" class="w-full"></div>
</template>

<script setup>
import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
import ApexCharts from 'apexcharts';

const props = defineProps({
  options: {
    type: Object,
    required: true,
  },
  series: {
    type: Array,
    required: true,
  },
  type: {
    type: String,
    default: 'line',
  },
  height: {
    type: [String, Number],
    default: 350,
  },
});

const chartRef = ref(null);
let chart = null;

onMounted(() => {
  const chartOptions = {
    ...props.options,
    series: props.series,
    chart: {
      ...props.options.chart,
      type: props.type,
      height: props.height,
      fontFamily: 'Inter, sans-serif',
      toolbar: {
        show: true,
        tools: {
          download: true,
          selection: true,
          zoom: true,
          zoomin: true,
          zoomout: true,
          pan: true,
          reset: true,
        },
      },
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800,
        animateGradually: {
          enabled: true,
          delay: 150,
        },
        dynamicAnimation: {
          enabled: true,
          speed: 350,
        },
      },
    },
  };

  chart = new ApexCharts(chartRef.value, chartOptions);
  chart.render();
});

watch(
  () => [props.series, props.options],
  () => {
    if (chart) {
      chart.updateOptions({
        ...props.options,
        series: props.series,
      });
    }
  },
  { deep: true }
);

onBeforeUnmount(() => {
  if (chart) {
    chart.destroy();
  }
});
</script>
