<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { orderRepository } from '../repositories/OrderRepository';
import { useToast } from '../composables/useToast';
import PageHero from '../components/layout/PageHero.vue';

const route = useRoute();
const router = useRouter();
const { showToast } = useToast();

const isLoading = ref(true);
const isConfirming = ref(false);
const tracking = ref<any>(null);

const timeline = computed(() => tracking.value?.logs || []);

const normalizedStatus = computed(() =>
  String(tracking.value?.status || '').toLowerCase()
);

const statusConfig = computed(() => {
  const map: Record<string, { label: string; color: string; bg: string; icon: string }> = {
    unpaid:     { label: 'Belum Bayar',    color: '#d97706', bg: 'rgba(217,119,6,0.08)',   icon: 'schedule' },
    paid:       { label: 'Lunas',          color: '#2563eb', bg: 'rgba(37,99,235,0.08)',   icon: 'payments' },
    processing: { label: 'Diproses',       color: '#7c3aed', bg: 'rgba(124,58,237,0.08)', icon: 'inventory_2' },
    shipped:    { label: 'Dikirim',        color: '#0891b2', bg: 'rgba(8,145,178,0.08)',  icon: 'local_shipping' },
    delivered:  { label: 'Diterima',       color: '#16a34a', bg: 'rgba(22,163,74,0.08)',  icon: 'check_circle' },
    cancelled:  { label: 'Dibatalkan',     color: '#dc2626', bg: 'rgba(220,38,38,0.08)',  icon: 'cancel' },
    refunded:   { label: 'Dikembalikan',   color: '#0369a1', bg: 'rgba(3,105,161,0.08)',  icon: 'undo' },
  };
  return map[normalizedStatus.value] ?? { label: tracking.value?.status ?? '-', color: '#5c4a3a', bg: 'rgba(138,122,96,0.08)', icon: 'info' };
});

// Urutan progress bar
const progressSteps = ['unpaid', 'paid', 'processing', 'shipped', 'delivered'];
const currentStepIndex = computed(() =>
  progressSteps.indexOf(normalizedStatus.value)
);

const stepLabels: Record<string, { label: string; icon: string }> = {
  unpaid:     { label: 'Pesanan Dibuat',   icon: 'shopping_bag' },
  paid:       { label: 'Pembayaran',       icon: 'payments' },
  processing: { label: 'Diproses',         icon: 'inventory_2' },
  shipped:    { label: 'Dikirim',          icon: 'local_shipping' },
  delivered:  { label: 'Diterima',         icon: 'home' },
};

const isCod = computed(() => {
  const pm = tracking.value?.payment?.paymentMethod || tracking.value?.payment?.payment_method;
  const code = typeof pm === 'object' ? pm?.code : pm;
  return String(code || '').toLowerCase() === 'cod';
});

const canConfirmDelivery = computed(() =>
  normalizedStatus.value === 'shipped'
);

const breadcrumbs = computed(() => [
  { label: 'Pesanan', to: '/orders' },
  { label: tracking.value?.order_number || '...', to: tracking.value ? `/orders/${tracking.value.id || tracking.value.order_id}` : undefined },
  { label: 'Tracking' },
]);

const formatDate = (value?: string | null) => {
  if (!value) return '-';
  return new Date(value).toLocaleString('id-ID', {
    day: 'numeric', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
};

const eventIcon = (eventType: string) => {
  const map: Record<string, string> = {
    order_created:          'shopping_bag',
    status_changed:         'swap_horiz',
    tracking_updated:       'local_shipping',
    payment_proof_uploaded: 'upload_file',
    payment_verified:       'verified',
  };
  return map[eventType] ?? 'circle';
};

const loadTracking = async () => {
  isLoading.value = true;
  try {
    tracking.value = await orderRepository.getTracking(Number(route.params.id));
  } catch {
    showToast('Gagal memuat tracking pesanan.', 'error');
    router.push('/orders');
  } finally {
    isLoading.value = false;
  }
};

const confirmDelivery = async () => {
  if (!tracking.value) return;
  isConfirming.value = true;
  try {
    const res = await orderRepository.confirmDelivery(tracking.value.id || tracking.value.order_id);
    showToast(`Pesanan dikonfirmasi! +${res.points_earned} poin loyalty.`, 'success');
    await loadTracking();
  } catch (error: any) {
    showToast(error?.response?.data?.message || 'Gagal mengkonfirmasi pesanan.', 'error');
  } finally {
    isConfirming.value = false;
  }
};

onMounted(loadTracking);
</script>

<template>
  <div>
    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-32">
      <span class="material-symbols-outlined animate-spin text-4xl" style="color: var(--gold);">sync</span>
    </div>

    <template v-else-if="tracking">
      <PageHero
        :title="`Tracking — ${tracking.order_number}`"
        :breadcrumbs="breadcrumbs"
        :backTo="`/orders/${tracking.id || tracking.order_id}`"
        backLabel="Kembali ke Detail Pesanan"
        titleClass="text-2xl font-black tracking-normal text-white sm:text-3xl"
      />

      <main class="container-commerce pt-32 pb-12 sm:pt-40 md:pb-10">

        <!-- Progress Bar Status -->
        <div v-if="!['cancelled','refunded'].includes(normalizedStatus)" class="premium-card mb-6 overflow-x-auto p-4 sm:p-6">
          <div class="relative flex min-w-[520px] items-center justify-between sm:min-w-0">
            <!-- Garis penghubung -->
            <div class="absolute left-0 right-0 top-4 h-0.5 mx-8 sm:top-5" style="background: var(--mist); z-index: 0;"></div>
            <div
              class="absolute left-0 top-4 h-0.5 mx-8 transition-all duration-700 sm:top-5"
              style="background: linear-gradient(90deg, var(--gold), #3d2c0e); z-index: 1;"
              :style="{ width: currentStepIndex >= 0 ? `${(currentStepIndex / (progressSteps.length - 1)) * 100}%` : '0%' }"
            ></div>

            <div v-for="(step, i) in progressSteps" :key="step" class="relative z-10 flex w-24 flex-col items-center gap-1.5 text-center sm:w-auto sm:gap-2">
              <div
                class="flex h-8 w-8 items-center justify-center rounded-full transition-all sm:h-10 sm:w-10"
                :style="i <= currentStepIndex
                  ? 'background: linear-gradient(135deg, var(--ink), #3d2c0e); color: var(--gold);'
                  : 'background: #f0ece4; color: #6b5748;'"
              >
                <span class="material-symbols-outlined text-base sm:text-lg">{{ stepLabels[step].icon }}</span>
              </div>
              <span class="w-20 text-center text-[9px] font-black uppercase leading-tight tracking-[0.12em] sm:w-16 sm:tracking-wider"
                :style="i <= currentStepIndex ? 'color: var(--ink);' : 'color: #6b5748;'">
                {{ stepLabels[step].label }}
              </span>
            </div>
          </div>
        </div>

        <!-- Status dibatalkan/refund -->
        <div v-else class="premium-card mb-6 flex items-center gap-3" :style="`background: ${statusConfig.bg}; border-color: ${statusConfig.color}30;`">
          <span class="material-symbols-outlined text-2xl" :style="`color: ${statusConfig.color};`">{{ statusConfig.icon }}</span>
          <div>
            <p class="text-sm font-black" :style="`color: ${statusConfig.color};`">Pesanan {{ statusConfig.label }}</p>
            <p class="text-xs mt-0.5" style="color: #5c4a3a;">Pesanan ini tidak dapat dilanjutkan.</p>
          </div>
        </div>

        <div class="grid gap-4 sm:gap-6 lg:grid-cols-[1.5fr,0.9fr]">

          <!-- Kiri: Timeline -->
          <div class="premium-card p-4 sm:p-6">
            <h2 class="mb-4 text-sm font-black uppercase tracking-wider sm:mb-6 sm:text-base" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Riwayat Pesanan</h2>

            <div v-if="timeline.length === 0" class="flex items-center gap-2 py-4" style="color: #5c4a3a;">
              <span class="material-symbols-outlined text-sm">info</span>
              <p class="text-sm">Belum ada riwayat untuk pesanan ini.</p>
            </div>

            <div v-else class="space-y-0">
              <div v-for="(log, index) in timeline" :key="log.id || index" class="flex gap-3 sm:gap-4">
                <!-- Dot + line -->
                <div class="flex flex-col items-center">
                  <div
                    class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full sm:h-9 sm:w-9"
                    :style="index === 0
                      ? 'background: linear-gradient(135deg, var(--ink), #3d2c0e); color: var(--gold);'
                      : 'background: #f0ece4; color: #5c4a3a;'"
                  >
                    <span class="material-symbols-outlined text-base">{{ eventIcon(log.event_type) }}</span>
                  </div>
                  <div v-if="index < timeline.length - 1" class="w-px flex-1 my-1" style="background: var(--mist); min-height: 24px;"></div>
                </div>

                <!-- Konten -->
                <div class="pb-6 flex-1 min-w-0">
                  <p class="text-sm font-black" style="color: var(--ink);">{{ log.title || log.action || 'Update' }}</p>
                  <p class="text-xs mt-0.5 leading-relaxed" style="color: #5c4a3a;">{{ log.description || 'Status pesanan diperbarui.' }}</p>
                  <div class="flex flex-wrap gap-3 mt-1.5 text-[10px]" style="color: #6b5748;">
                    <span>{{ formatDate(log.created_at) }}</span>
                    <span v-if="log.acted_by?.name">· {{ log.acted_by.name }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Kanan: Info & Aksi -->
          <div class="space-y-4">

            <!-- Info pesanan -->
            <div class="premium-card p-4 sm:p-5">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #5c4a3a;">Info Pengiriman</p>
              <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                  <span style="color: #5c4a3a;">Status</span>
                  <span class="font-black px-2 py-0.5 text-xs"
                    :style="`background: ${statusConfig.bg}; color: ${statusConfig.color};`">
                    {{ statusConfig.label }}
                  </span>
                </div>
                <div class="flex justify-between">
                  <span style="color: #5c4a3a;">Kurir</span>
                  <span class="font-bold uppercase" style="color: var(--ink);">{{ tracking.courier || '-' }}</span>
                </div>
                <div v-if="tracking.tracking_number" class="flex justify-between items-center">
                  <span style="color: #5c4a3a;">No. Resi</span>
                  <span class="font-bold font-mono text-xs" style="color: var(--ink);">{{ tracking.tracking_number }}</span>
                </div>
                <div class="flex justify-between">
                  <span style="color: #5c4a3a;">Pembayaran</span>
                  <span class="font-bold text-xs" :style="tracking.is_payment_verified ? 'color: #16a34a;' : 'color: #d97706;'">
                    {{ tracking.is_payment_verified ? 'Terverifikasi' : 'Menunggu' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Konfirmasi terima — hanya saat shipped -->
            <div v-if="canConfirmDelivery" class="premium-card p-5">
              <div class="flex items-center gap-2 mb-2">
                <span class="material-symbols-outlined text-base" style="color: var(--gold);">inventory</span>
                <p class="text-xs font-black uppercase tracking-wider" style="color: #5c4a3a;">Barang Sudah Tiba?</p>
              </div>
              <p class="text-xs leading-relaxed mb-3" style="color: var(--graphite);">
                Konfirmasi penerimaan barang untuk menyelesaikan pesanan dan mendapatkan loyalty points.
              </p>
              <button
                @click="confirmDelivery"
                :disabled="isConfirming"
                class="w-full py-3 text-[11px] font-black uppercase tracking-[0.14em] text-white transition-all disabled:opacity-50 sm:text-xs sm:tracking-[0.16em]"
                style="background: linear-gradient(135deg, #16a34a, #15803d);"
              >
                <span v-if="isConfirming" class="material-symbols-outlined animate-spin text-sm align-middle mr-1">sync</span>
                {{ isConfirming ? 'Memproses...' : 'Konfirmasi Sudah Diterima' }}
              </button>
            </div>

            <!-- Sudah delivered -->
            <div v-if="normalizedStatus === 'delivered'" class="border p-5" style="background: rgba(22,163,74,0.05); border-color: rgba(22,163,74,0.2);">
              <div class="flex items-center gap-2 mb-1">
                <span class="material-symbols-outlined text-base" style="color: #16a34a;">check_circle</span>
                <p class="text-xs font-black uppercase tracking-wider" style="color: #16a34a;">Pesanan Selesai</p>
              </div>
              <p class="text-xs" style="color: var(--graphite);">Terima kasih telah berbelanja di Optik Medio!</p>
            </div>

            <!-- Aksi -->
            <div class="space-y-2">
              <button
                @click="router.push(`/orders/${tracking.id || tracking.order_id}`)"
                class="w-full py-3 text-xs font-black uppercase tracking-[0.16em] text-white"
                style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
              >
                Lihat Detail Pesanan
              </button>
              <button
                v-if="['delivered','shipped'].includes(normalizedStatus)"
                @click="router.push({ name: 'Complaint', query: { order_id: tracking.id || tracking.order_id } })"
                class="w-full py-3 border text-xs font-black uppercase tracking-[0.16em] transition-all hover:bg-ivory"
                style="border-color: var(--mist); color: #5c4a3a;"
              >
                Ajukan Komplain
              </button>
            </div>

            <!-- COD info -->
            <div v-if="isCod" class="premium-card p-4">
              <div class="flex items-center gap-1.5 mb-1">
                <span class="material-symbols-outlined text-sm" style="color: var(--gold);">payments</span>
                <p class="text-[10px] font-black uppercase tracking-wider" style="color: #5c4a3a;">Cash On Delivery</p>
              </div>
              <p class="text-xs" style="color: var(--graphite);">Bayar tunai kepada kurir saat barang tiba. Pembayaran akan otomatis terverifikasi setelah Anda mengkonfirmasi penerimaan.</p>
            </div>
          </div>
        </div>
      </main>
    </template>
  </div>
</template>
