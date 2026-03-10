<template>
  <DashboardLayout>
    <div class="p-6">
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-12 text-center">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-purple-100 mb-4">
          <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Detail Prediksi</h2>
        <p class="text-gray-600 max-w-md mx-auto">
          Halaman untuk melihat detail prediksi okupansi dengan filter dan comparison charts
        </p>
        <div class="mt-6 inline-flex items-center px-4 py-2 bg-purple-50 rounded-lg">
          <svg class="w-5 h-5 text-purple-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          <span class="text-sm font-medium text-purple-900">Coming Soon - Under Development</span>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
</script>
