<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
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

// Transfer manual: bukan COD dan bukan xendit
const isManualTransfer = computed(() => {
  if (isCod.value) return false;
  const provider = order.value?.payment?.provider;
  return !provider || provider === 'manual';
});

const breadcrumbs = computed(() => [
  { label: 'Pesanan', to: '/orders' },
  { label: order.value?.order_number || '...', to: order.value ? `/orders/${order.value.id}` : undefined },
  { label: isCod.value ? 'Konfirmasi COD' : 'Selesaikan Pembayaran' },
]);

const loadOrder = async () => {
  isLoading.value = true;
  try {
    order.value = await orderRepository.getOrderDetails(Number(route.params.id));
  } catch {
    showToast('Gagal memuat detail pembayaran.', 'error');
    router.push('/orders');
  } finally {
    isLoading.value = false;
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
</script>

<template>
  <div>
    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-32">
      <span class="material-symbols-outlined animate-spin text-4xl" style="color: #c19a51;">sync</span>
    </div>

    <template v-else-if="order">
      <!-- Hero -->
      <PageHero
        :title="isCod ? 'Pesanan Dikonfirmasi' : 'Selesaikan Pembayaran'"
        :breadcrumbs="breadcrumbs"
        :backTo="`/orders/${order.id}`"
        backLabel="Lihat Detail Pesanan"
      />

      <main class="max-w-4xl mx-auto px-6 py-10">
        <div class="grid gap-6 lg:grid-cols-[1.4fr,0.9fr]">

          <!-- Kiri: Info Pembayaran -->
          <div class="space-y-6">

            <!-- Header status -->
            <div class="border p-6" style="background: white; border-color: rgba(193,154,81,0.2); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
              <p class="text-[10px] font-black uppercase tracking-[0.24em] mb-2" style="color: #c19a51;">
                {{ isCod ? 'Cash On Delivery' : 'Transfer Manual' }}
              </p>
              <h2 class="text-xl font-black mb-2" style="color: #1a1209; font-family: 'Outfit', sans-serif;">
                {{ isCod ? 'Pesanan Sedang Diproses' : 'Selesaikan Transfer Anda' }}
              </h2>
              <p class="text-sm leading-relaxed" style="color: #5a5248;">
                <template v-if="isCod">
                  Pesanan <strong>{{ order.order_number }}</strong> sudah dikonfirmasi. Siapkan uang tunai sesuai total dan bayar kepada kurir saat barang tiba.
                </template>
                <template v-else>
                  Pesanan <strong>{{ order.order_number }}</strong> sudah dibuat. Transfer ke rekening toko di bawah ini, lalu unggah bukti pembayaran untuk verifikasi admin.
                </template>
              </p>
            </div>

            <!-- Rekening tujuan — hanya untuk transfer manual -->
            <div v-if="isManualTransfer && bank" class="border p-6" style="background: #fffdf7; border-color: rgba(193,154,81,0.35);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #8a7a60;">Rekening Tujuan Transfer</p>
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-lg font-black" style="color: #1a1209;">{{ bank.name }}</p>
                  <p class="text-sm mb-2" style="color: #8a7a60;">a.n. {{ bank.account_name }}</p>
                  <p class="text-2xl font-black tracking-wider" style="color: #1a1209;">{{ bank.account_number }}</p>
                </div>
                <button
                  @click="copyText(bank.account_number, 'Nomor rekening')"
                  class="flex items-center gap-1.5 px-4 py-2 text-xs font-black uppercase tracking-wider border transition-all hover:bg-stone-50"
                  style="border-color: rgba(193,154,81,0.4); color: #8a7a60;"
                >
                  <span class="material-symbols-outlined text-sm">content_copy</span>
                  Salin
                </button>
              </div>
            </div>

            <!-- Info COD -->
            <div v-if="isCod" class="border p-6" style="background: #fffdf7; border-color: rgba(193,154,81,0.35);">
              <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined" style="color: #c19a51;">payments</span>
                <p class="text-[10px] font-black uppercase tracking-[0.2em]" style="color: #8a7a60;">Instruksi COD</p>
              </div>
              <p class="text-sm leading-relaxed" style="color: #1a1209;">
                Siapkan uang tunai sebesar <strong>Rp {{ Number(order.total_price || 0).toLocaleString('id-ID') }}</strong> saat kurir tiba. Tidak perlu melakukan transfer atau upload apapun.
              </p>
            </div>

            <!-- Ringkasan pesanan -->
            <div class="border p-6" style="background: white; border-color: rgba(193,154,81,0.15);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #8a7a60;">Ringkasan Pesanan</p>
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p class="text-[10px] font-black uppercase tracking-wider mb-1" style="color: #8a7a60;">Metode Pembayaran</p>
                  <p class="font-bold" style="color: #1a1209;">{{ paymentMethodName }}</p>
                </div>
                <div>
                  <p class="text-[10px] font-black uppercase tracking-wider mb-1" style="color: #8a7a60;">Nomor Pesanan</p>
                  <p class="font-bold" style="color: #1a1209;">{{ order.order_number }}</p>
                </div>
                <div class="col-span-2 pt-3 border-t" style="border-color: #f0ece4;">
                  <p class="text-[10px] font-black uppercase tracking-wider mb-1" style="color: #8a7a60;">Total Bayar</p>
                  <p class="text-2xl font-black" style="color: #c19a51;">Rp {{ Number(order.total_price || 0).toLocaleString('id-ID') }}</p>
                </div>
              </div>
            </div>

            <!-- Upload bukti transfer — HANYA untuk transfer manual, BUKAN COD -->
            <div v-if="isManualTransfer" class="border p-6" style="background: white; border-color: rgba(193,154,81,0.15);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #8a7a60;">Upload Bukti Transfer</p>

              <!-- Sudah upload -->
              <div v-if="order.payment_proof_image" class="flex items-start gap-3 p-4 mb-4" style="background: rgba(22,163,74,0.06); border: 1px solid rgba(22,163,74,0.2);">
                <span class="material-symbols-outlined text-xl" style="color: #16a34a;">check_circle</span>
                <div>
                  <p class="text-sm font-bold" style="color: #15803d;">Bukti pembayaran sudah terunggah</p>
                  <p class="text-xs mt-0.5" style="color: #8a7a60;">Admin akan memverifikasi pembayaran Anda segera.</p>
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
                <p class="text-[10px]" style="color: #b0a590;">Format: JPG, PNG, WEBP, atau PDF. Maks 4 MB.</p>
                <button
                  @click="submitProof"
                  :disabled="!proofFile || isUploading"
                  class="w-full py-3 text-xs font-black uppercase tracking-[0.16em] text-white disabled:opacity-50 transition-all"
                  style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
                >
                  <span v-if="isUploading" class="material-symbols-outlined animate-spin text-sm align-middle mr-1">sync</span>
                  {{ isUploading ? 'Mengunggah...' : 'Kirim Bukti Transfer' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Kanan: Langkah selanjutnya -->
          <div class="space-y-4">
            <div class="border p-6" style="background: #1a1209; border-color: #3d2c0e;">
              <h3 class="text-base font-black mb-5 text-white" style="font-family: 'Outfit', sans-serif;">Langkah Selanjutnya</h3>
              <ol class="space-y-4">
                <template v-if="isCod">
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(193,154,81,0.3); color: #c19a51;">1</span>
                    Tunggu pesanan diproses dan dikirim oleh tim kami.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(193,154,81,0.3); color: #c19a51;">2</span>
                    Siapkan uang tunai sesuai total pembayaran.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(193,154,81,0.3); color: #c19a51;">3</span>
                    Bayar kepada kurir saat barang tiba di tempat Anda.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(193,154,81,0.3); color: #c19a51;">4</span>
                    Pantau status pesanan dari halaman tracking.
                  </li>
                </template>
                <template v-else>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(193,154,81,0.3); color: #c19a51;">1</span>
                    Transfer sesuai total pembayaran ke rekening toko.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(193,154,81,0.3); color: #c19a51;">2</span>
                    Unggah bukti transfer yang jelas dan terbaca.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(193,154,81,0.3); color: #c19a51;">3</span>
                    Tunggu verifikasi admin sebelum pesanan diproses.
                  </li>
                  <li class="flex gap-3 text-sm" style="color: rgba(255,255,255,0.75);">
                    <span class="flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black" style="background: rgba(193,154,81,0.3); color: #c19a51;">4</span>
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
                style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
              >
                <span class="material-symbols-outlined text-sm align-middle mr-1">location_on</span>
                Lacak Pesanan
              </button>
              <button
                @click="router.push(`/orders/${order.id}`)"
                class="w-full py-3 border text-xs font-black uppercase tracking-[0.16em] transition-all hover:bg-stone-50"
                style="border-color: #e5e0d8; color: #8a7a60;"
              >
                Lihat Detail Pesanan
              </button>
            </div>

            <!-- Poin yang akan didapat -->
            <div class="border p-4" style="background: #fffdf7; border-color: rgba(193,154,81,0.25);">
              <div class="flex items-center gap-2 mb-1">
                <span class="material-symbols-outlined text-sm" style="color: #c19a51;">toll</span>
                <p class="text-[10px] font-black uppercase tracking-wider" style="color: #8a7a60;">Loyalty Points</p>
              </div>
              <p class="text-xs leading-relaxed" style="color: #5a5248;">
                Anda akan mendapatkan poin setelah mengkonfirmasi penerimaan barang. Poin dapat digunakan untuk diskon pembelian berikutnya.
              </p>
            </div>
          </div>
        </div>
      </main>
    </template>
  </div>
</template>
