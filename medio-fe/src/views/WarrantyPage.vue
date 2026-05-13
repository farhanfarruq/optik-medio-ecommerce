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
const isSubmitting = ref(false);
const warranties = ref<any[]>([]);
const claims = ref<any[]>([]);
const selectedClaim = ref<any | null>(null);
const activeTab = ref<'warranties' | 'claims' | 'new-claim'>('warranties');

const claimForm = ref({
  warranty_id: null as number | null,
  claim_type: '',
  description: '',
  images: [] as File[],
});

const claimTypes = [
  { value: 'warranty_repair', icon: 'verified', label: 'Perbaikan Garansi' },
  { value: 'lens_replacement', icon: 'sync', label: 'Ganti Lensa' },
  { value: 'frame_adjustment', icon: 'tune', label: 'Penyesuaian Frame' },
  { value: 'cleaning', icon: 'auto_awesome', label: 'Pembersihan' },
  { value: 'other', icon: 'receipt_long', label: 'Lainnya' },
];

const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
const apiOrigin = new URL(apiBaseUrl, window.location.origin).origin;
const lockedClaimStatuses = ['submitted', 'reviewing', 'approved', 'in_progress', 'completed'];

const breadcrumbs = [
  { label: 'Beranda', to: '/' },
  { label: 'Garansi & Servis' },
];

const isLoggedIn = computed(() => authStore.isAuthenticated);

const loadData = async () => {
  if (!isLoggedIn.value) { isLoading.value = false; return; }
  try {
    const [wRes, cRes] = await Promise.all([
      apiClient.get('/warranties'),
      apiClient.get('/service-claims'),
    ]);
    warranties.value = wRes.data.data || wRes.data;
    claims.value = cRes.data.data || cRes.data;
  } catch {
    showToast('Gagal memuat data garansi.', 'error');
  } finally {
    isLoading.value = false;
  }
};

const handleImageChange = (e: Event) => {
  const input = e.target as HTMLInputElement;
  claimForm.value.images = Array.from(input.files || []).slice(0, 3);
};

const relatedClaims = (warranty: any) => warranty.service_claims || warranty.serviceClaims || [];

const isWarrantyExpired = (warranty: any) => {
  if (!warranty?.warranty_expires_at) return false;
  const datePart = String(warranty.warranty_expires_at).slice(0, 10);
  return new Date(`${datePart}T23:59:59`).getTime() < Date.now();
};

const hasLockedClaim = (warranty: any) => relatedClaims(warranty).some((claim: any) => lockedClaimStatuses.includes(claim.status));

const isWarrantyClaimable = (warranty: any) => {
  return warranty?.status === 'active' && !isWarrantyExpired(warranty) && !hasLockedClaim(warranty);
};

const warrantyDisabledReason = (warranty: any) => {
  if (isWarrantyExpired(warranty) || warranty?.status === 'expired') return 'Garansi sudah kadaluarsa';
  if (warranty?.status === 'claimed' || hasLockedClaim(warranty)) return 'Garansi sudah pernah diklaim';
  if (warranty?.status === 'void') return 'Garansi tidak aktif';
  return 'Garansi belum bisa diklaim';
};

const startClaim = (warrantyId: number | null = null) => {
  if (warrantyId) {
    const warranty = warranties.value.find((item) => item.id === warrantyId);
    if (warranty && !isWarrantyClaimable(warranty)) {
      showToast(warrantyDisabledReason(warranty), 'error');
      return;
    }
  }

  claimForm.value.warranty_id = warrantyId;
  activeTab.value = 'new-claim';
};

const submitClaim = async () => {
  if (!claimForm.value.claim_type || !claimForm.value.description) {
    showToast('Lengkapi tipe dan deskripsi klaim.', 'error');
    return;
  }
  isSubmitting.value = true;
  try {
    const fd = new FormData();
    if (claimForm.value.warranty_id) fd.append('warranty_id', String(claimForm.value.warranty_id));
    fd.append('claim_type', claimForm.value.claim_type);
    fd.append('description', claimForm.value.description);
    claimForm.value.images.forEach((img, i) => fd.append(`images[${i}]`, img));

    const { data } = await apiClient.post('/service-claims', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    showToast(data.message, 'success');
    claimForm.value = { warranty_id: null, claim_type: '', description: '', images: [] };
    activeTab.value = 'claims';
    await loadData();
    selectedClaim.value = (data.claim && claims.value.find((claim) => claim.id === data.claim.id)) || data.claim || null;
  } catch (err: any) {
    showToast(err?.response?.data?.message || 'Gagal mengajukan klaim.', 'error');
  } finally {
    isSubmitting.value = false;
  }
};

const statusColor = (status: string) => {
  const map: Record<string, string> = {
    active: 'rgba(22,163,74,0.1); color: #16a34a',
    expired: 'rgba(239,68,68,0.1); color: #dc2626',
    claimed: 'rgba(245,158,11,0.1); color: #d97706',
    void: 'rgba(107,114,128,0.1); color: #6b7280',
    submitted: 'rgba(59,130,246,0.1); color: #2563eb',
    reviewing: 'rgba(14,165,233,0.1); color: #0284c7',
    approved: 'rgba(20,184,166,0.1); color: #0f766e',
    in_progress: 'rgba(139,92,246,0.1); color: #7c3aed',
    completed: 'rgba(22,163,74,0.1); color: #16a34a',
    rejected: 'rgba(239,68,68,0.1); color: #dc2626',
  };
  return map[status] || 'rgba(107,114,128,0.1); color: #6b7280';
};

const statusLabel = (status: string) => {
  const map: Record<string, string> = {
    active: 'Aktif',
    expired: 'Kadaluarsa',
    claimed: 'Sudah Diklaim',
    void: 'Tidak Aktif',
    submitted: 'Diajukan',
    reviewing: 'Direview',
    approved: 'Disetujui',
    in_progress: 'Diproses',
    completed: 'Selesai',
    rejected: 'Ditolak',
  };
  return map[status] || status;
};

const claimImageUrl = (path: string) => {
  if (!path) return '#';
  if (/^https?:\/\//.test(path)) return path;
  return `${apiOrigin}/storage/${path.replace(/^\/+/, '')}`;
};

const coverageLabel = (claim: any) => {
  if (claim.is_covered_by_warranty) return 'Ditanggung garansi';
  if (['approved', 'in_progress', 'completed', 'rejected'].includes(claim.status)) return 'Tidak ditanggung garansi';
  return 'Menunggu review admin';
};

const toggleClaimDetail = (claim: any) => {
  selectedClaim.value = selectedClaim.value?.id === claim.id ? null : claim;
};

onMounted(() => {
  setSeo({
    title: 'Garansi & Servis',
    description: 'Cek status garansi produk Anda dan ajukan klaim servis di Optik Medio.',
  });
  loadData();
});
</script>

<template>
  <div>
    <PageHero title="Garansi & Servis" subtitle="Kelola garansi dan klaim servis produk Anda" :breadcrumbs="breadcrumbs" />

    <main class="max-w-4xl mx-auto px-6 py-12">

      <div v-if="!isLoggedIn" class="text-center py-16 border" style="background: #fffdf7; border-color: rgba(193,154,81,0.2);">
        <span class="material-symbols-outlined text-5xl mb-4 block" style="color: #c19a51;">shield</span>
        <h3 class="text-xl font-black mb-3" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Login untuk Melihat Garansi</h3>
        <p class="text-sm mb-6" style="color: #8a7a60;">Masuk untuk melihat status garansi dan mengajukan klaim servis.</p>
        <button @click="router.push('/login')" class="px-6 py-3 text-xs font-black uppercase tracking-wider text-white" style="background: #1a1209;">Login</button>
      </div>

      <template v-else>
        <!-- Tabs -->
        <div class="flex gap-1 mb-8 border-b" style="border-color: #e5e0d8;">
          <button
            v-for="tab in [{ key: 'warranties', label: 'Garansi Saya' }, { key: 'claims', label: 'Riwayat Klaim' }, { key: 'new-claim', label: 'Ajukan Klaim' }]"
            :key="tab.key"
            @click="activeTab = tab.key as any"
            class="px-5 py-3 text-xs font-black uppercase tracking-wider transition-all"
            :style="activeTab === tab.key
              ? 'border-bottom: 2px solid #c19a51; color: #1a1209;'
              : 'color: #8a7a60;'"
          >
            {{ tab.label }}
          </button>
        </div>

        <div v-if="isLoading" class="flex justify-center py-12">
          <span class="material-symbols-outlined animate-spin text-3xl" style="color: #c19a51;">sync</span>
        </div>

        <!-- Garansi Saya -->
        <div v-else-if="activeTab === 'warranties'">
          <div v-if="warranties.length === 0" class="text-center py-12 border" style="background: #fffdf7; border-color: rgba(193,154,81,0.15);">
            <span class="material-symbols-outlined text-4xl mb-3 block" style="color: #c19a51;">shield</span>
            <p class="text-sm mb-4" style="color: #8a7a60;">Belum ada garansi terdaftar.</p>
            <button
              type="button"
              @click="startClaim()"
              class="inline-flex items-center justify-center gap-2 px-5 py-3 text-xs font-black uppercase tracking-wider text-white"
              style="background: #1a1209;"
            >
              <span class="material-symbols-outlined text-sm">support_agent</span>
              Ajukan Klaim Servis
            </button>
          </div>
          <div v-else class="space-y-4">
            <div v-for="w in warranties" :key="w.id" class="border p-6" style="background: white; border-color: rgba(193,154,81,0.15);">
              <div class="flex items-start justify-between gap-4 mb-3">
                <div>
                  <p class="font-bold" style="color: #1a1209;">{{ w.product_name }}</p>
                  <p class="text-xs mt-0.5" style="color: #8a7a60;">{{ w.warranty_number }}</p>
                </div>
                <span class="text-[10px] px-2 py-1 font-bold" :style="`background: ${statusColor(isWarrantyExpired(w) && w.status === 'active' ? 'expired' : w.status)}`">
                  {{ statusLabel(isWarrantyExpired(w) && w.status === 'active' ? 'expired' : w.status) }}
                </span>
              </div>
              <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                  <p style="color: #8a7a60;">Tanggal Beli</p>
                  <p class="font-bold" style="color: #1a1209;">{{ w.purchase_date }}</p>
                </div>
                <div>
                  <p style="color: #8a7a60;">Garansi Berakhir</p>
                  <p class="font-bold" :style="w.status === 'expired' ? 'color: #dc2626;' : 'color: #1a1209;'">{{ w.warranty_expires_at }}</p>
                </div>
              </div>
              <button
                v-if="isWarrantyClaimable(w)"
                type="button"
                @click="startClaim(w.id)"
                class="mt-5 inline-flex items-center justify-center gap-2 w-full px-4 py-3 text-xs font-black uppercase tracking-wider text-white transition-all hover:shadow-lg"
                style="background: #1a1209;"
              >
                <span class="material-symbols-outlined text-sm">support_agent</span>
                Klaim Garansi
              </button>
              <div v-else class="mt-5 flex items-center gap-2 border px-4 py-3 text-xs font-bold" style="background: #fffdf7; border-color: rgba(193,154,81,0.18); color: #8a7a60;">
                <span class="material-symbols-outlined text-base">block</span>
                {{ warrantyDisabledReason(w) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Riwayat Klaim -->
        <div v-else-if="activeTab === 'claims'">
          <div v-if="claims.length === 0" class="text-center py-12 border" style="background: #fffdf7; border-color: rgba(193,154,81,0.15);">
            <p class="text-sm" style="color: #8a7a60;">Belum ada klaim servis.</p>
          </div>
          <div v-else class="space-y-4">
            <div v-for="c in claims" :key="c.id" class="border p-5" style="background: white; border-color: rgba(193,154,81,0.15);">
              <div class="flex items-start justify-between gap-3 mb-2">
                <p class="font-bold text-sm" style="color: #1a1209;">{{ c.claim_number }}</p>
                <span class="text-[10px] px-2 py-0.5 font-bold" :style="`background: ${statusColor(c.status)}`">
                  {{ statusLabel(c.status) }}
                </span>
              </div>
              <p class="text-xs mb-1" style="color: #5a5248;">{{ c.claim_type_label || c.claim_type }}</p>
              <p class="text-xs" style="color: #8a7a60;">{{ c.description }}</p>
              <p v-if="c.admin_notes" class="text-xs mt-2 p-2" style="background: rgba(193,154,81,0.06); color: #5a5248;">
                <strong>Catatan Admin:</strong> {{ c.admin_notes }}
              </p>
              <button
                type="button"
                @click="toggleClaimDetail(c)"
                class="mt-4 inline-flex items-center gap-2 text-[11px] font-black uppercase tracking-wider"
                style="color: #c19a51;"
              >
                <span class="material-symbols-outlined text-sm">{{ selectedClaim?.id === c.id ? 'expand_less' : 'visibility' }}</span>
                {{ selectedClaim?.id === c.id ? 'Tutup Detail' : 'Lihat Detail' }}
              </button>

              <div v-if="selectedClaim?.id === c.id" class="mt-4 border-t pt-4 space-y-4" style="border-color: #e5e0d8;">
                <div class="grid sm:grid-cols-2 gap-3 text-xs">
                  <div>
                    <p class="font-bold uppercase tracking-wider" style="color: #8a7a60;">Produk</p>
                    <p class="mt-1 font-bold" style="color: #1a1209;">{{ c.warranty?.product_name || 'Servis umum' }}</p>
                    <p v-if="c.warranty?.warranty_number" class="mt-0.5" style="color: #8a7a60;">{{ c.warranty.warranty_number }}</p>
                  </div>
                  <div>
                    <p class="font-bold uppercase tracking-wider" style="color: #8a7a60;">Tanggal Klaim</p>
                    <p class="mt-1 font-bold" style="color: #1a1209;">{{ c.created_at }}</p>
                  </div>
                  <div>
                    <p class="font-bold uppercase tracking-wider" style="color: #8a7a60;">Biaya Servis</p>
                    <p class="mt-1 font-bold" style="color: #1a1209;">{{ c.service_cost ? `Rp ${Number(c.service_cost).toLocaleString('id-ID')}` : 'Belum ditentukan' }}</p>
                  </div>
                  <div>
                    <p class="font-bold uppercase tracking-wider" style="color: #8a7a60;">Cakupan Garansi</p>
                    <p class="mt-1 font-bold" style="color: #1a1209;">{{ coverageLabel(c) }}</p>
                  </div>
                  <div v-if="c.resolved_at">
                    <p class="font-bold uppercase tracking-wider" style="color: #8a7a60;">Tanggal Selesai</p>
                    <p class="mt-1 font-bold" style="color: #1a1209;">{{ c.resolved_at }}</p>
                  </div>
                </div>

                <div v-if="c.images?.length">
                  <p class="text-xs font-bold uppercase tracking-wider mb-2" style="color: #8a7a60;">Bukti Foto</p>
                  <div class="flex flex-wrap gap-2">
                    <a
                      v-for="(image, index) in c.images"
                      :key="image"
                      :href="claimImageUrl(image)"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="inline-flex items-center gap-1 px-3 py-2 border text-xs font-bold"
                      style="border-color: #e5e0d8; color: #1a1209;"
                    >
                      <span class="material-symbols-outlined text-sm">image</span>
                      Bukti {{ index + 1 }}
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Form Klaim Baru -->
        <div v-else-if="activeTab === 'new-claim'" class="border p-8" style="background: white; border-color: rgba(193,154,81,0.2);">
          <h3 class="text-lg font-black mb-6" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Ajukan Klaim Servis</h3>

          <div class="space-y-4">
            <div>
              <label class="text-xs font-bold uppercase tracking-wider block mb-2" style="color: #8a7a60;">Garansi Terkait (Opsional)</label>
              <select v-model="claimForm.warranty_id" class="w-full border px-4 py-3 text-sm focus:outline-none" style="border-color: #e5e0d8;">
                <option :value="null">Tanpa garansi / tidak tahu</option>
                <option v-for="w in warranties" :key="w.id" :value="w.id" :disabled="!isWarrantyClaimable(w)">
                  {{ w.warranty_number }} - {{ w.product_name }}{{ isWarrantyClaimable(w) ? '' : ` (${warrantyDisabledReason(w)})` }}
                </option>
              </select>
            </div>

            <div>
              <label class="text-xs font-bold uppercase tracking-wider block mb-2" style="color: #8a7a60;">Tipe Klaim</label>
              <div class="grid gap-2">
                <label v-for="ct in claimTypes" :key="ct.value" class="flex items-center gap-3 p-3 border cursor-pointer" :style="claimForm.claim_type === ct.value ? 'border-color: #c19a51; background: rgba(193,154,81,0.05);' : 'border-color: #e5e0d8;'">
                  <input type="radio" v-model="claimForm.claim_type" :value="ct.value" />
                  <span class="material-symbols-outlined text-lg" style="color: #c19a51;">{{ ct.icon }}</span>
                  <span class="text-sm font-medium" style="color: #1a1209;">{{ ct.label }}</span>
                </label>
              </div>
            </div>

            <div>
              <label class="text-xs font-bold uppercase tracking-wider block mb-2" style="color: #8a7a60;">Deskripsi Masalah</label>
              <textarea v-model="claimForm.description" rows="4" placeholder="Jelaskan masalah yang Anda alami..." class="w-full border px-4 py-3 text-sm focus:outline-none resize-none" style="border-color: #e5e0d8;"></textarea>
            </div>

            <div>
              <label class="text-xs font-bold uppercase tracking-wider block mb-2" style="color: #8a7a60;">Foto (Maks 3, Opsional)</label>
              <input type="file" accept=".jpg,.jpeg,.png,.webp" multiple @change="handleImageChange" class="block w-full text-sm" />
            </div>

            <button
              @click="submitClaim"
              :disabled="isSubmitting"
              class="w-full py-4 text-sm font-black uppercase tracking-wider text-white disabled:opacity-50"
              style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
            >
              {{ isSubmitting ? 'Mengirim...' : 'Kirim Klaim' }}
            </button>
          </div>
        </div>
      </template>
    </main>
  </div>
</template>
