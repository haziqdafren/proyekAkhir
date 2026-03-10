<template>
  <div class="min-h-screen flex items-center justify-center bg-background py-12 px-6 sm:px-8 lg:px-10">
    <div class="max-w-md w-full space-y-10">
      <!-- Logo & Header -->
      <div class="text-center space-y-4">
        <div class="flex justify-center">
          <div class="bg-primary text-white p-5 rounded-3xl shadow-sm">
            <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
        </div>
        <div class="space-y-2">
          <h2 class="text-3xl font-semibold text-primary-dark tracking-tight">
            Hotel Dashboard
          </h2>
          <p class="text-base text-gray-500 font-normal">
            Sistem Prediksi Okupansi Hotel
          </p>
        </div>
      </div>

      <!-- Login Form -->
      <div class="bg-white py-10 px-8 shadow-sm rounded-3xl border border-surface/50">
        <form class="space-y-7" @submit.prevent="submit">
          <!-- Email -->
          <div class="space-y-3">
            <label for="email" class="block text-sm font-medium text-primary-dark">
              Email
            </label>
            <input
              id="email"
              v-model="form.email"
              name="email"
              type="email"
              autocomplete="email"
              required
              class="appearance-none block w-full px-4 py-3.5 border border-surface rounded-2xl placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition duration-200 ease-in-out text-base bg-background"
              :class="{ 'border-red-400 focus:ring-red-100': form.errors.email }"
              placeholder="manager@dharmahotel.com"
            />
            <p v-if="form.errors.email" class="text-sm text-red-500 mt-2">
              {{ form.errors.email }}
            </p>
          </div>

          <!-- Password -->
          <div class="space-y-3">
            <label for="password" class="block text-sm font-medium text-primary-dark">
              Password
            </label>
            <div class="relative">
              <input
                id="password"
                v-model="form.password"
                name="password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                required
                class="appearance-none block w-full px-4 py-3.5 pr-12 border border-surface rounded-2xl placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition duration-200 ease-in-out text-base bg-background"
                placeholder="••••••••"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-gray-600 transition-colors"
              >
                <!-- Eye Icon (Show) -->
                <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <!-- Eye Slash Icon (Hide) -->
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Remember Me -->
          <div class="flex items-center">
            <input
              id="remember"
              v-model="form.remember"
              name="remember"
              type="checkbox"
              class="h-4 w-4 text-primary focus:ring-primary/20 border-surface rounded cursor-pointer"
            />
            <label for="remember" class="ml-3 block text-sm text-gray-600 cursor-pointer">
              Ingat saya
            </label>
          </div>

          <!-- Submit Button -->
          <div class="pt-3">
            <button
              type="submit"
              :disabled="form.processing"
              class="group relative w-full flex justify-center py-4 px-4 border border-transparent text-base font-medium rounded-2xl text-white bg-primary hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary/30 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 ease-in-out shadow-sm hover:shadow-md"
            >
              <span v-if="!form.processing">Masuk</span>
              <span v-else class="flex items-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Memproses...
              </span>
            </button>
          </div>

          <!-- Login Credentials Info - Hidden for screenshots -->
          <!--
          <div class="mt-6 p-5 bg-blue-50 rounded-2xl border border-blue-200">
            <div class="flex items-start space-x-3">
              <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div class="flex-1">
                <p class="text-xs font-semibold text-blue-900 mb-2 uppercase tracking-wide">Login Credentials</p>
                <div class="space-y-1.5">
                  <p class="text-sm text-blue-800"><span class="font-medium">Email:</span> <span class="font-mono">manager@dharmahotel.com</span></p>
                  <p class="text-sm text-blue-800"><span class="font-medium">Password:</span> <span class="font-mono">DharmaHotel2026!SecurePass</span></p>
                </div>
              </div>
            </div>
          </div>
          -->
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const showPassword = ref(false);

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

const submit = () => {
  form.post('/login', {
    onFinish: () => {
      form.reset('password');
      showPassword.value = false;
    },
  });
};
</script>
