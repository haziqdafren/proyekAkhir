<template>
  <DashboardLayout>
    <div class="p-8 space-y-8 max-w-[1600px] mx-auto">
      <!-- Welcome Banner -->
      <div class="relative bg-gradient-to-br from-primary to-primary-dark rounded-3xl shadow-md overflow-hidden">
        <div class="absolute inset-0 bg-grid-white/[0.02] bg-[size:32px_32px]"></div>
        <div class="absolute right-0 top-0 -mt-8 -mr-20 h-72 w-72 rounded-full bg-white/5 blur-3xl"></div>

        <div class="relative px-10 py-12">
          <div class="flex items-center justify-between">
            <div class="space-y-3">
              <h1 class="text-3xl font-semibold text-white">
                Selamat Datang, Manager 👋
              </h1>
              <p class="text-white/80 text-base font-normal max-w-2xl">
                Berikut ringkasan prediksi okupansi hotel untuk 30 hari ke depan
              </p>
            </div>
            <div class="hidden lg:block">
              <div class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-2xl px-6 py-4 min-w-[200px]">
                <p class="text-xs text-white/70 uppercase tracking-wide mb-1.5 font-medium">Hari Ini</p>
                <p class="text-xl font-semibold text-white">{{ currentDate }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Model Retraining Status Section -->
      <div v-if="retrainingStatus" class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
        <!-- Header -->
        <div class="px-8 pt-8 pb-6 border-b border-surface/20">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
              </div>
              <div>
                <h2 class="text-xl font-semibold text-primary-dark">Status Retraining Model</h2>
                <p class="text-sm text-gray-500 mt-0.5">Tracking siklus pelatihan ulang model (6 bulan)</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Model Status Cards -->
        <div class="px-8 py-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Single-Output Model Status -->
          <div
            class="border-2 rounded-2xl p-6 transition-all duration-200 hover:shadow-md"
            :class="{
              'border-red-200 bg-red-50/50': retrainingStatus.single?.urgency === 'critical' || retrainingStatus.single?.urgency === 'high',
              'border-yellow-200 bg-yellow-50/50': retrainingStatus.single?.urgency === 'medium',
              'border-green-200 bg-green-50/50': retrainingStatus.single?.urgency === 'low' || retrainingStatus.single?.urgency === 'none',
              'border-gray-200 bg-gray-50/50': !retrainingStatus.single?.urgency
            }"
          >
            <div class="flex items-start justify-between mb-4">
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <h3 class="text-lg font-semibold text-primary-dark">Single-Output Model</h3>
                  <span class="text-xl">{{ retrainingStatus.single?.icon || '📊' }}</span>
                </div>
                <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Total Hotel Occupancy</p>
              </div>
              <span
                class="px-3 py-1 rounded-lg text-xs font-bold uppercase tracking-wide"
                :class="{
                  'bg-red-600 text-white': retrainingStatus.single?.urgency === 'critical' || retrainingStatus.single?.urgency === 'high',
                  'bg-yellow-600 text-white': retrainingStatus.single?.urgency === 'medium',
                  'bg-green-600 text-white': retrainingStatus.single?.urgency === 'low' || retrainingStatus.single?.urgency === 'none',
                  'bg-gray-600 text-white': !retrainingStatus.single?.urgency
                }"
              >
                {{ retrainingStatus.single?.status || 'N/A' }}
              </span>
            </div>

            <div class="space-y-3 mb-5">
              <p class="text-sm font-medium text-gray-800">{{ retrainingStatus.single?.message || 'Status tidak tersedia' }}</p>
              <p class="text-xs text-gray-600">{{ retrainingStatus.single?.details || '' }}</p>
            </div>

            <!-- Metrics Grid -->
            <div class="grid grid-cols-3 gap-3 mb-5 pb-5 border-b border-gray-200">
              <div class="text-center">
                <p class="text-xs text-gray-500 mb-1">Bulan Sejak Training</p>
                <p class="text-xl font-bold text-primary-dark">{{ retrainingStatus.single?.months_since_training || 0 }}</p>
              </div>
              <div class="text-center">
                <p class="text-xs text-gray-500 mb-1">Data Baru (Bulan)</p>
                <p class="text-xl font-bold text-primary-dark">{{ retrainingStatus.single?.new_data_months || 0 }}</p>
              </div>
              <div class="text-center">
                <p class="text-xs text-gray-500 mb-1">Peningkatan Data</p>
                <p class="text-xl font-bold text-primary-dark">{{ retrainingStatus.single?.new_data_percentage || 0 }}%</p>
              </div>
            </div>

            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs text-gray-500">Jadwal Selanjutnya</p>
                <p class="text-sm font-semibold text-gray-800">{{ retrainingStatus.single?.next_due || 'N/A' }}</p>
              </div>
              <button
                v-if="retrainingStatus.single?.should_retrain"
                class="px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow"
                :class="{
                  'bg-red-600 text-white hover:bg-red-700': retrainingStatus.single?.urgency === 'critical' || retrainingStatus.single?.urgency === 'high',
                  'bg-yellow-600 text-white hover:bg-yellow-700': retrainingStatus.single?.urgency === 'medium',
                  'bg-blue-600 text-white hover:bg-blue-700': retrainingStatus.single?.urgency === 'low'
                }"
                @click="$inertia.visit('/model-versions')"
              >
                {{ retrainingStatus.single?.action_text || 'Retrain Now' }}
              </button>
              <span v-else class="text-xs text-gray-500 italic">{{ retrainingStatus.single?.action_text || 'No Action Needed' }}</span>
            </div>
          </div>

          <!-- Multi-Output Model Status -->
          <div
            class="border-2 rounded-2xl p-6 transition-all duration-200 hover:shadow-md"
            :class="{
              'border-red-200 bg-red-50/50': retrainingStatus.multi?.urgency === 'critical' || retrainingStatus.multi?.urgency === 'high',
              'border-yellow-200 bg-yellow-50/50': retrainingStatus.multi?.urgency === 'medium',
              'border-green-200 bg-green-50/50': retrainingStatus.multi?.urgency === 'low' || retrainingStatus.multi?.urgency === 'none',
              'border-gray-200 bg-gray-50/50': !retrainingStatus.multi?.urgency
            }"
          >
            <div class="flex items-start justify-between mb-4">
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <h3 class="text-lg font-semibold text-primary-dark">Multi-Output Model</h3>
                  <span class="text-xl">{{ retrainingStatus.multi?.icon || '📊' }}</span>
                </div>
                <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Per Room Type</p>
              </div>
              <span
                class="px-3 py-1 rounded-lg text-xs font-bold uppercase tracking-wide"
                :class="{
                  'bg-red-600 text-white': retrainingStatus.multi?.urgency === 'critical' || retrainingStatus.multi?.urgency === 'high',
                  'bg-yellow-600 text-white': retrainingStatus.multi?.urgency === 'medium',
                  'bg-green-600 text-white': retrainingStatus.multi?.urgency === 'low' || retrainingStatus.multi?.urgency === 'none',
                  'bg-gray-600 text-white': !retrainingStatus.multi?.urgency
                }"
              >
                {{ retrainingStatus.multi?.status || 'N/A' }}
              </span>
            </div>

            <div class="space-y-3 mb-5">
              <p class="text-sm font-medium text-gray-800">{{ retrainingStatus.multi?.message || 'Status tidak tersedia' }}</p>
              <p class="text-xs text-gray-600">{{ retrainingStatus.multi?.details || '' }}</p>
            </div>

            <!-- Metrics Grid -->
            <div class="grid grid-cols-3 gap-3 mb-5 pb-5 border-b border-gray-200">
              <div class="text-center">
                <p class="text-xs text-gray-500 mb-1">Bulan Sejak Training</p>
                <p class="text-xl font-bold text-primary-dark">{{ retrainingStatus.multi?.months_since_training || 0 }}</p>
              </div>
              <div class="text-center">
                <p class="text-xs text-gray-500 mb-1">Data Baru (Bulan)</p>
                <p class="text-xl font-bold text-primary-dark">{{ retrainingStatus.multi?.new_data_months || 0 }}</p>
              </div>
              <div class="text-center">
                <p class="text-xs text-gray-500 mb-1">Peningkatan Data</p>
                <p class="text-xl font-bold text-primary-dark">{{ retrainingStatus.multi?.new_data_percentage || 0 }}%</p>
              </div>
            </div>

            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs text-gray-500">Jadwal Selanjutnya</p>
                <p class="text-sm font-semibold text-gray-800">{{ retrainingStatus.multi?.next_due || 'N/A' }}</p>
              </div>
              <button
                v-if="retrainingStatus.multi?.should_retrain"
                class="px-4 py-2 rounded-xl text-sm font-semibold transition-all duration-200 shadow-sm hover:shadow"
                :class="{
                  'bg-red-600 text-white hover:bg-red-700': retrainingStatus.multi?.urgency === 'critical' || retrainingStatus.multi?.urgency === 'high',
                  'bg-yellow-600 text-white hover:bg-yellow-700': retrainingStatus.multi?.urgency === 'medium',
                  'bg-blue-600 text-white hover:bg-blue-700': retrainingStatus.multi?.urgency === 'low'
                }"
                @click="$inertia.visit('/model-versions')"
              >
                {{ retrainingStatus.multi?.action_text || 'Retrain Now' }}
              </button>
              <span v-else class="text-xs text-gray-500 italic">{{ retrainingStatus.multi?.action_text || 'No Action Needed' }}</span>
            </div>
          </div>
        </div>

        <!-- Info Footer -->
        <div class="px-8 pb-6">
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <div class="flex gap-3">
              <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div class="text-sm text-blue-800">
                <p class="font-semibold mb-1">Tentang Siklus Retraining 6 Bulan</p>
                <p class="text-xs text-blue-700 leading-relaxed">
                  Model LSTM dilatih ulang setiap 6 bulan untuk menjaga akurasi prediksi. Setiap kali ada 3+ bulan data baru (6% peningkatan dataset), Anda dapat melakukan retraining lebih awal.
                  Status akan otomatis diperbarui setelah upload data historis baru.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters Section - Collapsible -->
      <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
        <!-- Collapsible Header -->
        <button
          @click="showFilters = !showFilters"
          class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
        >
          <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            <div>
              <h3 class="text-base font-semibold text-primary-dark text-left">Filter Data</h3>
              <p class="text-xs text-gray-500 text-left">Sesuaikan periode dan tipe kamar</p>
            </div>
          </div>
          <svg
            class="w-5 h-5 text-gray-400 transition-transform duration-200"
            :class="{ 'rotate-180': showFilters }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <!-- Collapsible Content -->
        <div v-show="showFilters" class="px-6 pb-6 pt-2 border-t border-surface/20">
          <div class="space-y-4">
          <!-- Date Range Filters -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
              <input
                type="date"
                v-model="localFilters.date_start"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
              <input
                type="date"
                v-model="localFilters.date_end"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent transition"
              />
            </div>
          </div>

          <!-- Room Type Filter with Checkboxes -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">Tipe Kamar</label>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <label
                v-for="room in roomTypes"
                :key="room.id"
                class="flex items-center space-x-2 px-4 py-2.5 border-2 rounded-xl cursor-pointer transition-all duration-200"
                :class="localFilters.room_types.includes(room.id)
                  ? 'border-primary bg-primary/5 text-primary-dark'
                  : 'border-gray-300 hover:border-primary/30 text-gray-700'"
              >
                <input
                  type="checkbox"
                  :value="room.id"
                  v-model="localFilters.room_types"
                  class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary focus:ring-offset-0"
                />
                <span class="text-sm font-medium">{{ room.name }}</span>
              </label>
            </div>
          </div>

          <!-- Filter Actions -->
          <div class="flex gap-3 pt-2">
            <button
              @click="applyFilters"
              class="px-6 py-2.5 bg-primary text-white rounded-xl hover:bg-primary-dark transition-colors font-medium shadow-sm hover:shadow"
            >
              Terapkan Filter
            </button>
            <button
              @click="resetFilters"
              class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors font-medium"
            >
              Reset
            </button>
          </div>
          </div>
        </div>
      </div>

      <!-- Insights & Recommendations - Collapsible -->
      <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden">
        <!-- Collapsible Header -->
        <button
          @click="showInsights = !showInsights"
          class="w-full px-8 py-6 flex items-center justify-between hover:bg-gray-50 transition-colors"
        >
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
              </svg>
            </div>
            <div>
              <h2 class="text-xl font-semibold text-primary-dark text-left">Smart Analysis & Rekomendasi</h2>
              <p class="text-sm text-gray-500 text-left">Analisis cerdas berdasarkan prediksi okupansi</p>
            </div>
          </div>
          <svg
            class="w-5 h-5 text-gray-400 transition-transform duration-200"
            :class="{ 'rotate-180': showInsights }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <!-- Collapsible Content -->
        <div v-show="showInsights" class="px-8 pb-8 pt-2 border-t border-surface/20">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <InsightCard
            v-if="insightOccupancy"
            :type="insightOccupancy.type"
            :title="insightOccupancy.title"
            :description="insightOccupancy.description"
            :actions="insightOccupancy.actions"
            :status="insightOccupancy.status"
          />

          <InsightCard
            v-if="insightRevenue"
            type="success"
            :title="insightRevenue.title"
            :description="insightRevenue.description"
            :actions="insightRevenue.actions"
          />

          <InsightCard
            v-if="insightRoomTypes"
            :type="insightRoomTypes.type"
            :title="insightRoomTypes.title"
            :description="insightRoomTypes.description"
            :actions="insightRoomTypes.actions"
          />

          <InsightCard
            type="info"
            title="Tips Menggunakan Prediksi"
            description="Model LSTM menganalisis pola 6 bulan terakhir untuk memberikan prediksi yang akurat."
            :actions="[
              'Gunakan Single-Output untuk keputusan manajemen tingkat tinggi',
              'Gunakan Multi-Output untuk strategi pricing per tipe kamar',
              'Perbarui prediksi setiap bulan untuk hasil terbaik'
            ]"
          />
        </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <StatCard
          title="Rata-rata Okupansi"
          :value="stats.avgOccupancy"
          format="percentage"
          :trend="stats.occupancyTrend"
          :progress="stats.avgOccupancy"
          color="primary"
          :icon="ChartBarIcon"
          subtitle="Prediksi 30 hari ke depan"
          tooltip="Rata-rata persentase kamar yang terisi selama 30 hari ke depan berdasarkan prediksi model LSTM. Semakin tinggi semakin baik (target: >70%)."
          tooltip-title="Okupansi"
        />

        <StatCard
          title="Estimasi Pendapatan"
          :value="stats.predictedRevenue"
          format="currency"
          :trend="stats.revenueTrend"
          color="green"
          :icon="CurrencyDollarIcon"
          subtitle="30 hari ke depan"
          tooltip="Total estimasi pendapatan dari semua kamar untuk 30 hari ke depan. Dihitung dari: Okupansi × Harga Kamar × Jumlah Hari."
          tooltip-title="Revenue"
        />

        <StatCard
          title="Total Kamar"
          :value="stats.totalRooms"
          suffix="kamar"
          color="purple"
          :icon="HomeIcon"
          subtitle="Dari semua tipe kamar"
          tooltip="Total kapasitas kamar di hotel dari 4 tipe kamar: Standard (20), Superior (15), Junior Suite (13), dan Family (10)."
          tooltip-title="Kapasitas"
        />

        <StatCard
          title="Tingkat Kepercayaan"
          :value="85.5"
          format="percentage"
          :progress="85.5"
          color="indigo"
          :icon="SparklesIcon"
          subtitle="Model prediksi ML"
          tooltip="Confidence level model LSTM. Model Single-Output memiliki akurasi terbaik (MAPE 21.28%) sedangkan Multi-Output (MAPE 28.41%). Semakin tinggi semakin akurat."
          tooltip-title="Confidence Level"
        />
      </div>

      <!-- Main Charts -->
      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Occupancy Trend Chart -->
        <div class="xl:col-span-2 bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden hover:shadow-md transition-shadow duration-300">
          <div class="px-8 pt-8 pb-6 border-b border-surface/20">
            <div class="space-y-2.5">
              <h2 class="text-xl font-semibold text-primary-dark">Trend Okupansi</h2>
              <p class="text-sm text-gray-500 leading-relaxed">Data historis 30 hari vs Prediksi 30 hari ke depan</p>
            </div>
          </div>

          <div class="px-8 py-6">
            <ApexChart
              type="area"
              :height="380"
              :series="occupancyChartSeries"
              :options="occupancyChartOptions"
            />
          </div>
        </div>

        <!-- Revenue Breakdown -->
        <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden hover:shadow-md transition-shadow duration-300">
          <div class="px-8 pt-8 pb-6 border-b border-surface/20">
            <div class="space-y-2.5">
              <h2 class="text-xl font-semibold text-primary-dark">Revenue Breakdown</h2>
              <p class="text-sm text-gray-500 leading-relaxed">Per tipe kamar</p>
            </div>
          </div>

          <div class="px-8 py-6">
            <ApexChart
              type="donut"
              :height="340"
              :series="revenueChartSeries"
              :options="revenueChartOptions"
            />
          </div>
        </div>
      </div>

      <!-- Room Breakdown Table - Collapsible -->
      <div class="bg-white rounded-3xl shadow-sm border border-surface/30 overflow-hidden hover:shadow-md transition-shadow duration-300">
        <!-- Collapsible Header -->
        <button
          @click="showRoomBreakdown = !showRoomBreakdown"
          class="w-full px-8 py-6 flex items-center justify-between hover:bg-gray-50 transition-colors border-b border-surface/30"
        >
          <div>
            <h2 class="text-xl font-semibold text-primary-dark text-left">Breakdown Per Tipe Kamar</h2>
            <p class="text-sm text-gray-500 mt-1 text-left">Prediksi okupansi untuk 30 hari ke depan</p>
          </div>
          <svg
            class="w-5 h-5 text-gray-400 transition-transform duration-200"
            :class="{ 'rotate-180': showRoomBreakdown }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <!-- Collapsible Content -->
        <div v-show="showRoomBreakdown" class="overflow-x-auto">
          <table class="min-w-full">
            <thead class="bg-background/50">
              <tr>
                <th class="px-8 py-5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tipe Kamar</th>
                <th class="px-6 py-5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Kapasitas</th>
                <th class="px-6 py-5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Prediksi Terisi</th>
                <th class="px-6 py-5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Okupansi</th>
                <th class="px-6 py-5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Harga Dasar</th>
                <th class="px-6 py-5 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-surface/20">
              <tr
                v-for="room in roomBreakdown"
                :key="room.id"
                class="hover:bg-background/30 transition-colors duration-150"
              >
                <td class="px-8 py-6 whitespace-nowrap">
                  <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0 h-12 w-12 bg-primary/10 rounded-2xl flex items-center justify-center">
                      <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                      </svg>
                    </div>
                    <div>
                      <div class="text-sm font-semibold text-primary-dark">{{ room.name }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-6 whitespace-nowrap text-center">
                  <span class="text-sm font-medium text-gray-700">{{ room.capacity }}</span>
                </td>
                <td class="px-6 py-6 whitespace-nowrap text-center">
                  <span class="text-sm font-medium text-gray-700">{{ room.predicted_occupied }}</span>
                </td>
                <td class="px-6 py-6 whitespace-nowrap text-center">
                  <div class="flex flex-col items-center space-y-2.5">
                    <span class="text-sm font-bold text-primary-dark">{{ room.occupancy_rate }}%</span>
                    <div class="w-28 bg-surface/40 rounded-full h-2.5 overflow-hidden">
                      <div
                        class="h-full rounded-full transition-all duration-1000 ease-out"
                        :class="{
                          'bg-green-500': room.status === 'high',
                          'bg-yellow-500': room.status === 'medium',
                          'bg-red-500': room.status === 'low',
                        }"
                        :style="{ width: room.occupancy_rate + '%' }"
                      ></div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-6 whitespace-nowrap text-center">
                  <span class="text-sm font-medium text-gray-700">{{ formatCurrency(room.base_price) }}</span>
                </td>
                <td class="px-6 py-6 whitespace-nowrap text-center">
                  <span
                    class="inline-flex items-center px-4 py-2 rounded-xl text-xs font-semibold"
                    :class="{
                      'bg-green-50 text-green-700': room.status === 'high',
                      'bg-yellow-50 text-yellow-700': room.status === 'medium',
                      'bg-red-50 text-red-700': room.status === 'low',
                    }"
                  >
                    <span
                      class="w-2 h-2 rounded-full mr-2"
                      :class="{
                        'bg-green-500': room.status === 'high',
                        'bg-yellow-500': room.status === 'medium',
                        'bg-red-500': room.status === 'low',
                      }"
                    ></span>
                    {{ room.status === 'high' ? 'Tinggi' : room.status === 'medium' ? 'Sedang' : 'Rendah' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import ApexChart from '@/Components/ApexChart.vue';
import InsightCard from '@/Components/InsightCard.vue';
import {
  ChartBarIcon,
  CurrencyDollarIcon,
  HomeIcon,
  SparklesIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      avgOccupancy: 0,
      predictedRevenue: 0,
      totalRooms: 0,
      occupancyTrend: 0,
      revenueTrend: 0,
    }),
  },
  chartData: {
    type: Object,
    default: () => ({
      historical: [],
      predicted: [],
    }),
  },
  roomBreakdown: {
    type: Array,
    default: () => [],
  },
  recentPredictions: {
    type: Object,
    default: () => ({}),
  },
  roomTypes: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({
      date_start: null,
      date_end: null,
      room_types: null,
    }),
  },
  retrainingStatus: {
    type: Object,
    default: () => ({
      single: {},
      multi: {},
    }),
  },
});

// Local filter state
const localFilters = ref({
  date_start: props.filters.date_start,
  date_end: props.filters.date_end,
  room_types: props.filters.room_types || [],
});

// Collapsible states - collapsed by default for cleaner UI
const showFilters = ref(false);
const showInsights = ref(false);
const showRoomBreakdown = ref(true); // Keep this expanded as it's important

// Apply filters
const applyFilters = () => {
  router.get('/dashboard', {
    date_start: localFilters.value.date_start,
    date_end: localFilters.value.date_end,
    room_types: localFilters.value.room_types.length > 0 ? localFilters.value.room_types : null,
  }, {
    preserveState: true,
  });
};

// Reset filters
const resetFilters = () => {
  const today = new Date();
  const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
  const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 3, 0);

  localFilters.value = {
    date_start: startOfMonth.toISOString().split('T')[0],
    date_end: endOfMonth.toISOString().split('T')[0],
    room_types: [],
  };

  applyFilters();
};

const currentDate = computed(() => {
  return new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
});

const formatCurrency = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(value);
};

// Occupancy Chart
const occupancyChartSeries = computed(() => {
  const historical = (props.chartData?.historical || []).map(item => ({
    x: new Date(item.date).getTime(),
    y: item.occupancy,
  }));

  const predicted = (props.chartData?.predicted || []).map(item => ({
    x: new Date(item.date).getTime(),
    y: item.occupancy,
  }));

  return [
    {
      name: 'Data Historis',
      data: historical,
    },
    {
      name: 'Prediksi',
      data: predicted,
    },
  ];
});

const occupancyChartOptions = {
  chart: {
    type: 'area',
    stacked: false,
    zoom: {
      type: 'x',
      enabled: true,
      autoScaleYaxis: true,
    },
    fontFamily: 'Inter, sans-serif',
    toolbar: {
      show: true,
      offsetY: -10,
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
  },
  dataLabels: {
    enabled: false,
  },
  markers: {
    size: 0,
  },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      inverseColors: false,
      opacityFrom: 0.45,
      opacityTo: 0.05,
      stops: [20, 100, 100, 100],
    },
  },
  colors: ['#3F72AF', '#9333EA'],
  stroke: {
    curve: 'smooth',
    width: 2.5,
  },
  xaxis: {
    type: 'datetime',
    labels: {
      datetimeFormatter: {
        year: 'yyyy',
        month: 'MMM',
        day: 'dd MMM',
      },
      style: {
        colors: '#9CA3AF',
        fontSize: '12px',
      },
    },
    axisBorder: {
      show: false,
    },
    axisTicks: {
      show: false,
    },
  },
  yaxis: {
    title: {
      text: 'Okupansi (%)',
      style: {
        color: '#6B7280',
        fontSize: '13px',
        fontWeight: 500,
      },
    },
    min: 0,
    max: 100,
    labels: {
      style: {
        colors: '#9CA3AF',
        fontSize: '12px',
      },
    },
  },
  grid: {
    borderColor: '#F3F4F6',
    strokeDashArray: 3,
    padding: {
      top: 10,
      right: 0,
      bottom: 0,
      left: 10,
    },
  },
  tooltip: {
    shared: true,
    x: {
      format: 'dd MMM yyyy',
    },
    y: {
      formatter: function (val) {
        return val.toFixed(1) + '%';
      },
    },
  },
  legend: {
    show: true,
    position: 'top',
    horizontalAlign: 'left',
    fontSize: '13px',
    fontWeight: 500,
    offsetY: 0,
    offsetX: 0,
    itemMargin: {
      horizontal: 16,
      vertical: 8,
    },
    markers: {
      width: 10,
      height: 10,
      radius: 10,
      offsetX: -4,
    },
  },
  annotations: {
    xaxis: [
      {
        x: new Date().getTime(),
        borderColor: '#DBE2EF',
        strokeDashArray: 4,
        label: {
          text: 'Hari Ini',
          style: {
            color: '#fff',
            background: '#3F72AF',
            fontSize: '11px',
            fontWeight: 500,
          },
        },
      },
    ],
  },
};

// Revenue Chart
const revenueChartSeries = computed(() => {
  return (props.roomBreakdown || []).map(room => {
    return Math.round((room.predicted_occupied * room.base_price * 30));
  });
});

const revenueChartOptions = {
  chart: {
    type: 'donut',
    fontFamily: 'Inter, sans-serif',
  },
  labels: props.roomBreakdown.map(room => room.name),
  colors: ['#3F72AF', '#10B981', '#9333EA', '#F59E0B'],
  legend: {
    position: 'bottom',
    fontSize: '13px',
    fontWeight: 500,
    offsetY: 8,
    itemMargin: {
      horizontal: 12,
      vertical: 6,
    },
    markers: {
      width: 10,
      height: 10,
      radius: 10,
      offsetX: -4,
    },
  },
  plotOptions: {
    pie: {
      donut: {
        size: '68%',
        labels: {
          show: true,
          name: {
            show: true,
            fontSize: '14px',
            fontWeight: 500,
            offsetY: -8,
          },
          value: {
            show: true,
            fontSize: '22px',
            fontWeight: 600,
            color: '#112D4E',
            offsetY: 8,
            formatter: function (val) {
              return formatCurrency(val);
            },
          },
          total: {
            show: true,
            label: 'Total Revenue',
            fontSize: '13px',
            fontWeight: 500,
            color: '#6B7280',
            formatter: function (w) {
              const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
              return formatCurrency(total);
            },
          },
        },
      },
    },
  },
  dataLabels: {
    enabled: true,
    formatter: function (val) {
      return val.toFixed(1) + '%';
    },
    style: {
      fontSize: '12px',
      fontWeight: 600,
    },
    dropShadow: {
      enabled: false,
    },
  },
  tooltip: {
    y: {
      formatter: function (val) {
        return formatCurrency(val);
      },
    },
  },
};

// Insights & Recommendations
const insightOccupancy = computed(() => {
  const avgOcc = props.stats?.avgOccupancy || 0;

  if (avgOcc >= 75) {
    return {
      type: 'success',
      title: 'Okupansi Sangat Baik',
      description: `Prediksi okupansi rata-rata ${avgOcc.toFixed(1)}% untuk 30 hari ke depan menunjukkan performa sangat baik. Hotel diprediksi akan beroperasi pada kapasitas tinggi.`,
      actions: [
        'Pertahankan kualitas layanan untuk mempertahankan tingkat okupansi',
        'Pertimbangkan slight price increase untuk revenue optimization',
        'Pastikan staff cukup untuk menangani high occupancy'
      ],
      status: 'high'
    };
  } else if (avgOcc >= 50) {
    return {
      type: 'warning',
      title: 'Okupansi Stabil',
      description: `Prediksi okupansi rata-rata ${avgOcc.toFixed(1)}% menunjukkan performa yang stabil. Ada peluang untuk meningkatkan okupansi lebih lanjut.`,
      actions: [
        'Lakukan kampanye marketing untuk meningkatkan booking',
        'Tawarkan early bird discount untuk periode low season',
        'Tingkatkan presence di OTA (Online Travel Agent)'
      ],
      status: 'medium'
    };
  } else {
    return {
      type: 'danger',
      title: 'Okupansi Perlu Perhatian',
      description: `Prediksi okupansi rata-rata ${avgOcc.toFixed(1)}% menunjukkan performa di bawah target. Perlu strategi agresif untuk meningkatkan booking.`,
      actions: [
        'Implementasi promo agresif (diskon 20-30%)',
        'Aktifkan kampanye di semua channel marketing',
        'Review pricing strategy - mungkin terlalu tinggi',
        'Partnership dengan corporate clients untuk long-term booking'
      ],
      status: 'low'
    };
  }
});

const insightRevenue = computed(() => {
  const revenue = props.stats?.predictedRevenue || 0;
  const formattedRevenue = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(revenue);

  return {
    title: 'Proyeksi Pendapatan',
    description: `Estimasi revenue ${formattedRevenue} untuk 30 hari ke depan. Optimalkan pricing strategy untuk memaksimalkan pendapatan.`,
    actions: [
      'Monitor competitor pricing untuk competitive advantage',
      'Terapkan dynamic pricing di peak season',
      'Cross-sell services tambahan (breakfast, spa, dll)'
    ]
  };
});

const insightRoomTypes = computed(() => {
  if (!props.roomBreakdown || props.roomBreakdown.length === 0) {
    return null;
  }

  // Find lowest performing room type
  const lowestRoom = props.roomBreakdown.reduce((min, room) =>
    room.occupancy_rate < min.occupancy_rate ? room : min
  );

  // Find highest performing room type
  const highestRoom = props.roomBreakdown.reduce((max, room) =>
    room.occupancy_rate > max.occupancy_rate ? room : max
  );

  if (lowestRoom.occupancy_rate < 50) {
    return {
      type: 'warning',
      title: `Perhatian: ${lowestRoom.name}`,
      description: `Tipe kamar ${lowestRoom.name} memiliki prediksi okupansi terendah (${lowestRoom.occupancy_rate}%). Perlu strategi khusus untuk meningkatkan booking.`,
      actions: [
        `Buat paket promo khusus untuk ${lowestRoom.name}`,
        'Highlight unique features di marketing materials',
        'Review apakah pricing competitive dengan market',
        `Bundle dengan ${highestRoom.name} untuk paket keluarga`
      ]
    };
  } else {
    return {
      type: 'success',
      title: 'Performa Seimbang',
      description: `Semua tipe kamar menunjukkan okupansi yang baik. ${highestRoom.name} adalah best performer dengan ${highestRoom.occupancy_rate}% okupansi.`,
      actions: [
        `Learn dari strategy ${highestRoom.name} untuk tipe lain`,
        'Pertahankan quality consistency di semua room types',
        'Monitor guest feedback untuk continuous improvement'
      ]
    };
  }
});
</script>

<style>
.bg-grid-white\/\[0\.02\] {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32' width='32' height='32' fill='none' stroke='rgb(255 255 255 / 0.02)'%3e%3cpath d='M0 .5H31.5V32'/%3e%3c/svg%3e");
}
</style>
