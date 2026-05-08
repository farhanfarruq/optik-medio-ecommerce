<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { orderRepository } from '../../repositories/OrderRepository';
import { useToast } from '../../composables/useToast';

const route = useRoute();
const router = useRouter();
const { showToast } = useToast();

const isLoading = ref(true);
const isUploading = ref(false);
const order = ref<any>(null);
const proofFile = ref<File | null>(null);

const bank = computed(() => order.value?.bank || null);
const paymentMethod = computed(() => {
  const pm = order.value?.payment?.payment_method || order.value?.payment?.paymentMethod;
  if (typeof pm === 'object' && pm !== null) {
    return pm.name || 'Transfer Bank';
  }
  return pm || 'Transfer Bank';
});

// Deteksi apakah metode pembayaran adalah COD
const isCod = computed(() => {
  const pmCode = order.value?.payment?.paymentMethod?.code || order.value?.payment?.payment_method;
  if (typeof pmCode === 'object' && pmCode !== null) return false;
  return String(pmCode || '').toLowerCase() === 'cod';
});

const loadOrder = async () => {
  isLoading.value = true;

  try {
    order.value = await orderRepository.getOrderDetails(Number(route.params.id));
  } catch (error) {
    console.error('Failed to load order', error);
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
    const message = error?.response?.data?.message || 'Gagal mengunggah bukti transfer.';
    showToast(message, 'error');
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
  <div class="min-h-screen hero-bg font-sans selection:bg-amber-900 selection:text-amber-50" style="padding-top: 80px;">
    <main class="max-w-4xl mx-auto px-6 py-16 relative z-10">
      <div v-if="isLoading" class="space-y-4 animate-pulse">
      <div class="h-8 bg-stone-200 w-1/3"></div>
      <div class="h-36 bg-stone-100"></div>
      <div class="h-56 bg-stone-100"></div>
    </div>

    <div v-else-if="order" class="space-y-8">
      <section class="bg-white border border-stone-200 p-8 shadow-sm">
        <p class="text-xs font-black uppercase tracking-[0.24em] text-amber-700 mb-3">Menunggu Pembayaran</p>
        <h1 class="text-3xl font-black text-stone-900 mb-3" style="font-family: 'Outfit', sans-serif;">
          {{ isCod ? 'Pesanan Sedang Diproses' : 'Selesaikan Transfer Anda' }}
        </h1>
        <p class="text-sm text-stone-600 leading-relaxed max-w-2xl">
          <template v-if="isCod">
            Pesanan <strong>{{ order.order_number }}</strong> sudah dibuat. Bayar tunai kepada kurir saat barang tiba. Pantau status pesanan Anda di bawah ini.
          </template>
          <template v-else>
            Pesanan <strong>{{ order.order_number }}</strong> sudah dibuat. Transfer ke rekening toko di bawah ini,
            lalu unggah bukti pembayaran agar admin dapat melakukan verifikasi manual.
          </template>
        </p>
      </section>

      <section class="grid gap-6 lg:grid-cols-[1.3fr,0.9fr]">
        <div class="bg-white border border-stone-200 p-8 shadow-sm space-y-6">
          <div>
            <p class="text-xs font-black uppercase tracking-[0.2em] text-stone-500 mb-2">Metode Pembayaran</p>
            <p class="text-lg font-bold text-stone-900">{{ paymentMethod }}</p>
          </div>

          <div v-if="!isCod && bank" class="rounded-none border border-amber-200 bg-amber-50 p-6 space-y-3">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-amber-700">Rekening Tujuan</p>
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-lg font-black text-stone-900">{{ bank.name }}</p>
                <p class="text-sm text-stone-600">a.n. {{ bank.account_name }}</p>
                <p class="text-2xl font-black text-stone-900 mt-2">{{ bank.account_number }}</p>
              </div>
              <button @click="copyText(bank.account_number, 'Nomor rekening')" class="px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] border border-stone-300 hover:bg-white transition-all">
                Salin
              </button>
            </div>
          </div>

          <!-- Info khusus COD -->
          <div v-if="isCod" class="rounded-none border border-amber-200 bg-amber-50 p-6">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-amber-700 mb-2">Cash On Delivery</p>
            <p class="text-sm text-stone-700 leading-relaxed">Siapkan uang tunai sesuai total pembayaran. Serahkan kepada kurir saat barang tiba di tempat Anda.</p>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="border border-stone-200 p-4">
              <p class="text-xs font-black uppercase tracking-[0.16em] text-stone-500 mb-2">Nomor Pesanan</p>
              <p class="font-bold text-stone-900">{{ order.order_number }}</p>
            </div>
            <div class="border border-stone-200 p-4">
              <p class="text-xs font-black uppercase tracking-[0.16em] text-stone-500 mb-2">Total Bayar</p>
              <p class="font-black text-xl text-stone-900">Rp {{ Number(order.total_price || 0).toLocaleString('id-ID') }}</p>
            </div>
          </div>

          <!-- Upload bukti transfer: hanya untuk pembayaran manual, bukan COD -->
          <div v-if="!isCod" class="space-y-3">
            <label class="block text-xs font-black uppercase tracking-[0.18em] text-stone-500">Upload Bukti Transfer</label>
            <input
              type="file"
              accept=".jpg,.jpeg,.png,.webp,.pdf"
              @change="handleFileChange"
              class="block w-full border border-stone-300 bg-stone-50 px-4 py-3 text-sm"
            />
            <button
              @click="submitProof"
              :disabled="!proofFile || isUploading"
              class="px-6 py-3 bg-stone-900 text-white text-sm font-black uppercase tracking-[0.16em] disabled:opacity-50"
            >
              {{ isUploading ? 'Mengunggah...' : 'Kirim Bukti Transfer' }}
            </button>
          </div>

          <div v-if="!isCod && order.payment_proof_image" class="text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 p-4">
            Bukti pembayaran sudah terunggah. Admin akan memverifikasi pembayaran Anda.
          </div>
        </div>

        <div class="bg-stone-950 text-white p-8 space-y-5">
          <h2 class="text-lg font-black" style="font-family: 'Outfit', sans-serif;">Langkah Selanjutnya</h2>
          <ol class="space-y-4 text-sm text-white/75">
            <template v-if="isCod">
              <li>1. Tunggu pesanan Anda diproses dan dikirim oleh admin.</li>
              <li>2. Siapkan uang tunai sesuai total pembayaran.</li>
              <li>3. Bayar kepada kurir saat barang tiba.</li>
              <li>4. Pantau perubahan status dari halaman tracking pesanan.</li>
            </template>
            <template v-else>
              <li>1. Transfer sesuai total pembayaran di ringkasan pesanan.</li>
              <li>2. Unggah bukti transfer yang jelas dan terbaca.</li>
              <li>3. Tunggu verifikasi admin sebelum pesanan diproses.</li>
              <li>4. Pantau perubahan status dari halaman tracking pesanan.</li>
            </template>
          </ol>

          <div class="pt-4 border-t border-white/10 space-y-3">
            <button @click="router.push(`/tracking/${order.id}`)" class="w-full px-5 py-3 bg-white text-stone-900 text-sm font-black uppercase tracking-[0.16em]">
              Lacak Pesanan
            </button>
            <button @click="router.push(`/orders/${order.id}`)" class="w-full px-5 py-3 border border-white/20 text-sm font-black uppercase tracking-[0.16em]">
              Lihat Detail Pesanan
            </button>
          </div>
        </div>
      </section>
    </div>
    </main>
  </div>
</template>
