<script setup lang="ts">
import { logger } from '../core/utils/logger';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiClient } from '../core/api/axiosclient';
import { useAuthStore } from '../stores/authStore';
import { useSeoMeta } from '../composables/useSeoMeta';
import PageHero from '../components/layout/PageHero.vue';

const router    = useRouter();
const authStore = useAuthStore();
const { setSeo } = useSeoMeta();

const levelMembers  = ref<any[]>([]);
const loyaltyHistory = ref<any[]>([]);
const isLoading     = ref(true);

const isLoggedIn    = computed(() => authStore.isAuthenticated);
const userPoints    = computed(() => authStore.user?.loyalty_points || 0);
const currentLevel  = computed(() => authStore.user?.current_level_membership?.level_member);

const breadcrumbs = [
  { label: 'Beranda', to: '/' },
  { label: 'Loyalty Points' },
];

const loadData = async () => {
  try {
    const [levelsRes] = await Promise.all([
      apiClient.get('/level-members'),
      isLoggedIn.value ? loadHistory() : Promise.resolve(),
    ]);
    levelMembers.value = levelsRes.data;
  } catch (e) {
    logger.error('Failed to load loyalty data', e);
  } finally {
    isLoading.value = false;
  }
};

const loadHistory = async () => {
  try {
    const res = await apiClient.get('/orders/loyalty-history');
    loyaltyHistory.value = res.data.history?.data || [];
  } catch { /* silent */ }
};

const progressToNext = computed(() => {
  if (!levelMembers.value.length || !authStore.user) return 0;
  const pts = userPoints.value;
  const next = levelMembers.value.find((l: any) => l.min_points > pts);
  if (!next) return 100;
  const curr = currentLevel.value?.min_points || 0;
  return Math.min(100, Math.max(0, ((pts - curr) / (next.min_points - curr)) * 100));
});

const nextLevel = computed(() => {
  if (!levelMembers.value.length || !authStore.user) return null;
  return levelMembers.value.find((l: any) => l.min_points > userPoints.value);
});

onMounted(() => {
  setSeo({
    title: 'Loyalty Points',
    description: 'Kumpulkan poin setiap belanja di Optik Medio dan nikmati diskon eksklusif. Semakin banyak poin, semakin besar keuntungan Anda.',
  });
  loadData();
});
</script>

<template>
  <div>
    <PageHero title="Loyalty Points" subtitle="Belanja, kumpulkan poin, nikmati diskon" :breadcrumbs="breadcrumbs" />

    <main class="container-commerce py-12">

      <!-- Cara Kerja -->
      <section class="mb-12">
        <h2 class="text-2xl font-black mb-8" style="color: var(--ink); font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">Cara Kerja</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div v-for="(step, i) in [
            { icon: 'shopping_bag', title: 'Belanja', desc: 'Setiap pembelian menghasilkan poin loyalty.' },
            { icon: 'check_circle', title: 'Konfirmasi', desc: 'Poin diberikan setelah konfirmasi penerimaan barang.' },
            { icon: 'toll', title: 'Kumpulkan', desc: 'Semakin banyak poin, semakin tinggi level Anda.' },
            { icon: 'redeem', title: 'Tukarkan', desc: 'Gunakan poin sebagai diskon di checkout (1 poin = Rp 1.000).' },
          ]" :key="i" class="border p-5 text-center" style="background: white; border-color: rgba(184,138,68,0.2);">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3" style="background: rgba(184,138,68,0.15);">
              <span class="material-symbols-outlined" style="color: var(--gold);">{{ step.icon }}</span>
            </div>
            <p class="font-bold text-sm mb-1" style="color: var(--ink);">{{ step.title }}</p>
            <p class="text-xs leading-relaxed" style="color: #5c4a3a;">{{ step.desc }}</p>
          </div>
        </div>
      </section>

      <!-- Level Membership -->
      <section class="mb-12">
        <h2 class="text-2xl font-black mb-6" style="color: var(--ink); font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">Level Membership</h2>
        <div v-if="isLoading" class="flex justify-center py-8">
          <span class="material-symbols-outlined animate-spin text-3xl" style="color: var(--gold);">sync</span>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div
            v-for="level in levelMembers"
            :key="level.id"
            class="border p-6 relative"
            :style="currentLevel?.id === level.id
              ? 'border-color: var(--gold); background: rgba(184,138,68,0.05);'
              : 'border-color: rgba(184,138,68,0.15); background: white;'"
          >
            <div v-if="currentLevel?.id === level.id" class="absolute top-3 right-3 text-[10px] px-2 py-0.5 font-black uppercase" style="background: var(--gold); color: white;">Level Anda</div>
            <p class="text-xl font-black mb-1" style="color: var(--ink); font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">{{ level.name }}</p>
            <p class="text-xs mb-3" style="color: #5c4a3a;">Mulai dari {{ level.min_points?.toLocaleString('id-ID') }} poin</p>
            <div v-if="level.discount_percentage > 0" class="flex items-center gap-2 mb-2">
              <span class="material-symbols-outlined text-sm" style="color: var(--gold);">percent</span>
              <p class="text-sm font-bold" style="color: var(--ink);">Diskon {{ level.discount_percentage }}% setiap pembelian</p>
            </div>
            <div v-if="level.description" class="text-xs leading-relaxed" style="color: var(--graphite);">{{ level.description }}</div>
          </div>
        </div>
      </section>

      <!-- Status User (jika login) -->
      <template v-if="isLoggedIn">
        <section class="mb-12 border p-8" style="background: var(--porcelain); border-color: rgba(184,138,68,0.3);">
          <div class="flex items-center justify-between gap-4 mb-6">
            <div>
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-1" style="color: #5c4a3a;">Poin Anda</p>
              <p class="text-4xl font-black" style="color: var(--gold); font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">
                {{ userPoints.toLocaleString('id-ID') }}
              </p>
              <p class="text-xs mt-1" style="color: #5c4a3a;">= Rp {{ (userPoints * 1000).toLocaleString('id-ID') }} diskon</p>
            </div>
            <div class="text-right">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-1" style="color: #5c4a3a;">Level Saat Ini</p>
              <p class="text-xl font-black" style="color: var(--ink);">{{ currentLevel?.name || 'Bronze' }}</p>
            </div>
          </div>

          <!-- Progress bar -->
          <div v-if="nextLevel" class="mb-4">
            <div class="flex justify-between text-xs mb-2" style="color: #5c4a3a;">
              <span>{{ currentLevel?.name || 'Bronze' }}</span>
              <span>{{ nextLevel.name }} ({{ nextLevel.min_points?.toLocaleString('id-ID') }} poin)</span>
            </div>
            <div class="h-2 rounded-full overflow-hidden" style="background: rgba(184,138,68,0.15);">
              <div class="h-full rounded-full transition-all duration-500" :style="`width: ${progressToNext}%; background: linear-gradient(90deg, var(--gold), #e8c97a);`"></div>
            </div>
            <p class="text-xs mt-2" style="color: #5c4a3a;">
              Butuh {{ (nextLevel.min_points - userPoints).toLocaleString('id-ID') }} poin lagi untuk naik ke {{ nextLevel.name }}
            </p>
          </div>
          <div v-else class="flex items-center gap-2 text-sm font-bold" style="color: var(--gold);">
            <span class="material-symbols-outlined text-lg">stars</span>
            Anda sudah di level tertinggi!
          </div>
        </section>

        <!-- Riwayat Poin -->
        <section v-if="loyaltyHistory.length > 0">
          <h2 class="text-xl font-black mb-4" style="color: var(--ink); font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">Riwayat Poin</h2>
          <div class="border" style="background: white; border-color: rgba(184,138,68,0.15);">
            <div v-for="(log, i) in loyaltyHistory.slice(0, 10)" :key="i" class="flex items-center justify-between px-6 py-4 border-b last:border-b-0" style="border-color: #f0ece4;">
              <div>
                <p class="text-sm font-bold" style="color: var(--ink);">{{ log.description || 'Poin loyalty' }}</p>
                <p class="text-xs" style="color: #5c4a3a;">{{ log.created_at }}</p>
              </div>
              <span class="text-sm font-black" :style="log.points > 0 ? 'color: #16a34a;' : 'color: #dc2626;'">
                {{ log.points > 0 ? '+' : '' }}{{ log.points?.toLocaleString('id-ID') }}
              </span>
            </div>
          </div>
        </section>
      </template>

      <!-- CTA untuk guest -->
      <template v-else>
        <section class="text-center py-12 border" style="background: var(--porcelain); border-color: rgba(184,138,68,0.2);">
          <span class="material-symbols-outlined text-5xl mb-4 block" style="color: var(--gold);">toll</span>
          <h3 class="text-xl font-black mb-3" style="color: var(--ink); font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">Mulai Kumpulkan Poin</h3>
          <p class="text-sm mb-6" style="color: #5c4a3a;">Daftar atau login untuk mulai mengumpulkan poin dari setiap pembelian.</p>
          <div class="flex gap-3 justify-center">
            <button @click="router.push('/login')" class="px-6 py-3 border text-xs font-black uppercase tracking-wider" style="border-color: var(--mist); color: #5c4a3a;">Masuk</button>
            <button @click="router.push('/register')" class="px-6 py-3 text-xs font-black uppercase tracking-wider text-white" style="background: linear-gradient(135deg, var(--ink) 0%, var(--graphite) 100%);">Daftar Sekarang</button>
          </div>
        </section>
      </template>
    </main>
  </div>
</template>
