<script setup lang="ts">
import { ref } from 'vue';
import { useAuthStore } from '../stores/authStore';
import { useRouter } from 'vue-router';
import { useToast } from '../composables/useToast';
import AuthShell from '../components/layout/AuthShell.vue';

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
  <AuthShell
    eyebrow="Masuk Akun"
    title="Selamat datang kembali"
    description="Masuk untuk melihat status pesanan, alamat, resep optik, wishlist, dan komplain."
    panel-title="Kelola kebutuhan optik dari satu akun."
    panel-subtitle="Pantau pesanan, simpan resep, akses warranty, dan lanjutkan wishlist frame favorit tanpa mengulang data."
  >
    <form @submit.prevent="handleLogin" class="premium-card space-y-5 p-6 sm:p-8">
      <div v-if="errorMessage" class="alert-error">{{ errorMessage }}</div>
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
      <p class="text-center text-sm text-graphite/70">
        Belum punya akun?
        <router-link to="/register" class="font-semibold text-gold transition-colors hover:text-ink">Daftar sekarang</router-link>
      </p>
    </form>
  </AuthShell>
</template>
