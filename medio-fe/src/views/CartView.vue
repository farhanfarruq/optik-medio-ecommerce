<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useCartStore } from '../stores/cartStore';
import { useRouter } from 'vue-router';
import { resolveImageUrl } from '../core/utils/image';
import { useToast } from '../composables/useToast';
import { masterDataRepository } from '../repositories/MasterDataRepository';

const { showToast } = useToast();

const cartStore = useCartStore();
const router = useRouter();

const storeStatus = ref<{ is_closed: boolean; current_close: any | null } | null>(null);

onMounted(async () => {
  await cartStore.fetchPromos();
  await cartStore.calculateCart();
  try {
    storeStatus.value = await masterDataRepository.getStoreStatus();
  } catch (e) {
    console.warn('Could not load store status', e);
  }
});

watch(() => cartStore.items.length, () => {
  cartStore.calculateCart();
});

const groupedCart = computed(() => {
  const currentItems = cartStore.items;
  const frames = currentItems.filter((item: any) => !item.parent_item_id && !item.is_free);
  return frames.map((frame: any) => ({
    ...frame,
    attached_lens: currentItems.find((item: any) => item.parent_item_id === frame.cart_id)
  }));
});

const handleCheckout = () => {
  if (cartStore.items.length === 0) return;
  if (storeStatus.value?.is_closed) {
    showToast('Toko sedang tutup. Checkout tidak dapat dilakukan.', 'error');
    return;
  }
  router.push('/checkout');
};

const formatPromoDescription = (desc: string) => {
  if (!desc) return '';
  return desc.replace(/(\d+)\.00%/g, '$1%');
};

const handleSetPromo = async (promoId: number | null) => {
  try {
    await cartStore.setPromo(promoId);
    if (promoId) {
      showToast('Promo berhasil diterapkan!', 'success');
    } else {
      showToast('Promo dilepas.', 'info');
    }
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Gagal menerapkan promo.';
    showToast(msg, 'error');
  }
};

const getItemDiscount = (item: any) => {
  if (!cartStore.calculatedData?.items) return { isDiscounted: false, discountedPrice: item.price };
  
  const calcItem = cartStore.calculatedData.items.find((i: any) => 
    i.product_id === item.id && !i.is_free
  );

  return {
    isDiscounted: !!calcItem?.is_discounted,
    discountedPrice: calcItem?.discounted_price || item.price
  };
};
</script>

<template>
  <!-- Mini Hero with gradient bleed -->
  <div class="relative w-full" style="margin-bottom: -60px;">
    <div class="relative overflow-hidden" style="height: 280px;">
      <img src="/gambar/hero-bg.jpeg" alt="" class="absolute inset-0 w-full h-full object-cover object-center" style="transform: scale(1.08); object-position: center 40%;" />
      <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(10,8,5,0.65) 0%, rgba(30,20,10,0.45) 100%);"></div>
      <!-- Gradient bleed into page bg -->
      <div class="absolute bottom-0 left-0 right-0" style="height: 100px; background: linear-gradient(to bottom, transparent 0%, #F5F2EE 100%);"></div>
      <div class="absolute" style="bottom: 100px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.6), transparent);"></div>
      <div class="relative z-10 h-full max-w-[1000px] mx-auto px-6 flex flex-col justify-between" :style="{ paddingTop: 'calc(var(--header-height, 96px) + 16px)', paddingBottom: '56px' }">
        <!-- Breadcrumb + Back -->
        <div>
          <nav class="flex items-center gap-2 text-xs font-medium mb-2" style="color: rgba(255,255,255,0.55);">
            <router-link to="/" class="hover:text-white transition-colors">Beranda</router-link>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-white">Keranjang Belanja</span>
          </nav>
          <button @click="router.back()" class="flex items-center gap-2 text-sm font-bold group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
            <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
            Kembali
          </button>
        </div>
        <!-- Page Title -->
        <h1 class="text-4xl font-black tracking-tight text-white" style="font-family: 'Outfit', sans-serif;">Keranjang Belanja</h1>
      </div>
    </div>
  </div>

  <main class="max-w-[1000px] mx-auto w-full px-6 pb-20 flex-grow" style="padding-top: calc(var(--header-height, 96px) + 40px);">

    <!-- Store Close Alert Banner -->
    <div v-if="storeStatus?.is_closed" class="mb-6 p-4 border flex items-start gap-3" style="background: rgba(220,38,38,0.06); border-color: rgba(220,38,38,0.25);">
      <span class="material-symbols-outlined text-xl mt-0.5 shrink-0" style="color: #dc2626;">store</span>
      <div>
        <p class="font-black text-sm" style="color: #dc2626;">Toko Sedang Tutup</p>
        <p class="text-xs mt-0.5" style="color: #b91c1c;" v-if="storeStatus.current_close?.reason">{{ storeStatus.current_close.reason }}</p>
        <p class="text-xs" style="color: #ef4444;">Checkout tidak dapat diproses saat ini.</p>
      </div>
    </div>


    <!-- Empty Cart -->
    <div v-if="cartStore.items.length === 0" class="text-center py-24 rounded-none border border-dashed" style="border-color: rgba(193,154,81,0.25); background: rgba(193,154,81,0.04);">
      <span class="material-symbols-outlined text-6xl block mb-4" style="color: rgba(193,154,81,0.4);">shopping_bag</span>
      <h2 class="text-2xl font-black mb-3" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Keranjang kosong</h2>
      <p class="text-sm mb-8" style="color: #8a7a60;">Temukan koleksi kacamata premium kami.</p>
      <router-link
        to="/products"
        class="inline-flex items-center gap-2 px-8 py-3.5 rounded-none font-black text-sm text-white transition-all hover:shadow-xl active:scale-95"
        style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
      >
        <span class="material-symbols-outlined text-base">storefront</span>
        Jelajahi Koleksi
      </router-link>
    </div>

    <!-- Cart Items -->
    <div v-else class="flex flex-col md:flex-row gap-8">
      <!-- Items -->
      <div class="flex-grow flex flex-col gap-5">
        <div
          v-for="item in groupedCart"
          :key="item.cart_id"
          class="relative rounded-none border overflow-hidden transition-all hover:shadow-md"
          style="background: white; border-color: rgba(193,154,81,0.12);"
        >
          <div class="flex gap-5 p-5">
            <!-- Image -->
            <div class="w-28 h-28 rounded-none overflow-hidden shrink-0 flex items-center justify-center p-2 border" style="background: linear-gradient(145deg, #f5f2ee, #ede7dc); border-color: rgba(193,154,81,0.1);">
              <img :src="resolveImageUrl(item.images || item.image_url, item.name)" class="w-full h-full object-contain mix-blend-multiply" />
            </div>

            <!-- Info -->
            <div class="flex flex-col flex-grow min-w-0">
              <p class="text-[9px] font-black uppercase tracking-[0.2em] mb-1" style="color: #c19a51;">{{ item.name }}</p>
              <h3 class="font-black text-base leading-snug mb-1 line-clamp-2" style="color: #1a1209; font-family: 'Outfit', sans-serif;">{{ item.brand || 'Optik Medio' }}</h3>
              <p class="text-xs mb-3" style="color: #8a7a60;">{{ item.variant?.color }} {{ item.variant?.size ? `· ${item.variant.size}` : '' }}</p>
              <div class="flex items-center gap-2">
                <p v-if="getItemDiscount(item).isDiscounted" class="font-normal text-sm line-through text-stone-400">Rp {{ item.price.toLocaleString('id-ID') }}</p>
                <p class="font-black text-lg" style="color: #7a6230;">Rp {{ getItemDiscount(item).discountedPrice.toLocaleString('id-ID') }}</p>
              </div>

              <!-- Prescription -->
              <div v-if="item.prescription" class="mt-3 p-3 rounded-none text-xs flex flex-col gap-1" style="background: rgba(193,154,81,0.06); border: 1px solid rgba(193,154,81,0.12);">
                <span class="font-black" style="color: #7a6230;">Resep Optik Tercantum</span>
                <span style="color: #8a7a60;">OD: SPH {{ item.prescription.od.sph }}, CYL {{ item.prescription.od.cyl }}, Axis {{ item.prescription.od.axis }}</span>
                <span style="color: #8a7a60;">OS: SPH {{ item.prescription.os.sph }}, CYL {{ item.prescription.os.cyl }}, Axis {{ item.prescription.os.axis }}</span>
              </div>

              <!-- Attached Lens -->
              <div v-if="item.attached_lens" class="mt-3 pt-3 flex justify-between items-center border-t" style="border-color: rgba(193,154,81,0.12);">
                <div class="flex items-center gap-2">
                  <span class="material-symbols-outlined text-sm" style="color: #c19a51;">lens</span>
                  <span class="text-xs font-bold" style="color: #1a1209;">{{ item.attached_lens.name }}</span>
                </div>
                <span class="text-xs font-black" style="color: #c19a51;">+ Rp {{ item.attached_lens.price.toLocaleString('id-ID') }}</span>
              </div>
            </div>
          </div>

          <!-- Remove Button -->
          <button
            @click="cartStore.removeFromCart(item.cart_id)"
            class="absolute top-3 right-3 w-8 h-8 rounded-none flex items-center justify-center transition-all hover:scale-110"
            style="background: rgba(220,38,38,0.08); color: #dc2626;"
          >
            <span class="material-symbols-outlined text-sm">close</span>
          </button>
        </div>
      </div>
      
      <!-- Free Items from Promo -->
      <div v-if="cartStore.calculatedData" class="flex flex-col gap-3 mb-6">
        <template v-for="cItem in cartStore.calculatedData.items" :key="cItem.product_id + (cItem.name || cItem.product_name)">
          <div v-if="cItem.is_free" class="relative rounded-none border border-primary/20 bg-primary/5 p-4 flex gap-4 items-center shadow-sm">
            <div class="w-16 h-16 rounded-none bg-white p-1 border border-primary/10 flex items-center justify-center">
              <img v-if="cItem.image" :src="resolveImageUrl(cItem.image, cItem.name || cItem.product_name)" class="w-full h-full object-contain" />
              <span v-else class="material-symbols-outlined text-primary text-3xl">card_giftcard</span>
            </div>
            <div>
              <p class="text-[9px] font-black uppercase tracking-[0.2em] mb-1" style="color: #c19a51;">Item Gratis Promo</p>
              <h3 class="font-black text-sm text-stone-900 mb-1" style="font-family: 'Outfit', sans-serif;">{{ cItem.name || cItem.product_name }}</h3>
              <p class="text-xs text-stone-500 font-bold">Jumlah: {{ cItem.quantity }}</p>
            </div>
          </div>
        </template>
      </div>

      <!-- Order Summary -->
      <div class="w-full md:w-80 shrink-0">
        <!-- Promos Section -->
        <div class="rounded-none border p-5 mb-5" style="background: #fff; border-color: rgba(193,154,81,0.15);">
          <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-lg" style="color: #c19a51;">sell</span>
            <h2 class="font-black text-sm uppercase tracking-wider" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Promo & Voucher</h2>
          </div>

          <div v-if="cartStore.applicablePromos.length > 0" class="flex flex-col gap-3">
            <div 
              v-for="promo in cartStore.applicablePromos" 
              :key="promo.id"
              @click="handleSetPromo(promo.id === cartStore.appliedPromoId ? null : promo.id)"
              class="p-3 border cursor-pointer transition-all hover:bg-stone-50 group"
              :style="{
                borderColor: cartStore.appliedPromoId === promo.id ? '#c19a51' : 'rgba(193,154,81,0.1)',
                background: cartStore.appliedPromoId === promo.id ? 'rgba(193,154,81,0.05)' : 'white'
              }"
            >
              <div class="flex justify-between items-start gap-2">
                <div>
                  <p class="font-black text-[11px] leading-tight mb-1" :style="{ color: cartStore.appliedPromoId === promo.id ? '#7a6230' : '#1a1209' }">{{ promo.name }}</p>
                  <p class="text-[10px] leading-relaxed" style="color: #8a7a60;">{{ formatPromoDescription(promo.description) }}</p>
                </div>
                <span v-if="cartStore.appliedPromoId === promo.id" class="material-symbols-outlined text-sm" style="color: #c19a51;">check_circle</span>
              </div>
            </div>
          </div>
          <div v-else class="py-4 text-center border border-dashed border-stone-200">
            <p class="text-[10px] text-stone-400">Tidak ada promo tersedia saat ini</p>
          </div>
          
          <!-- Auto-applied indicator -->
          <div v-if="cartStore.calculatedData?.applied_promo" class="mt-3 p-2 bg-green-50 border border-green-100 flex items-center gap-2">
            <span class="material-symbols-outlined text-xs text-green-600">auto_awesome</span>
            <p class="text-[9px] font-bold text-green-700 uppercase tracking-tight">Promo Otomatis Terpasang</p>
          </div>
        </div>

        <div class="rounded-none border p-6 sticky top-24" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
          <h2 class="font-black text-lg mb-6" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Ringkasan</h2>

          <div class="flex flex-col gap-3 text-sm mb-6">
            <div class="flex justify-between">
              <span style="color: #8a7a60;">Subtotal</span>
              <span class="font-bold" style="color: #1a1209;">Rp {{ (cartStore.calculatedData ? cartStore.calculatedData.subtotal : cartStore.cartTotal).toLocaleString('id-ID') }}</span>
            </div>
            <div v-if="cartStore.calculatedData?.discount_amount > 0" class="flex justify-between text-green-600">
              <span>Diskon Kupon</span>
              <span class="font-bold">- Rp {{ cartStore.calculatedData.discount_amount.toLocaleString('id-ID') }}</span>
            </div>
            <div v-if="cartStore.calculatedData?.promo_discount_amount > 0" class="flex justify-between text-green-600">
              <span>Promo Diskon</span>
              <span class="font-bold">- Rp {{ cartStore.calculatedData.promo_discount_amount.toLocaleString('id-ID') }}</span>
            </div>
            <div class="flex justify-between">
              <span style="color: #8a7a60;">Ongkos Kirim</span>
              <span style="color: #8a7a60;">Dihitung saat checkout</span>
            </div>
          </div>

          <div class="h-px mb-6" style="background: rgba(193,154,81,0.2);"></div>

          <div class="flex justify-between items-end mb-8">
            <span class="text-sm font-bold" style="color: #5a5248;">Estimasi Total</span>
            <span class="text-2xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Rp {{ (cartStore.calculatedData ? cartStore.calculatedData.total_price : cartStore.cartTotal).toLocaleString('id-ID') }}</span>
          </div>

          <button
            @click="handleCheckout"
            class="w-full py-4 rounded-none font-black text-sm text-white uppercase tracking-wider flex items-center justify-center gap-2 transition-all hover:shadow-xl active:scale-95"
            style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
          >
            Lanjut ke Checkout
            <span class="material-symbols-outlined text-base">arrow_forward</span>
          </button>

          <!-- Trust -->
          <div class="flex justify-center gap-4 mt-5">
            <div class="flex items-center gap-1 text-[9px] font-bold uppercase tracking-wide" style="color: #8a7a60;">
              <span class="material-symbols-outlined text-sm" style="color: #c19a51;">lock</span>
              Aman
            </div>
            <div class="flex items-center gap-1 text-[9px] font-bold uppercase tracking-wide" style="color: #8a7a60;">
              <span class="material-symbols-outlined text-sm" style="color: #c19a51;">verified</span>
              Terpercaya
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>