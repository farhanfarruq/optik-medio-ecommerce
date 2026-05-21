<script setup lang="ts">
import { logger } from '../core/utils/logger';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { orderRepository } from '../repositories/OrderRepository';
import { RETURN_REASONS, returnRepository } from '../repositories/ReturnRepository';
import { reviewRepository } from '../repositories/ReviewRepository';
import { complaintRepository } from '../repositories/ComplaintRepository';
import { useToast } from '../composables/useToast';
import { apiClient } from '../core/api/axiosclient';
import { resolveImageUrl } from '../core/utils/image';
import PageHero from '../components/layout/PageHero.vue';

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
const existingShippingProtectionClaim = ref<any>(null);

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
        logger.warn('Silent sync failed', syncError);
      }
    }
  } catch (error) {
    logger.error('Failed to fetch order', error);
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
    existingComplain.value = await complaintRepository.getComplaintByOrder(id, 'general');
    existingShippingProtectionClaim.value = await complaintRepository.getComplaintByOrder(id, 'shipping_protection');
  } catch {
    // tidak ada komplain, biarkan null
  }
});

const normalizedStatus = computed(() => String(order.value?.status || '').toUpperCase());
const isOrderClosed = computed(() => ['CANCELLED', 'REFUNDED', 'EXPIRED'].includes(normalizedStatus.value));
const canConfirmDelivery = computed(() => normalizedStatus.value === 'SHIPPED');
const currentReturnRequest = computed(() => order.value?.return_request || order.value?.returnRequest || null);
const canSubmitReturn = computed(() => normalizedStatus.value === 'DELIVERED' && !currentReturnRequest.value);
const hasShippingProtection = computed(() => Boolean(order.value?.shipping_protection_opted));
const canSubmitShippingProtectionClaim = computed(() => (
  hasShippingProtection.value
  && ['SHIPPED', 'DELIVERED', 'COMPLETED'].includes(normalizedStatus.value)
  && !existingShippingProtectionClaim.value
));
const isStorePickupOrder = computed(() => order.value?.fulfillment_method === 'store_pickup');
const canBookPickup = computed(() => isStorePickupOrder.value && !isOrderClosed.value);

const pickupBookingQuery = computed<Record<string, string>>(() => {
  const orderId = String(order.value?.id || '');
  const orderNumber = String(order.value?.order_number || orderId);

  return {
    service: 'pickup',
    order_id: orderId,
    order_number: orderNumber,
    source_label: `Pesanan #${orderNumber}`,
  };
});

const goToPickupBooking = () => {
  if (!canBookPickup.value) return;
  router.push({ path: '/appointment', query: pickupBookingQuery.value });
};

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
        tone: 'background: rgba(251,191,36,0.12); color: var(--gold); border-color: rgba(251,191,36,0.24);',
        description: 'Pengajuan return sedang ditinjau oleh admin.',
      };
  }
};

const getComplaintStatusLabel = (status?: string) => (
  status === 'open'
    ? 'Menunggu'
    : status === 'in_progress'
      ? 'Diproses'
      : status === 'resolved'
        ? 'Selesai'
        : 'Ditolak'
);

const getStatusConfig = (status: string) => {
  switch (status?.toUpperCase()) {
    case 'UNPAID':
      return { bg: 'rgba(251,191,36,0.12)', color: 'var(--gold)', border: 'rgba(251,191,36,0.4)', label: 'Belum Bayar' };
    case 'PENDING':
      return { bg: 'rgba(251,191,36,0.12)', color: 'var(--gold)', border: 'rgba(251,191,36,0.4)', label: 'Menunggu Konfirmasi' };
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
  <PageHero
    title="Detail Pesanan"
    subtitle="Pantau rincian item, pembayaran, pengiriman, dan tindakan purna jual."
    :breadcrumbs="[{ label: 'Pesanan Saya', to: '/orders' }, { label: 'Detail Pesanan' }]"
    back-to="/orders"
    back-label="Kembali ke Pesanan"
  />

  <main class="container-commerce pt-8 pb-20 flex-grow">
    <div v-if="isLoading" class="animate-pulse space-y-6">
      <div class="h-12 rounded-lg w-1/3" style="background: rgba(184,138,68,0.1);"></div>
      <div class="h-64 rounded-lg" style="background: rgba(184,138,68,0.07);"></div>
    </div>

    <div v-else-if="!order" class="text-center py-24 rounded-lg border border-dashed" style="border-color: rgba(184,138,68,0.25); background: rgba(184,138,68,0.04);">
      <span class="material-symbols-outlined text-6xl block mb-4" style="color: rgba(184,138,68,0.4);">search_off</span>
      <p class="text-lg font-black mb-4" style="color: var(--ink);">Pesanan tidak ditemukan.</p>
      <button @click="router.push('/profile')" class="text-sm font-bold underline underline-offset-4" style="color: var(--gold);">Kembali ke Profil</button>
    </div>

    <div v-else class="space-y-6">
      <div class="premium-card p-6 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4" style="background: var(--porcelain); border-color: rgba(184,138,68,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
        <div>
          <p class="text-xs font-black uppercase tracking-[0.25em] mb-2" style="color: var(--gold);">Nomor Pesanan</p>
          <h2 class="text-2xl font-black" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">#{{ order.order_number }}</h2>
          <p class="text-xs mt-1 flex items-center gap-1.5" style="color: #5c4a3a;">
            <span class="material-symbols-outlined text-sm">calendar_today</span>
            {{ new Date(order.created_at).toLocaleString('id-ID', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}
          </p>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full lg:w-auto">
          <div
            class="px-5 py-2 rounded-lg text-[10px] font-black uppercase tracking-[0.2em] border-2 text-center"
            :style="`background: ${getStatusConfig(order.status).bg}; color: ${getStatusConfig(order.status).color}; border-color: ${getStatusConfig(order.status).border};`"
          >
            {{ getStatusConfig(order.status).label }}
          </div>
          <button
            v-if="canConfirmDelivery"
            @click="confirmDelivery"
            :disabled="isConfirmingDelivery"
            class="px-5 py-3 rounded-lg font-black text-xs uppercase tracking-[0.18em] text-white transition-all disabled:opacity-50"
            style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
          >
            {{ isConfirmingDelivery ? 'Menyimpan...' : 'Barang Sudah Diterima' }}
          </button>
        </div>
      </div>

      <div
        v-if="canBookPickup"
        class="rounded-lg border p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4"
        style="background: rgba(184,138,68,0.08); border-color: rgba(184,138,68,0.24);"
      >
        <div class="flex items-start gap-3">
          <span class="material-symbols-outlined text-3xl shrink-0" style="color: var(--gold);">event_available</span>
          <div>
            <p class="text-xs font-black uppercase tracking-[0.2em] mb-2" style="color: #7a6230;">
              Booking Pengambilan Toko
            </p>
            <p class="text-sm leading-relaxed" style="color: var(--graphite);">
              Pesanan ambil di toko bisa dijadwalkan dari sini. Pilih cabang dan waktu pengambilan agar staf menyiapkan pesanan Anda.
            </p>
          </div>
        </div>

        <button
          @click="goToPickupBooking"
          class="px-5 py-3 rounded-lg font-black text-xs uppercase tracking-[0.18em] text-white transition-all hover:translate-y-[-1px]"
          style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
        >
          Booking Ambil Pesanan
        </button>
      </div>

      <div
        v-if="normalizedStatus === 'SHIPPED'"
        class="rounded-lg border p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4"
        style="background: rgba(59,130,246,0.06); border-color: rgba(59,130,246,0.18);"
      >
        <div>
          <p class="text-xs font-black uppercase tracking-[0.2em] mb-2" style="color: #1d4ed8;">
            Konfirmasi Penerimaan
          </p>
          <p class="text-sm leading-relaxed" style="color: var(--graphite);">
            Jika paket sudah sampai di rumah atau tujuan Anda, silakan konfirmasi penerimaan pesanan.
          </p>
        </div>

        <button
          @click="confirmDelivery"
          :disabled="isConfirmingDelivery"
          class="px-5 py-3 rounded-lg font-black text-xs uppercase tracking-[0.18em] text-white transition-all disabled:opacity-50"
          style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
        >
          {{ isConfirmingDelivery ? 'Menyimpan...' : 'Konfirmasi Diterima' }}
        </button>
      </div>

      <div class="premium-card p-6">
        <div class="flex items-center gap-3 mb-6">
          <span class="material-symbols-outlined" style="color: var(--gold);">route</span>
          <h3 class="font-black text-base" style="color: var(--ink);">Status Pesanan</h3>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <div
            v-for="(step, index) in timelineSteps"
            :key="step.key"
            class="relative"
          >
            <div
              class="p-4 border rounded-lg h-full"
              :style="getTimelineState(index) === 'done' || isCompletedTimelineStep(index)
                ? 'background: rgba(22,163,74,0.08); border-color: rgba(22,163,74,0.25);'
                : getTimelineState(index) === 'active'
                  ? 'background: rgba(184,138,68,0.08); border-color: rgba(184,138,68,0.3);'
                  : getTimelineState(index) === 'closed'
                    ? 'background: rgba(107,114,128,0.06); border-color: rgba(107,114,128,0.18);'
                    : 'background: rgba(245,242,238,0.9); border-color: rgba(184,138,68,0.12);'"
            >
              <div class="flex items-center justify-between mb-3">
                <span
                  class="material-symbols-outlined text-xl"
                  :style="getTimelineState(index) === 'done' || isCompletedTimelineStep(index)
                    ? 'color: #15803d;'
                    : getTimelineState(index) === 'active'
                      ? 'color: var(--gold);'
                      : 'color: #a8a29e;'"
                >
                  {{ step.icon }}
                </span>
                <span class="text-[10px] font-black uppercase tracking-[0.18em]" style="color: #5c4a3a;">
                  {{ index + 1 }}
                </span>
              </div>
              <p class="text-sm font-black mb-1" style="color: var(--ink);">{{ step.label }}</p>
              <p class="text-xs" style="color: #5c4a3a;">
                {{ getTimelineDate(step.key) || (getTimelineState(index) === 'upcoming' ? 'Menunggu tahap ini' : 'Belum ada waktu') }}
              </p>
            </div>
          </div>
        </div>

        <div v-if="isOrderClosed" class="mt-5 p-4 border rounded-lg" style="background: rgba(107,114,128,0.06); border-color: rgba(107,114,128,0.18); color: #4b5563;">
          Pesanan ini sudah ditutup dengan status {{ getStatusConfig(order.status).label.toLowerCase() }}.
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="lg:col-span-2 space-y-4">
          <div class="rounded-lg border overflow-hidden" style="background: var(--porcelain); border-color: rgba(184,138,68,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b flex items-center gap-3" style="border-color: rgba(184,138,68,0.1);">
              <span class="material-symbols-outlined" style="color: var(--gold);">shopping_bag</span>
              <h3 class="font-black text-base" style="color: var(--ink);">Item Pesanan</h3>
            </div>

            <div class="p-6 flex flex-col gap-6">
              <div
                v-for="item in order.items"
                :key="item.id"
                class="pb-6 border-b last:border-0 last:pb-0"
                style="border-color: rgba(184,138,68,0.08);"
              >
                <div class="flex flex-col md:flex-row gap-5">
                  <router-link 
                    v-if="item.product?.slug"
                    :to="`/products/${item.product.slug}`"
                    class="w-20 h-20 rounded-lg overflow-hidden shrink-0 flex items-center justify-center p-2 border transition-all hover:scale-105 active:scale-95 shadow-card" 
                    style="background: linear-gradient(145deg, var(--ivory), var(--mist)); border-color: rgba(184,138,68,0.1);"
                  >
                    <img alt="" :src="resolveImageUrl(item.product, item.product.name)" class="w-full h-full object-contain mix-blend-multiply" loading="lazy" decoding="async" />
                  </router-link>
                  <div v-else class="w-20 h-20 rounded-lg overflow-hidden shrink-0 flex items-center justify-center p-2 border" style="background: linear-gradient(145deg, var(--ivory), var(--mist)); border-color: rgba(184,138,68,0.1);">
                    <span class="material-symbols-outlined text-2xl" style="color: var(--gold); opacity: 0.5;">image</span>
                  </div>

                  <div class="flex-grow flex flex-col gap-3">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-2">
                      <div>
                        <router-link 
                          v-if="item.product?.slug"
                          :to="`/products/${item.product.slug}`"
                          class="font-black text-sm mb-1 hover:text-[var(--gold)] transition-colors inline-block" 
                          style="color: var(--ink);"
                        >
                          {{ item.product?.name || item.product_name }}
                        </router-link>
                        <h4 v-else class="font-black text-sm mb-1" style="color: var(--ink);">{{ item.product?.name || item.product_name }}</h4>
                        <p v-if="formatVariant(item.variant)" class="text-[10px] font-bold uppercase tracking-widest" style="color: #5c4a3a;">
                          {{ formatVariant(item.variant) }}
                        </p>
                      </div>
                      <p class="font-black text-base" style="color: var(--ink);">
                        {{ formatCurrency((Number(item.product_price || item.price || 0) || 0) * Number(item.quantity || 0)) }}
                      </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                      <span class="text-[10px] font-bold px-2 py-1 rounded" style="background: rgba(184,138,68,0.1); color: #7a6230;">Qty: {{ item.quantity }}</span>
                      <span class="text-sm font-black" style="color: var(--ink);">{{ formatCurrency(item.product_price || item.price || 0) }}</span>
                    </div>

                    <div v-if="canReviewItem(item)" class="pt-2">
                      <button
                        v-if="!getReviewDraft(item.id).isOpen && !getReviewDraft(item.id).isSubmitted"
                        @click="toggleReviewForm(item.id)"
                        class="px-4 py-2 rounded-lg text-xs font-black uppercase tracking-[0.16em] transition-all"
                        style="background: rgba(184,138,68,0.12); color: #7a6230; border: 1px solid rgba(184,138,68,0.2);"
                      >
                        Tulis Ulasan
                      </button>

                      <div
                        v-if="getReviewDraft(item.id).isOpen && !getReviewDraft(item.id).isSubmitted"
                        class="mt-3 p-4 border rounded-lg space-y-4"
                        style="background: rgba(245,242,238,0.8); border-color: rgba(184,138,68,0.15);"
                      >
                        <div>
                          <p class="text-xs font-black uppercase tracking-[0.16em] mb-3" style="color: #5c4a3a;">Rating Produk</p>
                          <div class="flex items-center gap-2">
                            <button
                              v-for="star in 5"
                              :key="star"
                              type="button"
                              @click="getReviewDraft(item.id).rating = star"
                              class="material-symbols-outlined text-2xl transition-transform hover:scale-110"
                              :style="star <= getReviewDraft(item.id).rating ? 'color: var(--gold);' : 'color: rgba(184,138,68,0.25);'"
                            >
                              star
                            </button>
                          </div>
                        </div>

                        <div>
                          <label class="block text-xs font-black uppercase tracking-[0.16em] mb-2" style="color: #5c4a3a;">Ulasan</label>
                          <textarea
                            v-model="getReviewDraft(item.id).comment"
                            rows="3"
                            class="w-full rounded-lg border px-4 py-3 text-sm outline-none"
                            style="border-color: rgba(184,138,68,0.18); background: var(--porcelain); color: var(--ink);"
                            placeholder="Ceritakan pengalaman Anda dengan produk ini"
                          />
                        </div>

                        <div class="flex flex-wrap gap-3">
                          <button
                            @click="submitReview(item)"
                            :disabled="getReviewDraft(item.id).isSubmitting"
                            class="px-4 py-3 rounded-lg text-xs font-black uppercase tracking-[0.16em] text-white disabled:opacity-50"
                            style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
                          >
                            {{ getReviewDraft(item.id).isSubmitting ? 'Mengirim...' : 'Kirim Ulasan' }}
                          </button>
                          <button
                            @click="toggleReviewForm(item.id)"
                            class="px-4 py-3 rounded-lg text-xs font-black uppercase tracking-[0.16em]"
                            style="background: var(--porcelain); color: #7a6230; border: 1px solid rgba(184,138,68,0.2);"
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
          <div class="rounded-lg border overflow-hidden" style="background: var(--porcelain); border-color: rgba(184,138,68,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="px-6 py-5 border-b flex items-center gap-3" style="border-color: rgba(184,138,68,0.1);">
              <span class="material-symbols-outlined" style="color: var(--gold);">history</span>
              <h3 class="font-black text-base" style="color: var(--ink);">Riwayat Aktivitas Pesanan</h3>
            </div>

            <div class="p-6">
              <div v-if="order.logs && order.logs.length > 0" class="relative pl-8 space-y-8 before:absolute before:left-[11px] before:top-2 before:bottom-2 before:w-[2px] before:bg-mist">
                <div v-for="log in order.logs" :key="log.id" class="relative">
                  <!-- Timeline Node -->
                  <div class="absolute -left-[30px] top-1.5 w-6 h-6 rounded-full border-4 border-porcelain flex items-center justify-center shadow-card"
                    :style="log.current_status === 'completed' || log.current_status === 'delivered' 
                      ? 'background: #15803d;' 
                      : log.current_status === 'cancelled' || log.current_status === 'expired'
                        ? 'background: #dc2626;'
                        : 'background: var(--gold);'"
                  >
                    <span class="material-symbols-outlined text-[10px] text-white">
                      {{ log.event_type === 'status_changed' ? 'sync_alt' : 'check' }}
                    </span>
                  </div>
                  
                  <div class="flex flex-col gap-1">
                    <div class="flex items-center justify-between gap-4">
                      <h4 class="text-sm font-black" style="color: var(--ink);">{{ log.title }}</h4>
                      <span class="text-[10px] font-bold uppercase tracking-widest text-graphite/45">
                        {{ new Date(log.created_at).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }}
                      </span>
                    </div>
                    <p class="text-xs leading-relaxed" style="color: #5c4a3a;">{{ log.description }}</p>
                    
                    <!-- Metadata Info (Optional) -->
                    <div v-if="log.metadata?.tracking_number" class="mt-2 p-2 bg-ivory border border-mist flex items-center gap-2">
                      <span class="material-symbols-outlined text-xs text-graphite/45">local_shipping</span>
                      <span class="text-[10px] font-bold text-graphite/80 uppercase">RESI: {{ log.metadata.tracking_number }}</span>
                    </div>

                    <div v-if="log.acted_by || log.actedBy" class="mt-2 flex items-center gap-1.5">
                      <span class="material-symbols-outlined text-[10px]" style="color: var(--gold);">person</span>
                      <span class="text-[10px] font-bold text-graphite/65 uppercase tracking-normaler">Oleh: {{ (log.acted_by || log.actedBy).name }}</span>
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

        <div class="space-y-5 self-stretch">
          <div class="premium-card p-6">
            <div class="flex items-center gap-2 mb-6">
              <span class="material-symbols-outlined text-lg" style="color: var(--gold);">receipt_long</span>
              <h3 class="font-black text-base" style="color: var(--ink);">Rincian Biaya</h3>
            </div>

            <div class="flex flex-col gap-3 text-sm">
              <div class="flex justify-between">
                <span style="color: #5c4a3a;">Subtotal</span>
                <span class="font-bold" style="color: var(--ink);">{{ formatCurrency(order.subtotal || order.total_amount || 0) }}</span>
              </div>
              <div class="flex justify-between">
                <span style="color: #5c4a3a;">Ongkir ({{ order.courier?.toUpperCase() }} {{ order.courier_service }})</span>
                <span class="font-bold" style="color: var(--ink);">{{ formatCurrency(order.shipping_cost || 0) }}</span>
              </div>
              <div v-if="Number(order.shipping_protection_fee || 0) > 0" class="flex justify-between">
                <span style="color: #5c4a3a;">Proteksi Pengiriman</span>
                <span class="font-bold" style="color: var(--ink);">{{ formatCurrency(order.shipping_protection_fee || 0) }}</span>
              </div>
              <div v-if="Number(order.discount_amount || 0) > 0" class="flex justify-between">
                <span style="color: #5c4a3a;">Diskon</span>
                <span class="font-bold" style="color: #15803d;">-{{ formatCurrency(order.discount_amount || 0) }}</span>
              </div>
            </div>

            <div class="h-px my-4" style="background: rgba(184,138,68,0.2);"></div>

            <div class="flex justify-between items-end">
              <span class="text-sm font-bold" style="color: var(--graphite);">Total</span>
              <span class="text-xl font-black" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">
                {{ formatCurrency(order.total_price || order.total_amount || 0) }}
              </span>
            </div>

            <div v-if="['UNPAID', 'PENDING'].includes(normalizedStatus) && order.payment?.checkout_url" class="mt-6">
              <a
                :href="order.payment.checkout_url"
                class="block w-full text-center py-4 rounded-lg font-black text-sm uppercase tracking-wider text-white transition-all hover:shadow-soft active:scale-95 shadow-card"
                style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
              >
                Bayar Sekarang
              </a>
            </div>
          </div>

          <div class="premium-card p-6">
            <div class="flex items-center gap-2 mb-5">
              <span class="material-symbols-outlined text-lg" style="color: var(--gold);">assignment_return</span>
              <h3 class="font-black text-base" style="color: var(--ink);">Return & Refund</h3>
            </div>

            <p class="text-sm leading-relaxed mb-4" style="color: var(--graphite);">
              Jika barang sudah diterima dan ada kendala, Anda bisa mengajukan pengembalian satu kali untuk pesanan ini.
            </p>

            <div
              v-if="currentReturnRequest"
              class="mb-4 border rounded-lg p-4 space-y-3"
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
              class="w-full py-3 rounded-lg text-xs font-black uppercase tracking-[0.16em] transition-all"
              style="background: rgba(184,138,68,0.12); color: #7a6230; border: 1px solid rgba(184,138,68,0.2);"
            >
              {{ isReturnFormOpen ? 'Tutup Form Pengajuan' : 'Ajukan Pengembalian' }}
            </button>

            <div v-else-if="currentReturnRequest || hasSubmittedReturn" class="p-3 rounded-lg text-sm font-bold" style="background: rgba(22,163,74,0.08); color: #15803d; border: 1px solid rgba(22,163,74,0.18);">
              Pengajuan return sudah tercatat. Status terbaru ditampilkan di kartu di atas.
            </div>

            <div v-else class="p-3 rounded-lg text-sm" style="background: rgba(245,242,238,0.85); color: #5c4a3a; border: 1px solid rgba(184,138,68,0.12);">
              Return baru tersedia setelah pesanan berstatus diterima.
            </div>

            <div
              v-if="isReturnFormOpen && canSubmitReturn"
              class="mt-4 space-y-4 border rounded-lg p-4"
              style="background: rgba(245,242,238,0.8); border-color: rgba(184,138,68,0.15);"
            >
              <div>
                <label class="block text-xs font-black uppercase tracking-[0.16em] mb-2" style="color: #5c4a3a;">Alasan Pengembalian</label>
                <select
                  v-model="returnForm.reason"
                  class="w-full rounded-lg border px-4 py-3 text-sm outline-none"
                  style="border-color: rgba(184,138,68,0.18); background: var(--porcelain); color: var(--ink);"
                >
                  <option v-for="reason in RETURN_REASONS" :key="reason.value" :value="reason.value">
                    {{ reason.label }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-black uppercase tracking-[0.16em] mb-2" style="color: #5c4a3a;">Detail Tambahan</label>
                <textarea
                  v-model="returnForm.description"
                  rows="4"
                  class="w-full rounded-lg border px-4 py-3 text-sm outline-none"
                  style="border-color: rgba(184,138,68,0.18); background: var(--porcelain); color: var(--ink);"
                  placeholder="Jelaskan kendala yang Anda alami"
                />
              </div>

              <button
                @click="submitReturn"
                :disabled="isSubmittingReturn"
                class="w-full py-3 rounded-lg text-xs font-black uppercase tracking-[0.16em] text-white disabled:opacity-50"
                style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
              >
                {{ isSubmittingReturn ? 'Mengirim...' : 'Kirim Pengajuan Return' }}
              </button>
            </div>
          </div>

          <div
            v-if="hasShippingProtection && ['SHIPPED', 'DELIVERED', 'COMPLETED'].includes(normalizedStatus)"
            class="rounded-lg border p-6"
            style="background: var(--porcelain); border-color: rgba(59,130,246,0.16); box-shadow: 0 2px 12px rgba(0,0,0,0.04);"
          >
            <div class="flex items-center gap-2 mb-5">
              <span class="material-symbols-outlined text-lg" style="color: #2563eb;">verified_user</span>
              <h3 class="font-black text-base" style="color: var(--ink);">Klaim Proteksi Pengiriman</h3>
            </div>

            <p class="text-sm leading-relaxed mb-4" style="color: var(--graphite);">
              Pesanan ini memakai proteksi pengiriman sebesar {{ formatCurrency(order.shipping_protection_fee || 0) }}.
              Jika paket rusak, hilang, atau bermasalah saat tiba, Anda bisa mengajukan klaim langsung dari sini.
            </p>

            <template v-if="existingShippingProtectionClaim">
              <div class="mb-4 p-4 border" :style="`background: ${existingShippingProtectionClaim.status === 'resolved' ? 'rgba(22,163,74,0.05)' : existingShippingProtectionClaim.status === 'rejected' ? 'rgba(220,38,38,0.05)' : 'rgba(59,130,246,0.05)'}; border-color: rgba(59,130,246,0.18);`">
                <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
                  <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] mb-1" style="color: #2563eb;">Klaim #{{ existingShippingProtectionClaim.id }}</p>
                    <p class="text-sm font-bold" style="color: var(--ink);">{{ existingShippingProtectionClaim.subject }}</p>
                  </div>
                  <span
                    class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1"
                    :style="`background: ${existingShippingProtectionClaim.status === 'resolved' ? 'rgba(22,163,74,0.1)' : existingShippingProtectionClaim.status === 'rejected' ? 'rgba(220,38,38,0.1)' : existingShippingProtectionClaim.status === 'in_progress' ? 'rgba(37,99,235,0.1)' : 'rgba(217,119,6,0.1)'}; color: ${existingShippingProtectionClaim.status === 'resolved' ? '#16a34a' : existingShippingProtectionClaim.status === 'rejected' ? '#dc2626' : existingShippingProtectionClaim.status === 'in_progress' ? '#2563eb' : '#d97706'};`"
                  >
                    {{ getComplaintStatusLabel(existingShippingProtectionClaim.status) }}
                  </span>
                </div>

                <p class="text-sm leading-relaxed" style="color: var(--ink);">
                  {{ existingShippingProtectionClaim.message }}
                </p>

                <div v-if="existingShippingProtectionClaim.admin_notes" class="border-t pt-3 mt-3" style="border-color: rgba(59,130,246,0.18);">
                  <p class="text-[10px] font-black uppercase tracking-[0.18em] mb-2" style="color: #2563eb;">Respons Tim Kami</p>
                  <p class="text-sm leading-relaxed" style="color: var(--ink);">{{ existingShippingProtectionClaim.admin_notes }}</p>
                </div>
              </div>

              <button
                @click="router.push({ name: 'ComplaintDetail', params: { id: existingShippingProtectionClaim.id } })"
                class="w-full py-3 rounded-lg text-xs font-black uppercase tracking-[0.16em] transition-all"
                style="background: var(--porcelain); color: var(--ink); border: 1px solid rgba(26,18,9,0.2);"
              >
                Lihat Detail Klaim
              </button>
            </template>

            <button
              v-else-if="canSubmitShippingProtectionClaim"
              @click="router.push({ name: 'Complaint', query: { order_id: order.id, mode: 'shipping_protection' } })"
              class="w-full py-3 rounded-lg text-xs font-black uppercase tracking-[0.16em] transition-all"
              style="background: rgba(37,99,235,0.08); color: #1d4ed8; border: 1px solid rgba(37,99,235,0.2);"
            >
              Ajukan Klaim Proteksi
            </button>

            <div v-else class="p-3 rounded-lg text-sm" style="background: rgba(245,242,238,0.85); color: #5c4a3a; border: 1px solid rgba(184,138,68,0.12);">
              Klaim proteksi belum tersedia untuk status pesanan ini.
            </div>
          </div>

          <div v-if="['DELIVERED', 'COMPLETED'].includes(normalizedStatus)" class="premium-card p-6">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-lg" style="color: var(--gold);">support_agent</span>
              <h3 class="font-black text-base" style="color: var(--ink);">Komplain Pesanan</h3>
            </div>

            <!-- Sudah ada komplain -->
            <template v-if="existingComplain">
              <div class="mb-4 p-4 border" :style="`background: ${existingComplain.status === 'resolved' ? 'rgba(22,163,74,0.05)' : existingComplain.status === 'rejected' ? 'rgba(220,38,38,0.05)' : 'rgba(184,138,68,0.05)'}; border-color: rgba(184,138,68,0.2);`">
                <div class="flex items-start justify-between gap-3 flex-wrap mb-3">
                  <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] mb-1" style="color: #5c4a3a;">Komplain #{{ existingComplain.id }}</p>
                    <p class="text-sm font-bold" style="color: var(--ink);">{{ existingComplain.subject }}</p>
                  </div>
                  <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1"
                    :style="`background: ${existingComplain.status === 'resolved' ? 'rgba(22,163,74,0.1)' : existingComplain.status === 'rejected' ? 'rgba(220,38,38,0.1)' : existingComplain.status === 'in_progress' ? 'rgba(37,99,235,0.1)' : 'rgba(217,119,6,0.1)'}; color: ${existingComplain.status === 'resolved' ? '#16a34a' : existingComplain.status === 'rejected' ? '#dc2626' : existingComplain.status === 'in_progress' ? '#2563eb' : '#d97706'};`">
                    {{ existingComplain.status === 'open' ? 'Menunggu' : existingComplain.status === 'in_progress' ? 'Diproses' : existingComplain.status === 'resolved' ? 'Selesai' : 'Ditolak' }}
                  </span>
                </div>

                <!-- Respons admin jika ada -->
                <div v-if="existingComplain.admin_notes" class="border-t pt-3 mt-3" style="border-color: rgba(184,138,68,0.2);">
                  <p class="text-[10px] font-black uppercase tracking-[0.18em] mb-2" style="color: #5c4a3a;">Respons Tim Kami</p>
                  <p class="text-sm leading-relaxed" style="color: var(--ink);">{{ existingComplain.admin_notes }}</p>
                </div>
                <div v-else class="flex items-center gap-1.5 mt-2">
                  <span class="material-symbols-outlined text-sm" style="color: var(--gold);">schedule</span>
                  <p class="text-xs" style="color: #5c4a3a;">Menunggu respons dari tim kami. Anda akan dikirim email saat ada pembaruan.</p>
                </div>
              </div>

              <button
                @click="router.push({ name: 'ComplaintDetail', params: { id: existingComplain.id } })"
                class="w-full py-3 rounded-lg text-xs font-black uppercase tracking-[0.16em] transition-all"
                style="background: var(--porcelain); color: var(--ink); border: 1px solid rgba(26,18,9,0.2);">
                Lihat Detail Komplain
              </button>
            </template>

            <!-- Belum ada komplain -->
            <template v-else>
              <p class="text-sm leading-relaxed mb-4" style="color: var(--graphite);">
                Ada barang yang rusak, kurang, atau tidak sesuai pesanan? Laporkan keluhan Anda kepada kami.
              </p>
              <button
                @click="router.push({ name: 'Complaint', query: { order_id: order.id } })"
                class="w-full py-3 rounded-lg text-xs font-black uppercase tracking-[0.16em] transition-all"
                style="background: var(--porcelain); color: #dc2626; border: 1px solid rgba(220,38,38,0.3);">
                Ajukan Komplain
              </button>
            </template>
          </div>

          <div v-if="order.shipping_address" class="premium-card p-6">
            <div class="flex items-center gap-2 mb-5">
              <span class="material-symbols-outlined text-lg" style="color: var(--gold);">local_shipping</span>
              <h3 class="font-black text-base" style="color: var(--ink);">Informasi Pengiriman</h3>
            </div>

            <div v-if="order.tracking_number" class="mb-6 p-4 rounded-lg border-2 border-dashed flex flex-col gap-2" style="border-color: rgba(184,138,68,0.2); background: rgba(184,138,68,0.03);">
              <p class="text-[10px] font-black uppercase tracking-widest text-graphite/65">Nomor Resi ({{ order.courier?.toUpperCase() }})</p>
              <div class="flex items-center justify-between gap-3">
                <span class="text-lg font-black text-primary tracking-wider break-all" style="color: var(--ink);">{{ order.tracking_number }}</span>
                <button @click="copyToClipboard(order.tracking_number)" class="p-2 hover:bg-white rounded-lg transition-colors flex items-center gap-1 text-xs font-bold text-primary shrink-0" style="color: var(--gold);">
                  <span class="material-symbols-outlined text-sm">content_copy</span>
                  Salin
                </button>
              </div>
            </div>

            <div class="text-sm leading-relaxed" style="color: var(--graphite);">
              <p class="font-black text-base mb-1" style="color: var(--ink);">{{ order.shipping_address.recipient_name }}</p>
              <p class="font-bold mb-3" style="color: #5c4a3a;">{{ order.shipping_address.phone }}</p>
              <div class="p-3 rounded-lg text-xs leading-relaxed" style="background: rgba(184,138,68,0.06); border: 1px solid rgba(184,138,68,0.1);">
                {{ order.shipping_address.address }}<br>
                {{ order.shipping_address.district }}, {{ order.shipping_address.city }}<br>
                {{ order.shipping_address.province }} {{ order.shipping_address.postal_code }}
              </div>
            </div>
          </div>

          <div v-if="order.notes" class="premium-card p-6">
            <div class="flex items-center gap-2 mb-4">
              <span class="material-symbols-outlined text-lg" style="color: var(--gold);">notes</span>
              <h3 class="font-black text-base" style="color: var(--ink);">Catatan Pesanan</h3>
            </div>
            <p class="text-sm italic" style="color: var(--graphite);">"{{ order.notes }}"</p>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>
