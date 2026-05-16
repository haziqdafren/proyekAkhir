<script setup>
// Guide is intentionally kept simple — no FAQ, no template download
// until the template file and concept are ready.
</script>

<template>
  <!-- Upload Flow Guide: always visible, compact info strip -->
  <div class="bg-white rounded-2xl shadow-sm border border-surface/30 overflow-hidden">
    <div class="px-6 md:px-8 py-5 border-b border-surface/20">
      <h2 class="text-base font-semibold text-primary-dark">Cara Upload Data</h2>
      <p class="text-sm text-gray-500 mt-0.5">Ikuti langkah berikut untuk memperbarui data historis</p>
    </div>

    <div class="px-6 md:px-8 py-6">
      <!-- 4-step flow -->
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

        <div class="flex items-start gap-3">
          <div class="w-8 h-8 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
            <span class="text-xs font-bold text-primary">1</span>
          </div>
          <div>
            <p class="text-sm font-semibold text-primary-dark">Siapkan File Excel</p>
            <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">Buat file baru dengan data bulan terbaru. Jangan ubah file original.</p>
          </div>
        </div>

        <div class="flex items-start gap-3">
          <div class="w-8 h-8 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
            <span class="text-xs font-bold text-primary">2</span>
          </div>
          <div>
            <p class="text-sm font-semibold text-primary-dark">Format Sheet</p>
            <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">Satu sheet = satu bulan. Nama: <code class="px-1 bg-surface rounded text-[11px]">GUEST JANUARI 2026</code></p>
          </div>
        </div>

        <div class="flex items-start gap-3">
          <div class="w-8 h-8 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
            <span class="text-xs font-bold text-primary">3</span>
          </div>
          <div>
            <p class="text-sm font-semibold text-primary-dark">Upload & Preview</p>
            <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">Klik "Validasi & Preview" untuk memeriksa data sebelum diproses.</p>
          </div>
        </div>

        <div class="flex items-start gap-3">
          <div class="w-8 h-8 rounded-xl bg-primary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
            <span class="text-xs font-bold text-primary">4</span>
          </div>
          <div>
            <p class="text-sm font-semibold text-primary-dark">Proses Otomatis</p>
            <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">Data disimpan ke database, model LSTM dilatih ulang secara otomatis.</p>
          </div>
        </div>

      </div>

      <!-- Info note -->
      <div class="mt-5 flex items-start gap-2.5 p-3.5 bg-primary/5 border border-primary/15 rounded-xl">
        <svg class="w-4 h-4 text-primary flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-xs text-gray-600 leading-relaxed">
          File Excel original tidak berubah. Sistem menyimpan semua data ke database secara terpisah.
          Setiap upload menambahkan record baru tanpa menghapus data sebelumnya.
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
code {
  font-family: 'Courier New', monospace;
}
</style>
