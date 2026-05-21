<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiClient } from '../core/api/axiosclient';
import { useAuthStore } from '../stores/authStore';
import { useToast } from '../composables/useToast';
import { useSeoMeta } from '../composables/useSeoMeta';
import PageHero from '../components/layout/PageHero.vue';

const router = useRouter();
const authStore = useAuthStore();
const { showToast } = useToast();
const { setSeo } = useSeoMeta();

const isLoading = ref(true);
const referralData = ref<any>(null);
const referralCode = ref('');
const isApplying = ref(false);
const appliedCode = ref('');

const isLoggedIn = computed(() => authStore.isAuthenticated);

const breadcrumbs = [
  { label: 'Beranda', to: '/' },
  { label: 'Program Referral' },
];

const shareUrl = computed(() => referralData.value?.share_url || '');

const loadMyCode = async () => {
  if (!isLoggedIn.value) {
    isLoading.value = false;
    return;
  }
  try {
    const { data } = await apiClient.get('/referral/my-code');
    referralData.value = data;
  } catch {
    showToast('Gagal memuat kode referral.', 'error');
  } finally {
    isLoading.value = false;
  }
};

const applyCode = async () => {
  if (!referralCode.value.trim()) return;
  isApplying.value = true;
  try {
    const { data } = await apiClient.post('/referral/use', { code: referralCode.value.trim().toUpperCase() });
    showToast(data.message, 'success');
    appliedCode.value = referralCode.value;
    referralCode.value = '';
    await loadMyCode();
  } catch (err: any) {
    showToast(err?.response?.data?.message || 'Kode referral tidak valid.', 'error');
  } finally {
    isApplying.value = false;
  }
};

const copyCode = async () => {
  if (!referralData.value?.code) return;
  try {
    await navigator.clipboard.writeText(referralData.value.code);
    showToast('Kode referral disalin!', 'success');
  } catch {
    showToast('Gagal menyalin kode.', 'error');
  }
};

const copyShareUrl = async () => {
  if (!shareUrl.value) return;
  try {
    await navigator.clipboard.writeText(shareUrl.value);
    showToast('Link referral disalin!', 'success');
  } catch {
    showToast('Gagal menyalin link.', 'error');
  }
};

onMounted(() => {
  setSeo({
    title: 'Program Referral',
    description: 'Ajak teman belanja di Optik Medio dan dapatkan poin reward untuk setiap teman yang berhasil mendaftar dan berbelanja.',
  });
  loadMyCode();
});
</script>

<template>
  <div>
    <PageHero
      title="Program Referral"
      subtitle="Ajak teman, dapatkan poin bersama"
      :breadcrumbs="breadcrumbs"
    />

    <main class="container-commerce pt-8 pb-12">

      <!-- Loading -->
      <div v-if="isLoading" class="flex justify-center py-20">
        <span class="material-symbols-outlined animate-spin text-4xl" style="color: var(--gold);">sync</span>
      </div>

      <template v-else>
        <!-- Cara Kerja -->
        <section class="mb-12">
          <h2 class="text-2xl font-black mb-8" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Cara Kerja</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="border p-6 text-center" style="background: white; border-color: rgba(184,138,68,0.2);">
              <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4" style="background: rgba(184,138,68,0.15);">
                <span class="material-symbols-outlined" style="color: var(--gold);">share</span>
              </div>
              <h3 class="font-bold text-sm mb-2" style="color: var(--ink);">1. Bagikan Kode</h3>
              <p class="text-xs leading-relaxed" style="color: #5c4a3a;">Bagikan kode unik Anda kepada teman yang belum pernah belanja di Optik Medio.</p>
            </div>
            <div class="border p-6 text-center" style="background: white; border-color: rgba(184,138,68,0.2);">
              <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4" style="background: rgba(184,138,68,0.15);">
                <span class="material-symbols-outlined" style="color: var(--gold);">person_add</span>
              </div>
              <h3 class="font-bold text-sm mb-2" style="color: var(--ink);">2. Teman Daftar</h3>
              <p class="text-xs leading-relaxed" style="color: #5c4a3a;">Teman Anda mendaftar dan memasukkan kode referral saat registrasi atau di halaman ini.</p>
            </div>
            <div class="border p-6 text-center" style="background: white; border-color: rgba(184,138,68,0.2);">
              <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4" style="background: rgba(184,138,68,0.15);">
                <span class="material-symbols-outlined" style="color: var(--gold);">toll</span>
              </div>
              <h3 class="font-bold text-sm mb-2" style="color: var(--ink);">3. Dapat Poin</h3>
              <p class="text-xs leading-relaxed" style="color: #5c4a3a;">Anda mendapat poin reward setelah teman melakukan pembelian pertama. Teman juga dapat poin!</p>
            </div>
          </div>
        </section>

        <!-- Kode Referral User (jika login) -->
        <template v-if="isLoggedIn && referralData">
          <section class="mb-10">
            <h2 class="text-xl font-black mb-6" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Kode Referral Anda</h2>

            <div class="border p-8" style="background: var(--porcelain); border-color: rgba(184,138,68,0.35);">
              <div class="flex items-center justify-between gap-4 mb-6">
                <div>
                  <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-2" style="color: #5c4a3a;">Kode Unik Anda</p>
                  <p class="text-4xl font-black tracking-[0.3em]" style="color: var(--ink); font-family: 'JetBrains Mono', monospace;">
                    {{ referralData.code }}
                  </p>
                </div>
                <button
                  @click="copyCode"
                  class="flex items-center gap-2 px-5 py-3 border text-xs font-black uppercase tracking-wider transition-all hover:bg-ivory"
                  style="border-color: rgba(184,138,68,0.4); color: #5c4a3a;"
                >
                  <span class="material-symbols-outlined text-sm">content_copy</span>
                  Salin Kode
                </button>
              </div>

              <div class="flex items-center gap-3 p-4 mb-6" style="background: rgba(184,138,68,0.06); border: 1px solid rgba(184,138,68,0.2);">
                <span class="text-xs truncate flex-1" style="color: var(--graphite);">{{ shareUrl }}</span>
                <button
                  @click="copyShareUrl"
                  class="flex-shrink-0 flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold border transition-all hover:bg-ivory"
                  style="border-color: rgba(184,138,68,0.3); color: #5c4a3a;"
                >
                  <span class="material-symbols-outlined text-xs">link</span>
                  Salin Link
                </button>
              </div>

              <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                  <p class="text-2xl font-black" style="color: var(--gold);">{{ referralData.total_uses }}</p>
                  <p class="text-[10px] uppercase tracking-wider" style="color: #5c4a3a;">Total Digunakan</p>
                </div>
                <div>
                  <p class="text-2xl font-black" style="color: var(--gold);">+{{ referralData.reward_inviter }}</p>
                  <p class="text-[10px] uppercase tracking-wider" style="color: #5c4a3a;">Poin per Referral</p>
                </div>
                <div>
                  <p class="text-2xl font-black" style="color: var(--gold);">+{{ referralData.reward_invitee }}</p>
                  <p class="text-[10px] uppercase tracking-wider" style="color: #5c4a3a;">Poin untuk Teman</p>
                </div>
              </div>
            </div>
          </section>

          <!-- Riwayat Penggunaan -->
          <section v-if="referralData.recent_uses?.length" class="mb-10">
            <h2 class="text-xl font-black mb-4" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Teman yang Bergabung</h2>
            <div class="border" style="background: white; border-color: rgba(184,138,68,0.15);">
              <div
                v-for="(use, i) in referralData.recent_uses"
                :key="i"
                class="flex items-center justify-between px-6 py-4 border-b last:border-b-0"
                style="border-color: #f0ece4;"
              >
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background: rgba(184,138,68,0.15);">
                    <span class="material-symbols-outlined text-sm" style="color: var(--gold);">person</span>
                  </div>
                  <div>
                    <p class="text-sm font-bold" style="color: var(--ink);">{{ use.invitee_name }}</p>
                    <p class="text-xs" style="color: #5c4a3a;">Bergabung {{ use.joined_at }}</p>
                  </div>
                </div>
                <span
                  class="text-xs px-2 py-1 font-bold"
                  :style="use.rewarded ? 'background: rgba(22,163,74,0.1); color: #16a34a;' : 'background: rgba(245,158,11,0.1); color: #d97706;'"
                >
                  {{ use.rewarded ? 'Poin Diterima' : 'Menunggu Pembelian' }}
                </span>
              </div>
            </div>
          </section>
        </template>

        <!-- Gunakan Kode Referral (jika login tapi belum pakai) -->
        <section v-if="isLoggedIn" class="mb-10">
          <h2 class="text-xl font-black mb-4" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Punya Kode Referral?</h2>
          <div class="border p-6" style="background: white; border-color: rgba(184,138,68,0.15);">
            <p class="text-sm mb-4" style="color: var(--graphite);">Masukkan kode referral dari teman untuk mendapatkan poin bonus.</p>
            <div class="flex gap-3">
              <input
                v-model="referralCode"
                type="text"
                placeholder="Masukkan kode referral"
                class="flex-1 border px-4 py-3 text-sm uppercase font-bold tracking-widest focus:outline-none"
                style="border-color: var(--mist); background: var(--porcelain);"
                @keyup.enter="applyCode"
                :disabled="isApplying"
              />
              <button
                @click="applyCode"
                :disabled="!referralCode.trim() || isApplying"
                class="px-6 py-3 text-xs font-black uppercase tracking-wider text-white disabled:opacity-50 transition-all"
                style="background: linear-gradient(135deg, var(--ink) 0%, var(--graphite) 100%);"
              >
                {{ isApplying ? 'Memproses...' : 'Gunakan' }}
              </button>
            </div>
          </div>
        </section>

        <!-- CTA untuk yang belum login -->
        <section v-if="!isLoggedIn" class="text-center py-12 border" style="background: var(--porcelain); border-color: rgba(184,138,68,0.2);">
          <span class="material-symbols-outlined text-5xl mb-4 block" style="color: var(--gold);">group_add</span>
          <h3 class="text-xl font-black mb-3" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Daftar dan Dapatkan Poin</h3>
          <p class="text-sm mb-6" style="color: #5c4a3a;">Login atau daftar untuk mendapatkan kode referral Anda dan mulai mengundang teman.</p>
          <div class="flex gap-3 justify-center">
            <button
              @click="router.push('/login')"
              class="px-6 py-3 border text-xs font-black uppercase tracking-wider transition-all hover:bg-ivory"
              style="border-color: var(--mist); color: #5c4a3a;"
            >
              Masuk
            </button>
            <button
              @click="router.push('/register')"
              class="px-6 py-3 text-xs font-black uppercase tracking-wider text-white transition-all hover:opacity-90"
              style="background: linear-gradient(135deg, var(--ink) 0%, var(--graphite) 100%);"
            >
              Daftar Sekarang
            </button>
          </div>
        </section>

      </template>
    </main>
  </div>
</template>
