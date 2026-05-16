<template>
  <div class="min-h-screen bg-background flex">
    <!-- Mobile sidebar backdrop -->
    <div
      v-show="mobileSidebarOpen"
      class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
      @click="mobileSidebarOpen = false"
    ></div>

    <!--
      DESKTOP sidebar — no transform ever applied on desktop.
      Width-only transition so no stacking context is created,
      which means Teleport'd fixed tooltips work correctly.
    -->
    <aside
      class="hidden lg:flex fixed inset-y-0 left-0 z-30 flex-col bg-primary-dark overflow-hidden transition-[width] duration-300 ease-in-out"
      :class="isExpanded ? 'w-56' : 'w-14'"
      @mouseenter="onMouseEnter"
      @mouseleave="onMouseLeave"
    >
      <!-- Logo area -->
      <div class="flex items-center h-16 border-b border-white/10 flex-shrink-0 px-3 gap-3 overflow-hidden">
        <div class="w-8 h-8 bg-white/15 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
          </svg>
        </div>
        <div class="flex-1 min-w-0 whitespace-nowrap overflow-hidden">
          <p class="text-sm font-bold text-white leading-tight">Hotel Dharma</p>
          <p class="text-[10px] text-white/40">Dashboard Manajemen</p>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-2 py-4 space-y-0.5 overflow-hidden">
        <NavLink
          v-for="item in navigation"
          :key="item.name"
          :href="item.href"
          :active="item.current"
          :collapsed="isIconOnly"
        >
          <span class="whitespace-nowrap" v-if="!isIconOnly">{{ item.name }}</span>
        </NavLink>
      </nav>

      <!-- Pin toggle — bottom of nav, always visible -->
      <div class="flex-shrink-0 border-t border-white/10 px-2 py-2">
        <button
          @click="togglePin"
          class="flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-white/40 hover:text-white hover:bg-white/10 transition-colors w-full"
          :title="pinned ? 'Kecilkan sidebar' : 'Perbesar sidebar'"
        >
          <svg
            class="w-4 h-4 flex-shrink-0 transition-transform duration-300"
            :class="pinned ? '' : 'rotate-180'"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
          </svg>
          <span v-if="!isIconOnly" class="text-xs font-medium whitespace-nowrap">
            {{ pinned ? 'Kecilkan' : 'Perbesar' }}
          </span>
        </button>
      </div>

      <!-- User section -->
      <div class="flex-shrink-0 border-t border-white/10 p-3">
        <div class="flex items-center gap-3 overflow-hidden">
          <div class="w-8 h-8 rounded-lg bg-primary/60 text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
            MG
          </div>
          <div class="flex-1 min-w-0 whitespace-nowrap overflow-hidden">
            <p class="text-xs font-semibold text-white truncate">Manager</p>
            <p class="text-[10px] text-white/40 truncate">manager@dharmahotel.com</p>
          </div>
          <button
            v-if="!isIconOnly"
            @click="logout"
            class="flex-shrink-0 p-1.5 text-white/30 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
            title="Keluar"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
          </button>
        </div>
      </div>
    </aside>

    <!--
      MOBILE sidebar — uses transform for slide-in overlay.
      Transform is safe here because this element is hidden on desktop
      and the tooltip issue only affects desktop layout.
    -->
    <aside
      class="lg:hidden fixed inset-y-0 left-0 z-50 w-64 flex flex-col bg-primary-dark transition-transform duration-300 ease-in-out"
      :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex items-center justify-between h-16 px-5 border-b border-white/10 flex-shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 bg-white/15 rounded-xl flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <div>
            <p class="text-sm font-bold text-white">Hotel Dharma</p>
            <p class="text-[10px] text-white/40">Dashboard Manajemen</p>
          </div>
        </div>
        <button @click="mobileSidebarOpen = false" class="text-white/40 hover:text-white transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <NavLink v-for="item in navigation" :key="item.name" :href="item.href" :active="item.current">
          <span>{{ item.name }}</span>
        </NavLink>
      </nav>
      <div class="flex-shrink-0 border-t border-white/10 p-3">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-lg bg-primary/60 text-white flex items-center justify-center font-bold text-xs">MG</div>
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-white truncate">Manager</p>
            <p class="text-[10px] text-white/40 truncate">manager@dharmahotel.com</p>
          </div>
          <button @click="logout" class="p-1.5 text-white/30 hover:text-white hover:bg-white/10 rounded-lg transition-colors" title="Keluar">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
          </button>
        </div>
      </div>
    </aside>

    <!-- Main content — left padding matches desktop sidebar width only -->
    <div
      class="flex flex-col flex-1 h-screen transition-[padding-left] duration-300 ease-in-out"
      :class="isExpanded ? 'lg:pl-56' : 'lg:pl-14'"
    >
      <!-- Mobile top bar -->
      <div class="sticky top-0 z-30 flex h-12 items-center bg-primary-dark lg:hidden flex-shrink-0">
        <button @click="mobileSidebarOpen = true" class="px-4 text-white/70 focus:outline-none">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <h1 class="text-sm font-semibold text-white">{{ pageTitle }}</h1>
        <button @click="logout" class="ml-auto px-4 text-white/70">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
        </button>
      </div>

      <!-- Page content -->
      <main class="flex-1 min-h-0 overflow-auto">
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
const mobileSidebarOpen = ref(false);
const pinned = ref(false);
const hovered = ref(false);

let hoverTimer = null;

const isExpanded = computed(() => pinned.value || hovered.value);
const isIconOnly = computed(() => !isExpanded.value);

const onMouseEnter = () => {
  if (pinned.value) return;
  hoverTimer = setTimeout(() => { hovered.value = true; }, 100);
};

const onMouseLeave = () => {
  clearTimeout(hoverTimer);
  hovered.value = false;
};

const togglePin = () => {
  pinned.value = !pinned.value;
  hovered.value = false;
  clearTimeout(hoverTimer);
};

const pageTitle = computed(() => {
  const titles = {
    'dashboard': 'Dashboard',
    'predictions': 'Prediksi',
    'history': 'Riwayat Data',
    'export': 'Ekspor Laporan',
    'data-upload': 'Kelola Data',
  };
  return titles[page.url.split('/')[1] || 'dashboard'] || 'Dashboard';
});

const navigation = computed(() => {
  const url = page.url;
  return [
    { name: 'Dashboard',      href: '/dashboard',    current: url === '/dashboard' },
    { name: 'Prediksi',       href: '/predictions',  current: url.startsWith('/predictions') },
    { name: 'Riwayat Data',   href: '/history',      current: url === '/history' },
    { name: 'Ekspor Laporan', href: '/export',       current: url === '/export' },
    { name: 'Kelola Data',    href: '/data-upload',  current: url === '/data-upload' },
  ];
});

const logout = () => router.post('/logout');
</script>
