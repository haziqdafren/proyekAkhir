<script setup>
import { ref } from 'vue';

const showGuide = ref(false);
const showFAQ = ref(false);

const downloadTemplate = () => {
  window.location.href = route('data-upload.download-template');
};
</script>

<template>
  <!-- Guide Toggle Button -->
  <div class="mb-6">
    <button
      @click="showGuide = !showGuide"
      class="w-full px-6 py-4 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-semibold rounded-2xl shadow-md hover:shadow-lg transition-all duration-200 flex items-center justify-between"
    >
      <div class="flex items-center gap-3">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>📖 Panduan Upload Data & FAQ</span>
      </div>
      <svg
        class="w-5 h-5 transition-transform duration-200"
        :class="{ 'rotate-180': showGuide }"
        fill="none" stroke="currentColor" viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>
  </div>

  <!-- Expandable Guide Section -->
  <div v-show="showGuide" class="mb-8 bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
    <!-- Header -->
    <div class="px-8 py-6 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-blue-100">
      <h3 class="text-2xl font-bold text-blue-900 mb-2">Cara Upload Data Okupansi</h3>
      <p class="text-blue-700">Ikuti panduan lengkap di bawah untuk mengelola data hotel Anda</p>
    </div>

    <!-- Data Flow Visualization -->
    <div class="px-8 py-6 border-b border-gray-200">
      <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        Alur Proses Data
      </h4>

      <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-2xl p-6 border-2 border-blue-200">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <!-- Step 1 -->
          <div class="flex flex-col items-center text-center">
            <div class="w-16 h-16 bg-blue-600 text-white rounded-full flex items-center justify-center mb-3 shadow-lg">
              <span class="text-2xl font-bold">1</span>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-sm w-full">
              <p class="font-semibold text-sm text-gray-900">Upload Excel</p>
              <p class="text-xs text-gray-600 mt-1">File baru terpisah</p>
            </div>
          </div>

          <div class="hidden md:flex items-center justify-center">
            <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
          </div>

          <!-- Step 2 -->
          <div class="flex flex-col items-center text-center">
            <div class="w-16 h-16 bg-purple-600 text-white rounded-full flex items-center justify-center mb-3 shadow-lg">
              <span class="text-2xl font-bold">2</span>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-sm w-full">
              <p class="font-semibold text-sm text-gray-900">Parsing Data</p>
              <p class="text-xs text-gray-600 mt-1">Sistem baca & validasi</p>
            </div>
          </div>

          <div class="hidden md:flex items-center justify-center">
            <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
          </div>

          <!-- Step 3 -->
          <div class="flex flex-col items-center text-center">
            <div class="w-16 h-16 bg-green-600 text-white rounded-full flex items-center justify-center mb-3 shadow-lg">
              <span class="text-2xl font-bold">3</span>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-sm w-full">
              <p class="font-semibold text-sm text-gray-900">Simpan Database</p>
              <p class="text-xs text-gray-600 mt-1">Gabung otomatis</p>
            </div>
          </div>
        </div>

        <div class="mt-6 space-y-3">
          <div class="p-4 bg-white/80 rounded-xl border border-blue-200">
            <p class="text-sm text-gray-700 leading-relaxed">
              <strong class="text-blue-900">📌 Penting:</strong> Excel original <strong>TIDAK</strong> berubah! Sistem menyimpan semua data di database secara otomatis. Anda tidak perlu edit file Excel yang sudah ada.
            </p>
          </div>

          <!-- Download Template Button -->
          <button
            @click="downloadTemplate"
            class="w-full px-6 py-4 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-3"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>📥 Download Template Excel</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Step-by-Step Instructions -->
    <div class="px-8 py-6 border-b border-gray-200">
      <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
        </svg>
        Langkah-langkah Upload
      </h4>

      <div class="space-y-3">
        <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl hover:bg-blue-50 transition-colors">
          <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold flex-shrink-0">1</div>
          <div class="flex-1">
            <p class="font-semibold text-gray-900">Siapkan file Excel baru untuk data terbaru</p>
            <p class="text-sm text-gray-600 mt-1">Buat file Excel terpisah dengan data bulan-bulan terbaru (mis: Oktober 2025 - Februari 2026). <strong>Jangan ubah file Excel original</strong> 2021-2025.</p>
          </div>
        </div>

        <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl hover:bg-blue-50 transition-colors">
          <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold flex-shrink-0">2</div>
          <div class="flex-1">
            <p class="font-semibold text-gray-900">Pastikan format sheet sesuai template</p>
            <p class="text-sm text-gray-600 mt-1">Setiap sheet = 1 bulan. Nama sheet: <code class="px-2 py-0.5 bg-gray-200 rounded text-xs">GUEST JANUARI 2026</code>, <code class="px-2 py-0.5 bg-gray-200 rounded text-xs">GUEST FEBRUARI 2026</code>, dll.</p>
          </div>
        </div>

        <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl hover:bg-blue-50 transition-colors">
          <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold flex-shrink-0">3</div>
          <div class="flex-1">
            <p class="font-semibold text-gray-900">Upload file melalui form di atas</p>
            <p class="text-sm text-gray-600 mt-1">Klik "Validasi & Lihat Preview" untuk cek data sebelum proses final. Sistem akan menampilkan ringkasan data yang akan ditambahkan.</p>
          </div>
        </div>

        <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl hover:bg-green-50 transition-colors">
          <div class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center font-bold flex-shrink-0">4</div>
          <div class="flex-1">
            <p class="font-semibold text-gray-900">Tunggu proses selesai & model akan otomatis dilatih</p>
            <p class="text-sm text-gray-600 mt-1">Sistem akan parsing data, simpan ke database, lalu melatih ulang model LSTM dengan data terbaru (1-2 menit).</p>
          </div>
        </div>
      </div>
    </div>

    <!-- FAQ Section -->
    <div class="px-8 py-6">
      <button
        @click="showFAQ = !showFAQ"
        class="w-full flex items-center justify-between text-left mb-4"
      >
        <h4 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
          <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Pertanyaan yang Sering Ditanyakan (FAQ)
        </h4>
        <svg
          class="w-5 h-5 text-gray-400 transition-transform duration-200"
          :class="{ 'rotate-180': showFAQ }"
          fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <div v-show="showFAQ" class="space-y-4">
        <!-- FAQ Item 1 -->
        <div class="p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded-r-xl">
          <p class="font-semibold text-gray-900 mb-2">❓ Apakah saya harus mengubah file Excel original (2021-2025)?</p>
          <p class="text-sm text-gray-700 leading-relaxed">
            <strong>TIDAK!</strong> Jangan ubah file original. Buat file Excel <strong>baru dan terpisah</strong> untuk data bulan-bulan terbaru. Database akan menggabungkan semua data secara otomatis.
          </p>
        </div>

        <!-- FAQ Item 2 -->
        <div class="p-4 bg-blue-50 border-l-4 border-blue-400 rounded-r-xl">
          <p class="font-semibold text-gray-900 mb-2">❓ Bagaimana cara menambahkan data Oktober 2025 - Februari 2026?</p>
          <p class="text-sm text-gray-700 leading-relaxed">
            Buat file Excel baru dengan 5 sheet: <code class="px-2 py-0.5 bg-white rounded text-xs">GUEST OKTOBER 2025</code>, <code class="px-2 py-0.5 bg-white rounded text-xs">GUEST NOVEMBER 2025</code>, ..., <code class="px-2 py-0.5 bg-white rounded text-xs">GUEST FEBRUARI 2026</code>. Upload file ini, dan sistem akan menambahkan 5 bulan data ke database yang sudah ada.
          </p>
        </div>

        <!-- FAQ Item 3 -->
        <div class="p-4 bg-green-50 border-l-4 border-green-400 rounded-r-xl">
          <p class="font-semibold text-gray-900 mb-2">❓ Apa yang terjadi setelah saya upload?</p>
          <p class="text-sm text-gray-700 leading-relaxed">
            <strong>3 proses otomatis:</strong><br>
            1. <strong>Parsing:</strong> Sistem membaca semua sheet dan mengekstrak data okupansi harian<br>
            2. <strong>Penyimpanan:</strong> Data disimpan ke database (gabung dengan data lama)<br>
            3. <strong>Retraining:</strong> Model LSTM dilatih ulang dengan data terbaru (meningkatkan akurasi)
          </p>
        </div>

        <!-- FAQ Item 4 -->
        <div class="p-4 bg-purple-50 border-l-4 border-purple-400 rounded-r-xl">
          <p class="font-semibold text-gray-900 mb-2">❓ Bagaimana sistem menggabungkan data dari berbagai file Excel?</p>
          <p class="text-sm text-gray-700 leading-relaxed">
            Database menyimpan <strong>semua data dalam satu tabel</strong>. Ketika Anda upload file baru, sistem hanya menambahkan record baru ke database. File Excel hanya berfungsi sebagai <strong>sumber import</strong>, bukan storage utama.
          </p>
        </div>

        <!-- FAQ Item 5 -->
        <div class="p-4 bg-red-50 border-l-4 border-red-400 rounded-r-xl">
          <p class="font-semibold text-gray-900 mb-2">❓ Berapa sering saya harus upload data baru?</p>
          <p class="text-sm text-gray-700 leading-relaxed">
            <strong>Setiap bulan atau setiap 3 bulan.</strong> Model akan otomatis retrain setelah upload. Sistem merekomendasikan retraining formal setiap <strong>6 bulan</strong> untuk menjaga akurasi tanpa overfitting.
          </p>
        </div>

        <!-- FAQ Item 6 -->
        <div class="p-4 bg-indigo-50 border-l-4 border-indigo-400 rounded-r-xl">
          <p class="font-semibold text-gray-900 mb-2">❓ Apa format Excel yang benar?</p>
          <p class="text-sm text-gray-700 leading-relaxed">
            <strong>Struktur yang diperlukan:</strong><br>
            • Satu sheet = satu bulan<br>
            • Nama sheet: "GUEST [BULAN] [TAHUN]"<br>
            • Kolom: Tanggal, STD Total, SPR Total, FMY Total, JS Total<br>
            • Data harian (1 baris = 1 hari)<br>
            📥 <strong>Download template di bawah</strong> untuk panduan lengkap!
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
code {
  font-family: 'Courier New', monospace;
}
</style>
