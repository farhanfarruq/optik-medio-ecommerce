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
  <main class="min-h-screen bg-ivory text-ink">
    <section class="grid min-h-screen grid-cols-1 lg:grid-cols-[1.05fr_0.95fr]">
      <div class="relative hidden overflow-hidden bg-graphite lg:block">
        <img src="/gambar/hero-bg.jpeg" alt="" class="absolute inset-0 h-full w-full object-cover opacity-45" />
        <div class="absolute inset-0 bg-graphite/70"></div>
        <div class="relative z-10 flex h-full flex-col justify-between p-12 text-ivory">
          <router-link to="/" class="inline-flex items-center gap-3 text-sm font-semibold text-ivory/80 hover:text-ivory">
            <span class="material-symbols-outlined text-lg text-gold">arrow_back</span>
            Kembali ke Optik Medio
          </router-link>
          <div class="max-w-xl pb-10">
            <p class="mb-4 text-xs font-semibold uppercase tracking-[0.22em] text-gold">Member Area</p>
            <h1 class="font-headline text-5xl font-semibold leading-tight text-ivory">Kelola pesanan, resep, garansi, dan wishlist dalam satu akun.</h1>
            <div class="mt-8 grid grid-cols-3 gap-3 text-xs font-semibold text-ivory/70">
              <div class="rounded-lg border border-white/10 bg-white/5 p-4">Tracking pesanan</div>
              <div class="rounded-lg border border-white/10 bg-white/5 p-4">Resep optik</div>
              <div class="rounded-lg border border-white/10 bg-white/5 p-4">Garansi</div>
            </div>
          </div>
        </div>
      </div>
      <div class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8">
        <div class="w-full max-w-md">
          <div class="mb-8 text-center lg:text-left">
            <img src="/gambar/medio.jpeg" alt="Optik Medio" class="mx-auto mb-5 h-14 w-auto lg:mx-0" />
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gold">Masuk Akun</p>
            <h2 class="mt-3 font-headline text-4xl font-semibold text-ink">Selamat datang kembali</h2>
            <p class="mt-3 text-sm leading-6 text-graphite/70">Masuk untuk melihat status pesanan, alamat, resep optik, wishlist, dan komplain.</p>
          </div>
          <form @submit.prevent="handleLogin" class="premium-card p-6 sm:p-8">
            <div v-if="errorMessage" class="alert-error mb-5">{{ errorMessage }}</div>
            <div class="space-y-5">
              <label class="block">
                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-graphite/65">Email</span>
                <input v-model="form.email" type="email" required class="input-field" placeholder="nama@email.com" />
              </label>
              <label class="block">
                <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.16em] text-graphite/65">Password</span>
                <input v-model="form.password" type="password" required class="input-field" placeholder="Masukkan password" />
              </label>
              <button type="submit" :disabled="isLoading" class="btn-primary w-full">
                <span v-if="isLoading" class="material-symbols-outlined animate-spin text-base">sync</span>
                <span>{{ isLoading ? 'Memproses...' : 'Masuk' }}</span>
              </button>
            </div>
            <p class="mt-6 text-center text-sm text-graphite/70">
              Belum punya akun?
              <router-link to="/register" class="font-semibold text-gold hover:text-ink">Daftar sekarang</router-link>
            </p>
          </form>
        </div>
      </div>
    </section>
  </main>
</template>
