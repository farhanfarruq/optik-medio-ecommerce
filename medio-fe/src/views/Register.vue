<script setup lang="ts">
import { ref, onUnmounted } from 'vue';
import { useAuthStore } from '../stores/authStore';
import { useRouter } from 'vue-router';
import { useToast } from '../composables/useToast';

const { showToast } = useToast();
const authStore = useAuthStore();
const router = useRouter();
const route = useRoute(); // Tambahkan ini

// State
const step = ref<'register' | 'otp'>((route.query.step as any) || 'register');
const isLoading = ref(false);
const errorMessage = ref('');
const registeredEmail = ref((route.query.email as string) || '');

// Jika masuk via redirect login (step otp), langsung jalankan countdown
import { onMounted } from 'vue';
import { useRoute } from 'vue-router';
onMounted(() => {
  if (step.value === 'otp' && registeredEmail.value) {
    startCountdown();
  }
});

// Form register
const form = ref({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  register_as_affiliator: false,
  referral_code: '',
});

// OTP
const otpDigits = ref(['', '', '', '', '', '']);
const otpInputRefs = ref<HTMLInputElement[]>([]);
const countdown = ref(0);
let countdownTimer: ReturnType<typeof setInterval> | null = null;

const setOtpRef = (el: any, index: number) => {
  if (el) otpInputRefs.value[index] = el;
};

// ── Register ──────────────────────────────────────────────
const handleRegister = async () => {
  isLoading.value = true;
  errorMessage.value = '';

  try {
    const response = await authStore.register(form.value);
    registeredEmail.value = response.email;
    step.value = 'otp';
    startCountdown();
    showToast('Kode verifikasi telah dikirim ke email Anda!', 'success');
  } catch (error: any) {
    const errors = error.response?.data?.errors;
    if (errors) {
      const first = Object.values(errors)[0];
      errorMessage.value = Array.isArray(first) ? String(first[0]) : 'Data tidak valid.';
    } else {
      errorMessage.value = error.response?.data?.message || 'Registrasi gagal. Silakan coba lagi.';
    }
  } finally {
    isLoading.value = false;
  }
};

// ── OTP Input Handling ────────────────────────────────────
const handleOtpInput = (index: number, event: Event) => {
  const input = event.target as HTMLInputElement;
  const val = input.value.replace(/\D/g, '');
  otpDigits.value[index] = val.charAt(0) || '';

  if (val && index < 5) {
    otpInputRefs.value[index + 1]?.focus();
  }
};

const handleOtpKeydown = (index: number, event: KeyboardEvent) => {
  if (event.key === 'Backspace' && !otpDigits.value[index] && index > 0) {
    otpInputRefs.value[index - 1]?.focus();
  }
};

const handleOtpPaste = (event: ClipboardEvent) => {
  event.preventDefault();
  const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
  for (let i = 0; i < 6; i++) {
    otpDigits.value[i] = pasted[i] || '';
  }
  if (pasted.length >= 6) {
    otpInputRefs.value[5]?.focus();
  }
};

// ── Verify OTP ────────────────────────────────────────────
const handleVerifyOtp = async () => {
  const code = otpDigits.value.join('');
  if (code.length < 6) {
    errorMessage.value = 'Masukkan 6 digit kode verifikasi.';
    return;
  }

  isLoading.value = true;
  errorMessage.value = '';

  try {
    await authStore.verifyOtp(registeredEmail.value, code);
    showToast('Akun berhasil diverifikasi! Selamat datang di Optik Medio.', 'success');
    router.push('/products');
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Kode OTP tidak valid.';
    // Reset OTP input
    otpDigits.value = ['', '', '', '', '', ''];
    otpInputRefs.value[0]?.focus();
  } finally {
    isLoading.value = false;
  }
};

// ── Resend OTP ────────────────────────────────────────────
const handleResendOtp = async () => {
  if (countdown.value > 0) return;

  isLoading.value = true;
  errorMessage.value = '';

  try {
    await authStore.resendOtp(registeredEmail.value);
    showToast('Kode verifikasi baru telah dikirim!', 'success');
    startCountdown();
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Gagal mengirim ulang kode.';
  } finally {
    isLoading.value = false;
  }
};

// ── Countdown Timer ───────────────────────────────────────
const startCountdown = () => {
  countdown.value = 60;
  if (countdownTimer) clearInterval(countdownTimer);
  countdownTimer = setInterval(() => {
    countdown.value--;
    if (countdown.value <= 0) {
      if (countdownTimer) clearInterval(countdownTimer);
    }
  }, 1000);
};

onUnmounted(() => {
  if (countdownTimer) clearInterval(countdownTimer);
});
</script>

<template>
  <main class="min-h-screen bg-ivory text-ink">
    <section class="grid min-h-screen grid-cols-1 lg:grid-cols-[0.95fr_1.05fr]">
      <div class="relative hidden overflow-hidden bg-graphite lg:block">
        <img src="/gambar/hero-bg.jpeg" alt="" class="absolute inset-0 h-full w-full object-cover opacity-45" />
        <div class="absolute inset-0 bg-graphite/72"></div>
        <div class="relative z-10 flex h-full flex-col justify-between p-12 text-ivory">
          <router-link to="/" class="inline-flex items-center gap-3 text-sm font-semibold text-ivory/80 hover:text-ivory">
            <span class="material-symbols-outlined text-lg text-gold">arrow_back</span>
            Kembali ke Optik Medio
          </router-link>
          <div class="max-w-xl pb-10">
            <p class="mb-4 text-xs font-semibold uppercase tracking-[0.22em] text-gold">Akun Baru</p>
            <h1 class="font-headline text-5xl font-semibold leading-tight text-ivory">Simpan resep, alamat, warranty, dan benefit pelanggan dengan rapi.</h1>
            <p class="mt-5 text-sm leading-7 text-ivory/68">Verifikasi email menjaga pesanan, klaim garansi, dan riwayat transaksi tetap aman.</p>
          </div>
        </div>
      </div>
      <div class="flex min-h-screen items-start justify-center px-5 py-8 sm:px-8 lg:pt-36">
        <div class="w-full max-w-[520px]">
          <div class="mb-6 text-center lg:text-left">
            <img src="/gambar/medio.jpeg" alt="Optik Medio" class="mx-auto mb-4 h-12 w-auto lg:mx-0" />
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gold">{{ step === 'otp' ? 'Verifikasi Email' : 'Daftar Akun' }}</p>
            <h2 class="mt-3 font-headline text-3xl font-semibold text-ink">{{ step === 'otp' ? 'Masukkan kode OTP' : 'Buat akun Optik Medio' }}</h2>
            <p class="mt-2 text-sm leading-6 text-graphite/70">{{ step === 'otp' ? 'Kode 6 digit dikirim ke email yang terdaftar.' : 'Gunakan data aktif agar pesanan dan layanan purna jual mudah diproses.' }}</p>
          </div>
          <form v-if="step === 'register'" @submit.prevent="handleRegister" class="premium-card space-y-4 p-5 sm:p-6">
            <div v-if="errorMessage" class="alert-error">{{ errorMessage }}</div>
            <label class="block"><span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-graphite/65">Nama Lengkap</span><input v-model="form.name" type="text" required class="input-field py-2.5" placeholder="Nama sesuai penerima pesanan" /></label>
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="block"><span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-graphite/65">Email</span><input v-model="form.email" type="email" required class="input-field py-2.5" placeholder="nama@email.com" /></label>
              <label class="block"><span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-graphite/65">No. HP</span><input v-model="form.phone" type="tel" required class="input-field py-2.5" placeholder="08xxxxxxxxxx" /></label>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="block"><span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-graphite/65">Password</span><input v-model="form.password" type="password" required class="input-field py-2.5" placeholder="Minimal 8 karakter" /></label>
              <label class="block"><span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-graphite/65">Konfirmasi</span><input v-model="form.password_confirmation" type="password" required class="input-field py-2.5" placeholder="Ulangi password" /></label>
            </div>
            <label class="block"><span class="mb-1.5 block text-[11px] font-semibold uppercase tracking-[0.16em] text-graphite/65">Kode Referral</span><input v-model="form.referral_code" type="text" class="input-field py-2.5" placeholder="Opsional" /></label>
            <button type="submit" :disabled="isLoading" class="btn-primary w-full py-2.5"><span v-if="isLoading" class="material-symbols-outlined animate-spin text-base">sync</span><span>{{ isLoading ? 'Memproses...' : 'Daftar & Kirim OTP' }}</span></button>
            <p class="text-center text-sm text-graphite/70">Sudah punya akun? <router-link to="/login" class="font-semibold text-gold hover:text-ink">Masuk</router-link></p>
          </form>
          <section v-else class="premium-card space-y-6 p-6 text-center sm:p-8">
            <div v-if="errorMessage" class="alert-error text-left">{{ errorMessage }}</div>
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gold/10 text-gold"><span class="material-symbols-outlined text-3xl">mark_email_read</span></div>
            <div><p class="text-sm text-graphite/70">Kode dikirim ke</p><p class="mt-1 font-semibold text-ink">{{ registeredEmail || form.email }}</p></div>
            <div class="flex justify-center gap-2" @paste="handleOtpPaste">
              <input v-for="(_, index) in otpDigits" :key="index" :ref="(el) => setOtpRef(el, index)" v-model="otpDigits[index]" type="text" inputmode="numeric" maxlength="1" class="h-12 w-11 rounded-lg border border-mist bg-porcelain text-center text-lg font-semibold text-ink focus:border-gold focus:ring-2 focus:ring-gold/20" @input="handleOtpInput(index, $event)" @keydown="handleOtpKeydown(index, $event)" />
            </div>
            <button type="button" :disabled="isLoading" class="btn-primary w-full" @click="handleVerifyOtp"><span v-if="isLoading" class="material-symbols-outlined animate-spin text-base">sync</span><span>{{ isLoading ? 'Memverifikasi...' : 'Verifikasi Akun' }}</span></button>
            <button type="button" :disabled="countdown > 0 || isLoading" class="btn-outline w-full" @click="handleResendOtp">{{ countdown > 0 ? 'Kirim ulang dalam ' + countdown + 's' : 'Kirim Ulang OTP' }}</button>
          </section>
        </div>
      </div>
    </section>
  </main>
</template>
