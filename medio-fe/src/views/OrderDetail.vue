<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { orderRepository } from '../repositories/OrderRepository';
import { useToast } from '../composables/useToast';
import { apiClient } from '../core/api/axiosclient';

const { showToast } = useToast();

const route = useRoute();
const router = useRouter();
const order = ref<any>(null);
const isLoading = ref(true);

onMounted(async () => {
  const id = Number(route.params.id);
  try {
    order.value = await orderRepository.getOrderDetails(id);
    
    // Auto-sync status with Xendit
    if (['UNPAID', 'PENDING'].includes(order.value.status?.toUpperCase())) {
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
  } finally {
    isLoading.value = false;
  }
});

const getStatusConfig = (status: string) => {
  switch (status?.toUpperCase()) {
    case 'UNPAID':    return { bg: 'rgba(251,191,36,0.12)', color: '#b45309', border: 'rgba(251,191,36,0.4)', label: 'Belum Bayar' };
    case 'PENDING':   return { bg: 'rgba(251,191,36,0.12)', color: '#b45309', border: 'rgba(251,191,36,0.4)', label: 'Menunggu Konfirmasi' };
    case 'PAID':      return { bg: 'rgba(22,163,74,0.1)',   color: '#15803d', border: 'rgba(22,163,74,0.35)',  label: 'Lunas' };
    case 'COMPLETED': return { bg: 'rgba(22,163,74,0.1)',   color: '#15803d', border: 'rgba(22,163,74,0.35)',  label: 'Selesai' };
    case 'SHIPPED':   return { bg: 'rgba(59,130,246,0.1)',  color: '#1d4ed8', border: 'rgba(59,130,246,0.35)', label: 'Dikirim' };
    case 'CANCELLED': return { bg: 'rgba(220,38,38,0.08)',  color: '#dc2626', border: 'rgba(220,38,38,0.3)',   label: 'Dibatalkan' };
    case 'EXPIRED':   return { bg: 'rgba(107,114,128,0.1)', color: '#4b5563', border: 'rgba(107,114,128,0.3)', label: 'Kedaluwarsa' };
    default:          return { bg: 'rgba(107,114,128,0.1)', color: '#4b5563', border: 'rgba(107,114,128,0.3)', label: status };
  }
};

const formatVariant = (variant: any) => {
  if (!variant) return '';
  if (Array.isArray(variant)) return variant.join(', ');
  if (typeof variant === 'object') return Object.values(variant).filter(Boolean).join(', ');
  return String(variant);
};

const copyToClipboard = (text: string) => {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text);
    showToast('Resi berhasil disalin!', 'success');
  } else {
    showToast('Browser tidak mendukung fitur salin.', 'error');
  }
};
</script>

<template>
  <!-- Mini Hero with gradient bleed -->
  <div class="relative w-full" style="margin-bottom: -60px;">
    <div class="relative overflow-hidden" style="height: 280px;">
      <img src="/gambar/hero-bg.jpeg" alt="" class="absolute inset-0 w-full h-full object-cover object-center" style="transform: scale(1.08); object-position: center 40%;" />
      <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(10,8,5,0.65) 0%, rgba(30,20,10,0.45) 100%);"></div>
      <div class="absolute bottom-0 left-0 right-0" style="height: 100px; background: linear-gradient(to bottom, transparent 0%, #F5F2EE 100%);"></div>
      <div class="absolute" style="bottom: 100px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.6), transparent);"></div>
      <div class="relative z-10 h-full max-w-4xl mx-auto px-6 flex flex-col justify-end pb-24 pt-36">
        <button @click="router.back()" class="flex items-center gap-2 text-sm font-bold mb-3 group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
          <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
          Kembali
        </button>
        <h1 class="text-4xl font-black tracking-tight text-white" style="font-family: 'Outfit', sans-serif;">
          Detail Pesanan
        </h1>
      </div>
    </div>
  </div>

  <main class="max-w-4xl mx-auto px-6 pb-20 flex-grow" style="padding-top: 160px;">

    <!-- Loading -->
    <div v-if="isLoading" class="animate-pulse space-y-6">
      <div class="h-12 rounded-none w-1/3" style="background: rgba(193,154,81,0.1);"></div>
      <div class="h-64 rounded-none" style="background: rgba(193,154,81,0.07);"></div>
    </div>

    <!-- Not Found -->
    <div v-else-if="!order" class="text-center py-24 rounded-none border border-dashed" style="border-color: rgba(193,154,81,0.25); background: rgba(193,154,81,0.04);">
      <span class="material-symbols-outlined text-6xl block mb-4" style="color: rgba(193,154,81,0.4);">search_off</span>
      <p class="text-lg font-black mb-4" style="color: #1a1209;">Pesanan tidak ditemukan.</p>
      <button @click="router.push('/profile')" class="text-sm font-bold underline underline-offset-4" style="color: #c19a51;">Kembali ke Profil</button>
    </div>

    <!-- Order Content -->
    <div v-else class="space-y-6">

      <!-- Header Bar -->
      <div class="rounded-none border p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
        <div>
          <p class="text-xs font-black uppercase tracking-[0.25em] mb-2" style="color: #c19a51;">Nomor Pesanan</p>
          <h2 class="text-2xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">#{{ order.order_number }}</h2>
          <p class="text-xs mt-1 flex items-center gap-1.5" style="color: #8a7a60;">
            <span class="material-symbols-outlined text-sm">calendar_today</span>
            {{ new Date(order.created_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) }}
          </p>
        </div>
        <div
          class="px-5 py-2 rounded-none text-[10px] font-black uppercase tracking-[0.2em] border-2"
          :style="`background: ${getStatusConfig(order.status).bg}; color: ${getStatusConfig(order.status).color}; border-color: ${getStatusConfig(order.status).border};`"
        >
          {{ getStatusConfig(order.status).label }}
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        <!-- Items Section -->
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
                class="flex gap-5 group pb-5 border-b last:border-0 last:pb-0"
                style="border-color: rgba(193,154,81,0.08);"
              >
                <div class="w-20 h-20 rounded-none overflow-hidden shrink-0 flex items-center justify-center p-2 border transition-colors" style="background: linear-gradient(145deg, #f5f2ee, #ede7dc); border-color: rgba(193,154,81,0.1);">
                  <img v-if="item.product?.image_url" :src="item.product.image_url" class="w-full h-full object-contain mix-blend-multiply" />
                  <span v-else class="material-symbols-outlined text-2xl" style="color: #c19a51; opacity: 0.5;">image</span>
                </div>
                <div class="flex-grow flex flex-col justify-center">
                  <h4 class="font-black text-sm mb-1" style="color: #1a1209;">{{ item.product?.name }}</h4>
                  <p class="text-[10px] font-bold uppercase tracking-widest mb-2" style="color: #8a7a60;">{{ formatVariant(item.variant) }}</p>
                  <div class="flex items-center gap-3">
                    <span class="text-[10px] font-bold px-2 py-1 rounded" style="background: rgba(193,154,81,0.1); color: #7a6230;">Qty: {{ item.quantity }}</span>
                    <span class="text-sm font-black" style="color: #1a1209;">Rp {{ Number(item.product_price || item.price || 0).toLocaleString('id-ID') }}</span>
                  </div>
                </div>
                <div class="flex items-center shrink-0">
                  <p class="font-black text-base" style="color: #1a1209;">Rp {{ ((item.product_price || item.price || 0) * item.quantity).toLocaleString('id-ID') }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-5 sticky top-28 self-stretch">

          <!-- Summary -->
          <div class="rounded-none border p-6" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="flex items-center gap-2 mb-6">
              <span class="material-symbols-outlined text-lg" style="color: #c19a51;">receipt_long</span>
              <h3 class="font-black text-base" style="color: #1a1209;">Rincian Biaya</h3>
            </div>

            <div class="flex flex-col gap-3 text-sm">
              <div class="flex justify-between">
                <span style="color: #8a7a60;">Subtotal</span>
                <span class="font-bold" style="color: #1a1209;">Rp {{ Number(order.subtotal || order.total_amount || 0).toLocaleString('id-ID') }}</span>
              </div>
              <div class="flex justify-between">
                <span style="color: #8a7a60;">Ongkir ({{ order.courier?.toUpperCase() }} {{ order.courier_service }})</span>
                <span class="font-bold" style="color: #1a1209;">Rp {{ (order.shipping_cost || 0).toLocaleString('id-ID') }}</span>
              </div>
            </div>

            <div class="h-px my-4" style="background: rgba(193,154,81,0.2);"></div>

            <div class="flex justify-between items-end">
              <span class="text-sm font-bold" style="color: #5a5248;">Total</span>
              <span class="text-xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">
                Rp {{ Number(order.total_price || order.total_amount || 0).toLocaleString('id-ID') }}
              </span>
            </div>

            <div v-if="['UNPAID', 'PENDING'].includes(order.status?.toUpperCase()) && order.payment?.checkout_url" class="mt-6">
              <a
                :href="order.payment.checkout_url"
                class="block w-full text-center py-4 rounded-none font-black text-sm uppercase tracking-wider text-white transition-all hover:shadow-xl active:scale-95 shadow-lg"
                style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
              >
                Bayar Sekarang
              </a>
            </div>
          </div>

          <!-- Shipping Address -->
          <div v-if="order.shipping_address" class="rounded-none border p-6" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
            <div class="flex items-center gap-2 mb-5">
              <span class="material-symbols-outlined text-lg" style="color: #c19a51;">local_shipping</span>
              <h3 class="font-black text-base" style="color: #1a1209;">Informasi Pengiriman</h3>
            </div>

            <!-- Tracking Number (Resi) -->
            <div v-if="order.tracking_number" class="mb-6 p-4 rounded-none border-2 border-dashed flex flex-col gap-2" style="border-color: rgba(193,154,81,0.2); background: rgba(193,154,81,0.03);">
              <p class="text-[10px] font-black uppercase tracking-widest text-stone-500">Nomor Resi ({{ order.courier?.toUpperCase() }})</p>
              <div class="flex items-center justify-between">
                <span class="text-lg font-black text-primary tracking-wider" style="color: #1a1209;">{{ order.tracking_number }}</span>
                <button @click="copyToClipboard(order.tracking_number)" class="p-2 hover:bg-white rounded-lg transition-colors flex items-center gap-1 text-xs font-bold text-primary" style="color: #c19a51;">
                  <span class="material-symbols-outlined text-sm">content_copy</span>
                  Salin
                </button>
              </div>
            </div>

            <div class="text-sm leading-relaxed" style="color: #5a5248;">
              <p class="font-black text-base mb-1" style="color: #1a1209;">{{ order.shipping_address.recipient_name }}</p>
              <p class="font-bold mb-3" style="color: #8a7a60;">{{ order.shipping_address.phone }}</p>
              <div class="p-3 rounded-none text-xs leading-relaxed" style="background: rgba(193,154,81,0.06); border: 1px solid rgba(193,154,81,0.1);">
                {{ order.shipping_address.address }}<br/>
                {{ order.shipping_address.district }}, {{ order.shipping_address.city }}<br/>
                {{ order.shipping_address.province }} {{ order.shipping_address.postal_code }}
              </div>
            </div>
          </div>

          <!-- Notes -->
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
