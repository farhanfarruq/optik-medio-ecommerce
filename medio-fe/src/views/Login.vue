<script setup lang="ts">
import { ref } from 'vue';
import { useAuthStore } from '../stores/authStore';
import { useRouter } from 'vue-router';
import { useToast } from '../composables/useToast';

const { showToast } = useToast();
const authStore = useAuthStore();
const router = useRouter();

const form = ref({ email: '', password: '' });
const isLoading = ref(false);
const errorMessage = ref('');

const handleLogin = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  try {
    await authStore.login(form.value);
    router.push('/profile');
  } catch (error: any) {
    if (error.response?.status === 403 && error.response?.data?.requires_otp) {
      // Jika butuh verifikasi OTP, arahkan ke halaman verifikasi
      showToast('Akun Anda belum terverifikasi. Silakan masukkan kode OTP.', 'warning');
      router.push({
        name: 'Register',
        query: { step: 'otp', email: error.response.data.email }
      });
      return;
    }
    errorMessage.value = error.response?.data?.message || 'Login gagal. Silakan coba lagi.';
  } finally {
    isLoading.value = false;
  }
};
</script>

<template>
  <main class="flex-grow flex items-center justify-center relative min-h-screen overflow-hidden">
    <!-- Background Image -->
    <img
      src="/gambar/hero-bg.jpeg"
      alt=""
      class="absolute inset-0 w-full h-full object-cover object-center"
      style="transform: scale(1.05); filter: blur(2px);"
    />
    <!-- Dark Overlay -->
    <div class="absolute inset-0" style="background: linear-gradient(160deg, rgba(10,8,5,0.88) 0%, rgba(30,20,10,0.78) 55%, rgba(60,40,10,0.60) 100%);"></div>

    <!-- Login Card -->
    <div class="relative z-10 w-full max-w-md mx-6">
      <div class="p-10 md:p-12" style="background: rgba(255,255,255,0.97); box-shadow: 0 25px 60px rgba(0,0,0,0.3);">

        <!-- Logo -->
        <div class="flex flex-col items-center mb-10">
          <img
            src="/gambar/medio.jpeg"
            alt="Optik Medio"
            class="h-16 w-auto mb-5 object-contain"
          />
          <h1 class="text-2xl font-black tracking-tight" style="color: #1a1209; font-family: 'Outfit', sans-serif;">
            Selamat Datang
          </h1>
          <p class="text-xs font-medium mt-1.5 tracking-wide" style="color: #8a7a60;">
            Masuk ke akun Optik Medio Anda
          </p>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="mb-6 p-3 text-sm text-center font-medium rounded-none" style="background: rgba(220,38,38,0.08); color: #dc2626; border: 1px solid rgba(220,38,38,0.2);">
          {{ errorMessage }}
        </div>

        <!-- Form -->
        <form @submit.prevent="handleLogin" class="flex flex-col gap-5">
          <div>
            <label class="block text-[10px] font-black uppercase tracking-[0.2em] mb-2" style="color: #8a7a60;">Email</label>
            <input
              v-model="form.email"
              type="email"
              required
              class="w-full border rounded-none px-4 py-3 text-sm font-medium focus:outline-none transition-all"
              style="background: #faf9f7; border-color: #e5e0d8; color: #1a1209;"
              placeholder="email@contoh.com"
            />
          </div>

          <div>
            <label class="block text-[10px] font-black uppercase tracking-[0.2em] mb-2" style="color: #8a7a60;">Password</label>
            <input
              v-model="form.password"
              type="password"
              required
              class="w-full border rounded-none px-4 py-3 text-sm font-medium focus:outline-none transition-all"
              style="background: #faf9f7; border-color: #e5e0d8; color: #1a1209;"
              placeholder="••••••••"
            />
          </div>

          <button
            type="submit"
            :disabled="isLoading"
            class="w-full py-4 rounded-none font-black text-sm uppercase tracking-wider text-white transition-all hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:hover:scale-100 mt-2 flex items-center justify-center gap-2"
            style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%); box-shadow: 0 4px 14px rgba(26,18,9,0.3);"
          >
            <span v-if="isLoading" class="material-symbols-outlined animate-spin text-base">sync</span>
            {{ isLoading ? 'Memproses...' : 'Masuk' }}
            <span v-if="!isLoading" class="material-symbols-outlined text-base">arrow_forward</span>
          </button>
        </form>

        <!-- Gold accent line -->
        <div class="mt-8 mb-0" style="height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.5), transparent);"></div>

        <div class="mt-6 text-center">
            <p class="text-xs" style="color: #8a7a60;">
              Belum punya akun?
              <router-link to="/register" class="font-bold hover:underline" style="color: #c19a51;">Daftar di sini</router-link>
            </p>
        </div>

        <p class="text-center text-[10px] font-bold uppercase tracking-widest mt-5" style="color: #c19a51;">
          Optik Medio — Premium Eyewear
        </p>
      </div>
    </div>
  </main>
</template>