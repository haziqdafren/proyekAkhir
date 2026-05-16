<template>
  <DashboardLayout>
    <div class="h-full flex flex-col px-6 py-3 gap-2 overflow-hidden">

      <!-- Page Header -->
      <div class="bg-primary-dark rounded-2xl px-5 py-2.5 shadow-card flex items-center justify-between flex-shrink-0">
        <div>
          <h1 class="text-base font-bold text-white leading-tight">Ekspor Laporan</h1>
          <p class="text-xs text-white/60 mt-0.5">Unduh data prediksi dalam format yang Anda butuhkan</p>
        </div>
      </div>

      <!-- No predictions warning -->
      <div v-if="availablePredictions.length === 0" class="bg-white rounded-2xl border border-amber-200 p-4 flex items-center gap-3 flex-shrink-0">
        <div class="w-8 h-8 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <p class="text-sm text-amber-700">Belum ada data prediksi. Buat prediksi di <a href="/predictions" class="font-bold underline">halaman Prediksi</a> terlebih dahulu.</p>
      </div>

      <!-- 2-column layout -->
      <div class="grid grid-cols-[1fr_1.4fr] gap-2 items-start">

        <!-- LEFT COLUMN: Template + Format -->
        <div class="flex flex-col gap-2 min-h-0 items-start">

          <!-- Report type tabs (vertical) -->
          <div class="bg-white rounded-2xl border border-surface/30 shadow-card-md p-1 flex flex-col gap-0.5 flex-shrink-0 w-full">
            <button
              v-for="t in exportTemplates"
              :key="t.id"
              @click="selectTemplate(t.id)"
              class="w-full px-3 py-2 rounded-xl text-sm font-semibold transition-all text-left"
              :class="selectedTemplateId === t.id
                ? 'bg-primary text-white shadow-sm'
                : 'text-gray-500 hover:bg-surface/50 hover:text-primary-dark'"
            >
              {{ t.name }}
            </button>
          </div>

          <!-- Format File -->
          <div v-if="selectedTemplate" class="bg-white rounded-2xl border border-surface/30 shadow-card-md overflow-hidden flex-shrink-0 w-full">
            <div class="px-4 py-2.5 border-b border-surface/20">
              <h2 class="text-xs font-bold text-primary-dark uppercase tracking-wide">Format File</h2>
            </div>
            <div class="p-2 flex gap-1.5">
              <button
                v-for="fmt in selectedTemplate.format"
                :key="fmt"
                @click="exportConfig.format = fmt"
                class="flex-1 py-1.5 rounded-xl text-sm font-semibold border-2 transition-all"
                :class="exportConfig.format === fmt
                  ? 'border-primary bg-primary text-white'
                  : 'border-surface/40 text-gray-600 hover:border-primary/30'"
              >
                {{ fmt === 'excel' ? 'Excel' : fmt === 'pdf' ? 'PDF' : fmt.toUpperCase() }}
              </button>
            </div>
          </div>

          <!-- Empty state -->
          <div v-if="!selectedTemplate" class="bg-white rounded-2xl border border-surface/30 shadow-card-md w-full py-6 flex flex-col items-center justify-center gap-2 text-center px-4">
            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672L13.684 16.6m0 0l-2.51 2.225.569-9.47 5.227 7.917-3.286-.672zM12 2.25V4.5m5.834.166l-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243l-1.59-1.59" />
            </svg>
            <p class="text-xs font-semibold text-gray-400">Pilih jenis laporan</p>
          </div>

        </div>

        <!-- RIGHT COLUMN: Periode + Tipe Kamar + Ringkasan -->
        <div class="flex flex-col gap-2">

          <template v-if="selectedTemplate">

            <!-- Pilih Prediksi -->
            <div class="bg-white rounded-2xl border border-surface/30 shadow-card-md overflow-hidden flex-shrink-0">
              <div class="px-4 py-2 border-b border-surface/20 flex items-center justify-between">
                <h2 class="text-xs font-bold text-primary-dark uppercase tracking-wide">Pilih Periode Prediksi</h2>
                <span v-if="exportConfig.selected_predictions.length > 0" class="px-2 py-0.5 bg-primary/10 text-primary text-xs font-bold rounded-lg">
                  {{ exportConfig.selected_predictions.length }} dipilih
                </span>
              </div>

              <div v-if="filteredPredictions.length > 0">
                <!-- Select all -->
                <label class="flex items-center gap-2 px-4 py-1.5 bg-gray-50 border-b border-surface/20 cursor-pointer hover:bg-gray-100 transition-colors">
                  <input type="checkbox" :checked="isAllPredictionsSelected" @change="toggleAllPredictions"
                    class="w-3 h-3 rounded border-gray-300 text-primary focus:ring-primary/30" />
                  <span class="text-xs font-semibold text-primary-dark">Pilih Semua ({{ filteredPredictions.length }} periode)</span>
                </label>

                <!-- Prediction list — max height so it scrolls if many months -->
                <div class="max-h-48 overflow-y-auto divide-y divide-surface/10">
                  <label
                    v-for="prediction in filteredPredictions"
                    :key="`${prediction.month}-${prediction.model_type}`"
                    class="flex items-center gap-2 px-4 py-1.5 cursor-pointer hover:bg-surface/20 transition-colors"
                    :class="isPredictionSelected(prediction) ? 'bg-primary/5' : ''"
                  >
                    <input type="checkbox" :value="getPredictionKey(prediction)" v-model="exportConfig.selected_predictions"
                      class="w-3 h-3 rounded border-gray-300 text-primary focus:ring-primary/30 flex-shrink-0" />
                    <span class="text-xs font-medium text-primary-dark truncate">{{ prediction.label }}</span>
                    <span class="text-[10px] text-gray-400 ml-auto flex-shrink-0 bg-surface/50 px-1.5 py-0.5 rounded-md">{{ prediction.model_type === 'single' ? 'Overall' : 'Per Kamar' }}</span>
                  </label>
                </div>
              </div>

              <div v-else class="px-4 py-4 text-center text-xs text-gray-400">
                Tidak ada prediksi tersedia untuk jenis laporan ini
              </div>
            </div>

            <!-- Tipe Kamar -->
            <div v-if="roomTypes.length > 0" class="bg-white rounded-2xl border border-surface/30 shadow-card-md overflow-hidden flex-shrink-0">
              <div class="px-4 py-2.5 border-b border-surface/20">
                <h2 class="text-xs font-bold text-primary-dark uppercase tracking-wide">Tipe Kamar <span class="text-[10px] font-normal text-gray-400 ml-1">— opsional, kosong = semua</span></h2>
              </div>
              <div class="px-3 py-2 grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                <label
                  v-for="roomType in roomTypes"
                  :key="roomType.id"
                  class="flex items-center gap-2 px-3 py-2 border-2 rounded-xl cursor-pointer transition-all"
                  :class="exportConfig.room_types.includes(roomType.id)
                    ? 'border-primary bg-primary/5'
                    : 'border-surface/30 hover:border-primary/30'"
                >
                  <input
                    type="checkbox"
                    :value="roomType.id"
                    v-model="exportConfig.room_types"
                    class="w-3.5 h-3.5 rounded border-gray-300 text-primary focus:ring-primary/30"
                  />
                  <span class="text-xs font-medium text-gray-700">{{ roomType.name }}</span>
                </label>
              </div>
            </div>

            <!-- Export Summary + Button -->
            <div class="bg-white rounded-2xl border border-surface/30 shadow-card-md px-4 py-3 flex items-center gap-4 flex-shrink-0">
              <div class="flex-1 min-w-0">
                <p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide mb-0.5">Ringkasan Ekspor</p>
                <p class="text-xs font-semibold text-primary-dark truncate">
                  {{ selectedTemplate?.name }}
                  <span class="text-gray-400 font-normal"> · {{ exportConfig.format === 'excel' ? 'Excel (.xlsx)' : exportConfig.format === 'pdf' ? 'PDF (.pdf)' : exportConfig.format.toUpperCase() }}</span>
                  <span class="text-gray-400 font-normal"> · {{ exportConfig.selected_predictions.length }} periode</span>
                </p>
              </div>
              <button
                @click="handleExport"
                :disabled="isExporting || exportConfig.selected_predictions.length === 0"
                class="flex-shrink-0 flex items-center gap-2 px-5 py-2.5 bg-primary hover:bg-primary-dark text-white font-bold rounded-xl shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed text-sm"
              >
                <svg v-if="!isExporting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ isExporting ? 'Mengunduh...' : 'Ekspor Sekarang' }}
              </button>
            </div>

          </template>

          <!-- Empty state kanan -->
          <div v-if="!selectedTemplate" class="bg-white rounded-2xl border border-surface/30 shadow-card-md py-10 flex items-center justify-center">
            <p class="text-xs text-gray-400">Pilih jenis laporan di sebelah kiri</p>
          </div>

        </div>
      </div>

    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useToast } from 'vue-toastification';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import axios from 'axios';

const toast = useToast();

const props = defineProps({
  roomTypes: { type: Array, default: () => [] },
  availablePredictions: { type: Array, default: () => [] },
  exportTemplates: { type: Array, default: () => [] },
});

const selectedTemplateId = ref('');
const isExporting = ref(false);

const exportConfig = ref({
  format: 'excel',
  selected_predictions: [],
  room_types: [],
});

const selectedTemplate = computed(() =>
  props.exportTemplates.find(t => t.id === selectedTemplateId.value) || null
);

const selectTemplate = (id) => {
  selectedTemplateId.value = id;
  if (selectedTemplate.value) {
    exportConfig.value.format = selectedTemplate.value.format[0];
  }
  exportConfig.value.selected_predictions = [];
  exportConfig.value.room_types = [];
};

const filteredPredictions = computed(() => {
  if (!selectedTemplate.value) return props.availablePredictions || [];
  const id = selectedTemplate.value.id;
  if (id === 'prediction_single') return (props.availablePredictions || []).filter(p => p.model_type === 'single');
  if (id === 'prediction_multi')  return (props.availablePredictions || []).filter(p => p.model_type === 'multi');
  return props.availablePredictions || [];
});

const getPredictionKey = (prediction) => `${prediction.month}|${prediction.model_type}`;

const isPredictionSelected = (prediction) =>
  exportConfig.value.selected_predictions.includes(getPredictionKey(prediction));

const isAllPredictionsSelected = computed(() => {
  if (filteredPredictions.value.length === 0) return false;
  return exportConfig.value.selected_predictions.length === filteredPredictions.value.length;
});

const toggleAllPredictions = (event) => {
  exportConfig.value.selected_predictions = event.target.checked
    ? filteredPredictions.value.map(p => getPredictionKey(p))
    : [];
};

const handleExport = () => {
  if (!selectedTemplate.value) {
    toast.warning('Silakan pilih jenis laporan terlebih dahulu');
    return;
  }
  if (exportConfig.value.selected_predictions.length === 0) {
    toast.warning('Silakan pilih minimal satu data prediksi');
    return;
  }

  const selectedPredictionData = exportConfig.value.selected_predictions.map(key => {
    const [month, modelType] = key.split('|');
    const prediction = filteredPredictions.value.find(p => p.month === month && p.model_type === modelType);
    return prediction
      ? { month: prediction.month, model_type: prediction.model_type, start_date: prediction.start_date, end_date: prediction.end_date }
      : null;
  }).filter(Boolean);

  const exportData = {
    template_id: selectedTemplate.value.id,
    format: exportConfig.value.format,
    room_types: exportConfig.value.room_types.length > 0 ? exportConfig.value.room_types : null,
    predictions: selectedPredictionData,
  };

  isExporting.value = true;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!csrfToken) {
    toast.error('CSRF token tidak ditemukan. Silakan refresh halaman.');
    isExporting.value = false;
    return;
  }

  axios.post('/export/generate', exportData, { responseType: 'blob' })
    .then((response) => {
      const contentDisposition = response.headers['content-disposition'];
      let filename = 'export.xlsx';
      if (contentDisposition) {
        const match = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
        if (match?.[1]) filename = match[1].replace(/['"]/g, '');
      }
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', filename);
      document.body.appendChild(link);
      link.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(link);
      isExporting.value = false;
      toast.success('Laporan berhasil diunduh!');
    })
    .catch((error) => {
      isExporting.value = false;
      if (error.response?.data instanceof Blob) {
        const reader = new FileReader();
        reader.onload = () => {
          try { toast.error(JSON.parse(reader.result).message || 'Terjadi kesalahan saat mengunduh laporan'); }
          catch { toast.error('Terjadi kesalahan saat mengunduh laporan'); }
        };
        reader.readAsText(error.response.data);
      } else {
        toast.error(error.response?.data?.message || 'Terjadi kesalahan saat mengunduh laporan');
      }
    });
};
</script>
