<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { orderRepository } from '../repositories/OrderRepository';
import { RETURN_REASONS, returnRepository } from '../repositories/ReturnRepository';
import { reviewRepository } from '../repositories/ReviewRepository';
import { complaintRepository } from '../repositories/ComplaintRepository';
import { useToast } from '../composables/useToast';
import { apiClient } from '../core/api/axiosclient';
import { resolveImageUrl } from '../core/utils/image';

type ReviewDraft = {
  isOpen: boolean;
  isSubmitting: boolean;
  isSubmitted: boolean;
  rating: number;
  comment: string;
};

const { showToast } = useToast();

const route = useRoute();
const router = useRouter();
const order = ref<any>(null);
const isLoading = ref(true);
const isConfirmingDelivery = ref(false);
const isReturnFormOpen = ref(false);
const isSubmittingReturn = ref(false);
const hasSubmittedReturn = ref(false);
const reviewDrafts = ref<Record<number, ReviewDraft>>({});
const existingComplain = ref<any>(null);

const returnForm = ref({
  reason: RETURN_REASONS[0]?.value || '',
  description: '',
});

const timelineSteps = [
  { key: 'created', label: 'Pesanan Dibuat', icon: 'shopping_bag' },
  { key: 'paid', label: 'Pembayaran Terkonfirmasi', icon: 'payments' },
  { key: 'processing', label: 'Diproses Toko', icon: 'inventory_2' },
  { key: 'shipped', label: 'Sedang Dikirim', icon: 'local_shipping' },
  { key: 'delivered', label: 'Delivered', icon: 'home' },
];

const loadOrder = async () => {
  const id = Number(route.params.id);

  try {
    order.value = await orderRepository.getOrderDetails(id);

    if (['UNPAID', 'PENDING'].includes(normalizedStatus.value)) {
      try {
        const syncResponse = await apiClient.post(`/orders/${id}/sync-payment`);
        if (syncResponse.data?.order) {
          order.value = syncResponse.data.order;
        }
      } catch (syncError) {
        console.warn('Silent sync failed', syncError);
      }
    }
  } catch (error) {
    console.error('Failed to fetch order', error);
    showToast('Gagal memuat detail pesanan.', 'error');
  } finally {
    isLoading.value = false;
  }
};

onMounted(async () => {
  await loadOrder();
  // Load komplain terkait pesanan ini (jika ada)
  try {
    const id = Number(route.params.id);
    existingComplain.value = await complaintRepository.getComplaintByOrder(id);
  } catch {
    // tidak ada komplain, biarkan null
  }
});

const normalizedStatus = computed(() => String(order.value?.status || '').toUpperCase());
const isOrderClosed = computed(() => ['CANCELLED', 'REFUNDED', 'EXPIRED'].includes(normalizedStatus.value));
const canConfirmDelivery = computed(() => normalizedStatus.value === 'SHIPPED');
const currentReturnRequest = computed(() => order.value?.return_request || order.value?.returnRequest || null);
const canSubmitReturn = computed(() => normalizedStatus.value === 'DELIVERED' && !currentReturnRequest.value);

const getReturnStatusConfig = (status?: string) => {
  switch ((status || '').toLowerCase()) {
    case 'approved':
      return {
        label: 'Disetujui Admin',
        tone: 'background: rgba(22,163,74,0.08); color: #15803d; border-color: rgba(22,163,74,0.18);',
        description: 'Pengajuan return Anda sudah disetujui. Silakan cek catatan admin untuk langkah berikutnya.',
      };
    case 'rejected':
      return {
        label: 'Ditolak Admin',
        tone: 'background: rgba(220,38,38,0.08); color: #dc2626; border-color: rgba(220,38,38,0.18);',
        description: 'Pengajuan return ditolak. Anda bisa melihat alasan atau catatan admin di bawah.',
      };
    default:
      return {
        label: 'Menunggu Review Admin',
        tone: 'background: rgba(251,191,36,0.12); color: #b45309; border-color: rgba(251,191,36,0.24);',
        description: 'Pengajuan return sedang ditinjau oleh admin.',
      };
  }
};

const getStatusConfig = (status: string) => {
  switch (status?.toUpperCase()) {
    case 'UNPAID':
      return { bg: 'rgba(251,191,36,0.12)', color: '#b45309', border: 'rgba(251,191,36,0.4)', label: 'Belum Bayar' };
    case 'PENDING':
      return { bg: 'rgba(251,191,36,0.12)', color: '#b45309', border: 'rgba(251,191,36,0.4)', label: 'Menunggu Konfirmasi' };
    case 'PAID':
      return { bg: 'rgba(22,163,74,0.1)', color: '#15803d', border: 'rgba(22,163,74,0.35)', label: 'Lunas' };
    case 'PROCESSING':
      return { bg: 'rgba(124,58,237,0.1)', color: '#6d28d9', border: 'rgba(124,58,237,0.3)', label: 'Diproses' };
    case 'COMPLETED':
    case 'DELIVERED':
      return { bg: 'rgba(22,163,74,0.1)', color: '#15803d', border: 'rgba(22,163,74,0.35)', label: 'Delivered' };
    case 'SHIPPED':
      return { bg: 'rgba(59,130,246,0.1)', color: '#1d4ed8', border: 'rgba(59,130,246,0.35)', label: 'Dikirim' };
    case 'REFUNDED':
      return { bg: 'rgba(14,165,233,0.08)', color: '#0369a1', border: 'rgba(14,165,233,0.3)', label: 'Refunded' };
    case 'CANCELLED':
      return { bg: 'rgba(220,38,38,0.08)', color: '#dc2626', border: 'rgba(220,38,38,0.3)', label: 'Dibatalkan' };
    case 'EXPIRED':
      return { bg: 'rgba(107,114,128,0.1)', color: '#4b5563', border: 'rgba(107,114,128,0.3)', label: 'Kedaluwarsa' };
    default:
      return { bg: 'rgba(107,114,128,0.1)', color: '#4b5563', border: 'rgba(107,114,128,0.3)', label: status };
  }
};

const getStatusStepIndex = (status: string) => {
  switch (status?.toUpperCase()) {
    case 'UNPAID':
    case 'PENDING':
      return 0;
    case 'PAID':
      return 1;
    case 'PROCESSING':
      return 2;
    case 'SHIPPED':
      return 3;
    case 'DELIVERED':
    case 'COMPLETED':
      return 4;
    default:
      return 0;
  }
};

const getTimelineState = (stepIndex: number) => {
  if (isOrderClosed.value) {
    return 'closed';
  }

  const currentStep = getStatusStepIndex(normalizedStatus.value);
  if (stepIndex < currentStep) return 'done';
  if (stepIndex === currentStep) return 'active';
  return 'upcoming';
};

const isCompletedTimelineStep = (stepIndex: number) =>
  normalizedStatus.value === 'DELIVERED' && stepIndex === timelineSteps.length - 1;

const getTimelineDate = (stepKey: string) => {
  if (!order.value) return '';

  const sourceMap: Record<string, string | undefined> = {
    created: order.value.created_at,
    paid: order.value.paid_at || order.value.payment?.paid_at,
    processing: order.value.paid_at || order.value.payment?.paid_at,
    shipped: order.value.shipped_at,
    delivered: order.value.delivered_at,
  };

  const value = sourceMap[stepKey];
  if (!value) return '';

  return new Date(value).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatCurrency = (value: number | string | null | undefined) =>
  `Rp ${Number(value || 0).toLocaleString('id-ID')}`;

const formatVariant = (variant: unknown) => {
  if (!variant) return '';
  if (Array.isArray(variant)) return variant.join(', ');
  if (typeof variant === 'object') return Object.values(variant).filter(Boolean).join(', ');
  return String(variant);
};

const getReviewDraft = (itemId: number): ReviewDraft => {
  if (!reviewDrafts.value[itemId]) {
    reviewDrafts.value[itemId] = {
      isOpen: false,
      isSubmitting: false,
      isSubmitted: false,
      rating: 5,
      comment: '',
    };
  }

  return reviewDrafts.value[itemId];
};

const toggleReviewForm = (itemId: number) => {
  const draft = getReviewDraft(itemId);
  draft.isOpen = !draft.isOpen;
};

const canReviewItem = (item: any) =>
  normalizedStatus.value === 'DELIVERED' &&
  !item.review &&
  !getReviewDraft(item.id).isSubmitted &&
  !item.parent_item_id;

const copyToClipboard = async (text: string) => {
  if (!navigator.clipboard) {
    showToast('Browser tidak mendukung fitur salin.', 'error');
    return;
  }

  try {
    await navigator.clipboard.writeText(text);
    showToast('Resi berhasil disalin!', 'success');
  } catch {
    showToast('Gagal menyalin resi.', 'error');
  }
};

const confirmDelivery = async () => {
  if (!order.value || !canConfirmDelivery.value) return;

  isConfirmingDelivery.value = true;
  try {
    const response = await orderRepository.confirmDelivery(order.value.id);
    order.value = response.order;
    showToast(
      response.points_earned > 0
        ? `Pesanan dikonfirmasi. Anda mendapat ${response.points_earned} poin.`
        : response.message || 'Pesanan berhasil dikonfirmasi diterima.',
      'success',
    );
  } catch (error: any) {
    const message = error?.response?.data?.message || 'Gagal mengonfirmasi penerimaan barang.';
    showToast(message, 'error');
  } finally {
    isConfirmingDelivery.value = false;
  }
};

const submitReturn = async () => {
  if (!order.value || !returnForm.value.reason) return;

  isSubmittingReturn.value = true;
  try {
    await returnRepository.submitReturn(
      order.value.id,
      returnForm.value.reason,
      returnForm.value.description,
    );
    hasSubmittedReturn.value = true;
    await loadOrder();
    isReturnFormOpen.value = false;
    returnForm.value.description = '';
    showToast('Pengajuan pengembalian berhasil dikirim.', 'success');
  } catch (error: any) {
    const message = error?.response?.data?.message || 'Gagal mengirim pengajuan pengembalian.';
    showToast(message, 'error');
  } finally {
    isSubmittingReturn.value = false;
  }
};

const submitReview = async (item: any) => {
  const draft = getReviewDraft(item.id);
  if (draft.rating < 1 || draft.rating > 5) {
    showToast('Pilih rating terlebih dahulu.', 'error');
    return;
  }

  draft.isSubmitting = true;
  try {
    await reviewRepository.submitReview(item.id, draft.rating, draft.comment);
    item.review = {
      rating: draft.rating,
      comment: draft.comment,
    };
    draft.isSubmitted = true;
    draft.isOpen = false;
    showToast('Ulasan berhasil dikirim. Terima kasih!', 'success');
  } catch (error: any) {
    const message = error?.response?.data?.message || 'Gagal mengirim ulasan.';
    showToast(message, 'error');
  } finally {
    draft.isSubmitting = false;
  }
};
</script>

<template>
  <div class="relative w-full" style="margin-bottom: -60px;">
    <div class="relative overflow-hidden" style="height: 280px;">
      <img src="/gambar/hero-bg.jpeg" alt="" class="absolute inset-0 w-full h-full object-cover object-center" style="transform: scale(1.08); object-position: center 40%;" />
      <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(10,8,5,0.65) 0%, rgba(30,20,10,0.45) 100%);"></div>
      <div class="absolute bottom-0 left-0 right-0" style="height: 100px; background: linear-gradient(to bottom, transparent 0%, #F5F2EE 100%);"></div>
      <div class="absolute" style="bottom: 100px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.6), transparent);"></div>
      <div class="relative z-10 h-full max-w-6xl mx-auto px-6 flex flex-col justify-between" :style="{ paddingTop: 'calc(var(--header-height, 96px) + 16px)', paddingBottom: '56px' }">
        <!-- Breadcrumb + Back -->
        <div>
          <nav class="flex items-center gap-2 text-xs font-medium mb-2" style="color: rgba(255,255,255,0.55);">
            <router-link to="/" class="hover:text-white transition-colors">Beranda</router-link>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <router-link to="/profile" class="hover:text-white transition-colors">Pesanan Saya</router-link>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-white">Detail Pesanan</span>
          </nav>
          <button @click="router.back()" class="flex items-center gap-2 text-sm font-bold group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
            <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
            Kembali
          </button>
        </div>
        <!-- Page Title -->
        <h1 class="text-4xl font-black tracking-tight text-white" style="font-family: 'Outfit', sans-serif;">Detail Pesanan</h1>
      </div>
    </div>
  </div>

  <main class="max-w-6xl mx-auto px-6 pb-20 flex-grow" style="padding-top: calc(var(--header-height, 96px) + 40px);">
    <div v-if="isLoading" class="animate-pulse space-y-6">
      <div class="h-12 rounded-none w-1/3" style="background: rgba(193,154,81,0.1);"></div>
      <div class="h-64 rounded-none" style="background: rgba(193,154,81,0.07);"></div>
    </div>

    <div v-else-if="!order" class="text-center py-24 rounded-none border border-dashed" style="border-color: rgba(193,154,81,0.25); background: rgba(193,154,81,0.04);">
      <span class="material-symbols-outlined text-6xl block mb-4" style="color: rgba(193,154,81,0.4);">search_off</span>
      <p class="text-lg font-black mb-4" style="color: #1a1209;">Pesanan tidak ditemukan.</p>
      <button @click="router.push('/profile')" class="text-sm font-bold underline underline-offset-4" style="color: #c19a51;">Kembali ke Profil</button>
    </div>

    <div v-else class="space-y-6">
      <div class="rounded-none border p-6 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
        <div>
          <p class="text-xs font-black uppercase tracking-[0.25em] mb-2" style="color: #c19a51;">Nomor Pesanan</p>
          <h2 class="text-2xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">#{{ order.order_number }}</h2>
          <p class="text-xs mt-1 flex items-center gap-1.5" style="color: #8a7a60;">
            <span class="material-symbols-outlined text-sm">calendar_today</span>
            {{ new Date(order.created_at).toLocaleString('id-ID', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
          </p>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full lg:w-auto">
          <div
            class="px-5 py-2 rounded-none text-[10px] font-black uppercase tracking-[0.2em] border-2 text-center"
            :style="`background: ${getStatusConfig(order.status).bg}; color: ${getStatusConfig(order.status).color}; border-color: ${getStatusConfig(order.status).border};`"
          >
            {{ getStatusConfig(order.status).label }}
          </div>
          <button
            v-if="canConfirmDelivery"
            @click="confirmDelivery"
            :disabled="isConfirmingDelivery"
            class="px-5 py-3 rounded-none font-black text-xs uppercase tracking-[0.18em] text-white transition-all disabled:opacity-50"
            style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
          >
            {{ isConfirmingDelivery ? 'Menyimpan...' : 'Barang Sudah Diterima' }}
          </button>
        </div>
      </div>

      <div
        v-if="normalizedStatus === 'SHIPPED'"
        class="rounded-none border p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4"
        style="background: rgba(59,130,246,0.06); border-color: rgba(59,130,246,0.18);"
      >
        <div>
          <p class="text-xs font-black uppercase tracking-[0.2em] mb-2" style="color: #1d4ed8;">
            Konfirmasi Penerimaan
          </p>
          <p class="text-sm leading-relaxed" style="color: #5a5248;">
            Jika paket sudah sampai di rumah atau tujuan Anda, silakan konfirmasi penerimaan pesanan.
          </p>
        </div>

        <button
          @click="confirmDelivery"
          :disabled="isConfirmingDelivery"
          class="px-5 py-3 rounded-none font-black text-xs uppercase tracking-[0.18em] text-white transition-all disabled:opacity-50"
          style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
        >
          {{ isConfirmingDelivery ? 'Menyimpan...' : 'Konfirmasi Diterima' }}
        </button>
      </div>

      <div class="rounded-none border p-6" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
        <div class="flex items-center gap-3 mb-6">
          <span class="material-symbols-outlined" style="color: #c19a51;">route</span>
          <h3 class="font-black text-base" style="color: #1a1209;">Status Pesanan</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div
            v-for="(step, index) in timelineSteps"
            :key="step.key"
            class="relative"
          >
            <div
              class="p-4 border rounded-none h-full"
              :style="getTimelineState(index) === 'done' || isCompletedTimelineStep(index)
                ? 'background: rgba(22,163,74,0.08); border-color: rgba(22,163,74,0.25);'
                : getTimelineState(index) === 'active'
                  ? 'background: rgba(193,154,81,0.08); border-color: rgba(193,154,81,0.3);'
                  : getTimelineState(index) === 'closed'
                    ? 'background: rgba(107,114,128,0.06); border-color: rgba(107,114,128,0.18);'
                    : 'background: rgba(245,242,238,0.9); border-color: rgba(193,154,81,0.12);'"
            >
              <div class="flex items-center justify-between mb-3">
                <span
                  class="material-symbols-outlined text-xl"
                  :style="getTimelineState(index) === 'done' || isCompletedTimelineStep(index)
                    ? 'color: #15803d;'
                    : getTimelineState(index) === 'active'
                      ? 'color: #c19a51;'
                      : 'color: #a8a29e;'"
                >
                  {{ step.icon }}
                </span>
                <span class="text-[10px] font-black uppercase tracking-[0.18em]" style="color: #8a7a60;">
                  {{ index + 1 }}
                </span>
              </div>
              <p class="text-sm font-black mb-1" style="color: #1a1209;">{{ step.label }}</p>
              <p class="text-xs" style="color: #8a7a60;">
                {{ getTimelineDate(step.key) || (getTimelineState(index) === 'upcoming' ? 'Menunggu tahap ini' : 'Belum ada waktu') }}
              </p>
            </div>
          </div>
        </div>

        <div v-if="isOrderClosed" class="mt-5 p-4 border rounded-none" style="background: rgba(107,114,128,0.06); border-color: rgba(107,114,128,0.18); color: #4b5563;">
          Pesanan ini sudah ditutup dengan status {{ getStatusConfig(order.status).label.toLowerCase() }}.
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 space-y-4">
          <div class="rounded-none border overflow-hidden" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b flex items-center gap-3" style="border-color: rgba(193,154,81,0.1);">
              <span class="material-symbols-outlined" style="color: #c19a51;">shopping_bag</span>
              <h3 class="font-black text-base" style="color: #1a1209;">Item Pesanan</h3>
            </div>

            <div class="p-6 flex flex-col gap-6">
              <div
                v-for="item in order.items"
                :key="item.id"
                class="pb-6 border-b last:border-0 last:pb-0"
                style="border-color: rgba(193,154,81,0.08);"
              >
                <div class="flex flex-col md:flex-row gap-5">
                  <router-link 
                    v-if="item.product?.slug"
                    :to="`/products/${item.product.slug}`"
                    class="w-20 h-20 rounded-none overflow-hidden shrink-0 flex items-center justify-center p-2 border transition-all hover:scale-105 active:scale-95 shadow-sm" 
                    style="background: linear-gradient(145deg, #f5f2ee, #ede7dc); border-color: rgba(193,154,81,0.1);"
                  >
                    <img :src="resolveImageUrl(item.product, item.product.name)" class="w-full h-full object-contain mix-blend-multiply" />
                  </router-link>
                  <div v-else class="w-20 h-20 rounded-none overflow-hidden shrink-0 flex items-center justify-center p-2 border" style="background: linear-gradient(145deg, #f5f2ee, #ede7dc); border-color: rgba(193,154,81,0.1);">
                    <span class="material-symbols-outlined text-2xl" style="color: #c19a51; opacity: 0.5;">image</span>
                  </div>

                  <div class="flex-grow flex flex-col gap-3">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-2">
                      <div>
                        <router-link 
                          v-if="item.product?.slug"
                          :to="`/products/${item.product.slug}`"
                          class="font-black text-sm mb-1 hover:text-[#c19a51] transition-colors inline-block" 
                          style="color: #1a1209;"
                        >
                          {{ item.product?.name || item.product_name }}
                        </router-link>
                        <h4 v-else class="font-black text-sm mb-1" style="color: #1a1209;">{{ item.product?.name || item.product_name }}</h4>
                        <p v-if="formatVariant(item.variant)" class="text-[10px] font-bold uppercase tracking-widest" style="color: #8a7a60;">
                          {{ formatVariant(item.variant) }}
                        </p>
                      </div>
                      <p class="font-black text-base" style="color: #1a1209;">
                        {{ formatCurrency((Number(item.product_price || item.price || 0) || 0) * Number(item.quantity || 0)) }}
                      </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                      <span class="text-[10px] font-bold px-2 py-1 rounded" style="background: rgba(193,154,81,0.1); color: #7a6230;">Qty: {{ item.quantity }}</span>
                      <span class="text-sm font-black" style="color: #1a1209;">{{ formatCurrency(item.product_price || item.price || 0) }}</span>
                    </div>

                    <div v-if="canReviewItem(item)" class="pt-2">
                      <button
                        v-if="!getReviewDraft(item.id).isOpen && !getReviewDraft(item.id).isSubmitted"
                        @click="toggleReviewForm(item.id)"
                        class="px-4 py-2 rounded-none text-xs font-black uppercase tracking-[0.16em] transition-all"
                        style="background: rgba(193,154,81,0.12); color: #7a6230; border: 1px solid rgba(193,154,81,0.2);"
                      >
                        Tulis Ulasan
                      </button>

                      <div
                        v-if="getReviewDraft(item.id).isOpen && !getReviewDraft(item.id).isSubmitted"
                        class="mt-3 p-4 border rounded-none space-y-4"
                        style="background: rgba(245,242,238,0.8); border-color: rgba(193,154,81,0.15);"
                      >
                        <div>
                          <p class="text-xs font-black uppercase tracking-[0.16em] mb-3" style="color: #8a7a60;">Rating Produk</p>
                          <div class="flex items-center gap-2">
                            <button
                              v-for="star in 5"
                              :key="star"
                              type="button"
                              @click="getReviewDraft(item.id).rating = star"
                              class="material-symbols-outlined text-2xl transition-transform hover:scale-110"
                              :style="star <= getReviewDraft(item.id).rating ? 'color: #c19a51;' : 'color: rgba(193,154,81,0.25);'"
                            >
                              star
                            </button>
                          </div>
                        </div>

                        <div>
                          <label class="block text-xs font-black uppercase tracking-[0.16em] mb-2" style="color: #8a7a60;">Ulasan</label>
                          <textarea
                            v-model="getReviewDraft(item.id).comment"
                            rows="3"
                            class="w-full rounded-none border px-4 py-3 text-sm outline-none"
                            style="border-color: rgba(193,154,81,0.18); background: white; color: #1a1209;"
                            placeholder="Ceritakan pengalaman Anda dengan produk ini"
                          />
                        </div>

                        <div class="flex flex-wrap gap-3">
                          <button
                            @click="submitReview(item)"
                            :disabled="getReviewDraft(item.id).isSubmitting"
                            class="px-4 py-3 rounded-none text-xs font-black uppercase tracking-[0.16em] text-white disabled:opacity-50"
                            style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
                          >
                            {{ getReviewDraft(item.id).isSubmitting ? 'Mengirim...' : 'Kirim Ulasan' }}
                          </button>
                          <button
                            @click="toggleReviewForm(item.id)"
                            class="px-4 py-3 rounded-none text-xs font-black uppercase tracking-[0.16em]"
                            style="background: white; color: #7a6230; border: 1px solid rgba(193,154,81,0.2);"
                          >
                            Batal
                          </button>
                        </div>
                      </div>

                      <p
                        v-if="getReviewDraft(item.id).isSubmitted"
                        class="text-xs font-bold mt-3"
                        style="color: #15803d;"
                      >
                        Ulasan Anda sudah terkirim.
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Order History Log -->
          <div class="rounded-none border overflow-hidden" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b flex items-center gap-3" style="border-color: rgba(193,154,81,0.1);">
              <span class="material-symbols-outlined" style="color: #c19a51;">history</span>
              <h3 class="font-black text-base" style="color: #1a1209;">Riwayat Aktivitas Pesanan</h3>
            </div>

            <div class="p-6">
              <div v-if="order.logs && order.logs.length > 0" class="relative pl-8 space-y-8 before:absolute before:left-[11px] before:top-2 before:bottom-2 before:w-[2px] before:bg-stone-100">
                <div v-for="log in order.logs" :key="log.id" class="relative">
                  <!-- Timeline Node -->
                  <div class="absolute -left-[30px] top-1.5 w-6 h-6 rounded-full border-4 border-white flex items-center justify-center shadow-sm"
                    :style="log.current_status === 'completed' || log.current_status === 'delivered' 
                      ? 'background: #15803d;' 
                      : log.current_status === 'cancelled' || log.current_status === 'expired'
                        ? 'background: #dc2626;'
                        : 'background: #c19a51;'"
                  >
                    <span class="material-symbols-outlined text-[10px] text-white">
                      {{ log.event_type === 'status_changed' ? 'sync_alt' : 'check' }}
                    </span>
                  </div>
                  
                  <div class="flex flex-col gap-1">
                    <div class="flex items-center justify-between gap-4">
                      <h4 class="text-sm font-black" style="color: #1a1209;">{{ log.title }}</h4>
                      <span class="text-[10px] font-bold uppercase tracking-widest text-stone-400">
                        {{ new Date(log.created_at).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}
                      </span>
                    </div>
                    <p class="text-xs leading-relaxed" style="color: #8a7a60;">{{ log.description }}</p>
                    
                    <!-- Metadata Info (Optional) -->
                    <div v-if="log.metadata?.tracking_number" class="mt-2 p-2 bg-stone-50 border border-stone-100 flex items-center gap-2">
                      <span class="material-symbols-outlined text-xs text-stone-400">local_shipping</span>
                      <span class="text-[10px] font-bold text-stone-600 uppercase">RESI: {{ log.metadata.tracking_number }}</span>
                    </div>

                    <div v-if="log.acted_by || log.actedBy" class="mt-2 flex items-center gap-1.5">
                      <span class="material-symbols-outlined text-[10px]" style="color: #c19a51;">person</span>
                      <span class="text-[10px] font-bold text-stone-500 uppercase tracking-tighter">Oleh: {{ (log.acted_by || log.actedBy).name }}</span>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-10 opacity-50">
                <p class="text-sm italic">Belum ada riwayat aktivitas.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-5 sticky top-28 self-stretch">
          <div class="rounded-none border p-6" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="flex items-center gap-2 mb-6">
              <span class="material-symbols-outlined text-lg" style="color: #c19a51;">receipt_long</span>
              <h3 class="font-black text-base" style="color: #1a1209;">Rincian Biaya</h3>
            </div>

            <div class="flex flex-col gap-3 text-sm">
              <div class="flex justify-between">
                <span style="color: #8a7a60;">Subtotal</span>
                <span class="font-bold" style="color: #1a1209;">{{ formatCurrency(order.subtotal || order.total_amount || 0) }}</span>
              </div>
              <div class="flex justify-between">
                <span style="color: #8a7a60;">Ongkir ({{ order.courier?.toUpperCase() }} {{ order.courier_service }})</span>
                <span class="font-bold" style="color: #1a1209;">{{ formatCurrency(order.shipping_cost || 0) }}</span>
              </div>
              <div v-if="Number(order.discount_amount || 0) > 0" class="flex justify-between">
                <span style="color: #8a7a60;">Diskon</span>
                <span class="font-bold" style="color: #15803d;">-{{ formatCurrency(order.discount_amount || 0) }}</span>
              </div>
            </div>

            <div class="h-px my-4" style="background: rgba(193,154,81,0.2);"></div>

            <div class="flex justify-between items-end">
              <span class="text-sm font-bold" style="color: #5a5248;">Total</span>
              <span class="text-xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">
                {{ formatCurrency(order.total_price || order.total_amount || 0) }}
              </span>
            </div>

            <div v-if="['UNPAID', 'PENDING'].includes(normalizedStatus) && order.payment?.checkout_url" class="mt-6">
              <a
                :href="order.payment.checkout_url"
                class="block w-full text-center py-4 rounded-none font-black text-sm uppercase tracking-wider text-white transition-all hover:shadow-xl active:scale-95 shadow-lg"
                style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
              >
                Bayar Sekarang
              </a>
            </div>
          </div>

          <div class="rounded-none border p-6" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="flex items-center gap-2 mb-5">
              <span class="material-symbols-outlined text-lg" style="color: #c19a51;">assignment_return</span>
              <h3 class="font-black text-base" style="color: #1a1209;">Return & Refund</h3>
            </div>

            <p class="text-sm leading-relaxed mb-4" style="color: #5a5248;">
              Jika barang sudah diterima dan ada kendala, Anda bisa mengajukan pengembalian satu kali untuk pesanan ini.
            </p>

            <div
              v-if="currentReturnRequest"
              class="mb-4 border rounded-none p-4 space-y-3"
              :style="getReturnStatusConfig(currentReturnRequest.status).tone"
            >
              <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-black uppercase tracking-[0.16em]">
                  Status Return
                </p>
                <span class="text-xs font-black uppercase tracking-[0.16em]">
                  {{ getReturnStatusConfig(currentReturnRequest.status).label }}
                </span>
              </div>

              <p class="text-sm leading-relaxed">
                {{ getReturnStatusConfig(currentReturnRequest.status).description }}
              </p>

              <div class="text-sm">
                <p class="font-bold mb-1">Alasan Pengajuan</p>
                <p>{{ currentReturnRequest.reason }}</p>
              </div>

              <div v-if="currentReturnRequest.description" class="text-sm">
                <p class="font-bold mb-1">Keterangan Anda</p>
                <p>{{ currentReturnRequest.description }}</p>
              </div>

              <div v-if="currentReturnRequest.admin_notes" class="text-sm">
                <p class="font-bold mb-1">Catatan Admin</p>
                <p>{{ currentReturnRequest.admin_notes }}</p>
              </div>
            </div>

            <button
              v-if="canSubmitReturn"
              @click="isReturnFormOpen = !isReturnFormOpen"
              class="w-full py-3 rounded-none text-xs font-black uppercase tracking-[0.16em] transition-all"
              style="background: rgba(193,154,81,0.12); color: #7a6230; border: 1px solid rgba(193,154,81,0.2);"
            >
              {{ isReturnFormOpen ? 'Tutup Form Pengajuan' : 'Ajukan Pengembalian' }}
            </button>

            <div v-else-if="currentReturnRequest || hasSubmittedReturn" class="p-3 rounded-none text-sm font-bold" style="background: rgba(22,163,74,0.08); color: #15803d; border: 1px solid rgba(22,163,74,0.18);">
              Pengajuan return sudah tercatat. Status terbaru ditampilkan di kartu di atas.
            </div>

            <div v-else class="p-3 rounded-none text-sm" style="background: rgba(245,242,238,0.85); color: #8a7a60; border: 1px solid rgba(193,154,81,0.12);">
              Return baru tersedia setelah pesanan berstatus diterima.
            </div>

            <div
              v-if="isReturnFormOpen && canSubmitReturn"
              class="mt-4 space-y-4 border rounded-none p-4"
              style="background: rgba(245,242,238,0.8); border-color: rgba(193,154,81,0.15);"
            >
              <div>
                <label class="block text-xs font-black uppercase tracking-[0.16em] mb-2" style="color: #8a7a60;">Alasan Pengembalian</label>
                <select
                  v-model="returnForm.reason"
                  class="w-full rounded-none border px-4 py-3 text-sm outline-none"
                  style="border-color: rgba(193,154,81,0.18); background: white; color: #1a1209;"
                >
                  <option v-for="reason in RETURN_REASONS" :key="reason.value" :value="reason.value">
                    {{ reason.label }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-black uppercase tracking-[0.16em] mb-2" style="color: #8a7a60;">Detail Tambahan</label>
                <textarea
                  v-model="returnForm.description"
                  rows="4"
                  class="w-full rounded-none border px-4 py-3 text-sm outline-none"
                  style="border-color: rgba(193,154,81,0.18); background: white; color: #1a1209;"
                  placeholder="Jelaskan kendala yang Anda alami"
                />
              </div>

              <button
                @click="submitReturn"
                :disabled="isSubmittingReturn"
                class="w-full py-3 rounded-none text-xs font-black uppercase tracking-[0.16em] text-white disabled:opacity-50"
                style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
              >
                {{ isSubmittingReturn ? 'Mengirim...' : 'Kirim Pengajuan Return' }}
              </button>
            </div>
          </div>

          <div v-if="['DELIVERED', 'COMPLETED'].includes(normalizedStatus)" class="rounded-none border p-6" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-lg" style="color: #c19a51;">support_agent</span>
              <h3 class="font-black text-base" style="color: #1a1209;">Komplain Pesanan</h3>
            </div>

            <!-- Sudah ada komplain -->
            <template v-if="existingComplain">
              <div class="mb-4 p-4 border" :style="`background: ${existingComplain.status === 'resolved' ? 'rgba(22,163,74,0.05)' : existingComplain.status === 'rejected' ? 'rgba(220,38,38,0.05)' : 'rgba(193,154,81,0.05)'}; border-color: rgba(193,154,81,0.2);`">
                <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
                  <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] mb-1" style="color: #8a7a60;">Komplain #{{ existingComplain.id }}</p>
                    <p class="text-sm font-bold" style="color: #1a1209;">{{ existingComplain.subject }}</p>
                  </div>
                  <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1"
                    :style="`background: ${existingComplain.status === 'resolved' ? 'rgba(22,163,74,0.1)' : existingComplain.status === 'rejected' ? 'rgba(220,38,38,0.1)' : existingComplain.status === 'in_progress' ? 'rgba(37,99,235,0.1)' : 'rgba(217,119,6,0.1)'}; color: ${existingComplain.status === 'resolved' ? '#16a34a' : existingComplain.status === 'rejected' ? '#dc2626' : existingComplain.status === 'in_progress' ? '#2563eb' : '#d97706'};`">
                    {{ existingComplain.status === 'open' ? 'Menunggu' : existingComplain.status === 'in_progress' ? 'Diproses' : existingComplain.status === 'resolved' ? 'Selesai' : 'Ditolak' }}
                  </span>
                </div>

                <!-- Respons admin jika ada -->
                <div v-if="existingComplain.admin_notes" class="border-t pt-3 mt-3" style="border-color: rgba(193,154,81,0.2);">
                  <p class="text-[10px] font-black uppercase tracking-[0.18em] mb-2" style="color: #8a7a60;">Respons Tim Kami</p>
                  <p class="text-sm leading-relaxed" style="color: #1a1209;">{{ existingComplain.admin_notes }}</p>
                </div>
                <div v-else class="flex items-center gap-1.5 mt-2">
                  <span class="material-symbols-outlined text-sm" style="color: #c19a51;">schedule</span>
                  <p class="text-xs" style="color: #8a7a60;">Menunggu respons dari tim kami. Anda akan dikirim email saat ada pembaruan.</p>
                </div>
              </div>

              <button
                @click="router.push({ name: 'ComplaintDetail', params: { id: existingComplain.id } })"
                class="w-full py-3 rounded-none text-xs font-black uppercase tracking-[0.16em] transition-all"
                style="background: white; color: #1a1209; border: 1px solid rgba(26,18,9,0.2);">
                Lihat Detail Komplain
              </button>
            </template>

            <!-- Belum ada komplain -->
            <template v-else>
              <p class="text-sm leading-relaxed mb-4" style="color: #5a5248;">
                Ada barang yang rusak, kurang, atau tidak sesuai pesanan? Laporkan keluhan Anda kepada kami.
              </p>
              <button
                @click="router.push({ name: 'Complaint', query: { order_id: order.id } })"
                class="w-full py-3 rounded-none text-xs font-black uppercase tracking-[0.16em] transition-all"
                style="background: white; color: #dc2626; border: 1px solid rgba(220,38,38,0.3);">
                Ajukan Komplain
              </button>
            </template>
          </div>

          <div v-if="order.shipping_address" class="rounded-none border p-6" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="flex items-center gap-2 mb-5">
              <span class="material-symbols-outlined text-lg" style="color: #c19a51;">local_shipping</span>
              <h3 class="font-black text-base" style="color: #1a1209;">Informasi Pengiriman</h3>
            </div>

            <div v-if="order.tracking_number" class="mb-6 p-4 rounded-none border-2 border-dashed flex flex-col gap-2" style="border-color: rgba(193,154,81,0.2); background: rgba(193,154,81,0.03);">
              <p class="text-[10px] font-black uppercase tracking-widest text-stone-500">Nomor Resi ({{ order.courier?.toUpperCase() }})</p>
              <div class="flex items-center justify-between gap-3">
                <span class="text-lg font-black text-primary tracking-wider break-all" style="color: #1a1209;">{{ order.tracking_number }}</span>
                <button @click="copyToClipboard(order.tracking_number)" class="p-2 hover:bg-white rounded-lg transition-colors flex items-center gap-1 text-xs font-bold text-primary shrink-0" style="color: #c19a51;">
                  <span class="material-symbols-outlined text-sm">content_copy</span>
                  Salin
                </button>
              </div>
            </div>

            <div class="text-sm leading-relaxed" style="color: #5a5248;">
              <p class="font-black text-base mb-1" style="color: #1a1209;">{{ order.shipping_address.recipient_name }}</p>
              <p class="font-bold mb-3" style="color: #8a7a60;">{{ order.shipping_address.phone }}</p>
              <div class="p-3 rounded-none text-xs leading-relaxed" style="background: rgba(193,154,81,0.06); border: 1px solid rgba(193,154,81,0.1);">
                {{ order.shipping_address.address }}<br>
                {{ order.shipping_address.district }}, {{ order.shipping_address.city }}<br>
                {{ order.shipping_address.province }} {{ order.shipping_address.postal_code }}
              </div>
            </div>
          </div>

          <div v-if="order.notes" class="rounded-none border p-6" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-lg" style="color: #c19a51;">notes</span>
              <h3 class="font-black text-base" style="color: #1a1209;">Catatan Pesanan</h3>
            </div>
            <p class="text-sm italic" style="color: #5a5248;">"{{ order.notes }}"</p>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>
