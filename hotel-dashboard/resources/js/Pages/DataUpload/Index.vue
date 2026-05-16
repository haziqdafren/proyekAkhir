<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import LoadingModal from '@/Components/LoadingModal.vue';
import Pagination from '@/Components/Pagination.vue';
import axios from 'axios';

const toast = useToast();

const props = defineProps({
  uploads: { type: Object, default: () => ({ data: [], current_page: 1, last_page: 1, total: 0, per_page: 10 }) },
  modelVersions: Array,
  champions: Object,
  retrainingStatus: {
    type: Object,
    default: () => ({ single: {}, multi: {} }),
  },
});

// ── State ──────────────────────────────────────────────────────────────────
const uploading          = ref(false);
const uploadProgress     = ref(0);
const selectedFile       = ref(null);
const dragOver           = ref(false);
const pollingInterval    = ref(null);
const localUploads       = ref([...(props.uploads?.data ?? [])]);
const processingUploadId = ref(null);
const selectedUploadDetails = ref(null);
const showDetailsModal   = ref(false);
const modelTypeFilter    = ref('all');
const showConfirmationModal = ref(false);
const filePreview        = ref(null);
const validating         = ref(false);
const showCompletionModal = ref(false);
const completionResults  = ref(null);
const showDeleteModal    = ref(false);
const deleteTargetId     = ref(null);
const activeHistoryTab   = ref('uploads');
const showHistory        = ref(false);
const activeTab          = ref('upload');

// Upload history pagination
const goToUploadsPage = (page) => {
  router.get('/data-upload', { uploads_page: page }, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      localUploads.value = [...(props.uploads?.data ?? [])];
    },
  });
};

// ── Computed ────────────────────────────────────────────────────────────────
const filteredModelVersions = computed(() => {
  if (modelTypeFilter.value === 'all') return props.modelVersions;
  return props.modelVersions.filter(v => v.model_type === modelTypeFilter.value);
});
const singleModels = computed(() => props.modelVersions.filter(v => v.model_type === 'single'));
const multiModels  = computed(() => props.modelVersions.filter(v => v.model_type === 'multi'));

const modelHealthStatus = computed(() => {
  const s = props.retrainingStatus?.single;
  const m = props.retrainingStatus?.multi;
  if (!s && !m) return { level: 'ok', label: 'Model Aktif', color: 'green' };
  if (s?.color === 'red' || m?.color === 'red')
    return { level: 'critical', label: 'Perlu Diperbarui Segera', color: 'red' };
  if (s?.color === 'yellow' || m?.color === 'yellow')
    return { level: 'warn', label: 'Disarankan Diperbarui', color: 'yellow' };
  return { level: 'ok', label: 'Model Berjalan Optimal', color: 'green' };
});

// ── Helpers ─────────────────────────────────────────────────────────────────
const displayAccuracy = (mape) => {
  if (mape == null) return '–';
  return Math.max(0, 100 - Number(mape)).toFixed(1) + '%';
};

// Ganti versi teknis (v1.0.0-thesis) dengan tanggal update
const formatVersionLabel = (version, trainedAt) => {
  if (!version && !trainedAt) return 'Belum ada model';
  if (trainedAt) {
    try {
      const d = new Date(trainedAt);
      return 'Diperbarui: ' + d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    } catch {
      return version ?? 'Belum ada model';
    }
  }
  return version ?? 'Belum ada model';
};

const formatFileSize = (bytes) => {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const formatDate = (dateString) => {
  if (!dateString) return '–';
  return new Date(dateString + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
};

const getStatusBadgeClass = (color) => {
  const map = {
    gray:   'bg-gray-100 text-gray-700 border-gray-200',
    blue:   'bg-primary/10 text-primary border-primary/20',
    indigo: 'bg-primary/10 text-primary border-primary/20',
    purple: 'bg-surface text-primary-dark border-surface',
    green:  'bg-green-50 text-green-700 border-green-200',
    red:    'bg-red-50 text-red-700 border-red-200',
  };
  return map[color] || map.gray;
};

const getStatusIcon = (status) => {
  const icons = {
    pending:    'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    parsing:    'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4',
    inserting:  'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12',
    retraining: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
    completed:  'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    failed:     'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
  };
  return icons[status] || icons.pending;
};

// ── File handling ────────────────────────────────────────────────────────────
const handleFileSelect = (event) => {
  const file = event.target.files[0];
  if (file) validateAndSetFile(file);
};

const handleDrop = (event) => {
  event.preventDefault();
  dragOver.value = false;
  const file = event.dataTransfer.files[0];
  if (file) validateAndSetFile(file);
};

const validateAndSetFile = (file) => {
  const ext = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
  if (!['.xls', '.xlsx'].includes(ext)) {
    toast.error('Format file tidak valid. Gunakan file .xls atau .xlsx');
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    toast.error('Ukuran file terlalu besar. Maksimal 10MB');
    return;
  }
  selectedFile.value = file;
};

const clearFile = () => { selectedFile.value = null; };

// ── Step 1: Validate ─────────────────────────────────────────────────────────
const validateFile = async () => {
  if (!selectedFile.value) return;
  validating.value = true;
  const formData = new FormData();
  formData.append('file', selectedFile.value);
  try {
    const response = await axios.post('/data-upload/validate', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    if (response.data.success) {
      filePreview.value = response.data.summary;
      showConfirmationModal.value = true;
    } else {
      toast.error(response.data.message || 'File tidak valid');
    }
  } catch (error) {
    if (error.response?.status === 429) {
      toast.error('Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.');
    } else {
      toast.error(error.response?.data?.message || 'Gagal memvalidasi file');
    }
  } finally {
    validating.value = false;
  }
};

// ── Step 2: Upload ───────────────────────────────────────────────────────────
const confirmAndUpload = async () => {
  if (!selectedFile.value) return;
  showConfirmationModal.value = false;
  uploading.value = true;
  uploadProgress.value = 0;
  const formData = new FormData();
  formData.append('file', selectedFile.value);
  try {
    const response = await axios.post('/data-upload/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (e) => {
        uploadProgress.value = Math.round((e.loaded * 100) / e.total);
      },
    });
    if (response.data.success) {
      const uploadId = response.data.upload_id;
      const uploadedFileName = selectedFile.value?.name || 'File';
      selectedFile.value = null;
      filePreview.value = null;
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
      processingUploadId.value = uploadId;
      startPolling(uploadId);
    } else {
      toast.error(response.data.message || 'Upload gagal');
    }
  } catch (error) {
    if (error.response?.status === 429) {
      toast.error('Terlalu banyak upload. Tunggu sebentar lalu coba lagi.');
    } else {
      toast.error(error.response?.data?.message || 'Terjadi kesalahan saat upload');
    }
  } finally {
    uploading.value = false;
    uploadProgress.value = 0;
  }
};

const cancelUpload = () => {
  showConfirmationModal.value = false;
  filePreview.value = null;
};

// ── Loading modal state ──────────────────────────────────────────────────────
const loadingModal = ref({
  show: false,
  title: 'Memproses Data',
  message: '',
  currentStep: 'parsing', // 'parsing' | 'inserting'
});

// ── Polling ──────────────────────────────────────────────────────────────────
const startPolling = (uploadId) => {
  loadingModal.value.show = true;
  pollStatus(uploadId);
  pollingInterval.value = setInterval(() => pollStatus(uploadId), 3000);
};

const pollStatus = async (uploadId) => {
  try {
    const response = await axios.get(`/data-upload/status/${uploadId}`);
    const data = response.data;

    if (!data || !data.id) {
      clearInterval(pollingInterval.value);
      pollingInterval.value = null;
      processingUploadId.value = null;
      loadingModal.value.show = false;
      toast.error('Upload tidak ditemukan.');
      return;
    }

    // Update local uploads list
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

    // Update loading modal based on status
    if (data.status === 'parsing') {
      loadingModal.value.currentStep = 'parsing';
      loadingModal.value.message = 'Membaca sheet-sheet Excel dan mengekstrak data okupansi harian...';
    } else if (data.status === 'inserting') {
      loadingModal.value.currentStep = 'inserting';
      const total   = data.records_parsed || 0;
      const inserted = data.records_inserted || 0;
      const pct     = total > 0 ? Math.round((inserted / (total * 4)) * 100) : 0;
      loadingModal.value.message = `Menyimpan data ke database... ${pct}%`;
    }

    // Done or failed
    if (data.status === 'completed' || data.status === 'failed') {
      clearInterval(pollingInterval.value);
      pollingInterval.value = null;
      processingUploadId.value = null;
      loadingModal.value.show = false;

      if (data.status === 'completed') {
        // Always show completion modal — with or without model results
        const trainingResults = parseTrainingResults(data.parsing_log);
        completionResults.value = {
          records_inserted: data.records_inserted,
          records_parsed:   data.records_parsed,
          hasModelResults:  !!trainingResults,
          ...(trainingResults || {}),
        };
        showCompletionModal.value = true;
      } else {
        toast.error(`Proses gagal: ${data.error_message || 'Terjadi kesalahan tidak diketahui'}`);
      }
    }
  } catch (error) {
    console.error('Polling error:', error);
  }
};

// ── Retraining ───────────────────────────────────────────────────────────────
const retrainingState = ref({
  single: { active: false, polling: null },
  multi:  { active: false, polling: null },
});

// Retrain progress modal
const retrainModal = ref({
  show:      false,
  modelType: '',   // 'single' | 'multi' | 'both'
  phase:     'starting', // 'starting' | 'training' | 'done' | 'failed'
  elapsed:   0,
  results:   [],  // array of { type, version, mape, r2, promoted, oldMape, oldR2 }
  error:     '',
});
let retrainElapsedTimer = null;

const retrainModelLabel = (type) => type === 'single' ? 'Single-Output (Total Hotel)' : 'Multi-Output (Per Kamar)';

const startRetrainTimer = () => {
  retrainModal.value.elapsed = 0;
  retrainElapsedTimer = setInterval(() => { retrainModal.value.elapsed++; }, 1000);
};
const stopRetrainTimer = () => {
  if (retrainElapsedTimer) { clearInterval(retrainElapsedTimer); retrainElapsedTimer = null; }
};
const formatElapsed = (s) => {
  if (!s || s <= 0) return '–';
  if (s < 60) return `${s} detik`;
  return `${Math.floor(s/60)} menit ${s%60} detik`;
};

const closeRetrainModal = () => {
  retrainModal.value.show = false;
  stopRetrainTimer();
  if (retrainModal.value.phase === 'done' || retrainModal.value.phase === 'failed') {
    window.location.reload();
  }
};

const triggerRetrain = async (modelType = 'both') => {
  const types = modelType === 'both' ? ['single', 'multi'] : [modelType];

  // Snapshot current champion metrics before training
  const oldMetrics = {
    single: props.champions?.single ? { mape: props.champions.single.mape, r2: props.champions.single.r2_score, version: props.champions.single.version } : null,
    multi:  props.champions?.multi  ? { mape: props.champions.multi.mape,  r2: props.champions.multi.r2_score,  version: props.champions.multi.version  } : null,
  };

  // Show blocking modal immediately
  retrainModal.value = {
    show:      true,
    modelType,
    phase:     'starting',
    elapsed:   0,
    results:   [],
    error:     '',
  };
  startRetrainTimer();
  types.forEach(t => { retrainingState.value[t].active = true; });

  try {
    const response = await axios.post('/data-upload/retrain', { model_type: modelType, simulate: false });
    if (response.data.success) {
      retrainModal.value.phase = 'training';
      types.forEach(t => startRetrainPolling(t, oldMetrics[t]));
    } else {
      retrainModal.value.phase = 'failed';
      retrainModal.value.error = response.data.message || 'Gagal memulai retraining';
      stopRetrainTimer();
      types.forEach(t => { retrainingState.value[t].active = false; });
    }
  } catch (error) {
    retrainModal.value.phase = 'failed';
    retrainModal.value.error = error.response?.status === 429
      ? 'Terlalu banyak permintaan. Tunggu 1 menit lalu coba lagi.'
      : (error.response?.data?.message || 'Gagal memulai retraining');
    stopRetrainTimer();
    types.forEach(t => { retrainingState.value[t].active = false; });
  }
};

const startRetrainPolling = (modelType, oldMetric) => {
  if (retrainingState.value[modelType].polling) clearInterval(retrainingState.value[modelType].polling);

  const types = retrainModal.value.modelType === 'both' ? ['single', 'multi'] : [retrainModal.value.modelType];
  // Minimum time (ms) to show the training phase so user sees the process
  const MIN_TRAINING_DISPLAY_MS = 3000;
  const pollStart = Date.now();

  const finishType = (typeData) => {
    clearInterval(retrainingState.value[modelType].polling);
    retrainingState.value[modelType].polling = null;
    retrainingState.value[modelType].active  = false;

    const latest = typeData?.latest;
    if (latest) {
      // Use server-reported training duration if elapsed timer was too fast
      const serverDuration = latest.training_duration ? Math.round(latest.training_duration) : null;
      retrainModal.value.results.push({
        type:            modelType,
        version:         latest.version,
        mape:            latest.mape,
        r2:              latest.r2_score,
        promoted:        latest.is_champion,
        status:          latest.status,
        error:           latest.error_message,
        oldMape:         oldMetric?.mape ?? null,
        oldR2:           oldMetric?.r2   ?? null,
        oldVersion:      oldMetric?.version ?? null,
        trainingDuration: serverDuration,
      });
    }

    // Check if all requested types are done
    const allDone = types.every(t => !retrainingState.value[t].active);
    if (allDone) {
      stopRetrainTimer();
      // Use server duration for the elapsed display if available (only single model)
      if (types.length === 1 && retrainModal.value.results[0]?.trainingDuration) {
        retrainModal.value.elapsed = retrainModal.value.results[0].trainingDuration;
      }
      const anyFailed = retrainModal.value.results.some(r => r.status === 'failed');
      retrainModal.value.phase = anyFailed && retrainModal.value.results.every(r => r.status === 'failed') ? 'failed' : 'done';
    }
  };

  const poll = async () => {
    try {
      const response = await axios.get('/data-upload/retrain-status', { params: { model_type: modelType } });
      const data     = response.data;
      const typeData = data.per_type?.[modelType];

      if (!typeData?.is_training) {
        // Enforce minimum display time so user sees training phase
        const elapsed = Date.now() - pollStart;
        const delay   = Math.max(0, MIN_TRAINING_DISPLAY_MS - elapsed);
        setTimeout(() => finishType(typeData), delay);
        // Stop the polling interval
        clearInterval(retrainingState.value[modelType].polling);
        retrainingState.value[modelType].polling = null;
      }
      // else: still training — keep polling
    } catch (e) {
      // Silently ignore transient polling errors
    }
  };

  retrainingState.value[modelType].polling = setInterval(poll, 3000);
  // First poll after a short delay to let the job start
  setTimeout(poll, 1500);
};

// ── Upload management ────────────────────────────────────────────────────────
const retryUpload = async (uploadId) => {
  try {
    const response = await axios.post(`/data-upload/retry/${uploadId}`);
    if (response.data.success) {
      toast.info(response.data.message);
      processingUploadId.value = uploadId;
      startPolling(uploadId);
    } else {
      toast.error(response.data.message || 'Gagal retry');
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Gagal retry');
  }
};

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

const showResultsFromDetails = () => {
  if (!selectedUploadDetails.value) return;
  const trainingResults = parseTrainingResults(selectedUploadDetails.value.parsing_log);
  completionResults.value = {
    records_inserted: selectedUploadDetails.value.records_inserted,
    records_parsed:   selectedUploadDetails.value.records_parsed,
    hasModelResults:  !!trainingResults,
    ...(trainingResults || {}),
  };
  showDetailsModal.value = false;
  selectedUploadDetails.value = null;
  showCompletionModal.value = true;
};

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
    const response = await axios.delete(`/data-upload/${uploadId}`);
    localUploads.value = localUploads.value.filter(u => u.id !== uploadId);
    toast.success(response.data?.message || 'Upload berhasil dihapus');
  } catch (error) {
    if (error.response?.status === 429) {
      toast.error('Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.');
    } else {
      toast.error(error.response?.data?.message || 'Gagal menghapus upload');
    }
  }
};

const closeCompletionModal = (navigate = null) => {
  showCompletionModal.value = false;
  completionResults.value = null;
  if (navigate) {
    router.visit(navigate);
  } else {
    window.location.reload();
  }
};

// ── Log parsing ──────────────────────────────────────────────────────────────
const simplifyLogMessage = (message) => {
  const hidePatterns = [/Parsed sheet:/, /Processing sheet:/, /Room breakdown for/, /Daily data processed:/, /Found \d+ occupied rooms on/];
  for (const pattern of hidePatterns) {
    if (pattern.test(message)) return null;
  }
  const patterns = [
    { regex: /Starting to parse file: (.+)/, replacement: 'Membuka file: $1' },
    { regex: /Found (\d+) sheets/, replacement: 'Menemukan $1 sheet dalam file Excel' },
    { regex: /Skipping sheet: (.+)/, replacement: 'Melewati sheet: $1' },
    { regex: /Parsing complete: (\d+) days parsed/, replacement: 'Parsing selesai: $1 hari data siap disimpan' },
    { regex: /Inserted (\d+) records/, replacement: 'Berhasil menyimpan $1 records ke database' },
    { regex: /Dispatching model retraining/, replacement: 'Memulai proses training model LSTM...' },
    { regex: /Model retraining completed/, replacement: 'Training model selesai' },
    { regex: /Warning: Zero occupancy/, replacement: 'Ditemukan tanggal dengan okupansi 0%' },
    { regex: /Data berhasil disimpan/, replacement: 'Data berhasil disimpan ke database' },
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

const parseTrainingResults = (logs) => {
  if (!logs || logs.length === 0) return null;
  // [\w.\-]+ captures versions like "1.1.0" or "1.0.0-thesis"
  const trainingPattern = /Model retraining completed: v+([\w.\-]+),\s*MAPE:\s*([\d.]+)%,\s*R²:\s*(-?[\d.]+|N\/A),?\s*(PROMOTED|Not promoted)/i;
  for (const log of logs) {
    const message = log.message || '';
    const match   = message.match(trainingPattern);
    if (match) {
      // Avoid double "v" prefix if version already starts with v
      const ver = match[1].startsWith('v') ? match[1] : 'v' + match[1];
      return {
        version:  ver,
        mape:     parseFloat(match[2]),
        r2:       match[3] === 'N/A' ? null : parseFloat(match[3]),
        promoted: match[4].toUpperCase() === 'PROMOTED',
      };
    }
  }
  return null;
};

onUnmounted(() => {
  if (pollingInterval.value) clearInterval(pollingInterval.value);
  stopRetrainTimer();
  ['single', 'multi'].forEach(t => {
    if (retrainingState.value[t].polling) clearInterval(retrainingState.value[t].polling);
  });
});

const flowSteps = [
  { num: 1, label: 'Pilih File Excel',    desc: 'Format .xls / .xlsx, maks. 10MB',                           icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z' },
  { num: 2, label: 'Data Tersimpan',      desc: 'Sistem membaca dan menyimpan ke database',                  icon: 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 2.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125' },
  { num: 3, label: 'Perbarui Model Prediksi', desc: 'Tekan tombol Perbarui Model di bawah',                icon: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15' },
  { num: 4, label: 'Prediksi Diperbarui', desc: 'Model baru siap digunakan untuk prediksi',                  icon: 'M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5' },
];
</script>

<template>
  <DashboardLayout>
    <div class="px-6 py-6 space-y-5 max-w-[1400px] mx-auto">

      <!-- ── Page Header (navy banner) ───────────────────────────────────── -->
      <div class="bg-primary-dark rounded-2xl px-6 py-4 shadow-card flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-lg font-bold text-white leading-tight">Kelola Data</h1>
          <p class="text-sm text-white/60 mt-0.5">Tambahkan data bulanan untuk memperbarui sistem prediksi</p>
        </div>
        <div class="flex items-center gap-2 self-start sm:self-auto px-4 py-2 rounded-xl text-sm font-semibold bg-white/10 border border-white/20 text-white">
          <span class="w-2 h-2 rounded-full flex-shrink-0" :class="{ 'bg-green-400': modelHealthStatus.color === 'green', 'bg-amber-400': modelHealthStatus.color === 'yellow', 'bg-red-400': modelHealthStatus.color === 'red' }"></span>
          {{ modelHealthStatus.label }}
        </div>
      </div>

      <!-- ── Tab Navigation ─────────────────────────────────────────────── -->
      <div class="bg-white rounded-2xl border border-surface/30 shadow-card-md p-1.5 flex gap-1">
        <button
          v-for="tab in [
            { key: 'upload',  label: 'Unggah Data',   icon: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12' },
            { key: 'model',   label: 'Status Model',  icon: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15' },
            { key: 'history', label: 'Riwayat',       icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
          ]"
          :key="tab.key"
          @click="activeTab = tab.key"
          class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all"
          :class="activeTab === tab.key ? 'bg-primary text-white shadow-sm' : 'text-gray-500 hover:bg-surface/50 hover:text-primary-dark'"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
          </svg>
          {{ tab.label }}
        </button>
      </div>

      <!-- ── TAB 1: Unggah Data ─────────────────────────────────────────── -->
      <div v-show="activeTab === 'upload'">

      <!-- ── Upload Card (full width) ─────────────────────────────────────── -->
      <div class="bg-white rounded-2xl shadow-card-md border border-surface/30 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-surface/20 flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold text-primary-dark">Tambah Data Baru</h2>
            <p class="text-xs text-gray-400 mt-0.5">Format .xls / .xlsx • Maks. 10MB • Satu file = satu bulan</p>
          </div>
          <span class="text-xs px-3 py-1.5 bg-primary/10 text-primary rounded-xl font-semibold">Langkah 1 &amp; 2</span>
        </div>
        <div class="p-5">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
            <!-- Dropzone -->
            <div>
              <div
                @dragover.prevent="dragOver = true"
                @dragleave.prevent="dragOver = false"
                @drop="handleDrop"
                :class="[
                  'border-2 border-dashed rounded-2xl transition-all cursor-pointer',
                  dragOver      ? 'border-primary bg-primary/5 scale-[1.01]' :
                  selectedFile  ? 'border-green-400/60 bg-green-50/30' :
                                  'border-surface hover:border-primary/40 hover:bg-surface/20'
                ]"
                @click="$refs.fileInput.click()"
              >
                <input ref="fileInput" type="file" accept=".xls,.xlsx" class="hidden" @change="handleFileSelect" />

                <!-- Empty state -->
                <div v-if="!selectedFile" class="py-10 flex flex-col items-center gap-3">
                  <div class="w-14 h-14 bg-primary/10 rounded-2xl flex items-center justify-center">
                    <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12l-3-3m0 0l-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                  </div>
                  <div class="text-center">
                    <p class="text-sm text-gray-700"><span class="font-semibold text-primary">Klik untuk memilih file</span> atau seret ke sini</p>
                    <p class="text-xs text-gray-400 mt-1">Excel .xls atau .xlsx — maks. 10MB</p>
                  </div>
                </div>

                <!-- File selected -->
                <div v-else class="px-5 py-4 flex items-center gap-3">
                  <div class="w-10 h-10 bg-green-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-900 text-sm truncate">{{ selectedFile.name }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ formatFileSize(selectedFile.size) }} • Siap diupload</p>
                  </div>
                  <button @click.stop="clearFile" class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Upload progress -->
              <div v-if="uploading" class="mt-3 space-y-1.5">
                <div class="flex justify-between text-xs text-gray-500">
                  <span>Mengupload...</span>
                  <span class="font-semibold text-primary">{{ uploadProgress }}%</span>
                </div>
                <div class="w-full bg-surface rounded-full h-1.5 overflow-hidden">
                  <div class="h-full bg-primary rounded-full transition-all" :style="{ width: uploadProgress + '%' }"></div>
                </div>
              </div>

              <!-- CTA -->
              <button
                @click="validateFile"
                :disabled="!selectedFile || validating || uploading"
                class="mt-4 w-full py-3.5 bg-primary hover:bg-primary-dark text-white font-semibold rounded-xl transition-all disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2 text-sm"
              >
                <svg v-if="validating || uploading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4-4m0 0l-4 4m4-4v12" />
                </svg>
                {{ validating ? 'Memeriksa data...' : uploading ? 'Mengunggah...' : 'Unggah & Simpan Data' }}
              </button>
            </div>

            <!-- Info panel -->
            <div class="space-y-3">
              <div class="p-4 bg-primary/5 rounded-xl border border-primary/15">
                <p class="text-xs font-semibold text-primary mb-2">Setelah upload selesai:</p>
                <ul class="space-y-1.5 text-xs text-gray-600">
                  <li class="flex items-start gap-2">
                    <svg class="w-3.5 h-3.5 text-primary mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                    Data tersimpan otomatis ke database
                  </li>
                  <li class="flex items-start gap-2">
                    <svg class="w-3.5 h-3.5 text-primary mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                    Model <strong>tidak langsung diperbarui</strong> — buka tab "Status Model" untuk memperbarui
                  </li>
                  <li class="flex items-start gap-2">
                    <svg class="w-3.5 h-3.5 text-primary mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                    Upload data bulan yang terlewat tetap diperbolehkan
                  </li>
                </ul>
              </div>
              <div class="p-4 bg-surface/40 rounded-xl border border-surface text-xs text-gray-500 leading-relaxed">
                <span class="font-semibold text-primary-dark">Format file:</span> Satu file Excel = satu bulan data. Setiap sheet = satu hari (nama: "01 JANUARI 2026"). File asli tidak berubah setelah upload.
              </div>
            </div>
          </div>
        </div>
      </div>

      </div><!-- end TAB 1: Unggah Data -->

      <!-- ── TAB 2: Status Model ─────────────────────────────────────────── -->
      <div v-show="activeTab === 'model'">

      <!-- ── Model Status + Manual Retrain ───────────────────────────────── -->
      <div class="bg-white rounded-2xl shadow-card-md border border-surface/30 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-surface/20">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
              <h2 class="text-sm font-semibold text-primary-dark">Status Sistem Prediksi</h2>
              <p class="text-xs text-gray-400 mt-1 leading-relaxed max-w-lg">
                Setelah upload data baru, tekan <span class="font-semibold text-primary-dark">"Perbarui Kedua Model"</span> untuk memperbarui prediksi. Model hanya diganti jika hasil pembaruan lebih baik dari versi aktif saat ini.
              </p>
            </div>
            <button
              @click="triggerRetrain('both')"
              :disabled="retrainingState.single.active || retrainingState.multi.active"
              class="flex-shrink-0 flex items-center gap-2 px-5 py-2.5 text-sm font-semibold bg-primary text-white rounded-xl hover:bg-primary-dark transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
            >
              <svg class="w-4 h-4" :class="(retrainingState.single.active || retrainingState.multi.active) ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              {{ (retrainingState.single.active || retrainingState.multi.active) ? 'Sedang Memperbarui...' : 'Perbarui Kedua Model' }}
            </button>
          </div>
        </div>

        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">

          <!-- Single model card -->
          <div class="rounded-2xl border border-surface/30 p-5 transition-all"
            :class="{
              'border-primary/40 bg-primary/5':   retrainingStatus.single?.color === 'red',
              'border-amber-200 bg-amber-50/20':  retrainingStatus.single?.color === 'yellow',
              'bg-surface/10':  !['red','yellow'].includes(retrainingStatus.single?.color),
            }">
            <div class="flex items-start justify-between mb-4">
              <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-0.5">Prediksi Total Hotel</p>
                <p class="text-xs text-gray-400 mb-1">Prediksi total tingkat hunian hotel</p>
                <p class="text-lg font-bold text-primary-dark">{{ formatVersionLabel(champions?.single?.version, champions?.single?.trained_at) }}</p>
              </div>
              <span class="text-xs px-3 py-1 rounded-xl font-semibold"
                :class="{
                  'bg-primary/10 text-primary-dark': retrainingStatus.single?.color === 'red',
                  'bg-amber-100 text-amber-700':     retrainingStatus.single?.color === 'yellow',
                  'bg-green-100 text-green-700':     !['red','yellow'].includes(retrainingStatus.single?.color),
                }">
                {{ retrainingStatus.single?.color === 'red' ? 'Perlu Diperbarui' : retrainingStatus.single?.color === 'yellow' ? 'Disarankan Diperbarui' : 'Berjalan Baik' }}
              </span>
            </div>

            <div v-if="champions?.single" class="grid grid-cols-2 gap-3 mb-4">
              <div class="bg-white rounded-xl p-3 border border-surface/60 text-center">
                <p class="text-xs text-gray-400 mb-1">Akurasi Prediksi</p>
                <p class="text-xl font-bold text-primary-dark">{{ displayAccuracy(champions.single.mape) }}</p>
              </div>
              <div class="bg-white rounded-xl p-3 border border-surface/60 text-center">
                <p class="text-xs text-gray-400 mb-1">Data Belum Diproses</p>
                <p class="text-xl font-bold" :class="(retrainingStatus.single?.new_data_months ?? 0) > 0 ? 'text-amber-600' : 'text-primary-dark'">
                  {{ retrainingStatus.single?.new_data_months ?? 0 }}
                  <span class="text-sm font-normal text-gray-400 ml-1">bln</span>
                </p>
                <p v-if="(retrainingStatus.single?.new_data_months ?? 0) === 0" class="text-[10px] text-green-600 font-medium mt-0.5">model ter-update</p>
              </div>
            </div>
            <div v-else class="mb-4 p-3 bg-white rounded-xl border border-surface/60 text-center">
              <p class="text-sm text-gray-400">Belum ada model aktif</p>
            </div>

            <p class="text-sm text-gray-500 leading-relaxed mb-4">{{ retrainingStatus.single?.message }}</p>

            <button @click="triggerRetrain('single')"
              :disabled="retrainingState.single.active || retrainingState.multi.active"
              class="w-full py-2.5 text-sm font-semibold rounded-xl transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed border border-primary/30 text-primary hover:bg-primary hover:text-white hover:border-primary">
              <svg class="w-4 h-4" :class="retrainingState.single.active ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              {{ retrainingState.single.active ? 'Sedang Memperbarui...' : 'Perbarui Model Ini Saja' }}
            </button>
          </div>

          <!-- Multi model card -->
          <div class="rounded-2xl border border-surface/30 p-5 transition-all"
            :class="{
              'border-primary/40 bg-primary/5':   retrainingStatus.multi?.color === 'red',
              'border-amber-200 bg-amber-50/20':  retrainingStatus.multi?.color === 'yellow',
              'bg-surface/10':  !['red','yellow'].includes(retrainingStatus.multi?.color),
            }">
            <div class="flex items-start justify-between mb-4">
              <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-0.5">Prediksi Per Tipe Kamar</p>
                <p class="text-xs text-gray-400 mb-1">Standard, Superior, Junior Suite, Family</p>
                <p class="text-lg font-bold text-primary-dark">{{ formatVersionLabel(champions?.multi?.version, champions?.multi?.trained_at) }}</p>
              </div>
              <span class="text-xs px-3 py-1 rounded-xl font-semibold"
                :class="{
                  'bg-primary/10 text-primary-dark': retrainingStatus.multi?.color === 'red',
                  'bg-amber-100 text-amber-700':     retrainingStatus.multi?.color === 'yellow',
                  'bg-green-100 text-green-700':     !['red','yellow'].includes(retrainingStatus.multi?.color),
                }">
                {{ retrainingStatus.multi?.color === 'red' ? 'Perlu Diperbarui' : retrainingStatus.multi?.color === 'yellow' ? 'Disarankan Diperbarui' : 'Berjalan Baik' }}
              </span>
            </div>

            <div v-if="champions?.multi" class="grid grid-cols-2 gap-3 mb-4">
              <div class="bg-white rounded-xl p-3 border border-surface/60 text-center">
                <p class="text-xs text-gray-400 mb-1">Akurasi Prediksi</p>
                <p class="text-xl font-bold text-primary-dark">{{ displayAccuracy(champions.multi.mape) }}</p>
              </div>
              <div class="bg-white rounded-xl p-3 border border-surface/60 text-center">
                <p class="text-xs text-gray-400 mb-1">Data Belum Diproses</p>
                <p class="text-xl font-bold" :class="(retrainingStatus.multi?.new_data_months ?? 0) > 0 ? 'text-amber-600' : 'text-primary-dark'">
                  {{ retrainingStatus.multi?.new_data_months ?? 0 }}
                  <span class="text-sm font-normal text-gray-400 ml-1">bln</span>
                </p>
                <p v-if="(retrainingStatus.multi?.new_data_months ?? 0) === 0" class="text-[10px] text-green-600 font-medium mt-0.5">model ter-update</p>
              </div>
            </div>
            <div v-else class="mb-4 p-3 bg-white rounded-xl border border-surface/60 text-center">
              <p class="text-sm text-gray-400">Belum ada model aktif</p>
            </div>

            <p class="text-sm text-gray-500 leading-relaxed mb-4">{{ retrainingStatus.multi?.message }}</p>

            <button @click="triggerRetrain('multi')"
              :disabled="retrainingState.single.active || retrainingState.multi.active"
              class="w-full py-2.5 text-sm font-semibold rounded-xl transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed border border-primary/30 text-primary hover:bg-primary hover:text-white hover:border-primary">
              <svg class="w-4 h-4" :class="retrainingState.multi.active ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              {{ retrainingState.multi.active ? 'Sedang Memperbarui...' : 'Perbarui Model Ini Saja' }}
            </button>
          </div>
        </div>
      </div>

      </div><!-- end TAB 2: Status Model -->

      <!-- ── TAB 3: Riwayat ─────────────────────────────────────────────── -->
      <div v-show="activeTab === 'history'">

      <!-- ── History tabs (no collapsible, always shown) ───────────────── -->
      <div class="rounded-2xl overflow-hidden bg-white border border-surface/30 shadow-card-md">
        <div class="px-5 py-4 border-b border-surface/20 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-primary-dark">Riwayat Upload &amp; Versi Model</h2>
        </div>
        <div class="flex border-b border-surface/20">
          <button @click="activeHistoryTab = 'uploads'"
            class="flex-1 px-6 py-4 text-sm font-semibold transition-colors flex items-center justify-center gap-2"
            :class="activeHistoryTab === 'uploads' ? 'text-primary border-b-2 border-primary' : 'text-gray-500 hover:text-gray-700 hover:bg-surface/20'">
            Riwayat Upload
            <span class="text-xs px-2 py-0.5 rounded-full bg-surface text-gray-500">{{ uploads?.total ?? localUploads.length }}</span>
          </button>
          <button @click="activeHistoryTab = 'models'"
            class="flex-1 px-6 py-4 text-sm font-semibold transition-colors flex items-center justify-center gap-2"
            :class="activeHistoryTab === 'models' ? 'text-primary border-b-2 border-primary' : 'text-gray-500 hover:text-gray-700 hover:bg-surface/20'">
            Versi Model
            <span class="text-xs px-2 py-0.5 rounded-full bg-surface text-gray-500">{{ modelVersions.length }}</span>
          </button>
        </div>

        <!-- Upload History -->
        <div v-show="activeHistoryTab === 'uploads'">
          <div v-if="localUploads.length === 0" class="py-16 text-center">
            <div class="w-12 h-12 bg-surface/50 rounded-2xl flex items-center justify-center mx-auto mb-3">
              <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <p class="text-sm text-gray-400">Belum ada file yang diupload</p>
          </div>
          <div v-else class="divide-y divide-surface/20">
            <div v-for="upload in localUploads" :key="upload.id"
              @click="showUploadDetails(upload.id)"
              class="px-6 md:px-8 py-4 hover:bg-surface/10 transition-colors cursor-pointer">
              <div class="flex items-center gap-4">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                  :class="{
                    'bg-surface/60': upload.status_color === 'gray',
                    'bg-primary/10': ['blue','indigo','green'].includes(upload.status_color),
                    'bg-surface': upload.status_color === 'purple',
                    'bg-red-50': upload.status_color === 'red',
                  }">
                  <svg class="w-4 h-4" :class="{
                    'text-gray-400': upload.status_color === 'gray',
                    'text-primary': ['blue','indigo','green'].includes(upload.status_color),
                    'text-primary animate-spin': upload.status_color === 'purple',
                    'text-red-500': upload.status_color === 'red',
                  }" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="getStatusIcon(upload.status)" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <p class="text-sm font-semibold text-primary-dark truncate">{{ upload.file_name }}</p>
                    <span :class="['text-xs px-2 py-0.5 rounded-lg font-medium border', getStatusBadgeClass(upload.status_color)]">{{ upload.status_label }}</span>
                  </div>
                  <div class="flex items-center gap-3 mt-0.5">
                    <p class="text-xs text-gray-400">{{ upload.created_at }}</p>
                    <p v-if="upload.records_inserted > 0" class="text-xs text-primary font-medium">{{ upload.records_inserted }} data tersimpan</p>
                  </div>
                  <p v-if="upload.error_message" class="text-xs text-red-500 mt-0.5 truncate">{{ upload.error_message }}</p>
                </div>
                <div class="flex gap-1 flex-shrink-0" @click.stop>
                  <button v-if="upload.status === 'failed'" @click="retryUpload(upload.id)" class="px-3 py-1.5 text-xs font-medium text-primary hover:bg-primary/10 rounded-lg transition-colors">Coba Lagi</button>
                  <button @click="confirmDelete(upload.id)" class="px-3 py-1.5 text-xs font-medium text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">Hapus</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Upload Pagination -->
          <div v-if="(uploads?.last_page ?? 1) > 1" class="px-6 py-3 border-t border-surface/20">
            <Pagination
              :current-page="uploads?.current_page ?? 1"
              :total-pages="uploads?.last_page ?? 1"
              :total="uploads?.total ?? 0"
              :per-page="uploads?.per_page ?? 10"
              item-label="riwayat upload"
              @change="goToUploadsPage"
            />
          </div>
        </div>

        <!-- Model Versions -->
        <div v-show="activeHistoryTab === 'models'">
          <div v-if="modelVersions.length === 0" class="py-16 text-center">
            <p class="text-sm text-gray-400">Belum ada riwayat model</p>
          </div>
          <div v-else>
            <div class="flex gap-2 px-6 md:px-8 py-3 border-b border-surface/20">
              <button v-for="tab in [
                { key: 'all',    label: 'Semua',         count: modelVersions.length },
                { key: 'single', label: 'Single-Output', count: singleModels.length },
                { key: 'multi',  label: 'Multi-Output',  count: multiModels.length },
              ]" :key="tab.key"
                @click="modelTypeFilter = tab.key"
                :class="['px-3 py-1.5 text-xs font-semibold rounded-xl transition-all', modelTypeFilter === tab.key ? 'bg-primary text-white' : 'text-gray-500 hover:bg-surface/60']">
                {{ tab.label }} ({{ tab.count }})
              </button>
            </div>
            <div class="divide-y divide-surface/20 max-h-72 overflow-y-auto">
              <div v-for="version in filteredModelVersions" :key="version.id"
                class="px-6 md:px-8 py-4 transition-colors"
                :class="version.is_champion ? 'bg-primary/5' : 'hover:bg-surface/10'">
                <div class="flex items-center justify-between gap-4">
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0"
                      :class="version.is_champion ? 'bg-primary/10' : 'bg-surface/60'">
                      <svg v-if="version.is_champion" class="w-4 h-4 text-primary" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                      </svg>
                      <span v-else class="w-2 h-2 rounded-full bg-gray-300"></span>
                    </div>
                    <div class="min-w-0">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-sm font-bold text-primary-dark">{{ version.version }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-lg font-medium bg-surface text-gray-500">{{ version.model_type === 'single' ? 'Single-Output' : version.model_type === 'multi' ? 'Multi-Output' : version.model_type }}</span>
                        <span v-if="version.is_champion" class="text-xs px-2 py-0.5 rounded-lg font-semibold bg-primary text-white">Aktif</span>
                      </div>
                      <div class="flex items-center gap-3 mt-0.5">
                        <span v-if="version.mape != null" class="text-xs text-gray-400">Akurasi <span class="font-semibold text-primary-dark">{{ displayAccuracy(version.mape) }}</span></span>
                        <span class="text-xs text-gray-400">{{ version.created_at }}</span>
                      </div>
                    </div>
                  </div>
                  <span class="text-xs px-2.5 py-1 rounded-lg font-medium flex-shrink-0"
                    :class="{
                      'bg-green-50 text-green-700': version.status === 'completed',
                      'bg-red-100 text-red-700':    version.status === 'failed',
                      'bg-primary/10 text-primary':  version.status === 'training',
                      'bg-surface text-gray-500':    !['completed','failed','training'].includes(version.status),
                    }">
                    {{ version.status === 'completed' ? 'Selesai' : version.status === 'failed' ? 'Gagal' : version.status === 'training' ? 'Melatih' : version.status }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div><!-- end history card -->

      </div><!-- end TAB 3: Riwayat -->

    </div>

    <!-- ── Loading Modal (Upload processing) ─────────────────────────────── -->
    <LoadingModal :show="loadingModal.show" title="Memproses Data" :message="loadingModal.message">
      <template #content>
        <div class="mt-5 space-y-4">
          <!-- Step indicators -->
          <div class="flex items-center justify-center gap-4">
            <!-- Step: Baca -->
            <div class="flex items-center gap-1.5">
              <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0"
                :class="loadingModal.currentStep === 'parsing' ? 'bg-primary' : loadingModal.currentStep === 'inserting' ? 'bg-green-500' : 'bg-surface'">
                <svg v-if="loadingModal.currentStep === 'inserting'" class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <svg v-else-if="loadingModal.currentStep === 'parsing'" class="w-3 h-3 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span v-else class="w-2 h-2 rounded-full bg-gray-300"></span>
              </div>
              <span class="text-xs font-medium" :class="loadingModal.currentStep === 'parsing' ? 'text-primary' : loadingModal.currentStep === 'inserting' ? 'text-green-600' : 'text-gray-300'">Baca</span>
            </div>

            <span class="text-gray-200">—</span>

            <!-- Step: Simpan -->
            <div class="flex items-center gap-1.5">
              <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0"
                :class="loadingModal.currentStep === 'inserting' ? 'bg-primary' : 'bg-surface'">
                <svg v-if="loadingModal.currentStep === 'inserting'" class="w-3 h-3 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span v-else class="w-2 h-2 rounded-full bg-gray-300"></span>
              </div>
              <span class="text-xs font-medium" :class="loadingModal.currentStep === 'inserting' ? 'text-primary' : 'text-gray-300'">Simpan</span>
            </div>
          </div>

          <div class="w-full bg-surface rounded-full h-1.5 overflow-hidden">
            <div class="h-full bg-primary rounded-full animate-pulse w-full"></div>
          </div>
          <p class="text-xs text-center text-gray-400">Mohon tunggu, jangan tutup halaman ini...</p>
        </div>
      </template>
    </LoadingModal>

    <!-- ── Confirmation Modal ─────────────────────────────────────────────── -->
    <teleport to="body">
      <div v-if="showConfirmationModal && filePreview"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
        @click.self="cancelUpload">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full flex flex-col max-h-[90vh]">
          <!-- Header -->
          <div class="bg-gradient-to-br from-primary to-primary-dark px-7 pt-6 pb-5 rounded-t-2xl flex items-start justify-between flex-shrink-0">
            <div>
              <h3 class="text-lg font-bold text-white">Ringkasan Data</h3>
              <p class="text-white/70 text-sm mt-0.5">Periksa sebelum diproses</p>
            </div>
            <button @click="cancelUpload" class="p-1.5 rounded-xl text-white/60 hover:text-white hover:bg-white/10 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="px-7 py-5 overflow-y-auto flex-1 space-y-4">
            <!-- File name -->
            <div class="flex items-center gap-3 px-4 py-3 bg-surface/40 rounded-xl border border-surface">
              <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
              </svg>
              <p class="text-sm font-medium text-primary-dark truncate">{{ selectedFile?.name }}</p>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-3">
              <div class="p-3 bg-surface/30 rounded-xl border border-surface text-center">
                <p class="text-xl font-bold text-primary-dark">{{ filePreview.total_days }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Hari Data</p>
              </div>
              <div class="p-3 bg-surface/30 rounded-xl border border-surface text-center">
                <p class="text-xl font-bold text-primary-dark">{{ filePreview.total_records }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Record</p>
              </div>
              <div class="p-3 bg-surface/30 rounded-xl border border-surface text-center">
                <p class="text-xl font-bold text-primary-dark">{{ filePreview.room_types?.length ?? 0 }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Tipe Kamar</p>
              </div>
            </div>

            <!-- Date range -->
            <div class="flex items-center gap-3 p-3 bg-surface/20 rounded-xl border border-surface">
              <div class="flex-1 text-center">
                <p class="text-xs text-gray-400 mb-1">Mulai</p>
                <p class="text-sm font-semibold text-primary-dark">{{ formatDate(filePreview.date_range?.start) }}</p>
              </div>
              <svg class="w-4 h-4 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
              </svg>
              <div class="flex-1 text-center">
                <p class="text-xs text-gray-400 mb-1">Selesai</p>
                <p class="text-sm font-semibold text-primary-dark">{{ formatDate(filePreview.date_range?.end) }}</p>
              </div>
            </div>

            <!-- Period check banner -->
            <div v-if="filePreview.expected_period"
              :class="filePreview.is_future
                ? 'bg-red-50 border-red-200 text-red-700'
                : filePreview.period_match
                  ? 'bg-green-50 border-green-200 text-green-700'
                  : 'bg-amber-50 border-amber-200 text-amber-700'"
              class="flex items-start gap-3 p-3 rounded-xl border text-sm">
              <svg v-if="filePreview.is_future" class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
              <svg v-else-if="filePreview.period_match" class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              <svg v-else class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
              <div>
                <p class="font-semibold">
                  <template v-if="filePreview.is_future">Data tidak bisa diupload — periode belum terjadi</template>
                  <template v-else-if="filePreview.period_match">Periode sesuai — {{ filePreview.expected_period }}</template>
                  <template v-else>Periode tidak sesuai ekspektasi</template>
                </p>
                <p class="text-xs mt-1 opacity-80 leading-relaxed">
                  <template v-if="filePreview.is_future">Data yang diupload ({{ formatDate(filePreview.date_range?.start) }}) adalah data masa depan. Upload hanya untuk data yang sudah terjadi.</template>
                  <template v-else-if="filePreview.period_match">Data bulan ini akan ditambahkan ke database. Tekan "Perbarui Model" setelah upload untuk memperbarui sistem prediksi.</template>
                  <template v-else>Data periode {{ formatDate(filePreview.date_range?.start) }} akan ditambahkan ke histori. Ekspektasi sistem: {{ filePreview.expected_period }}. Pastikan data bulan yang terlewat juga diupload untuk hasil prediksi terbaik.</template>
                </p>
              </div>
            </div>

            <!-- Room types -->
            <div>
              <p class="text-xs text-gray-400 mb-2">Tipe Kamar Ditemukan</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="roomType in filePreview.room_types" :key="roomType"
                  class="px-3 py-1 bg-primary/10 text-primary text-xs font-semibold rounded-lg">{{ roomType }}</span>
              </div>
            </div>
          </div>

          <div class="px-7 py-5 flex items-center justify-end gap-3 border-t border-surface/30 flex-shrink-0">
            <button @click="cancelUpload" class="px-5 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:bg-surface/50 rounded-xl transition-colors">Batal</button>
            <button v-if="!filePreview.is_future" @click="confirmAndUpload"
              class="px-6 py-2.5 text-sm font-semibold bg-primary text-white rounded-xl hover:bg-primary-dark transition-colors">
              Proses Data
            </button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- ── Upload Details Modal ───────────────────────────────────────────── -->
    <teleport to="body">
      <div v-if="showDetailsModal && selectedUploadDetails"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
        @click.self="closeDetailsModal">
        <div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full max-h-[85vh] flex flex-col">
          <div class="px-7 pt-6 pb-5 flex items-start justify-between border-b border-surface/30 flex-shrink-0">
            <div>
              <h3 class="text-lg font-bold text-primary-dark">Detail Riwayat</h3>
              <p class="text-sm text-gray-400 mt-0.5 truncate max-w-xs">{{ selectedUploadDetails.file_name ?? '' }}</p>
            </div>
            <button @click="closeDetailsModal" class="p-1.5 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-surface/50 transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="px-7 py-4 grid grid-cols-2 gap-3 border-b border-surface/20 flex-shrink-0">
            <div class="p-3 bg-surface/30 rounded-xl">
              <p class="text-xs text-gray-400 mb-1">Status</p>
              <span :class="['inline-flex px-2.5 py-1 text-xs font-semibold rounded-lg border', getStatusBadgeClass(selectedUploadDetails.status_color)]">{{ selectedUploadDetails.status_label }}</span>
            </div>
            <div class="p-3 bg-surface/30 rounded-xl">
              <p class="text-xs text-gray-400 mb-1">Data Tersimpan</p>
              <p class="text-lg font-bold text-primary-dark">{{ selectedUploadDetails.records_inserted || 0 }} <span class="text-xs font-normal text-gray-400">record</span></p>
            </div>
          </div>

          <div class="px-7 py-5 overflow-y-auto flex-1">
            <div v-if="selectedUploadDetails.error_message" class="mb-4 flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-xl">
              <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              <div>
                <p class="text-sm font-semibold text-red-800">Terjadi Error</p>
                <p class="text-xs text-red-600 mt-0.5 leading-relaxed">{{ selectedUploadDetails.error_message }}</p>
              </div>
            </div>

            <div v-if="selectedUploadDetails.parsing_log && selectedUploadDetails.parsing_log.length > 0">
              <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Log Proses</p>
              <div class="space-y-1.5">
                <div v-for="(log, index) in selectedUploadDetails.parsing_log" :key="index"
                  v-show="simplifyLogMessage(log.message) !== null"
                  class="flex items-start gap-2 px-3 py-2 rounded-lg text-xs"
                  :class="{
                    'bg-red-50 border border-red-100':    log.level === 'error',
                    'bg-amber-50 border border-amber-100': log.level === 'warning',
                    'bg-surface/40':                      log.level === 'info',
                  }">
                  <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" :class="{ 'text-red-500': log.level === 'error', 'text-amber-500': log.level === 'warning', 'text-primary': log.level === 'info' }" fill="currentColor" viewBox="0 0 20 20">
                    <path v-if="log.level === 'error'" fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    <path v-else-if="log.level === 'warning'" fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    <path v-else fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  <p :class="{ 'text-red-700': log.level === 'error', 'text-amber-700': log.level === 'warning', 'text-gray-600': log.level === 'info' }" class="leading-relaxed">{{ simplifyLogMessage(log.message) }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="px-7 py-4 flex items-center justify-end gap-3 border-t border-surface/30 flex-shrink-0">
            <button v-if="selectedUploadDetails.status === 'completed'" @click="showResultsFromDetails"
              class="px-5 py-2.5 text-sm font-semibold bg-primary text-white rounded-xl hover:bg-primary-dark transition-colors">Lihat Hasil</button>
            <button @click="closeDetailsModal" class="px-5 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:bg-surface/50 rounded-xl transition-colors">Tutup</button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- ── Completion Modal ──────────────────────────────────────────────── -->
    <teleport to="body">
      <div v-if="showCompletionModal && completionResults"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
        @click.self="closeCompletionModal">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full">
          <!-- Header -->
          <div class="bg-gradient-to-br from-green-500 to-green-600 px-7 pt-8 pb-6 text-center rounded-t-2xl">
            <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
              <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-lg font-bold text-white">Data Berhasil Disimpan!</h3>
            <p class="text-white/80 text-sm mt-1">Data tersimpan ke database</p>
          </div>

          <div class="px-7 py-5 space-y-4">
            <!-- Data stats -->
            <div class="grid grid-cols-2 gap-3">
              <div class="p-3 bg-surface/30 rounded-xl border border-surface text-center">
                <p class="text-xl font-bold text-primary-dark">{{ completionResults.records_inserted }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Data Tersimpan</p>
              </div>
              <div class="p-3 bg-surface/30 rounded-xl border border-surface text-center">
                <p class="text-xl font-bold text-primary-dark">{{ completionResults.records_parsed }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Hari Diproses</p>
              </div>
            </div>

            <!-- Model result (only if available) -->
            <div v-if="completionResults.hasModelResults" class="border border-surface rounded-xl overflow-hidden">
              <div class="flex items-center justify-between px-4 py-3 bg-surface/20 border-b border-surface">
                <span class="text-xs font-semibold text-gray-500">Versi Model Baru</span>
                <span class="text-sm font-bold text-primary-dark">{{ completionResults.version }}</span>
              </div>
              <div class="flex items-center justify-between px-4 py-3">
                <span class="text-sm text-gray-500">Akurasi Prediksi</span>
                <span class="text-sm font-bold text-primary-dark">{{ displayAccuracy(completionResults.mape) }}</span>
              </div>
              <!-- Promotion status -->
              <div class="px-4 pb-3">
                <div class="flex items-start gap-2 p-3 rounded-xl border text-xs"
                  :class="completionResults.promoted ? 'bg-primary/5 border-primary/20 text-primary-dark' : 'bg-amber-50 border-amber-200 text-amber-800'">
                  <svg class="w-4 h-4 flex-shrink-0 mt-0.5" :class="completionResults.promoted ? 'text-primary' : 'text-amber-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path v-if="completionResults.promoted" stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  <span class="font-semibold">{{ completionResults.promoted ? 'Model Baru Aktif' : 'Model Lama Dipertahankan' }}</span>
                </div>
              </div>
            </div>

            <!-- Next step hint -->
            <div class="flex items-start gap-2 p-3 bg-primary/5 rounded-xl border border-primary/15 text-xs text-primary-dark">
              <svg class="w-4 h-4 text-primary flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Buka tab <strong>"Status Model"</strong> untuk memperbarui sistem prediksi dengan data baru ini.</span>
            </div>
          </div>

          <div class="px-7 pb-6 flex gap-3">
            <button @click="() => { showCompletionModal = false; completionResults = null; activeTab = 'model'; }"
              class="flex-1 py-2.5 text-sm font-semibold border-2 border-primary text-primary rounded-xl hover:bg-primary/5 transition-colors">
              Perbarui Model
            </button>
            <button @click="closeCompletionModal()"
              class="flex-1 py-2.5 text-sm font-semibold bg-primary text-white rounded-xl hover:bg-primary-dark transition-colors">
              Selesai
            </button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- ── Retrain Progress Modal ───────────────────────────────────────── -->
    <teleport to="body">
      <div v-if="retrainModal.show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh]">

          <!-- Header — compact, changes by phase -->
          <div class="rounded-t-2xl px-6 py-5 flex items-center gap-4 flex-shrink-0"
            :class="{
              'bg-gradient-to-br from-primary to-primary-dark': retrainModal.phase === 'starting' || retrainModal.phase === 'training',
              'bg-gradient-to-br from-green-500 to-green-600':  retrainModal.phase === 'done',
              'bg-gradient-to-br from-red-500 to-red-600':      retrainModal.phase === 'failed',
            }">
            <!-- Icon -->
            <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0 bg-white/20">
              <svg v-if="retrainModal.phase === 'starting' || retrainModal.phase === 'training'" class="w-6 h-6 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <svg v-else-if="retrainModal.phase === 'done'" class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <svg v-else class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-base font-bold text-white leading-tight">
                <template v-if="retrainModal.phase === 'starting'">Memulai Pelatihan...</template>
                <template v-else-if="retrainModal.phase === 'training'">Sedang Melatih Model</template>
                <template v-else-if="retrainModal.phase === 'done'">Pelatihan Selesai</template>
                <template v-else>Pelatihan Gagal</template>
              </h3>
              <p class="text-white/75 text-xs mt-0.5">
                <template v-if="retrainModal.phase === 'starting' || retrainModal.phase === 'training'">
                  {{ retrainModal.modelType === 'both' ? 'Single-Output &amp; Multi-Output' : retrainModelLabel(retrainModal.modelType) }}
                  <span class="ml-2 font-bold tabular-nums">{{ formatElapsed(retrainModal.elapsed) }}</span>
                </template>
                <template v-else-if="retrainModal.phase === 'done'">
                  Durasi pelatihan: {{ formatElapsed(retrainModal.elapsed) }}
                </template>
                <template v-else>{{ retrainModal.error }}</template>
              </p>
            </div>
          </div>

          <!-- Body — training phase -->
          <div v-if="retrainModal.phase === 'starting' || retrainModal.phase === 'training'" class="px-6 py-5 space-y-4 flex-shrink-0">
            <!-- Animated progress bar -->
            <div class="space-y-2">
              <div class="flex justify-between text-xs text-gray-500">
                <span>Melatih model LSTM dari data historis...</span>
                <span class="font-semibold text-primary">{{ formatElapsed(retrainModal.elapsed) }}</span>
              </div>
              <div class="w-full bg-surface rounded-full h-2.5 overflow-hidden">
                <div class="h-full bg-gradient-to-r from-primary to-primary-dark rounded-full animate-[shimmer_2s_ease-in-out_infinite]" style="background-size:200% 100%;animation:shimmer 2s ease-in-out infinite;width:100%"></div>
              </div>
            </div>
            <!-- Status steps -->
            <div class="space-y-2">
              <div class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary/5 border border-primary/10">
                <svg class="w-4 h-4 text-primary animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span class="text-xs text-primary font-medium">Sistem prediksi sedang belajar dari seluruh data historis</span>
              </div>
              <p class="text-xs text-gray-400 text-center">Proses ini memerlukan 1–3 menit. Jangan tutup halaman ini.</p>
            </div>
          </div>

          <!-- Body — done phase: scrollable results -->
          <div v-else-if="retrainModal.phase === 'done'" class="overflow-y-auto flex-1 px-6 py-4 space-y-3">
            <div v-for="result in retrainModal.results" :key="result.type"
              class="rounded-xl border-2 overflow-hidden"
              :class="result.promoted ? 'border-green-200' : result.status === 'failed' ? 'border-red-200' : 'border-surface'">

              <!-- Model header row -->
              <div class="px-4 py-3 flex items-center justify-between gap-2"
                :class="result.promoted ? 'bg-green-50' : result.status === 'failed' ? 'bg-red-50' : 'bg-surface/40'">
                <div class="flex items-center gap-2 min-w-0">
                  <span class="text-xs font-bold uppercase tracking-wider flex-shrink-0"
                    :class="result.promoted ? 'text-green-700' : result.status === 'failed' ? 'text-primary-dark' : 'text-gray-500'">
                    {{ result.type === 'single' ? 'Total Hotel' : 'Per Tipe Kamar' }}
                  </span>
                  <span class="text-xs text-gray-400 truncate">
                    {{ result.oldVersion ?? '–' }} → <span class="font-semibold" :class="result.promoted ? 'text-green-700' : 'text-gray-600'">{{ result.version }}</span>
                  </span>
                </div>
                <span class="text-xs font-bold px-2.5 py-1 rounded-lg flex-shrink-0"
                  :class="result.promoted ? 'bg-green-100 text-green-700' : result.status === 'failed' ? 'bg-primary/10 text-primary-dark' : 'bg-amber-100 text-amber-700'">
                  {{ result.promoted ? '✓ Diperbarui' : result.status === 'failed' ? 'Gagal' : 'Tidak Ada Perubahan' }}
                </span>
              </div>

              <!-- Perbandingan akurasi — bahasa manajemen -->
              <div v-if="result.status !== 'failed'" class="px-4 py-3">
                <!-- Column headers -->
                <div class="grid grid-cols-3 mb-2">
                  <span class="text-xs text-gray-400">Metrik</span>
                  <span class="text-xs text-gray-400 text-center">Sebelum Diperbarui</span>
                  <span class="text-xs text-center font-semibold" :class="result.promoted ? 'text-green-600' : 'text-gray-400'">Setelah Diperbarui</span>
                </div>
                <!-- Akurasi -->
                <div class="grid grid-cols-3 items-center py-1.5 border-t border-surface/40">
                  <div>
                    <span class="text-xs font-semibold text-gray-700">Tingkat Akurasi</span>
                    <span class="block text-[10px] text-gray-400">Semakin tinggi semakin baik</span>
                  </div>
                  <div class="text-center">
                    <span class="text-sm font-semibold text-gray-500">{{ result.oldMape != null ? Math.max(0, 100 - Number(result.oldMape)).toFixed(1) + '%' : '–' }}</span>
                  </div>
                  <div class="text-center">
                    <span class="text-sm font-bold" :class="result.promoted ? 'text-green-700' : 'text-primary-dark'">{{ Math.max(0, 100 - Number(result.mape)).toFixed(1) }}%</span>
                    <span v-if="result.oldMape != null" class="block text-[10px] font-bold"
                      :class="result.promoted ? 'text-green-600' : 'text-gray-400'">
                      {{ result.promoted
                        ? '↑ +'+ (Math.max(0,100-Number(result.mape)) - Math.max(0,100-Number(result.oldMape))).toFixed(1) + '%'
                        : '↓ ' + (Math.max(0,100-Number(result.oldMape)) - Math.max(0,100-Number(result.mape))).toFixed(1) + '%' }}
                    </span>
                  </div>
                </div>
                <!-- Peringatan model kurang baik (r2 negatif diinternalisasi) -->
                <div v-if="result.r2 != null && Number(result.r2) < 0"
                  class="mt-1 mb-1 rounded-lg px-3 py-2 text-xs flex items-start gap-2 bg-yellow-50 border border-yellow-200 text-yellow-800">
                  <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                  <span>Pembaruan ditolak — kualitas sistem prediksi baru belum cukup baik. Model sebelumnya tetap digunakan. Coba tambahkan lebih banyak data historis terlebih dahulu.</span>
                </div>
                <!-- Verdict -->
                <div class="mt-2 rounded-lg px-3 py-2 text-xs flex items-start gap-2"
                  :class="result.promoted ? 'bg-green-50 text-green-800' : (result.r2 != null && Number(result.r2) < 0) ? 'bg-yellow-50 text-yellow-800' : 'bg-amber-50 text-amber-800'">
                  <span class="flex-shrink-0 font-bold">{{ result.promoted ? '✓' : 'ℹ' }}</span>
                  <span v-if="result.promoted">Sistem prediksi berhasil diperbarui dan kini lebih akurat.</span>
                  <span v-else>Sistem prediksi lama masih lebih akurat dan tetap digunakan. Tidak ada perubahan.</span>
                </div>
              </div>
              <!-- Failed state -->
              <div v-else class="px-4 py-3 flex items-start gap-2 text-xs text-red-700 bg-red-50">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>{{ result.error || 'Pelatihan gagal. Periksa koneksi ke Flask API dan coba lagi.' }}</span>
              </div>
            </div>
          </div>

          <!-- Body — global failed phase -->
          <div v-else-if="retrainModal.phase === 'failed'" class="px-6 py-5 flex-shrink-0">
            <div class="flex items-start gap-3 p-4 bg-red-50 rounded-xl border border-red-200">
              <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
              <div>
                <p class="text-sm font-semibold text-red-800">Pelatihan Gagal</p>
                <p class="text-xs text-red-600 mt-1 leading-relaxed">{{ retrainModal.error || 'Periksa koneksi ke Flask API dan coba lagi.' }}</p>
              </div>
            </div>
          </div>

          <!-- Footer — always visible, not scrolled -->
          <div class="px-6 py-4 border-t border-surface/20 flex-shrink-0">
            <button v-if="retrainModal.phase === 'done' || retrainModal.phase === 'failed'"
              @click="closeRetrainModal"
              class="w-full py-2.5 text-sm font-semibold bg-primary text-white rounded-xl hover:bg-primary-dark transition-colors">
              Tutup &amp; Refresh
            </button>
            <div v-else class="flex items-center justify-center gap-2 text-xs text-gray-400">
              <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              Mohon tunggu, jangan tutup halaman ini...
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- ── Delete Modal ───────────────────────────────────────────────────── -->
    <teleport to="body">
      <div v-if="showDeleteModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
        @click.self="cancelDelete">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full">
          <div class="px-7 pt-6 pb-5">
            <div class="flex items-start gap-4">
              <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </div>
              <div>
                <p class="text-base font-semibold text-gray-900">Hapus upload ini?</p>
                <p class="text-sm text-gray-500 mt-1 leading-relaxed">Data historis bulan tersebut juga akan dihapus dari database, sehingga Anda bisa upload ulang file yang sama.</p>
              </div>
            </div>
          </div>
          <div class="px-7 pb-6 flex items-center justify-end gap-3">
            <button @click="cancelDelete" class="px-5 py-2.5 text-sm font-semibold text-gray-500 hover:text-gray-700 hover:bg-surface/50 rounded-xl transition-colors">Batal</button>
            <button @click="proceedDelete" class="px-5 py-2.5 text-sm font-semibold bg-red-500 text-white rounded-xl hover:bg-red-600 transition-colors">Hapus</button>
          </div>
        </div>
      </div>
    </teleport>

  </DashboardLayout>
</template>
