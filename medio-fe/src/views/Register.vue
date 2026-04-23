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

    <!-- Card -->
    <div class="relative z-10 w-full max-w-md mx-6">
      <div class="p-10 md:p-12" style="background: rgba(255,255,255,0.97); box-shadow: 0 25px 60px rgba(0,0,0,0.3);">

        <!-- Logo -->
        <div class="flex flex-col items-center mb-8">
          <img
            src="/gambar/medio.jpeg"
            alt="Optik Medio"
            class="h-16 w-auto mb-4 object-contain"
          />
        </div>

        <!-- ════════════════════════════════ -->
        <!-- STEP 1: REGISTER FORM           -->
        <!-- ════════════════════════════════ -->
        <template v-if="step === 'register'">
          <div class="text-center mb-8">
            <h1 class="text-2xl font-black tracking-tight" style="color: #1a1209; font-family: 'Outfit', sans-serif;">
              Buat Akun Baru
            </h1>
            <p class="text-xs font-medium mt-1.5 tracking-wide" style="color: #8a7a60;">
              Daftarkan diri Anda di Optik Medio
            </p>
          </div>

          <!-- Error -->
          <div v-if="errorMessage" class="mb-5 p-3 text-sm text-center font-medium rounded-none" style="background: rgba(220,38,38,0.08); color: #dc2626; border: 1px solid rgba(220,38,38,0.2);">
            {{ errorMessage }}
          </div>

          <form @submit.prevent="handleRegister" class="flex flex-col gap-4">
            <div>
              <label class="block text-[10px] font-black uppercase tracking-[0.2em] mb-2" style="color: #8a7a60;">Nama Lengkap</label>
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full border rounded-none px-4 py-3 text-sm font-medium focus:outline-none transition-all"
                style="background: #faf9f7; border-color: #e5e0d8; color: #1a1209;"
                placeholder="Nama Anda"
              />
            </div>

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
              <label class="block text-[10px] font-black uppercase tracking-[0.2em] mb-2" style="color: #8a7a60;">No. Handphone</label>
              <input
                v-model="form.phone"
                type="tel"
                class="w-full border rounded-none px-4 py-3 text-sm font-medium focus:outline-none transition-all"
                style="background: #faf9f7; border-color: #e5e0d8; color: #1a1209;"
                placeholder="08xxxxxxxxxx"
              />
            </div>

            <div>
              <label class="block text-[10px] font-black uppercase tracking-[0.2em] mb-2" style="color: #8a7a60;">Password</label>
              <input
                v-model="form.password"
                type="password"
                required
                minlength="8"
                class="w-full border rounded-none px-4 py-3 text-sm font-medium focus:outline-none transition-all"
                style="background: #faf9f7; border-color: #e5e0d8; color: #1a1209;"
                placeholder="Minimal 8 karakter"
              />
            </div>

            <div>
              <label class="block text-[10px] font-black uppercase tracking-[0.2em] mb-2" style="color: #8a7a60;">Konfirmasi Password</label>
              <input
                v-model="form.password_confirmation"
                type="password"
                required
                class="w-full border rounded-none px-4 py-3 text-sm font-medium focus:outline-none transition-all"
                style="background: #faf9f7; border-color: #e5e0d8; color: #1a1209;"
                placeholder="Ulangi password"
              />
            </div>

            <button
              type="submit"
              :disabled="isLoading"
              class="w-full py-4 rounded-none font-black text-sm uppercase tracking-wider text-white transition-all hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:hover:scale-100 mt-1 flex items-center justify-center gap-2"
              style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%); box-shadow: 0 4px 14px rgba(26,18,9,0.3);"
            >
              <span v-if="isLoading" class="material-symbols-outlined animate-spin text-base">sync</span>
              {{ isLoading ? 'Memproses...' : 'Daftar' }}
              <span v-if="!isLoading" class="material-symbols-outlined text-base">arrow_forward</span>
            </button>
          </form>

          <!-- Link ke Login -->
          <div class="mt-6 text-center">
            <p class="text-xs" style="color: #8a7a60;">
              Sudah punya akun?
              <router-link to="/login" class="font-bold hover:underline" style="color: #c19a51;">Masuk di sini</router-link>
            </p>
          </div>
        </template>

        <!-- ════════════════════════════════ -->
        <!-- STEP 2: OTP VERIFICATION        -->
        <!-- ════════════════════════════════ -->
        <template v-if="step === 'otp'">
          <div class="text-center mb-8">
            <div class="w-16 h-16 mx-auto mb-4 rounded-none flex items-center justify-center" style="background: rgba(193,154,81,0.1);">
              <span class="material-symbols-outlined text-3xl" style="color: #c19a51;">mail</span>
            </div>
            <h1 class="text-2xl font-black tracking-tight" style="color: #1a1209; font-family: 'Outfit', sans-serif;">
              Verifikasi Email
            </h1>
            <p class="text-xs font-medium mt-2 leading-relaxed" style="color: #8a7a60;">
              Kami telah mengirim kode 6 digit ke<br/>
              <strong style="color: #1a1209;">{{ registeredEmail }}</strong>
            </p>
          </div>

          <!-- Error -->
          <div v-if="errorMessage" class="mb-5 p-3 text-sm text-center font-medium rounded-none" style="background: rgba(220,38,38,0.08); color: #dc2626; border: 1px solid rgba(220,38,38,0.2);">
            {{ errorMessage }}
          </div>

          <!-- OTP Input Boxes -->
          <form @submit.prevent="handleVerifyOtp">
            <div class="flex justify-center gap-2.5 mb-6">
              <input
                v-for="(_, index) in otpDigits"
                :key="index"
                :ref="(el: any) => setOtpRef(el, index)"
                type="text"
                inputmode="numeric"
                maxlength="1"
                :value="otpDigits[index]"
                @input="handleOtpInput(index, $event)"
                @keydown="handleOtpKeydown(index, $event)"
                @paste="handleOtpPaste"
                class="w-12 h-14 text-center text-xl font-black border-2 rounded-none focus:outline-none transition-all"
                :style="otpDigits[index]
                  ? 'border-color: #c19a51; background: rgba(193,154,81,0.05); color: #1a1209;'
                  : 'border-color: #e5e0d8; background: #faf9f7; color: #1a1209;'"
              />
            </div>

            <button
              type="submit"
              :disabled="isLoading || otpDigits.join('').length < 6"
              class="w-full py-4 rounded-none font-black text-sm uppercase tracking-wider text-white transition-all hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:hover:scale-100 flex items-center justify-center gap-2"
              style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%); box-shadow: 0 4px 14px rgba(26,18,9,0.3);"
            >
              <span v-if="isLoading" class="material-symbols-outlined animate-spin text-base">sync</span>
              {{ isLoading ? 'Memverifikasi...' : 'Verifikasi' }}
              <span v-if="!isLoading" class="material-symbols-outlined text-base">verified</span>
            </button>
          </form>

          <!-- Resend OTP -->
          <div class="mt-6 text-center">
            <p v-if="countdown > 0" class="text-xs" style="color: #8a7a60;">
              Kirim ulang kode dalam <strong style="color: #c19a51;">{{ countdown }}s</strong>
            </p>
            <button
              v-else
              @click="handleResendOtp"
              :disabled="isLoading"
              class="text-xs font-bold hover:underline transition-all disabled:opacity-50"
              style="color: #c19a51;"
            >
              Kirim Ulang Kode
            </button>
          </div>

          <!-- Back to register -->
          <div class="mt-4 text-center">
            <button
              @click="step = 'register'; errorMessage = ''"
              class="text-xs hover:underline transition-all"
              style="color: #8a7a60;"
            >
              ← Kembali ke form pendaftaran
            </button>
          </div>
        </template>

        <!-- Gold accent line -->
        <div class="mt-8 mb-0" style="height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.5), transparent);"></div>
        <p class="text-center text-[10px] font-bold uppercase tracking-widest mt-5" style="color: #c19a51;">
          Optik Medio — Premium Eyewear
        </p>
      </div>
    </div>
  </main>
</template>
