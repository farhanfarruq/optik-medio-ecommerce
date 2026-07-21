<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { orderRepository } from '../../repositories/OrderRepository';
import { useToast } from '../../composables/useToast';
import PageHero from '../../components/layout/PageHero.vue';

const route = useRoute();
const router = useRouter();
const { showToast } = useToast();

const isLoading = ref(true);
const isUploading = ref(false);
const order = ref<any>(null);
const proofFile = ref<File | null>(null);

// Polling state untuk Xendit
const isPolling = ref(false);
const pollCount = ref(0);
const MAX_POLL = 60; // maks 60x polling = 5 menit (interval 5 detik)
const POLL_INTERVAL_MS = 5000;
let pollTimer: ReturnType<typeof setInterval> | null = null;
const isExpired = ref(false);
const paymentStatusMsg = ref('');

const bank = computed(() => order.value?.bank || null);

const paymentMethodName = computed(() => {
  const pm = order.value?.payment?.paymentMethod || order.value?.payment?.payment_method;
  if (pm && typeof pm === 'object') return pm.name || 'Transfer Bank';
  return pm || 'Transfer Bank';
});

// COD: cek dari code payment method
const isCod = computed(() => {
  const pm = order.value?.payment?.paymentMethod || order.value?.payment?.payment_method;
  const code = typeof pm === 'object' ? pm?.code : pm;
  return String(code || '').toLowerCase() === 'cod';
});

// Xendit: provider = xendit
const isXendit = computed(() => {
  return order.value?.payment?.provider === 'xendit';
});

// Transfer manual: bukan COD dan bukan xendit
const isManualTransfer = computed(() => {
  if (isCod.value) return false;
  const provider = order.value?.payment?.provider;
  return !provider || provider === 'manual';
});

const openXenditCheckout = (url: string) => {
  window.location.assign(url);
};

const breadcrumbs = computed(() => [
  { label: 'Pesanan', to: '/orders' },
  { label: order.value?.order_number || '...', to: order.value ? `/orders/${order.value.id}` : undefined },
  { label: isCod.value ? 'Konfirmasi COD' : 'Selesaikan Pembayaran' },
]);

const pickupBookingQuery = (targetOrder: any): Record<string, string> => ({
  service: 'pickup',
  order_id: String(targetOrder.id),
  order_number: String(targetOrder.order_number || targetOrder.id),
  source_label: `Pesanan #${targetOrder.order_number || targetOrder.id}`,
});

const redirectAfterPayment = (targetOrder: any) => {
  if (targetOrder?.fulfillment_method === 'store_pickup') {
    router.push({ path: '/appointment', query: pickupBookingQuery(targetOrder) });
    return;
  }

  router.push(`/orders/${targetOrder.id}`);
};

const loadOrder = async () => {
  isLoading.value = true;
  try {
    order.value = await orderRepository.getOrderDetails(Number(route.params.id));

    // Jika sudah paid/processing/dll, langsung redirect
    if (['paid', 'lens_processing', 'processing', 'shipped', 'delivered', 'completed'].includes(order.value?.status)) {
      showToast('Pembayaran berhasil dikonfirmasi!', 'success');
      redirectAfterPayment(order.value);
      return;
    }

    // Mulai polling hanya untuk Xendit
    if (isXendit.value && order.value?.status === 'unpaid') {
      startPolling();
    }
  } catch {
    showToast('Gagal memuat detail pembayaran.', 'error');
    router.push('/orders');
  } finally {
    isLoading.value = false;
  }
};

/**
 * Polling ringan ke /api/orders/{id}/payment-status
 * Hanya untuk Xendit — cek apakah webhook sudah masuk.
 */
const startPolling = () => {
  if (pollTimer) return;
  isPolling.value = true;
  paymentStatusMsg.value = 'Menunggu konfirmasi pembayaran...';

  pollTimer = setInterval(async () => {
    pollCount.value++;

    if (pollCount.value > MAX_POLL) {
      stopPolling();
      paymentStatusMsg.value = 'Waktu polling habis. Klik "Cek Status" untuk memperbarui manual.';
      return;
    }

    try {
      const status = await orderRepository.getPaymentStatus(Number(route.params.id));

      if (status.should_redirect) {
        stopPolling();
        showToast('Pembayaran berhasil dikonfirmasi!', 'success');
        redirectAfterPayment({
          ...order.value,
          id: status.order_id,
          order_number: status.order_number,
        });
        return;
      }

      if (status.is_expired) {
        stopPolling();
        isExpired.value = true;
        paymentStatusMsg.value = 'Pembayaran telah kedaluwarsa atau dibatalkan.';
        return;
      }

      paymentStatusMsg.value = `Menunggu konfirmasi pembayaran... (${pollCount.value}/${MAX_POLL})`;
    } catch {
      // Abaikan error polling, coba lagi di interval berikutnya
    }
  }, POLL_INTERVAL_MS);
};

const stopPolling = () => {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
  isPolling.value = false;
};

/**
 * Manual sync untuk Xendit — panggil syncPayment endpoint.
 */
const syncPaymentManual = async () => {
  if (!order.value) return;
  try {
    paymentStatusMsg.value = 'Menyinkronkan status pembayaran...';
    const result = await orderRepository.syncPayment(order.value.id);
    if (['paid', 'lens_processing', 'processing', 'shipped', 'delivered'].includes(result.status)) {
      showToast('Pembayaran berhasil dikonfirmasi!', 'success');
      redirectAfterPayment(result.order || order.value);
    } else if (result.status === 'cancelled') {
      isExpired.value = true;
      paymentStatusMsg.value = 'Pembayaran telah kedaluwarsa atau dibatalkan.';
    } else {
      paymentStatusMsg.value = 'Status belum berubah. Coba lagi beberapa saat.';
    }
  } catch {
    paymentStatusMsg.value = 'Gagal menyinkronkan. Coba lagi.';
  }
};

const handleFileChange = (event: Event) => {
  const input = event.target as HTMLInputElement;
  proofFile.value = input.files?.[0] || null;
};

const submitProof = async () => {
  if (!order.value || !proofFile.value) {
    showToast('Pilih file bukti transfer terlebih dahulu.', 'error');
    return;
  }
  isUploading.value = true;
  try {
    order.value = await orderRepository.uploadPaymentProof(order.value.id, proofFile.value);
    proofFile.value = null;
    showToast('Bukti transfer berhasil diunggah.', 'success');
  } catch (error: any) {
    showToast(error?.response?.data?.message || 'Gagal mengunggah bukti transfer.', 'error');
  } finally {
    isUploading.value = false;
  }
};

const copyText = async (value: string, label: string) => {
  try {
    await navigator.clipboard.writeText(value);
    showToast(`${label} berhasil disalin.`, 'success');
  } catch {
    showToast(`Gagal menyalin ${label.toLowerCase()}.`, 'error');
  }
};

onMounted(loadOrder);
onUnmounted(stopPolling);
</script>

<template>
  <div>
    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center bg-ivory py-32">
      <span class="material-symbols-outlined animate-spin text-4xl" style="color: var(--gold);">sync</span>
    </div>

    <template v-else-if="order">
      <!-- Hero -->
      <PageHero
        :title="isCod ? 'Pesanan Dikonfirmasi' : 'Selesaikan Pembayaran'"
        :breadcrumbs="breadcrumbs"
        :backTo="`/orders/${order.id}`"
        backLabel="Lihat Detail Pesanan"
      />

      <main class="container-commerce pt-8 pb-10">

        <!-- Banner Expired -->
        <div v-if="isExpired" class="alert-error mb-6 flex items-start gap-4 p-5">
          <span class="material-symbols-outlined text-2xl flex-shrink-0" style="color: #dc2626;">error</span>
          <div>
            <p class="font-bold text-sm" style="color: #dc2626;">Pembayaran Kedaluwarsa atau Dibatalkan</p>
            <p class="text-xs mt-1" style="color: var(--graphite);">Pesanan ini tidak dapat diproses. Silakan buat pesanan baru jika masih ingin melanjutkan.</p>
            <button
              @click="router.push('/cart')"
              class="btn-primary mt-3 px-4 py-2 text-xs uppercase tracking-[0.12em]"
              style="background: #dc2626;"
            >
              Kembali ke Keranjang
            </button>
          </div>
        </div>

        <!-- Banner Polling Xendit -->
        <div v-if="isXendit && isPolling && !isExpired" class="alert-base mb-6 flex items-center gap-4 p-5">
          <span class="material-symbols-outlined animate-spin text-2xl flex-shrink-0" style="color: var(--gold);">sync</span>
          <div class="flex-1">
            <p class="font-bold text-sm" style="color: var(--ink);">{{ paymentStatusMsg }}</p>
            <p class="text-xs mt-0.5" style="color: #5c4a3a;">Halaman ini otomatis memperbarui status setiap 5 detik.</p>
          </div>
        </div>

        <!-- Banner Polling Selesai (timeout) -->
        <div v-if="isXendit && !isPolling && !isExpired && paymentStatusMsg && order?.status === 'unpaid'" class="alert-base mb-6 flex items-start gap-4 p-5">
          <span class="material-symbols-outlined text-2xl flex-shrink-0" style="color: #d97706;">warning</span>
          <div class="flex-1">
            <p class="font-bold text-sm" style="color: #92400e;">{{ paymentStatusMsg }}</p>
            <button
              @click="syncPaymentManual"
              class="btn-primary mt-2 px-4 py-2 text-xs uppercase tracking-[0.12em]"
              style="background: #d97706;"
            >
              Cek Status Sekarang
            </button>
          </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1.4fr,0.9fr]">

          <!-- Kiri: Info Pembayaran -->
          <div class="space-y-6">

            <!-- Header status -->
            <div class="premium-card p-6">
              <p class="text-[10px] font-black uppercase tracking-[0.24em] mb-2" style="color: var(--gold);">
                {{ isCod ? 'Cash On Delivery' : isXendit ? 'Pembayaran Online' : 'Transfer Manual' }}
              </p>
              <h2 class="text-xl font-black mb-2" style="color: var(--ink); font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">
                {{ isCod ? 'Pesanan Sedang Diproses' : isXendit ? 'Selesaikan Pembayaran Online' : 'Selesaikan Transfer Anda' }}
              </h2>
              <p class="text-sm leading-relaxed" style="color: var(--graphite);">
                <template v-if="isCod">
                  Pesanan <strong>{{ order.order_number }}</strong> sudah dikonfirmasi. Siapkan uang tunai sesuai total dan bayar kepada kurir saat barang tiba.
                </template>
                <template v-else-if="isXendit">
                  Pesanan <strong>{{ order.order_number }}</strong> sudah dibuat. Selesaikan pembayaran melalui halaman Xendit. Halaman ini akan otomatis memperbarui status setelah pembayaran dikonfirmasi.
                </template>
                <template v-else>
                  Pesanan <strong>{{ order.order_number }}</strong> sudah dibuat. Transfer ke rekening toko di bawah ini, lalu unggah bukti pembayaran untuk verifikasi admin.
                </template>
              </p>
            </div>

            <!-- Xendit: tombol lanjutkan pembayaran -->
            <div v-if="isXendit && order?.payment?.checkout_url && order?.status === 'unpaid'" class="premium-card p-6">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-3" style="color: #5c4a3a;">Lanjutkan Pembayaran</p>
              <p class="text-sm mb-4" style="color: var(--graphite);">Klik tombol di bawah untuk membuka halaman pembayaran Xendit. Setelah selesai, halaman ini akan otomatis diperbarui.</p>
              <button
                type="button"
                @click="openXenditCheckout(order.payment.checkout_url)"
                class="btn-primary inline-flex px-6 py-3 text-xs uppercase tracking-[0.12em]"
                style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
              >
                <span class="material-symbols-outlined text-sm">open_in_new</span>
                Buka Halaman Pembayaran
              </button>
              <button
                @click="syncPaymentManual"
                class="btn-outline ml-3 px-4 py-3 text-xs uppercase tracking-[0.12em]"
                style="border-color: #e5e0d8; color: #5c4a3a;"
              >
                Cek Status Manual
              </button>
            </div>

            <!-- Rekening tujuan — hanya untuk transfer manual -->
            <div v-if="isManualTransfer && bank" class="premium-card p-6">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #5c4a3a;">Rekening Tujuan Transfer</p>
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-lg font-black" style="color: var(--ink);">{{ bank.name }}</p>
                  <p class="text-sm mb-2" style="color: #5c4a3a;">a.n. {{ bank.account_name }}</p>
                  <p class="text-2xl font-black tracking-wider" style="color: var(--ink);">{{ bank.account_number }}</p>
                </div>
                <button
                  @click="copyText(bank.account_number, 'Nomor rekening')"
                  class="flex items-center gap-1.5 px-4 py-2 text-xs font-black uppercase tracking-wider border transition-all hover:bg-ivory"
                  style="border-color: rgba(184,138,68,0.4); color: #5c4a3a;"
                >
                  <span class="material-symbols-outlined text-sm">content_copy</span>
                  Salin
                </button>
              </div>
            </div>

            <!-- Info COD -->
            <div v-if="isCod" class="premium-card p-6">
              <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined" style="color: var(--gold);">payments</span>
                <p class="text-[10px] font-black uppercase tracking-[0.2em]" style="color: #5c4a3a;">Instruksi COD</p>
              </div>
              <p class="text-sm leading-relaxed" style="color: var(--ink);">
                Siapkan uang tunai sebesar <strong>Rp {{ Number(order.total_price || 0).toLocaleString('id-ID') }}</strong> saat kurir tiba. Tidak perlu melakukan transfer atau upload apapun.
              </p>
            </div>

            <!-- Ringkasan pesanan -->
            <div class="border p-6" style="background: white; border-color: rgba(184,138,68,0.15);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #5c4a3a;">Ringkasan Pesanan</p>
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p class="text-[10px] font-black uppercase tracking-wider mb-1" style="color: #5c4a3a;">Metode Pembayaran</p>
                  <p class="font-bold" style="color: var(--ink);">{{ paymentMethodName }}</p>
                </div>
                <div>
                  <p class="text-[10px] font-black uppercase tracking-wider mb-1" style="color: #5c4a3a;">Nomor Pesanan</p>
                  <p class="font-bold" style="color: var(--ink);">{{ order.order_number }}</p>
                </div>
                <div class="col-span-2 pt-3 border-t" style="border-color: #f0ece4;">
                  <p class="text-[10px] font-black uppercase tracking-wider mb-1" style="color: #5c4a3a;">Total Bayar</p>
                  <p class="text-2xl font-black" style="color: var(--gold);">Rp {{ Number(order.total_price || 0).toLocaleString('id-ID') }}</p>
                </div>
              </div>
            </div>

            <!-- Upload bukti transfer — HANYA untuk transfer manual, BUKAN COD -->
            <div v-if="isManualTransfer" class="border p-6" style="background: white; border-color: rgba(184,138,68,0.15);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #5c4a3a;">Upload Bukti Transfer</p>

              <!-- Sudah upload -->
              <div v-if="order.payment_proof_image" class="flex items-start gap-3 p-4 mb-4" style="background: rgba(22,163,74,0.06); border: 1px solid rgba(22,163,74,0.2);">
                <span class="material-symbols-outlined text-xl" style="color: #16a34a;">check_circle</span>
                <div>
                  <p class="text-sm font-bold" style="color: #15803d;">Bukti pembayaran sudah terunggah</p>
                  <p class="text-xs mt-0.5" style="color: #5c4a3a;">Admin akan memverifikasi pembayaran Anda segera.</p>
                </div>
              </div>

              <div class="space-y-3">
                <input
                  type="file"
                  accept=".jpg,.jpeg,.png,.webp,.pdf"
                  @change="handleFileChange"
                  class="block w-full border px-4 py-3 text-sm"
                  style="border-color: #e5e0d8; background: #faf9f7;"
                />
                <p class="text-[10px]" style="color: #6b5748;">Format: JPG, PNG, WEBP, atau PDF. Maks 4 MB.</p>
                <button
                  @click="submitProof"
                  :disabled="!proofFile || isUploading"
                  class="w-full py-3 text-xs font-black uppercase tracking-[0.16em] text-white disabled:opacity-50 transition-all"
                  style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
                >
                  <span v-if="isUploading" class="material-symbols-outlined animate-spin text-sm align-middle mr-1">sync</span>
                  {{ isUploading ? 'Mengunggah...' : 'Kirim Bukti Transfer' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Kanan: Langkah selanjutnya -->
          <div class="space-y-4">
            <div class="border p-6" style="background: var(--ink); border-color: #3d2c0e;">
              <h3 class="text-base font-black mb-5 text-white" style="font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">Langkah Selanjutnya</h3>
              <ol class="space-y-4">
                <template v-if="isCod">
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(184,138,68,0.3); color: var(--gold);">1</span>
                    Tunggu pesanan diproses dan dikirim oleh tim kami.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(184,138,68,0.3); color: var(--gold);">2</span>
                    Siapkan uang tunai sesuai total pembayaran.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(184,138,68,0.3); color: var(--gold);">3</span>
                    Bayar kepada kurir saat barang tiba di tempat Anda.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(184,138,68,0.3); color: var(--gold);">4</span>
                    Pantau status pesanan dari halaman tracking.
                  </li>
                </template>
                <template v-else-if="isXendit">
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(184,138,68,0.3); color: var(--gold);">1</span>
                    Klik "Buka Halaman Pembayaran" dan selesaikan di Xendit.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(184,138,68,0.3); color: var(--gold);">2</span>
                    Halaman ini otomatis memperbarui status setelah pembayaran.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(184,138,68,0.3); color: var(--gold);">3</span>
                    Jika tidak otomatis, klik "Cek Status Manual".
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(184,138,68,0.3); color: var(--gold);">4</span>
                    Pantau perubahan status dari halaman tracking.
                  </li>
                </template>
                <template v-else>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(184,138,68,0.3); color: var(--gold);">1</span>
                    Transfer sesuai total pembayaran ke rekening toko.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(184,138,68,0.3); color: var(--gold);">2</span>
                    Unggah bukti transfer yang jelas dan terbaca.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(184,138,68,0.3); color: var(--gold);">3</span>
                    Tunggu verifikasi admin sebelum pesanan diproses.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(184,138,68,0.3); color: var(--gold);">4</span>
                    Pantau perubahan status dari halaman tracking.
                  </li>
                </template>
              </ol>
            </div>

            <!-- Tombol aksi -->
            <div class="space-y-3">
              <button
                @click="router.push(`/tracking/${order.id}`)"
                class="w-full py-3 text-xs font-black uppercase tracking-[0.16em] text-white transition-all hover:opacity-90"
                style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
              >
                <span class="material-symbols-outlined text-sm align-middle mr-1">location_on</span>
                Lacak Pesanan
              </button>
              <button
                @click="router.push(`/orders/${order.id}`)"
                class="w-full py-3 border text-xs font-black uppercase tracking-[0.16em] transition-all hover:bg-ivory"
                style="border-color: #e5e0d8; color: #5c4a3a;"
              >
                Lihat Detail Pesanan
              </button>
            </div>

            <!-- Poin yang akan didapat -->
            <div class="border p-4" style="background: var(--porcelain); border-color: rgba(184,138,68,0.25);">
              <div class="flex items-center gap-2 mb-1">
                <span class="material-symbols-outlined text-sm" style="color: var(--gold);">toll</span>
                <p class="text-[10px] font-black uppercase tracking-wider" style="color: #5c4a3a;">Loyalty Points</p>
              </div>
              <p class="text-xs leading-relaxed" style="color: var(--graphite);">
                Anda akan mendapatkan poin setelah mengkonfirmasi penerimaan barang. Poin dapat digunakan untuk diskon pembelian berikutnya.
              </p>
            </div>
          </div>
        </div>
      </main>
    </template>
  </div>
</template>
