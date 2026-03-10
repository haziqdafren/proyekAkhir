<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useToast } from 'vue-toastification';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import LoadingModal from '@/Components/LoadingModal.vue';
import DataUploadGuide from '@/Components/DataUploadGuide.vue';
import axios from 'axios';

const toast = useToast();

const props = defineProps({
  uploads: Array,
  modelVersions: Array,
  champions: Object,
  retrainingStatus: {
    type: Object,
    default: () => ({
      single: {},
      multi: {},
    }),
  },
});

// State
const uploading = ref(false);
const uploadProgress = ref(0);
const selectedFile = ref(null);
const dragOver = ref(false);
const pollingInterval = ref(null);
const localUploads = ref([...props.uploads]);
const retraining = ref(false);
const showUploadHistory = ref(true);
const showModelVersions = ref(true);
const processingUploadId = ref(null);
const selectedUploadDetails = ref(null);
const showDetailsModal = ref(false);
const modelTypeFilter = ref('all'); // 'all', 'single', 'multi'
const showConfirmationModal = ref(false);
const filePreview = ref(null);
const validating = ref(false);
const showCompletionModal = ref(false);
const completionResults = ref(null);
const showDeleteModal = ref(false);
const deleteTargetId = ref(null);

// Computed: Filter model versions by type
const filteredModelVersions = computed(() => {
  if (modelTypeFilter.value === 'all') {
    return props.modelVersions;
  }
  return props.modelVersions.filter(v => v.model_type === modelTypeFilter.value);
});

// Computed: Group models by type
const singleModels = computed(() => props.modelVersions.filter(v => v.model_type === 'single'));
const multiModels = computed(() => props.modelVersions.filter(v => v.model_type === 'multi'));

// Loading modal state
const loadingModal = ref({
  show: false,
  title: 'Memproses Data',
  message: 'Mohon tunggu sebentar...',
  status: '',
  progress: '',
});

// File handling
const handleFileSelect = (event) => {
  const file = event.target.files[0];
  if (file) {
    validateAndSetFile(file);
  }
};

const handleDrop = (event) => {
  event.preventDefault();
  dragOver.value = false;
  const file = event.dataTransfer.files[0];
  if (file) {
    validateAndSetFile(file);
  }
};

const validateAndSetFile = (file) => {
  // Check file type
  const validExtensions = ['.xls', '.xlsx'];
  const ext = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
  
  if (!validExtensions.includes(ext)) {
    toast.error('Format file tidak valid. Gunakan file .xls atau .xlsx');
    return;
  }
  
  // Check file size (max 10MB)
  if (file.size > 10 * 1024 * 1024) {
    toast.error('Ukuran file terlalu besar. Maksimal 10MB');
    return;
  }
  
  selectedFile.value = file;
  toast.success(`File "${file.name}" siap untuk diupload`);
};

const clearFile = () => {
  selectedFile.value = null;
};

const formatFileSize = (bytes) => {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

// Step 1: Validate file and show preview
const validateFile = async () => {
  if (!selectedFile.value) return;

  validating.value = true;

  const formData = new FormData();
  formData.append('file', selectedFile.value);

  try {
    const response = await axios.post('/data-upload/validate', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    if (response.data.success) {
      // Show confirmation modal with file preview
      filePreview.value = response.data.summary;
      showConfirmationModal.value = true;
    } else {
      toast.error(response.data.message || 'File tidak valid');
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Gagal memvalidasi file');
  } finally {
    validating.value = false;
  }
};

// Step 2: Actually upload and process the file (after confirmation)
const confirmAndUpload = async () => {
  if (!selectedFile.value) return;

  showConfirmationModal.value = false;
  uploading.value = true;
  uploadProgress.value = 0;

  const formData = new FormData();
  formData.append('file', selectedFile.value);

  try {
    const response = await axios.post('/data-upload/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      onUploadProgress: (progressEvent) => {
        uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
      },
    });

    if (response.data.success) {
      const uploadId = response.data.upload_id;
      console.log('Upload successful, ID:', uploadId);

      toast.success('Memproses data...');
      const uploadedFileName = selectedFile.value?.name || 'File';
      selectedFile.value = null;
      filePreview.value = null;

      // Add new upload to list
      localUploads.value.unshift({
        id: uploadId,
        file_name: uploadedFileName,
        status: 'pending',
        status_label: 'Menunggu',
        status_color: 'gray',
        records_parsed: 0,
        records_inserted: 0,
        created_at: new Date().toLocaleString('id-ID'),
      });

      // Start polling for status
      processingUploadId.value = uploadId;
      console.log('Starting polling for upload ID:', uploadId);
      startPolling(uploadId);
    } else {
      toast.error(response.data.message || 'Upload gagal');
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Terjadi kesalahan saat upload');
  } finally {
    uploading.value = false;
    uploadProgress.value = 0;
  }
};

// Cancel upload
const cancelUpload = () => {
  showConfirmationModal.value = false;
  filePreview.value = null;
};

// Poll for upload status
const startPolling = (uploadId) => {
  pollStatus(uploadId);
  
  pollingInterval.value = setInterval(() => {
    pollStatus(uploadId);
  }, 3000);
};

const pollStatus = async (uploadId) => {
  try {
    const response = await axios.get(`/data-upload/status/${uploadId}`);
    const data = response.data;

    // If upload doesn't exist or was deleted
    if (!data || !data.id) {
      console.error('Upload not found:', uploadId);
      clearInterval(pollingInterval.value);
      pollingInterval.value = null;
      processingUploadId.value = null;
      loadingModal.value.show = false;
      toast.error('Upload tidak ditemukan. Halaman akan di-refresh.');
      setTimeout(() => window.location.reload(), 2000);
      return;
    }

    // Update local upload
    const index = localUploads.value.findIndex(u => u.id === uploadId);
    if (index !== -1) {
      localUploads.value[index] = {
        ...localUploads.value[index],
        status: data.status,
        status_label: data.status_label,
        status_color: data.status_color,
        records_parsed: data.records_parsed,
        records_inserted: data.records_inserted,
        error_message: data.error_message,
        processed_at: data.processed_at,
      };
    }

    // Update loading modal with current status
    if (data.status !== 'completed' && data.status !== 'failed') {
      loadingModal.value.show = true;
      loadingModal.value.title = 'Memproses Upload';

      if (data.status === 'parsing') {
        loadingModal.value.message = 'Sedang membaca sheet-sheet Excel dan mengekstrak data okupansi harian...';
        loadingModal.value.status = 'Menganalisis File Excel';
        loadingModal.value.progress = data.records_parsed
          ? `${data.records_parsed} hari data telah dibaca`
          : 'Membaca struktur file...';
      } else if (data.status === 'inserting') {
        loadingModal.value.message = 'Data okupansi sedang disimpan ke database untuk training model...';
        loadingModal.value.status = 'Menyimpan ke Database';
        const total = data.records_parsed || 0;
        const inserted = data.records_inserted || 0;
        loadingModal.value.progress = total > 0
          ? `${inserted} dari ${total * 4} records (${Math.round((inserted / (total * 4)) * 100)}%)`
          : `${inserted} records disimpan`;
      } else if (data.status === 'retraining') {
        loadingModal.value.message = 'Model LSTM sedang dilatih dengan data baru. Proses ini memerlukan waktu 1-2 menit...';
        loadingModal.value.status = 'Melatih Model AI';
        loadingModal.value.progress = 'Model sedang belajar dari pola data historis';
      }
    }

    // Stop polling if completed or failed
    if (data.status === 'completed' || data.status === 'failed') {
      clearInterval(pollingInterval.value);
      pollingInterval.value = null;
      processingUploadId.value = null;
      loadingModal.value.show = false;

      if (data.status === 'completed') {
        // Parse training results from logs
        console.log('Upload completed! Parsing logs...', data.parsing_log);
        const trainingResults = parseTrainingResults(data.parsing_log);
        console.log('Parsed training results:', trainingResults);

        if (trainingResults) {
          // Show completion modal with training results
          completionResults.value = {
            ...trainingResults,
            records_inserted: data.records_inserted,
            records_parsed: data.records_parsed,
          };
          console.log('Setting completion modal data:', completionResults.value);
          showCompletionModal.value = true;
          console.log('Completion modal should now be visible');
        } else {
          // Fallback to toast if can't parse results
          console.warn('Could not parse training results from logs. Showing toast instead.');
          toast.success(`Data berhasil diproses! ${data.records_inserted} records ditambahkan.`);
          setTimeout(() => window.location.reload(), 1500);
        }
      } else {
        toast.error(`Proses gagal: ${data.error_message || 'Unknown error'}`);
      }
    }
  } catch (error) {
    console.error('Polling error:', error);
  }
};

// Manual retrain
const triggerRetrain = async (modelType = 'both', simulate = true) => {
  retraining.value = true;
  loadingModal.value = {
    show: true,
    title: 'Melatih Model',
    message: 'Model sedang dilatih ulang dengan data terbaru...',
  };
  
  try {
    const response = await axios.post('/data-upload/retrain', {
      model_type: modelType,
      simulate: simulate,
    });
    
    loadingModal.value.show = false;
    
    if (response.data.success) {
      toast.success(response.data.message);
    } else {
      toast.error(response.data.message);
    }
  } catch (error) {
    loadingModal.value.show = false;
    toast.error(error.response?.data?.message || 'Gagal memulai retraining');
  } finally {
    retraining.value = false;
  }
};

// Retry failed upload
const retryUpload = async (uploadId) => {
  try {
    const response = await axios.post(`/data-upload/retry/${uploadId}`);
    if (response.data.success) {
      toast.info(response.data.message);
      processingUploadId.value = uploadId;
      startPolling(uploadId);
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Gagal retry');
  }
};

// Show upload details modal
const showUploadDetails = async (uploadId) => {
  try {
    const response = await axios.get(`/data-upload/status/${uploadId}`);
    selectedUploadDetails.value = response.data;
    showDetailsModal.value = true;
  } catch (error) {
    toast.error('Gagal memuat detail upload');
  }
};

const closeDetailsModal = () => {
  showDetailsModal.value = false;
  selectedUploadDetails.value = null;
};

// Show completion modal from details modal
const showResultsFromDetails = () => {
  if (!selectedUploadDetails.value) return;

  const trainingResults = parseTrainingResults(selectedUploadDetails.value.parsing_log);

  if (trainingResults) {
    completionResults.value = {
      ...trainingResults,
      records_inserted: selectedUploadDetails.value.records_inserted,
      records_parsed: selectedUploadDetails.value.records_parsed,
    };

    // Close details modal and show completion modal
    showDetailsModal.value = false;
    selectedUploadDetails.value = null;
    showCompletionModal.value = true;
  } else {
    toast.warning('Tidak dapat menemukan hasil training dalam log');
  }
};

// Status badge styling
const getStatusBadgeClass = (color) => {
  const colors = {
    gray: 'bg-gray-100 text-gray-700 border-gray-200',
    blue: 'bg-blue-50 text-blue-700 border-blue-200',
    indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    purple: 'bg-purple-50 text-purple-700 border-purple-200',
    green: 'bg-green-50 text-green-700 border-green-200',
    red: 'bg-red-50 text-red-700 border-red-200',
  };
  return colors[color] || colors.gray;
};

const getStatusIcon = (status) => {
  const icons = {
    pending: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    parsing: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4',
    inserting: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
    retraining: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
    completed: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    failed: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
  };
  return icons[status] || icons.pending;
};

// Retraining status helper functions
const urgencyBorderClass = (status) => {
  if (!status) return 'border-gray-200';

  const urgency = status.urgency;
  if (urgency === 'critical' || urgency === 'high') {
    return 'border-red-300 bg-red-50/30';
  } else if (urgency === 'medium') {
    return 'border-yellow-300 bg-yellow-50/30';
  } else {
    return 'border-green-300 bg-green-50/30';
  }
};

const urgencyBadgeClass = (status) => {
  if (!status) return 'bg-gray-100 text-gray-700';

  const urgency = status.urgency;
  if (urgency === 'critical' || urgency === 'high') {
    return 'bg-red-100 text-red-700 border-red-300';
  } else if (urgency === 'medium') {
    return 'bg-yellow-100 text-yellow-700 border-yellow-300';
  } else {
    return 'bg-green-100 text-green-700 border-green-300';
  }
};

// Format date for display
const formatDate = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleDateString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  });
};

// Simplify log messages - only show important events
const simplifyLogMessage = (message) => {
  // Hide technical/verbose messages
  const hidePatterns = [
    /Parsed sheet:/,
    /Processing sheet:/,
    /Room breakdown for/,
    /Daily data processed:/,
    /Found \d+ occupied rooms on/,
  ];

  for (const pattern of hidePatterns) {
    if (pattern.test(message)) {
      return null; // Hide this message
    }
  }

  // Convert remaining technical messages to user-friendly ones
  const patterns = [
    { regex: /Starting to parse file: (.+)/, replacement: 'Membuka file: $1' },
    { regex: /Found (\d+) sheets/, replacement: 'Menemukan $1 sheet dalam file Excel' },
    { regex: /Sheet (.+) does not match expected month/, replacement: 'Sheet $1 dilewati (bukan data tanggal)' },
    { regex: /Skipping sheet: (.+)/, replacement: 'Melewati sheet: $1' },
    { regex: /Successfully parsed (\d+) days/, replacement: 'Berhasil membaca $1 hari data okupansi' },
    { regex: /Parsing complete: (\d+) days parsed/, replacement: 'Parsing selesai: $1 hari data siap disimpan' },
    { regex: /Inserted (\d+) records/, replacement: 'Berhasil menyimpan $1 records ke database' },
    { regex: /Saving to database/, replacement: 'Menyimpan data ke database...' },
    { regex: /Dispatching model retraining/, replacement: 'Memulai proses training model LSTM...' },
    { regex: /Model retraining completed/, replacement: 'Training model selesai' },
    { regex: /Warning: Zero occupancy/, replacement: 'Ditemukan tanggal dengan okupansi 0%' },
  ];

  let simplified = message;
  for (const pattern of patterns) {
    if (pattern.regex.test(message)) {
      simplified = message.replace(pattern.regex, pattern.replacement);
      break;
    }
  }

  return simplified;
};

// Parse training results from logs
const parseTrainingResults = (logs) => {
  console.log('parseTrainingResults called with:', logs);

  if (!logs || logs.length === 0) {
    console.log('No logs or empty logs array');
    return null;
  }

  // Look for training completion messages
  // Examples:
  // "Model retraining completed: v1.13.0, MAPE: 24.2100%, R²: 0.85, PROMOTED as new champion!"
  // "Model retraining completed: v1.22.0, MAPE: 25.5700%, R²: -4.0698, Not promoted (not better)"
  // Match both "v1.x" and "vv1.x" formats, and handle negative R² values
  const trainingPattern = /Model retraining completed: v+([\d.]+),\s*MAPE:\s*([\d.]+)%,\s*R²:\s*(-?[\d.]+|N\/A),?\s*(PROMOTED|Not promoted)/i;
  const promotionReasonPattern = /\(([^)]+)\)/;

  for (const log of logs) {
    const message = log.message || '';
    console.log('Checking log message:', message);
    const match = message.match(trainingPattern);

    if (match) {
      console.log('Pattern matched!', match);
      const version = 'v' + match[1];
      const mape = parseFloat(match[2]);
      const r2 = match[3] === 'N/A' ? null : parseFloat(match[3]);
      const promoted = match[4].toUpperCase() === 'PROMOTED';

      // Extract reason from parentheses
      let reason = '';
      const reasonMatch = message.match(promotionReasonPattern);
      if (reasonMatch) {
        reason = reasonMatch[1];
      } else if (promoted) {
        reason = 'better performance';
      }

      const result = {
        version,
        mape,
        r2,
        promoted,
        reason,
      };
      console.log('Returning parsed result:', result);
      return result;
    }
  }

  console.log('No matching log message found');
  return null;
};

// Close completion modal and refresh
const closeCompletionModal = () => {
  showCompletionModal.value = false;
  completionResults.value = null;
  window.location.reload();
};

// Delete upload with confirmation modal
const confirmDelete = (uploadId) => {
  deleteTargetId.value = uploadId;
  showDeleteModal.value = true;
};

const cancelDelete = () => {
  showDeleteModal.value = false;
  deleteTargetId.value = null;
};

const proceedDelete = async () => {
  const uploadId = deleteTargetId.value;
  showDeleteModal.value = false;
  deleteTargetId.value = null;

  try {
    await axios.delete(`/data-upload/${uploadId}`);
    localUploads.value = localUploads.value.filter(u => u.id !== uploadId);
    toast.success('Upload berhasil dihapus');
  } catch (error) {
    toast.error(error.response?.data?.message || 'Gagal menghapus');
  }
};

// Cleanup
onUnmounted(() => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value);
  }
});
</script>

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
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
              </div>
              <h1 class="text-3xl font-semibold text-white">Data Upload & Training</h1>
            </div>
            <p class="text-white/80 text-base font-normal max-w-2xl">
              Upload data okupansi bulanan dalam format Excel untuk melatih ulang model prediksi LSTM
            </p>
          </div>
        </div>
      </div>

      <!-- Retraining Status Cards - Compact Version for Data Upload Page -->
      <div v-if="retrainingStatus" class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
        <div class="px-8 py-6 border-b border-surface/30">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
              </div>
              <div>
                <h2 class="text-lg font-semibold text-primary-dark">Status Retraining (6 Bulan)</h2>
                <p class="text-sm text-gray-500">Data baru akan mempengaruhi jadwal retraining</p>
              </div>
            </div>
          </div>
        </div>

        <div class="px-8 py-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Single-Output Model Status -->
          <div class="border-2 rounded-2xl p-5" :class="urgencyBorderClass(retrainingStatus.single)">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <span class="text-lg font-bold text-primary-dark">Single Output</span>
                <span class="text-2xl">{{ retrainingStatus.single?.icon || '📊' }}</span>
              </div>
              <span :class="['px-3 py-1 text-xs font-semibold rounded-lg border', urgencyBadgeClass(retrainingStatus.single)]">
                {{ retrainingStatus.single?.urgency === 'high' || retrainingStatus.single?.urgency === 'critical' ? 'Perlu Retraining' :
                   retrainingStatus.single?.urgency === 'medium' ? 'Segera' : 'Optimal' }}
              </span>
            </div>

            <p class="text-sm text-gray-700 mb-3">{{ retrainingStatus.single?.message }}</p>

            <div class="grid grid-cols-2 gap-3 mb-3">
              <div class="p-2.5 bg-white/70 rounded-lg border border-gray-200">
                <p class="text-xs text-gray-500 mb-1">Data Baru</p>
                <p class="text-lg font-bold text-primary-dark">{{ retrainingStatus.single?.new_data_months || 0 }} bulan</p>
              </div>
              <div class="p-2.5 bg-white/70 rounded-lg border border-gray-200">
                <p class="text-xs text-gray-500 mb-1">Persentase</p>
                <p class="text-lg font-bold text-primary-dark">{{ retrainingStatus.single?.new_data_percentage || 0 }}%</p>
              </div>
            </div>

            <div class="text-xs text-gray-600 mb-3">
              <span class="font-medium">Jadwal berikutnya:</span> {{ retrainingStatus.single?.next_due || 'Belum dijadwalkan' }}
            </div>
          </div>

          <!-- Multi-Output Model Status -->
          <div class="border-2 rounded-2xl p-5" :class="urgencyBorderClass(retrainingStatus.multi)">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <span class="text-lg font-bold text-primary-dark">Multi Output</span>
                <span class="text-2xl">{{ retrainingStatus.multi?.icon || '📊' }}</span>
              </div>
              <span :class="['px-3 py-1 text-xs font-semibold rounded-lg border', urgencyBadgeClass(retrainingStatus.multi)]">
                {{ retrainingStatus.multi?.urgency === 'high' || retrainingStatus.multi?.urgency === 'critical' ? 'Perlu Retraining' :
                   retrainingStatus.multi?.urgency === 'medium' ? 'Segera' : 'Optimal' }}
              </span>
            </div>

            <p class="text-sm text-gray-700 mb-3">{{ retrainingStatus.multi?.message }}</p>

            <div class="grid grid-cols-2 gap-3 mb-3">
              <div class="p-2.5 bg-white/70 rounded-lg border border-gray-200">
                <p class="text-xs text-gray-500 mb-1">Data Baru</p>
                <p class="text-lg font-bold text-primary-dark">{{ retrainingStatus.multi?.new_data_months || 0 }} bulan</p>
              </div>
              <div class="p-2.5 bg-white/70 rounded-lg border border-gray-200">
                <p class="text-xs text-gray-500 mb-1">Persentase</p>
                <p class="text-lg font-bold text-primary-dark">{{ retrainingStatus.multi?.new_data_percentage || 0 }}%</p>
              </div>
            </div>

            <div class="text-xs text-gray-600 mb-3">
              <span class="font-medium">Jadwal berikutnya:</span> {{ retrainingStatus.multi?.next_due || 'Belum dijadwalkan' }}
            </div>
          </div>
        </div>

        <!-- Info Footer -->
        <div class="px-8 pb-6">
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
              <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div class="flex-1">
                <p class="font-semibold text-sm text-blue-900 mb-1">Tentang Siklus 6 Bulan</p>
                <p class="text-xs text-blue-700 leading-relaxed">
                  Sistem merekomendasikan retraining setiap <strong>6 bulan</strong> untuk menjaga akurasi model. Data yang Anda upload akan menambah jumlah data baru dan mempengaruhi persentase kelengkapan data untuk retraining berikutnya. Retraining awal dapat dilakukan jika data baru ≥3 bulan.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        <!-- Left Column - Upload Section -->
        <div class="xl:col-span-2 space-y-6">
          <!-- Data Upload Guide Component -->
          <DataUploadGuide />

          <!-- Upload Card -->
          <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
            <div class="px-8 py-6 border-b border-surface/30">
              <div class="flex items-center space-x-3">
                <div class="p-2.5 bg-primary/10 rounded-xl">
                  <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-semibold text-primary-dark">Upload File Excel</h2>
                  <p class="text-sm text-gray-500">Format .xls atau .xlsx, maksimal 10MB</p>
                </div>
              </div>
            </div>
            
            <div class="p-8">
              <!-- Dropzone -->
              <div
                @dragover.prevent="dragOver = true"
                @dragleave.prevent="dragOver = false"
                @drop="handleDrop"
                :class="[
                  'relative border-2 border-dashed rounded-2xl p-8 text-center transition-all duration-300 cursor-pointer',
                  dragOver ? 'border-primary bg-primary/5 scale-[1.02]' : 'border-surface/60 hover:border-primary/40 hover:bg-surface/20',
                  selectedFile ? 'border-green-400 bg-green-50' : ''
                ]"
                @click="$refs.fileInput.click()"
              >
                <input
                  ref="fileInput"
                  type="file"
                  accept=".xls,.xlsx"
                  class="hidden"
                  @change="handleFileSelect"
                />
                
                <div v-if="!selectedFile" class="space-y-3">
                  <div class="mx-auto w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                  </div>
                  <div>
                    <p class="text-base text-gray-700">
                      <span class="font-semibold text-primary hover:text-primary-dark cursor-pointer">Klik untuk upload</span>
                      atau drag & drop file di sini
                    </p>
                    <p class="mt-1 text-sm text-gray-500">
                      Format Excel (.xls, .xlsx) • Maksimal 10MB
                    </p>
                  </div>
                </div>
                
                <div v-else class="flex items-center justify-center space-x-4">
                  <div class="w-14 h-14 bg-green-100 rounded-2xl flex items-center justify-center">
                    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <div class="text-left">
                    <p class="font-semibold text-gray-900">{{ selectedFile.name }}</p>
                    <p class="text-sm text-gray-500">{{ formatFileSize(selectedFile.size) }}</p>
                  </div>
                  <button 
                    @click.stop="clearFile" 
                    class="p-2 rounded-xl text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                  >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>
              
              <!-- Upload Progress -->
              <div v-if="uploading" class="mt-6 space-y-3">
                <div class="flex justify-between text-sm">
                  <span class="font-medium text-gray-700">Mengupload file...</span>
                  <span class="font-semibold text-primary">{{ uploadProgress }}%</span>
                </div>
                <div class="w-full bg-surface/50 rounded-full h-2.5 overflow-hidden">
                  <div 
                    class="h-full bg-gradient-to-r from-primary to-primary-dark rounded-full transition-all duration-300"
                    :style="{ width: uploadProgress + '%' }"
                  ></div>
                </div>
              </div>
              
              <!-- Validate Button -->
              <button
                @click="validateFile"
                :disabled="!selectedFile || validating || uploading"
                class="mt-6 w-full px-6 py-4 bg-primary hover:bg-primary-dark text-white font-semibold rounded-2xl shadow-sm hover:shadow-md transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-3"
              >
                <svg v-if="!validating && !uploading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ validating ? 'Memvalidasi...' : uploading ? 'Mengupload...' : 'Validasi & Lihat Preview' }}</span>
              </button>
            </div>
          </div>

          <!-- Upload History - Collapsible -->
          <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
            <button
              @click="showUploadHistory = !showUploadHistory"
              class="w-full px-8 py-6 flex items-center justify-between hover:bg-gray-50 transition-colors"
            >
              <div class="flex items-center space-x-3">
                <div class="p-2.5 bg-indigo-100 rounded-xl">
                  <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div class="text-left">
                  <h2 class="text-xl font-semibold text-primary-dark">Riwayat Upload</h2>
                  <p class="text-sm text-gray-500">{{ localUploads.length }} file telah diupload</p>
                </div>
              </div>
              <svg
                class="w-5 h-5 text-gray-400 transition-transform duration-200"
                :class="{ 'rotate-180': showUploadHistory }"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
            
            <div v-show="showUploadHistory" class="border-t border-surface/30">
              <div v-if="localUploads.length === 0" class="p-12 text-center">
                <div class="mx-auto w-16 h-16 bg-surface/50 rounded-2xl flex items-center justify-center mb-4">
                  <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                  </svg>
                </div>
                <p class="text-gray-500 font-medium">Belum ada upload</p>
                <p class="text-gray-400 text-sm mt-1">Upload file Excel untuk memulai</p>
              </div>
              
              <div v-else class="divide-y divide-surface/30">
                <div
                  v-for="upload in localUploads"
                  :key="upload.id"
                  @click="showUploadDetails(upload.id)"
                  class="px-8 py-5 hover:bg-surface/20 transition-colors cursor-pointer"
                >
                  <div class="flex items-start justify-between">
                    <div class="flex items-start space-x-4">
                      <div 
                        class="p-2.5 rounded-xl"
                        :class="{
                          'bg-gray-100': upload.status_color === 'gray',
                          'bg-blue-100': upload.status_color === 'blue',
                          'bg-indigo-100': upload.status_color === 'indigo',
                          'bg-purple-100': upload.status_color === 'purple',
                          'bg-green-100': upload.status_color === 'green',
                          'bg-red-100': upload.status_color === 'red',
                        }"
                      >
                        <svg 
                          class="w-5 h-5"
                          :class="{
                            'text-gray-600': upload.status_color === 'gray',
                            'text-blue-600': upload.status_color === 'blue',
                            'text-indigo-600': upload.status_color === 'indigo',
                            'text-purple-600 animate-spin': upload.status_color === 'purple',
                            'text-green-600': upload.status_color === 'green',
                            'text-red-600': upload.status_color === 'red',
                          }"
                          fill="none" 
                          stroke="currentColor" 
                          viewBox="0 0 24 24" 
                          stroke-width="2"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" :d="getStatusIcon(upload.status)" />
                        </svg>
                      </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-center space-x-3">
                          <p class="font-semibold text-primary-dark truncate">{{ upload.file_name }}</p>
                          <span 
                            :class="['px-2.5 py-1 text-xs font-semibold rounded-lg border', getStatusBadgeClass(upload.status_color)]"
                          >
                            {{ upload.status_label }}
                          </span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">
                          {{ upload.period_label || 'Processing...' }} • {{ upload.created_at }}
                        </p>
                        <div v-if="upload.records_inserted > 0" class="flex items-center space-x-1 text-sm mt-1">
                          <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                          </svg>
                          <span class="text-green-600 font-medium">{{ upload.records_inserted }} records</span>
                          <span class="text-gray-500">ditambahkan</span>
                        </div>
                        <p v-if="upload.error_message" class="text-sm text-red-600 mt-1 flex items-center space-x-1">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                          </svg>
                          <span>{{ upload.error_message }}</span>
                        </p>
                      </div>
                    </div>
                    <div class="flex space-x-2 flex-shrink-0">
                      <button
                        v-if="upload.status === 'failed'"
                        @click.stop="retryUpload(upload.id)"
                        class="px-3 py-1.5 text-sm font-medium text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-colors"
                      >
                        Retry
                      </button>
                      <button
                        @click.stop="confirmDelete(upload.id)"
                        class="px-3 py-1.5 text-sm font-medium text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors"
                      >
                        Hapus
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column - Model Info -->
        <div class="space-y-6">
          <!-- Current Champions -->
          <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
            <div class="px-6 py-5 border-b border-surface/30">
              <div class="flex items-center space-x-3">
                <div class="p-2 bg-green-100 rounded-xl">
                  <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                  </svg>
                </div>
                <h2 class="text-lg font-semibold text-primary-dark">Model Aktif</h2>
              </div>
            </div>
            
            <div class="p-6 space-y-4">
              <!-- Single Model -->
              <div class="p-4 rounded-2xl border border-surface/40 hover:border-blue-200 hover:bg-blue-50/30 transition-colors">
                <div class="flex items-center justify-between mb-3">
                  <span class="text-sm font-medium text-gray-600">Single Output LSTM</span>
                  <span v-if="champions?.single" class="text-xs font-semibold bg-green-100 text-green-700 px-2.5 py-1 rounded-lg">
                    Champion
                  </span>
                </div>
                <div v-if="champions?.single">
                  <p class="text-xl font-bold text-primary-dark">{{ champions.single.version }}</p>
                  <div class="flex items-center space-x-4 mt-2">
                    <div class="text-sm">
                      <span class="text-gray-500">MAPE:</span>
                      <span class="font-semibold text-gray-700 ml-1">{{ Number(champions.single.mape).toFixed(2) }}%</span>
                    </div>
                    <div class="text-sm" v-if="champions.single.r2_score != null">
                      <span class="text-gray-500">R²:</span>
                      <span class="font-semibold text-gray-700 ml-1">{{ Number(champions.single.r2_score).toFixed(4) }}</span>
                    </div>
                  </div>
                </div>
                <p v-else class="text-sm text-gray-400">Belum ada model</p>
              </div>
              
              <!-- Multi Model -->
              <div class="p-4 rounded-2xl border border-surface/40 hover:border-purple-200 hover:bg-purple-50/30 transition-colors">
                <div class="flex items-center justify-between mb-3">
                  <span class="text-sm font-medium text-gray-600">Multi Output LSTM</span>
                  <span v-if="champions?.multi" class="text-xs font-semibold bg-green-100 text-green-700 px-2.5 py-1 rounded-lg">
                    Champion
                  </span>
                </div>
                <div v-if="champions?.multi">
                  <p class="text-xl font-bold text-primary-dark">{{ champions.multi.version }}</p>
                  <div class="flex items-center space-x-4 mt-2">
                    <div class="text-sm">
                      <span class="text-gray-500">MAPE:</span>
                      <span class="font-semibold text-gray-700 ml-1">{{ Number(champions.multi.mape).toFixed(2) }}%</span>
                    </div>
                    <div class="text-sm" v-if="champions.multi.r2_score != null">
                      <span class="text-gray-500">R²:</span>
                      <span class="font-semibold text-gray-700 ml-1">{{ Number(champions.multi.r2_score).toFixed(4) }}</span>
                    </div>
                  </div>
                </div>
                <p v-else class="text-sm text-gray-400">Belum ada model</p>
              </div>
            </div>

          </div>

          <!-- Model Version History - Grouped by Type -->
          <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
            <button
              @click="showModelVersions = !showModelVersions"
              class="w-full px-6 py-5 flex items-center justify-between hover:bg-gray-50 transition-colors"
            >
              <div class="flex items-center space-x-3">
                <div class="p-2 bg-gray-100 rounded-xl">
                  <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                  </svg>
                </div>
                <div class="text-left">
                  <h2 class="text-lg font-semibold text-primary-dark">Riwayat Model</h2>
                  <p class="text-xs text-gray-500">{{ modelVersions.length }} versi total</p>
                </div>
              </div>
              <svg
                class="w-5 h-5 text-gray-400 transition-transform duration-200"
                :class="{ 'rotate-180': showModelVersions }"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div v-show="showModelVersions" class="border-t border-surface/30">
              <div v-if="modelVersions.length === 0" class="p-8 text-center">
                <p class="text-gray-400 text-sm">Belum ada riwayat model</p>
              </div>

              <div v-else class="p-4">
                <!-- Filter Tabs -->
                <div class="flex space-x-2 mb-4">
                  <button
                    @click="modelTypeFilter = 'all'"
                    :class="[
                      'px-4 py-2 text-sm font-semibold rounded-xl transition-all',
                      modelTypeFilter === 'all'
                        ? 'bg-primary text-white shadow-sm'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                  >
                    Semua ({{ modelVersions.length }})
                  </button>
                  <button
                    @click="modelTypeFilter = 'single'"
                    :class="[
                      'px-4 py-2 text-sm font-semibold rounded-xl transition-all',
                      modelTypeFilter === 'single'
                        ? 'bg-blue-600 text-white shadow-sm'
                        : 'bg-blue-50 text-blue-700 hover:bg-blue-100'
                    ]"
                  >
                    Single ({{ singleModels.length }})
                  </button>
                  <button
                    @click="modelTypeFilter = 'multi'"
                    :class="[
                      'px-4 py-2 text-sm font-semibold rounded-xl transition-all',
                      modelTypeFilter === 'multi'
                        ? 'bg-purple-600 text-white shadow-sm'
                        : 'bg-purple-50 text-purple-700 hover:bg-purple-100'
                    ]"
                  >
                    Multi ({{ multiModels.length }})
                  </button>
                </div>

                <!-- Model List -->
                <div class="space-y-2 max-h-96 overflow-y-auto">
                  <div
                    v-for="version in filteredModelVersions"
                    :key="version.id"
                    class="p-4 rounded-2xl border hover:border-primary/40 hover:bg-surface/20 transition-all"
                    :class="{
                      'border-blue-200 bg-blue-50/30': version.model_type === 'single',
                      'border-purple-200 bg-purple-50/30': version.model_type === 'multi',
                      'ring-2 ring-yellow-400': version.is_champion,
                    }"
                  >
                    <div class="flex items-start justify-between">
                      <div class="flex items-start space-x-3">
                        <!-- Champion Badge -->
                        <div v-if="version.is_champion" class="flex-shrink-0">
                          <svg class="w-6 h-6 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                          </svg>
                        </div>

                        <!-- Version Info -->
                        <div class="flex-1">
                          <div class="flex items-center space-x-2 mb-1">
                            <span class="text-lg font-bold text-primary-dark">{{ version.version }}</span>
                            <span
                              :class="[
                                'text-xs font-semibold px-2.5 py-1 rounded-lg',
                                version.model_type === 'single' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'
                              ]"
                            >
                              {{ version.model_type === 'single' ? 'Single Output' : 'Multi Output' }}
                            </span>
                            <span v-if="version.is_champion" class="text-xs font-bold bg-yellow-100 text-yellow-800 px-2.5 py-1 rounded-lg">
                              CHAMPION
                            </span>
                          </div>

                          <!-- Metrics -->
                          <div class="flex items-center space-x-4 text-sm mt-2">
                            <div>
                              <span class="text-gray-500">MAPE:</span>
                              <span class="font-semibold text-gray-800 ml-1">
                                {{ version.mape != null ? Number(version.mape).toFixed(2) + '%' : '-' }}
                              </span>
                            </div>
                            <div v-if="version.r2_score != null">
                              <span class="text-gray-500">R²:</span>
                              <span
                                class="font-semibold ml-1"
                                :class="version.r2_score >= 0 && version.r2_score <= 1 ? 'text-green-700' : 'text-red-700'"
                              >
                                {{ Number(version.r2_score).toFixed(4) }}
                              </span>
                            </div>
                            <div v-if="version.training_duration">
                              <span class="text-gray-500">Time:</span>
                              <span class="font-semibold text-gray-800 ml-1">
                                {{ Number(version.training_duration).toFixed(1) }}s
                              </span>
                            </div>
                          </div>

                          <!-- Status & Date -->
                          <div class="flex items-center space-x-3 mt-2">
                            <span
                              :class="[
                                'text-xs px-2.5 py-1 rounded-lg font-medium',
                                version.status === 'completed' ? 'bg-green-100 text-green-700' :
                                version.status === 'failed' ? 'bg-red-100 text-red-700' :
                                'bg-gray-100 text-gray-700'
                              ]"
                            >
                              {{ version.status }}
                            </span>
                            <span class="text-xs text-gray-500">{{ version.created_at }}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading Modal with Progress -->
    <LoadingModal
      :show="loadingModal.show"
      :title="loadingModal.title"
      :message="loadingModal.message"
    >
      <template #content v-if="loadingModal.status">
        <div class="mt-6 space-y-4">
          <div class="text-center">
            <p class="text-base font-semibold text-primary mb-2">{{ loadingModal.status }}</p>
            <p class="text-sm text-gray-600 leading-relaxed">{{ loadingModal.progress }}</p>
          </div>

          <!-- Animated Progress Bar -->
          <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
            <div class="h-full bg-gradient-to-r from-primary via-primary-dark to-primary rounded-full animate-pulse relative">
              <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-30 animate-shimmer"></div>
            </div>
          </div>

          <!-- Status Icons -->
          <div class="flex justify-center items-center space-x-2 text-xs text-gray-500">
            <span class="flex items-center space-x-1">
              <svg class="w-4 h-4 text-green-500" :class="{ 'animate-pulse': loadingModal.status.includes('Menganalisis') }" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span>Parse</span>
            </span>
            <span class="text-gray-300">→</span>
            <span class="flex items-center space-x-1">
              <svg class="w-4 h-4" :class="loadingModal.status.includes('Menyimpan') ? 'text-green-500 animate-pulse' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span>Simpan</span>
            </span>
            <span class="text-gray-300">→</span>
            <span class="flex items-center space-x-1">
              <svg class="w-4 h-4" :class="loadingModal.status.includes('Melatih') ? 'text-green-500 animate-pulse' : 'text-gray-400'" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
              <span>Train</span>
            </span>
          </div>
        </div>
      </template>
    </LoadingModal>

    <!-- Confirmation Modal -->
    <teleport to="body">
      <div
        v-if="showConfirmationModal && filePreview"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 overflow-y-auto"
        @click.self="cancelUpload"
      >
        <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full my-8 max-h-[90vh] flex flex-col">
          <!-- Header -->
          <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-green-500 to-emerald-600 flex-shrink-0">
            <div class="flex items-start justify-between">
              <div>
                <h3 class="text-2xl font-bold text-white">Konfirmasi Data Upload</h3>
                <p class="text-white/80 text-sm mt-1">Validasi berhasil! Periksa data sebelum diproses</p>
              </div>
              <button
                @click="cancelUpload"
                class="p-2 rounded-xl hover:bg-white/20 transition-colors flex-shrink-0"
              >
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Scrollable Content -->
          <div class="px-8 py-5 overflow-y-auto flex-1">
            <!-- Success Icon -->
            <div class="flex justify-center mb-4">
              <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
              </div>
            </div>

            <!-- File Info -->
            <div class="text-center mb-5">
              <p class="text-gray-600 text-xs mb-1">File</p>
              <p class="text-base font-semibold text-primary-dark">{{ selectedFile?.name }}</p>
            </div>

            <!-- Data Summary -->
            <div class="grid grid-cols-2 gap-3 mb-4">
              <div class="p-4 bg-blue-50 rounded-2xl border border-blue-100">
                <p class="text-xs text-blue-600 font-medium mb-1">Total Hari</p>
                <p class="text-2xl font-bold text-blue-900">{{ filePreview.total_days }}</p>
                <p class="text-xs text-blue-600 mt-1">hari data ditemukan</p>
              </div>

              <div class="p-4 bg-indigo-50 rounded-2xl border border-indigo-100">
                <p class="text-xs text-indigo-600 font-medium mb-1">Total Records</p>
                <p class="text-2xl font-bold text-indigo-900">{{ filePreview.total_records }}</p>
                <p class="text-xs text-indigo-600 mt-1">records akan diproses</p>
              </div>
            </div>

            <!-- Date Range -->
            <div class="p-4 bg-gradient-to-br from-purple-50 to-pink-50 rounded-2xl border border-purple-100 mb-4">
              <p class="text-xs text-purple-600 font-medium mb-2">Periode Data</p>
              <div class="flex items-center justify-between">
                <div class="text-center flex-1">
                  <p class="text-xs text-purple-600 mb-1">Tanggal Awal</p>
                  <p class="text-base font-bold text-purple-900">{{ formatDate(filePreview.date_range.start) }}</p>
                </div>
                <div class="px-3">
                  <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                  </svg>
                </div>
                <div class="text-center flex-1">
                  <p class="text-xs text-purple-600 mb-1">Tanggal Akhir</p>
                  <p class="text-base font-bold text-purple-900">{{ formatDate(filePreview.date_range.end) }}</p>
                </div>
              </div>
            </div>

            <!-- Room Types -->
            <div class="p-4 bg-orange-50 rounded-2xl border border-orange-100 mb-4">
              <p class="text-xs text-orange-600 font-medium mb-2">Tipe Kamar Ditemukan</p>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="roomType in filePreview.room_types"
                  :key="roomType"
                  class="px-2.5 py-1 bg-white border border-orange-200 text-orange-700 font-semibold rounded-lg text-xs"
                >
                  {{ roomType }}
                </span>
              </div>
            </div>

            <!-- Info Message -->
            <div class="p-3 bg-blue-50 border border-blue-200 rounded-2xl flex items-start space-x-2">
              <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div>
                <p class="font-semibold text-blue-900 text-sm">Proses Selanjutnya</p>
                <p class="text-xs text-blue-700 mt-0.5 leading-relaxed">
                  Setelah dikonfirmasi, data akan diparse dan disimpan ke database.
                  Model LSTM akan otomatis dilatih ulang menggunakan data terbaru.
                </p>
              </div>
            </div>
          </div>

          <!-- Fixed Footer -->
          <div class="px-8 py-4 border-t border-gray-200 bg-gray-50 flex justify-end space-x-3 flex-shrink-0">
            <button
              @click="cancelUpload"
              class="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-colors"
            >
              Batal
            </button>
            <button
              @click="confirmAndUpload"
              class="px-6 py-2.5 bg-gradient-to-r from-primary to-primary-dark text-white font-semibold rounded-xl hover:opacity-90 transition-opacity flex items-center space-x-2"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              <span>Proses Data</span>
            </button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Upload Details Modal -->
    <teleport to="body">
      <div
        v-if="showDetailsModal && selectedUploadDetails"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        @click.self="closeDetailsModal"
      >
        <div class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full max-h-[80vh] overflow-hidden">
          <!-- Header -->
          <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-primary to-primary-dark">
            <div class="flex items-start justify-between">
              <div>
                <h3 class="text-2xl font-bold text-white">Detail Proses Upload</h3>
                <p class="text-white/80 text-sm mt-1">Informasi lengkap tentang proses upload dan training</p>
              </div>
              <button
                @click="closeDetailsModal"
                class="p-2 rounded-xl hover:bg-white/20 transition-colors"
              >
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Content -->
          <div class="px-8 py-6 overflow-y-auto max-h-[60vh]">
            <!-- Status Info -->
            <div class="grid grid-cols-2 gap-4 mb-6">
              <div class="p-4 bg-surface/30 rounded-2xl">
                <p class="text-sm text-gray-500 mb-1">Status</p>
                <span
                  :class="['inline-flex px-3 py-1 text-sm font-semibold rounded-lg', getStatusBadgeClass(selectedUploadDetails.status_color)]"
                >
                  {{ selectedUploadDetails.status_label }}
                </span>
              </div>
              <div class="p-4 bg-surface/30 rounded-2xl">
                <p class="text-sm text-gray-500 mb-1">Records</p>
                <p class="text-lg font-bold text-primary-dark">
                  {{ selectedUploadDetails.records_inserted || 0 }} inserted
                </p>
              </div>
            </div>

            <!-- Processing Logs -->
            <div v-if="selectedUploadDetails.parsing_log && selectedUploadDetails.parsing_log.length > 0">
              <h4 class="text-lg font-semibold text-primary-dark mb-3">Ringkasan Proses</h4>
              <div class="space-y-2 max-h-96 overflow-y-auto">
                <div
                  v-for="(log, index) in selectedUploadDetails.parsing_log"
                  :key="index"
                  v-show="simplifyLogMessage(log.message) !== null"
                  class="p-3 rounded-xl text-sm"
                  :class="{
                    'bg-red-50 border border-red-200': log.level === 'error',
                    'bg-yellow-50 border border-yellow-200': log.level === 'warning',
                    'bg-blue-50 border border-blue-200': log.level === 'info',
                  }"
                >
                  <div class="flex items-start space-x-2">
                    <span
                      class="flex-shrink-0 mt-0.5"
                      :class="{
                        'text-red-600': log.level === 'error',
                        'text-yellow-600': log.level === 'warning',
                        'text-blue-600': log.level === 'info',
                      }"
                    >
                      <svg v-if="log.level === 'error'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                      </svg>
                      <svg v-else-if="log.level === 'warning'" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                      </svg>
                      <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                      </svg>
                    </span>
                    <div class="flex-1">
                      <p
                        class="font-medium leading-relaxed"
                        :class="{
                          'text-red-800': log.level === 'error',
                          'text-yellow-800': log.level === 'warning',
                          'text-blue-800': log.level === 'info',
                        }"
                      >
                        {{ simplifyLogMessage(log.message) }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Error Message -->
            <div v-if="selectedUploadDetails.error_message" class="mt-6 p-4 bg-red-50 border border-red-200 rounded-2xl">
              <div class="flex items-start space-x-3">
                <svg class="w-6 h-6 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                  <p class="font-semibold text-red-900">Error</p>
                  <p class="text-sm text-red-700 mt-1">{{ selectedUploadDetails.error_message }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-8 py-4 border-t border-gray-200 bg-gray-50 flex justify-end space-x-3">
            <!-- Show Results Button (only if completed) -->
            <button
              v-if="selectedUploadDetails.status === 'completed'"
              @click="showResultsFromDetails"
              class="px-6 py-2.5 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all shadow-lg hover:shadow-xl"
            >
              Lihat Hasil Training
            </button>
            <button
              @click="closeDetailsModal"
              class="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-colors"
            >
              Tutup
            </button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Completion Modal (Training Results) -->
    <teleport to="body">
      <div
        v-if="showCompletionModal && completionResults"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        @click.self="closeCompletionModal"
      >
        <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
          <!-- Header -->
          <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-green-500 to-emerald-600">
            <div class="flex items-start justify-between">
              <div>
                <h3 class="text-2xl font-bold text-white">Proses Selesai!</h3>
                <p class="text-white/90 text-sm mt-1">Data berhasil diproses dan model telah dilatih</p>
              </div>
              <button
                @click="closeCompletionModal"
                class="p-2 rounded-xl hover:bg-white/20 transition-colors"
              >
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Content -->
          <div class="px-8 py-6 space-y-6 overflow-y-auto max-h-[70vh]">
            <!-- Success Icon -->
            <div class="flex justify-center">
              <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center">
                <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
              </div>
            </div>

            <!-- Data Summary -->
            <div class="grid grid-cols-2 gap-4">
              <div class="p-4 bg-blue-50 border border-blue-200 rounded-2xl text-center">
                <p class="text-sm text-blue-600 font-medium mb-1">Data Tersimpan</p>
                <p class="text-2xl font-bold text-blue-900">{{ completionResults.records_inserted }}</p>
                <p class="text-xs text-blue-600 mt-1">records ditambahkan</p>
              </div>
              <div class="p-4 bg-purple-50 border border-purple-200 rounded-2xl text-center">
                <p class="text-sm text-purple-600 font-medium mb-1">Hari Data</p>
                <p class="text-2xl font-bold text-purple-900">{{ completionResults.records_parsed }}</p>
                <p class="text-xs text-purple-600 mt-1">hari diproses</p>
              </div>
            </div>

            <!-- Training Results -->
            <div class="p-5 bg-gradient-to-br from-indigo-50 to-purple-50 border border-indigo-200 rounded-2xl">
              <h4 class="text-lg font-semibold text-indigo-900 mb-4 flex items-center">
                <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Hasil Training Model
              </h4>

              <div class="space-y-3">
                <!-- Model Version -->
                <div class="flex items-center justify-between p-3 bg-white rounded-xl">
                  <span class="text-sm font-medium text-gray-600">Versi Model Baru</span>
                  <span class="text-lg font-bold text-indigo-900">{{ completionResults.version }}</span>
                </div>

                <!-- MAPE Score -->
                <div class="flex items-center justify-between p-3 bg-white rounded-xl">
                  <span class="text-sm font-medium text-gray-600">Akurasi (MAPE)</span>
                  <span class="text-lg font-bold text-indigo-900">{{ completionResults.mape.toFixed(2) }}%</span>
                </div>

                <!-- R² Score -->
                <div class="flex items-center justify-between p-3 bg-white rounded-xl">
                  <span class="text-sm font-medium text-gray-600">R² Score</span>
                  <span class="text-lg font-bold text-indigo-900">
                    {{ completionResults.r2 !== null ? completionResults.r2.toFixed(4) : 'N/A' }}
                  </span>
                </div>

                <!-- Promotion Status -->
                <div class="p-4 rounded-xl" :class="completionResults.promoted ? 'bg-green-100 border-2 border-green-300' : 'bg-yellow-100 border-2 border-yellow-300'">
                  <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0 mt-0.5">
                      <svg v-if="completionResults.promoted" class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                      </svg>
                      <svg v-else class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                      </svg>
                    </div>
                    <div class="flex-1">
                      <p class="font-semibold mb-1" :class="completionResults.promoted ? 'text-green-900' : 'text-yellow-900'">
                        {{ completionResults.promoted ? 'Model Baru Dipromosikan!' : 'Model Lama Dipertahankan' }}
                      </p>
                      <p class="text-sm" :class="completionResults.promoted ? 'text-green-700' : 'text-yellow-700'">
                        <span v-if="completionResults.promoted">
                          Model baru memiliki akurasi yang lebih baik dan akan digunakan untuk prediksi selanjutnya.
                        </span>
                        <span v-else>
                          Model lama masih memiliki akurasi lebih baik ({{ completionResults.reason }}) dan akan tetap digunakan untuk prediksi.
                        </span>
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Info Note -->
            <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl">
              <div class="flex items-start space-x-3">
                <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm text-blue-700">
                  Sistem otomatis memilih model terbaik berdasarkan nilai MAPE (semakin rendah semakin baik). Halaman akan di-refresh untuk menampilkan data terbaru.
                </p>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-8 py-4 border-t border-gray-200 bg-gray-50 flex justify-end">
            <button
              @click="closeCompletionModal"
              class="px-8 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all shadow-lg hover:shadow-xl"
            >
              Tutup & Refresh
            </button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Delete Confirmation Modal -->
    <teleport to="body">
      <div
        v-if="showDeleteModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        @click.self="cancelDelete"
      >
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
          <!-- Header -->
          <div class="px-8 py-6 border-b border-gray-200 bg-gradient-to-r from-red-500 to-red-600">
            <h3 class="text-2xl font-bold text-white">Konfirmasi Hapus</h3>
          </div>

          <!-- Content -->
          <div class="px-8 py-6">
            <div class="flex items-start space-x-4">
              <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                  <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                </div>
              </div>
              <div class="flex-1">
                <p class="text-lg font-semibold text-gray-900 mb-2">Yakin ingin menghapus upload ini?</p>
                <p class="text-sm text-gray-600">
                  Data yang sudah tersimpan di database tidak akan dihapus, hanya riwayat upload yang akan dihilangkan. Tindakan ini tidak dapat dibatalkan.
                </p>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-8 py-4 border-t border-gray-200 bg-gray-50 flex justify-end space-x-3">
            <button
              @click="cancelDelete"
              class="px-6 py-2.5 bg-white border border-gray-300 text-gray-700 font-semibold rounded-xl hover:bg-gray-50 transition-colors"
            >
              Batal
            </button>
            <button
              @click="proceedDelete"
              class="px-6 py-2.5 bg-gradient-to-r from-red-500 to-red-600 text-white font-semibold rounded-xl hover:from-red-600 hover:to-red-700 transition-all shadow-lg"
            >
              Ya, Hapus
            </button>
          </div>
        </div>
      </div>
    </teleport>
  </DashboardLayout>
</template>

<style>
.bg-grid-white\/\[0\.02\] {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32' width='32' height='32' fill='none' stroke='rgb(255 255 255 / 0.02)'%3e%3cpath d='M0 .5H31.5V32'/%3e%3c/svg%3e");
}
</style>
