<template>
  <DashboardLayout>
    <div class="px-6 py-6 space-y-5 max-w-[1400px] mx-auto w-full">

      <!-- Header -->
      <div class="bg-primary-dark rounded-2xl overflow-hidden">
        <div class="px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h1 class="text-lg font-bold text-white leading-tight">Riwayat Pembaruan Sistem Prediksi</h1>
            <p class="text-white/70 text-sm mt-0.5">Rekam jejak setiap pembaruan sistem prediksi tingkat hunian hotel</p>
          </div>
          <a
            href="/data-upload"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-white/15 hover:bg-white/25 text-white text-sm font-semibold rounded-2xl border border-white/20 transition-all backdrop-blur-sm"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Perbarui Model
          </a>
        </div>
      </div>

      <!-- Summary Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-surface/30 shadow-sm p-5">
          <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Total Pembaruan</p>
          <p class="text-2xl font-bold text-primary-dark">{{ stats.total }}</p>
          <p class="text-xs text-gray-400 mt-1">kali diperbarui</p>
        </div>
        <div class="bg-white rounded-2xl border border-surface/30 shadow-sm p-5">
          <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Prediksi Keseluruhan</p>
          <p class="text-2xl font-bold text-primary">{{ stats.single_count }}</p>
          <p class="text-xs text-gray-400 mt-1">versi</p>
        </div>
        <div class="bg-white rounded-2xl border border-surface/30 shadow-sm p-5">
          <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Per Tipe Kamar</p>
          <p class="text-2xl font-bold text-primary-dark">{{ stats.multi_count }}</p>
          <p class="text-xs text-gray-400 mt-1">versi</p>
        </div>
        <div class="bg-white rounded-2xl border border-surface/30 shadow-sm p-5">
          <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Akurasi Terbaik</p>
          <p class="text-2xl font-bold text-green-600">
            {{ stats.best_single_mape != null ? (Math.max(0, 100 - stats.best_single_mape)).toFixed(1) + '%' : 'N/A' }}
          </p>
          <p class="text-xs text-gray-400 mt-1">Model aktif total hotel</p>
        </div>
      </div>

      <!-- Current Champions -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- Single Champion -->
        <div class="bg-white rounded-2xl border-2 border-primary/20 shadow-sm overflow-hidden">
          <div class="bg-primary/5 px-6 py-4 border-b border-primary/10 flex items-center gap-3">
            <div class="w-8 h-8 bg-primary rounded-xl flex items-center justify-center flex-shrink-0">
              <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
            </div>
            <div>
              <p class="text-xs font-semibold text-primary uppercase tracking-wide">Model Aktif — Prediksi Total Hotel</p>
              <p class="text-sm font-bold text-primary-dark">{{ formatVersionLabel(champions.single?.version, champions.single?.trained_at) }}</p>
            </div>
          </div>
          <div class="px-6 py-5 grid grid-cols-2 gap-4 text-center">
            <div>
              <p class="text-2xl font-bold text-primary-dark">{{ champions.single?.accuracy != null ? champions.single.accuracy + '%' : (champions.single?.mape != null ? (100 - champions.single.mape).toFixed(1) + '%' : 'N/A') }}</p>
              <p class="text-xs text-gray-500 mt-1">Tingkat Akurasi Prediksi</p>
            </div>
            <div>
              <p class="text-2xl font-bold text-primary-dark">{{ champions.single?.trained_at ? new Date(champions.single.trained_at).toLocaleDateString('id-ID', {day:'numeric',month:'short',year:'numeric'}) : 'N/A' }}</p>
              <p class="text-xs text-gray-500 mt-1">Tanggal Diperbarui</p>
            </div>
          </div>
        </div>

        <!-- Multi Champion -->
        <div class="bg-white rounded-2xl border-2 border-primary/20 shadow-sm overflow-hidden">
          <div class="bg-primary/5 px-6 py-4 border-b border-primary/10 flex items-center gap-3">
            <div class="w-8 h-8 bg-primary-dark rounded-xl flex items-center justify-center flex-shrink-0">
              <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
            </div>
            <div>
              <p class="text-xs font-semibold text-primary uppercase tracking-wide">Model Aktif — Prediksi Per Tipe Kamar</p>
              <p class="text-sm font-bold text-primary-dark">{{ formatVersionLabel(champions.multi?.version, champions.multi?.trained_at) }}</p>
            </div>
          </div>
          <div class="px-6 py-5 grid grid-cols-2 gap-4 text-center">
            <div>
              <p class="text-2xl font-bold text-primary-dark">{{ champions.multi?.accuracy != null ? champions.multi.accuracy + '%' : (champions.multi?.mape != null ? (100 - champions.multi.mape).toFixed(1) + '%' : 'N/A') }}</p>
              <p class="text-xs text-gray-500 mt-1">Tingkat Akurasi Prediksi</p>
            </div>
            <div>
              <p class="text-2xl font-bold text-primary-dark">{{ champions.multi?.trained_at ? new Date(champions.multi.trained_at).toLocaleDateString('id-ID', {day:'numeric',month:'short',year:'numeric'}) : 'N/A' }}</p>
              <p class="text-xs text-gray-500 mt-1">Tanggal Diperbarui</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filter Tabs -->
      <div class="bg-white rounded-2xl border border-surface/30 shadow-sm overflow-hidden">
        <div class="flex border-b border-surface/20">
          <button
            v-for="tab in tabs" :key="tab.key"
            @click="activeTab = tab.key"
            class="flex-1 px-6 py-4 text-sm font-semibold transition-colors flex items-center justify-center gap-2"
            :class="activeTab === tab.key
              ? 'text-primary border-b-2 border-primary bg-primary/5'
              : 'text-gray-500 hover:text-gray-700 hover:bg-surface/20'"
          >
            {{ tab.label }}
            <span class="text-xs px-2 py-0.5 rounded-full bg-surface text-gray-500">{{ tab.count }}</span>
          </button>
        </div>

        <!-- Version list -->
        <div v-if="filteredVersions.length === 0" class="py-16 text-center">
          <div class="w-12 h-12 bg-surface/50 rounded-2xl flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
            </svg>
          </div>
          <p class="text-sm text-gray-400">Belum ada riwayat pelatihan</p>
          <p class="text-xs text-gray-400 mt-1">Tekan "Latih Ulang" di halaman Data Upload untuk memulai</p>
        </div>

        <div v-else class="divide-y divide-surface/20">
          <div
            v-for="v in filteredVersions"
            :key="v.id"
            class="px-6 md:px-8 py-5 transition-colors"
            :class="v.is_champion ? 'bg-primary/5' : 'hover:bg-surface/10'"
          >
            <div class="flex items-start gap-4">
              <!-- Icon -->
              <div
                class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5"
                :class="v.is_champion ? 'bg-primary/10' : (v.status === 'failed' ? 'bg-red-50' : 'bg-surface/60')"
              >
                <!-- Champion star -->
                <svg v-if="v.is_champion" class="w-5 h-5 text-primary" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
                <!-- Failed X -->
                <svg v-else-if="v.status === 'failed'" class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <!-- Default dot -->
                <span v-else class="w-2.5 h-2.5 rounded-full bg-gray-300"></span>
              </div>

              <!-- Main info -->
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                  <span class="text-sm font-bold text-primary-dark">{{ v.version }}</span>
                  <span
                    class="text-xs px-2 py-0.5 rounded-lg font-semibold"
                    :class="v.model_type === 'single' ? 'bg-primary/10 text-primary' : 'bg-primary-dark/10 text-primary-dark'"
                  >
                    {{ v.model_type === 'single' ? 'Prediksi Total Hotel' : 'Per Tipe Kamar' }}
                  </span>
                  <span v-if="v.is_champion" class="text-xs px-2 py-0.5 rounded-lg font-bold bg-primary text-white">
                    Model Aktif
                  </span>
                  <span
                    class="text-xs px-2 py-0.5 rounded-lg font-medium"
                    :class="{
                      'bg-green-50 text-green-700':   v.status === 'completed',
                      'bg-red-50 text-red-700':       v.status === 'failed',
                      'bg-blue-50 text-blue-700':     v.status === 'training',
                      'bg-surface text-gray-500':     !['completed','failed','training'].includes(v.status),
                    }"
                  >
                    {{ statusLabel(v.status) }}
                  </span>
                </div>

                <!-- Metrics row — bahasa manajemen, tidak ada MAPE/R² -->
                <div v-if="v.status === 'completed' && v.mape != null" class="flex flex-wrap gap-x-5 gap-y-1 mt-2">
                  <div>
                    <span class="text-xs text-gray-400">Tingkat Akurasi </span>
                    <span class="text-sm font-bold" :class="accuracyColor(v.mape)">{{ accuracyDisplay(v.mape) }}</span>
                  </div>
                  <div v-if="v.trained_on_records">
                    <span class="text-xs text-gray-400">Data Latih </span>
                    <span class="text-sm font-bold text-primary-dark">{{ v.trained_on_records.toLocaleString() }} baris</span>
                  </div>
                  <div v-if="v.training_duration">
                    <span class="text-xs text-gray-400">Durasi Pembaruan </span>
                    <span class="text-sm font-bold text-primary-dark">{{ formatDuration(v.training_duration) }}</span>
                  </div>
                </div>

                <!-- Per-room breakdown for multi — tampilkan nama lengkap, bukan MAPE -->
                <div v-if="v.model_type === 'multi' && v.per_room" class="mt-2 flex flex-wrap gap-2">
                  <span
                    v-for="(mape, code) in v.per_room"
                    :key="code"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-surface text-gray-600"
                  >
                    <span class="font-bold text-primary-dark">{{ roomCodeLabel(code) }}</span>
                    Akurasi {{ accuracyDisplay(mape) }}
                  </span>
                </div>

                <!-- Error -->
                <p v-if="v.status === 'failed' && v.error_message" class="text-xs text-red-500 mt-1">
                  Gagal diperbarui. Silakan coba lagi.
                </p>

                <!-- Footer: date -->
                <div class="flex flex-wrap items-center gap-3 mt-2">
                  <span class="text-xs text-gray-400">Diperbarui: {{ v.created_at }}</span>
                </div>
              </div>

              <!-- Akurasi badge right side -->
              <div v-if="v.status === 'completed' && v.mape != null" class="flex-shrink-0 text-right">
                <div
                  class="px-3 py-1.5 rounded-xl text-sm font-bold"
                  :class="accuracyBackground(v.mape)"
                >
                  {{ accuracyDisplay(v.mape) }}
                </div>
                <p class="text-xs text-gray-400 mt-1">Akurasi</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Legend — bahasa manajemen -->
      <div class="bg-white rounded-2xl border border-surface/30 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-primary-dark mb-3">Panduan Tingkat Akurasi</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-gray-600">
          <div class="space-y-2">
            <p><span class="font-bold text-primary-dark">Tingkat Akurasi</span> — Seberapa tepat sistem memprediksi tingkat hunian. Semakin tinggi semakin baik.</p>
            <p><span class="font-bold text-primary-dark">Data Latih</span> — Jumlah data historis yang digunakan untuk memperbarui sistem.</p>
            <p><span class="font-bold text-primary-dark">Durasi Pembaruan</span> — Waktu yang dibutuhkan sistem untuk belajar dari data baru.</p>
          </div>
          <div class="space-y-2">
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-green-100 inline-block"></span><span>Akurasi &gt; 80% — Sangat Baik</span></div>
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-blue-100 inline-block"></span><span>Akurasi 65–80% — Baik</span></div>
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-yellow-100 inline-block"></span><span>Akurasi 50–65% — Cukup</span></div>
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-red-100 inline-block"></span><span>Akurasi &lt; 50% — Perlu Ditingkatkan</span></div>
          </div>
        </div>
      </div>

    </div>
  </DashboardLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({
  versions: { type: Array, default: () => [] },
  champions: { type: Object, default: () => ({}) },
  stats: { type: Object, default: () => ({}) },
});

const activeTab = ref('all');

const tabs = computed(() => [
  { key: 'all',    label: 'Semua',               count: props.versions.length },
  { key: 'single', label: 'Prediksi Total Hotel', count: props.versions.filter(v => v.model_type === 'single').length },
  { key: 'multi',  label: 'Per Tipe Kamar',       count: props.versions.filter(v => v.model_type === 'multi').length },
]);

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

const filteredVersions = computed(() => {
  if (activeTab.value === 'all') return props.versions;
  return props.versions.filter(v => v.model_type === activeTab.value);
});

const statusLabel = (status) => ({
  completed: 'Selesai',
  failed:    'Gagal',
  training:  'Sedang Diperbarui',
  retired:   'Tidak Aktif',
}[status] ?? status);

// Konversi MAPE → akurasi yang mudah dibaca manajemen
const accuracyDisplay = (mape) => {
  if (mape == null) return '–';
  return Math.max(0, 100 - Number(mape)).toFixed(1) + '%';
};

// Warna berdasarkan akurasi (100 - MAPE), bukan MAPE langsung
const accuracyColor = (mape) => {
  const acc = 100 - Number(mape);
  if (acc >= 80) return 'text-green-600';
  if (acc >= 65) return 'text-blue-600';
  if (acc >= 50) return 'text-yellow-600';
  return 'text-red-600';
};

const accuracyBackground = (mape) => {
  const acc = 100 - Number(mape);
  if (acc >= 80) return 'bg-green-50 text-green-700';
  if (acc >= 65) return 'bg-blue-50 text-blue-700';
  if (acc >= 50) return 'bg-yellow-50 text-yellow-700';
  return 'bg-red-50 text-red-700';
};

// Nama lengkap tipe kamar untuk tampilan non-teknis
const roomCodeLabel = (code) => ({
  STD: 'Standard',
  SPR: 'Superior',
  FMY: 'Family',
  JS:  'Junior Suite',
}[code?.toUpperCase()] ?? code);

const formatDuration = (seconds) => {
  if (!seconds) return '–';
  if (seconds < 60) return `${Math.round(seconds)}s`;
  const m = Math.floor(seconds / 60);
  const s = Math.round(seconds % 60);
  return `${m}m ${s}s`;
};
</script>
