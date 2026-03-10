<template>
  <div class="min-h-screen bg-background">
    <!-- Mobile sidebar backdrop -->
    <div
      v-show="sidebarOpen"
      class="fixed inset-0 z-40 bg-primary-dark/50 backdrop-blur-sm transition-opacity lg:hidden"
      @click="sidebarOpen = false"
    ></div>

    <!-- Mobile sidebar -->
    <div
      class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-lg transform transition-transform duration-300 ease-in-out lg:hidden"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex items-center justify-between h-20 px-6 border-b border-surface/30">
        <div class="flex items-center space-x-3">
          <div class="bg-primary text-white p-2.5 rounded-2xl">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <span class="text-lg font-semibold text-primary-dark">Hotel Dashboard</span>
        </div>
        <button @click="sidebarOpen = false" class="text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <nav class="px-4 py-8 space-y-2">
        <NavLink v-for="item in navigation" :key="item.name" :href="item.href" :active="item.current">
          <component :is="item.icon" class="w-5 h-5" />
          <span>{{ item.name }}</span>
        </NavLink>
      </nav>
    </div>

    <!-- Desktop sidebar -->
    <div class="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-72 lg:flex-col">
      <div class="flex flex-col flex-grow bg-white border-r border-surface/30 overflow-y-auto">
        <!-- Logo -->
        <div class="flex items-center flex-shrink-0 px-6 h-20 border-b border-surface/30">
          <div class="bg-primary text-white p-2.5 rounded-2xl shadow-sm">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <span class="ml-3 text-lg font-semibold text-primary-dark tracking-tight">Hotel Dashboard</span>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 py-8 space-y-2">
          <NavLink v-for="item in navigation" :key="item.name" :href="item.href" :active="item.current">
            <component :is="item.icon" class="w-5 h-5" />
            <span>{{ item.name }}</span>
          </NavLink>
        </nav>

        <!-- User section -->
        <div class="flex-shrink-0 border-t border-surface/30 p-6">
          <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
              <div class="w-11 h-11 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-semibold text-sm">
                {{ userInitials }}
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-primary-dark truncate">Manager</p>
              <p class="text-xs text-gray-500 truncate">manager@dharmahotel.com</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main content -->
    <div class="lg:pl-72">
      <!-- Top header -->
      <div class="sticky top-0 z-30 flex h-20 flex-shrink-0 bg-white/80 backdrop-blur-md border-b border-surface/30">
        <button
          @click="sidebarOpen = true"
          class="px-4 text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500 lg:hidden"
        >
          <span class="sr-only">Open sidebar</span>
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <div class="flex flex-1 justify-between px-6">
          <div class="flex flex-1 items-center">
            <h1 class="text-xl font-semibold text-primary-dark">{{ pageTitle }}</h1>
          </div>

          <!-- Desktop user menu -->
          <div class="hidden lg:ml-4 lg:flex lg:items-center">
            <button
              @click="logout"
              class="inline-flex items-center px-5 py-2.5 text-sm font-medium text-primary hover:text-primary-dark hover:bg-surface/40 rounded-2xl transition-all duration-200"
            >
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              Logout
            </button>
          </div>

          <!-- Mobile logout -->
          <div class="flex items-center lg:hidden">
            <button
              @click="logout"
              class="p-2.5 text-primary hover:text-primary-dark hover:bg-surface/40 rounded-xl transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Page content -->
      <main class="flex-1">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import NavLink from '@/Components/NavLink.vue';

const page = usePage();
const sidebarOpen = ref(false);

const userInitials = computed(() => {
  // Hardcoded for manager-only authentication
  return 'MG';
});

const pageTitle = computed(() => {
  const titles = {
    'dashboard': 'Dashboard',
    'predictions': 'Detail Prediksi',
    'history': 'Riwayat Data',
    'export': 'Ekspor Laporan',
    'data-upload': 'Data Upload & Training',
  };
  const currentRoute = page.url.split('/')[1] || 'dashboard';
  return titles[currentRoute] || 'Dashboard';
});

const navigation = computed(() => {
  const currentRoute = page.url;
  return [
    {
      name: 'Dashboard',
      href: '/dashboard',
      current: currentRoute === '/dashboard',
      icon: 'svg',
    },
    {
      name: 'Detail Prediksi',
      href: '/predictions',
      current: currentRoute === '/predictions',
      icon: 'svg',
    },
    {
      name: 'Riwayat Data',
      href: '/history',
      current: currentRoute === '/history',
      icon: 'svg',
    },
    {
      name: 'Ekspor Laporan',
      href: '/export',
      current: currentRoute === '/export',
      icon: 'svg',
    },
    {
      name: 'Data Upload',
      href: '/data-upload',
      current: currentRoute === '/data-upload',
      icon: 'svg',
    },
  ];
});

const logout = () => {
  router.post('/logout');
};
</script>
