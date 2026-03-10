<template>
  <DashboardLayout>
    <div class="p-8 space-y-8 max-w-[1600px] mx-auto">
      <!-- Header -->
      <div class="bg-gradient-to-br from-primary to-primary-dark rounded-3xl shadow-md overflow-hidden">
        <div class="relative px-10 py-12">
          <div class="absolute inset-0 bg-grid-white/[0.02] bg-[size:32px_32px]"></div>
          <div class="absolute right-0 top-0 -mt-8 -mr-20 h-72 w-72 rounded-full bg-white/5 blur-3xl"></div>

          <div class="relative">
            <div class="flex items-center space-x-3 mb-4">
              <div class="p-3 bg-white/10 rounded-2xl">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <h1 class="text-3xl font-semibold text-white">Ekspor Laporan</h1>
            </div>
            <p class="text-white/80 text-base font-normal max-w-2xl">
              Export data hasil prediksi okupansi dalam format Excel, CSV, atau PDF untuk analisis lebih lanjut
            </p>
          </div>
        </div>
      </div>

      <!-- Export Templates -->
      <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
        <div class="px-8 py-6 border-b border-surface/30">
          <h2 class="text-xl font-semibold text-primary-dark">Template Laporan</h2>
          <p class="text-sm text-gray-500 mt-1">Pilih jenis laporan yang ingin di-export</p>
        </div>

        <div class="p-8">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
              v-for="template in exportTemplates"
              :key="template.id"
              @click="selectTemplate(template)"
              class="group relative p-6 rounded-2xl border-2 border-surface/40 hover:border-primary/50 transition-all duration-300 hover:shadow-lg hover:-translate-y-1 cursor-pointer"
              :class="{ 'border-primary bg-primary/5': selectedTemplate?.id === template.id }"
            >
              <div class="flex items-start justify-between mb-5">
                <div
                  class="p-4 rounded-2xl transition-transform duration-300 group-hover:scale-110"
                  :class="selectedTemplate?.id === template.id ? 'bg-primary/10' : 'bg-surface/50'"
                >
                  <svg
                    class="w-7 h-7 transition-colors"
                    :class="selectedTemplate?.id === template.id ? 'text-primary' : 'text-gray-600'"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                  >
                    <path v-if="template.icon === 'chart'" stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    <path v-else-if="template.icon === 'document'" stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    <path v-else-if="template.icon === 'documents'" stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                  </svg>
                </div>

                <div v-if="selectedTemplate?.id === template.id" class="p-1.5 bg-primary rounded-full">
                  <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </div>
              </div>

              <div class="space-y-2">
                <h3 class="text-lg font-semibold text-primary-dark">{{ template.name }}</h3>
                <p class="text-sm text-gray-600 leading-relaxed">{{ template.description }}</p>
              </div>

              <div class="mt-5 pt-5 border-t border-surface/30">
                <div class="flex flex-wrap gap-2">
                  <span
                    v-for="format in template.format"
                    :key="format"
                    class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-semibold bg-surface/40 text-gray-700"
                  >
                    {{ format.toUpperCase() }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Export Configuration -->
      <div v-if="selectedTemplate" class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
        <div class="px-8 py-6 border-b border-surface/30">
          <h2 class="text-xl font-semibold text-primary-dark">Konfigurasi Export</h2>
          <p class="text-sm text-gray-500 mt-1">Sesuaikan parameter export sesuai kebutuhan</p>
        </div>

        <div class="p-8">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left Column -->
            <div class="space-y-6">
              <!-- Format Selection -->
              <div class="space-y-3">
                <label class="text-sm font-semibold text-primary-dark">Format File</label>
                <div class="grid grid-cols-2 gap-3">
                  <button
                    v-for="format in selectedTemplate.format"
                    :key="format"
                    @click="exportConfig.format = format"
                    class="px-5 py-3.5 rounded-2xl border-2 text-sm font-semibold transition-all duration-200"
                    :class="exportConfig.format === format
                      ? 'border-primary bg-primary text-white'
                      : 'border-surface/50 bg-background text-gray-700 hover:border-primary/30'"
                  >
                    {{ format.toUpperCase() }}
                  </button>
                </div>
              </div>

              <!-- Available Predictions Selection -->
              <div class="space-y-3">
                <label class="text-sm font-semibold text-primary-dark">Pilih Data Prediksi</label>
                <p v-if="isMultiSelectMode" class="text-xs text-gray-500 -mt-1">
                  Pilih satu atau lebih prediksi untuk di-export
                </p>
                <div v-if="filteredPredictions.length > 0" class="space-y-2 max-h-64 overflow-y-auto pr-2">
                  <!-- Select All option for multi-select mode -->
                  <label
                    v-if="isMultiSelectMode"
                    class="flex items-center justify-between p-4 rounded-2xl border-2 cursor-pointer transition-all duration-200"
                    :class="isAllPredictionsSelected
                      ? 'border-primary bg-primary/5'
                      : 'border-surface/50 hover:border-primary/30 hover:bg-surface/20'"
                  >
                    <input
                      type="checkbox"
                      :checked="isAllPredictionsSelected"
                      @change="toggleAllPredictions"
                      class="w-5 h-5 rounded-lg border-gray-300 text-primary focus:ring-primary/30"
                    />
                    <div class="ml-3 flex-1">
                      <span class="text-sm font-semibold text-primary-dark">Pilih Semua Prediksi</span>
                      <p class="text-xs text-gray-500 mt-1">{{ filteredPredictions.length }} periode tersedia</p>
                    </div>
                  </label>
                  
                  <!-- Multi-select checkboxes for prediction_report -->
                  <template v-if="isMultiSelectMode">
                    <label
                      v-for="prediction in filteredPredictions"
                      :key="`${prediction.month}-${prediction.model_type}`"
                      class="flex items-center justify-between p-4 rounded-2xl border-2 cursor-pointer transition-all duration-200"
                      :class="isPredictionSelected(prediction)
                        ? 'border-primary bg-primary/5'
                        : 'border-surface/50 hover:border-primary/30 hover:bg-surface/20'"
                    >
                      <input
                        type="checkbox"
                        :value="getPredictionKey(prediction)"
                        v-model="exportConfig.selected_predictions"
                        class="w-5 h-5 rounded-lg border-gray-300 text-primary focus:ring-primary/30"
                      />
                      <div class="ml-3 flex-1">
                        <div class="flex items-center justify-between">
                          <span class="text-sm font-semibold text-primary-dark">{{ prediction.label }}</span>
                          <span class="text-xs font-semibold px-2.5 py-1 rounded-lg"
                            :class="prediction.model_type === 'single' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                          >
                            {{ prediction.model_label }}
                          </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ prediction.count }} prediksi tersedia</p>
                      </div>
                    </label>
                  </template>
                  
                  <!-- Single-select radio for other templates -->
                  <template v-else>
                    <label
                      v-for="prediction in filteredPredictions"
                      :key="`${prediction.month}-${prediction.model_type}`"
                      class="flex items-center justify-between p-4 rounded-2xl border-2 cursor-pointer transition-all duration-200"
                      :class="exportConfig.selected_prediction?.month === prediction.month && exportConfig.selected_prediction?.model_type === prediction.model_type
                        ? 'border-primary bg-primary/5'
                        : 'border-surface/50 hover:border-primary/30 hover:bg-surface/20'"
                    >
                      <input
                        type="radio"
                        name="prediction_selection"
                        :value="prediction"
                        v-model="exportConfig.selected_prediction"
                        class="w-5 h-5 text-primary focus:ring-primary/30"
                      />
                      <div class="ml-3 flex-1">
                        <div class="flex items-center justify-between">
                          <span class="text-sm font-semibold text-primary-dark">{{ prediction.label }}</span>
                          <span class="text-xs font-semibold px-2.5 py-1 rounded-lg"
                            :class="prediction.model_type === 'single' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                          >
                            {{ prediction.model_label }}
                          </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">{{ prediction.count }} prediksi tersedia</p>
                      </div>
                    </label>
                  </template>
                </div>
                <div v-else class="p-6 rounded-2xl bg-orange-50 border border-orange-200">
                  <div class="flex items-start space-x-3">
                    <svg class="w-5 h-5 text-orange-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                      <p class="text-sm font-semibold text-orange-800">Tidak Ada Data Prediksi</p>
                      <p class="text-xs text-orange-600 mt-1">Silakan generate prediksi terlebih dahulu di halaman Detail Prediksi</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Room Type Selection -->
              <div class="space-y-3">
                <label class="text-sm font-semibold text-primary-dark">Tipe Kamar</label>
                <div class="space-y-2">
                  <label class="flex items-center p-4 rounded-2xl border border-surface/50 cursor-pointer hover:bg-surface/20 transition-colors">
                    <input
                      type="checkbox"
                      :checked="isAllRoomsSelected"
                      @change="toggleAllRooms"
                      class="w-5 h-5 rounded-lg border-gray-300 text-primary focus:ring-primary/30"
                    />
                    <span class="ml-3 text-sm font-medium text-gray-700">Semua Tipe Kamar</span>
                  </label>

                  <label
                    v-for="roomType in roomTypes"
                    :key="roomType.id"
                    class="flex items-center p-4 rounded-2xl border border-surface/50 cursor-pointer hover:bg-surface/20 transition-colors"
                  >
                    <input
                      type="checkbox"
                      :value="roomType.id"
                      v-model="exportConfig.room_types"
                      class="w-5 h-5 rounded-lg border-gray-300 text-primary focus:ring-primary/30"
                    />
                    <span class="ml-3 text-sm font-medium text-gray-700">{{ roomType.name }}</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
              <!-- Preview Info -->
              <div class="p-6 rounded-2xl bg-primary/5 border border-primary/20">
                <div class="flex items-start space-x-3">
                  <div class="p-2 bg-primary/10 rounded-xl">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <div class="flex-1">
                    <h4 class="text-sm font-semibold text-primary-dark mb-2">Informasi Export</h4>
                    <div class="space-y-1.5 text-xs text-gray-600">
                      <p><span class="font-medium">Template:</span> {{ selectedTemplate.name }}</p>
                      <p><span class="font-medium">Format:</span> {{ exportConfig.format.toUpperCase() }}</p>
                      <!-- Multi-select info -->
                      <template v-if="isMultiSelectMode">
                        <p><span class="font-medium">Prediksi Dipilih:</span> {{ exportConfig.selected_predictions.length }} periode</p>
                        <p v-if="selectedPredictionsInfo.length > 0" class="ml-2 text-gray-500">
                          {{ selectedPredictionsInfo.slice(0, 3).join(', ') }}
                          <span v-if="selectedPredictionsInfo.length > 3"> +{{ selectedPredictionsInfo.length - 3 }} lainnya</span>
                        </p>
                      </template>
                      <!-- Single-select info -->
                      <template v-else>
                        <p v-if="exportConfig.selected_prediction"><span class="font-medium">Periode:</span> {{ exportConfig.selected_prediction.label }}</p>
                        <p v-if="exportConfig.selected_prediction"><span class="font-medium">Model:</span> {{ exportConfig.selected_prediction.model_label }}</p>
                      </template>
                      <p><span class="font-medium">Kamar:</span> {{ exportConfig.room_types.length > 0 ? exportConfig.room_types.length + ' tipe' : 'Semua' }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Export Button -->
              <button
                @click="handleExport"
                :disabled="isExporting"
                class="w-full px-6 py-4 bg-primary hover:bg-primary-dark text-white font-semibold rounded-2xl shadow-sm hover:shadow-md transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-3"
              >
                <svg v-if="!isExporting" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ isExporting ? 'Membuat Export...' : 'Export Laporan' }}</span>
              </button>
            </div>
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
  roomTypes: {
    type: Array,
    default: () => []
  },
  availablePredictions: {
    type: Array,
    default: () => []
  },
  exportTemplates: {
    type: Array,
    default: () => []
  },
});

const selectedTemplate = ref(null);
const isExporting = ref(false);

const exportConfig = ref({
  format: 'excel',
  selected_prediction: null,       // For single-select templates
  selected_predictions: [],        // For multi-select templates (prediction_report)
  room_types: [],
});

// Filter predictions based on selected template
const filteredPredictions = computed(() => {
  if (!selectedTemplate.value) return props.availablePredictions || [];

  const template = selectedTemplate.value.id;

  if (template === 'prediction_single') {
    return (props.availablePredictions || []).filter(p => p.model_type === 'single');
  } else if (template === 'prediction_multi') {
    return (props.availablePredictions || []).filter(p => p.model_type === 'multi');
  }

  return props.availablePredictions || [];
});

// Check if current template supports multi-selection
// All templates now support multi-selection
const isMultiSelectMode = computed(() => {
  return selectedTemplate.value !== null;
});

const selectTemplate = (template) => {
  selectedTemplate.value = template;
  exportConfig.value.format = template.format[0]; // Set default format
  // Reset selections when switching templates
  exportConfig.value.selected_prediction = null;
  exportConfig.value.selected_predictions = [];
};

// Helper to get a unique key for each prediction
const getPredictionKey = (prediction) => {
  return `${prediction.month}|${prediction.model_type}`;
};

// Check if a prediction is selected (for multi-select)
const isPredictionSelected = (prediction) => {
  return exportConfig.value.selected_predictions.includes(getPredictionKey(prediction));
};

// Check if all predictions are selected
const isAllPredictionsSelected = computed(() => {
  if (filteredPredictions.value.length === 0) return false;
  return exportConfig.value.selected_predictions.length === filteredPredictions.value.length;
});

// Toggle all predictions selection
const toggleAllPredictions = (event) => {
  if (event.target.checked) {
    exportConfig.value.selected_predictions = filteredPredictions.value.map(p => getPredictionKey(p));
  } else {
    exportConfig.value.selected_predictions = [];
  }
};

// Get labels of selected predictions for info display
const selectedPredictionsInfo = computed(() => {
  return exportConfig.value.selected_predictions.map(key => {
    const [month, modelType] = key.split('|');
    const prediction = filteredPredictions.value.find(
      p => p.month === month && p.model_type === modelType
    );
    return prediction ? `${prediction.label} (${prediction.model_label})` : key;
  });
});

const isAllRoomsSelected = computed(() => {
  if (!props.roomTypes || props.roomTypes.length === 0) return false;
  return exportConfig.value.room_types.length === props.roomTypes.length;
});

const toggleAllRooms = (event) => {
  if (event.target.checked) {
    exportConfig.value.room_types = props.roomTypes.map(r => r.id);
  } else {
    exportConfig.value.room_types = [];
  }
};

const handleExport = () => {
  if (!selectedTemplate.value) {
    toast.warning('Silakan pilih template terlebih dahulu');
    return;
  }

  // Validate selection - always use multi-select mode
  if (exportConfig.value.selected_predictions.length === 0) {
    toast.warning('Silakan pilih minimal satu data prediksi yang akan di-export');
    return;
  }

  // Build export data - always use multi-select predictions array
  const selectedPredictionData = exportConfig.value.selected_predictions.map(key => {
    const [month, modelType] = key.split('|');
    const prediction = filteredPredictions.value.find(
      p => p.month === month && p.model_type === modelType
    );
    return prediction ? {
      month: prediction.month,
      model_type: prediction.model_type,
      start_date: prediction.start_date,
      end_date: prediction.end_date,
    } : null;
  }).filter(p => p !== null);

  let exportData = {
    template_id: selectedTemplate.value.id,
    format: exportConfig.value.format,
    room_types: exportConfig.value.room_types.length > 0 ? exportConfig.value.room_types : null,
    predictions: selectedPredictionData,
  };

  isExporting.value = true;

  // Get CSRF token from meta tag
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  if (!csrfToken) {
    toast.error('CSRF token tidak ditemukan. Silakan refresh halaman.');
    isExporting.value = false;
    return;
  }

  // Use Axios for automatic CSRF handling
  axios.post('/export/generate', exportData, {
    responseType: 'blob',
  })
    .then((response) => {
      // Get filename from Content-Disposition header if available
      const contentDisposition = response.headers['content-disposition'];
      let filename = 'export.xlsx';

      if (contentDisposition) {
        const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
        if (filenameMatch && filenameMatch[1]) {
          filename = filenameMatch[1].replace(/['"]/g, '');
        }
      }

      // Create download link
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', filename);
      document.body.appendChild(link);
      link.click();

      // Cleanup
      window.URL.revokeObjectURL(url);
      document.body.removeChild(link);

      isExporting.value = false;
      toast.success('Export berhasil! File telah diunduh.');
    })
    .catch((error) => {
      isExporting.value = false;
      console.error('Export failed:', error);

      // Try to read error message from blob response
      if (error.response && error.response.data instanceof Blob) {
        const reader = new FileReader();
        reader.onload = () => {
          try {
            const errorData = JSON.parse(reader.result);
            toast.error(errorData.message || 'Terjadi kesalahan saat melakukan export');
          } catch (e) {
            toast.error('Terjadi kesalahan saat melakukan export');
          }
        };
        reader.readAsText(error.response.data);
      } else {
        toast.error(error.response?.data?.message || error.message || 'Terjadi kesalahan saat melakukan export');
      }
    });
};
</script>

<style>
.bg-grid-white\/\[0\.02\] {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32' width='32' height='32' fill='none' stroke='rgb(255 255 255 / 0.02)'%3e%3cpath d='M0 .5H31.5V32'/%3e%3c/svg%3e");
}
</style>
