<script setup lang="ts">
import { computed } from 'vue';
import { useCartStore } from '../stores/cartStore';
import { useRouter } from 'vue-router';
import { resolveImageUrl } from '../core/utils/image';

const cartStore = useCartStore();
const router = useRouter();

const groupedCart = computed(() => {
  const frames = cartStore.items.filter((item: any) => !item.parent_item_id);
  return frames.map((frame: any) => ({
    ...frame,
    attached_lens: cartStore.items.find((item: any) => item.parent_item_id === frame.cart_id)
  }));
});

const handleCheckout = () => {
  if (cartStore.items.length === 0) return;
  router.push('/checkout');
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
      <div class="relative z-10 h-full max-w-[1000px] mx-auto px-6 flex flex-col justify-end pb-24 pt-36">
        <button @click="router.back()" class="flex items-center gap-2 text-sm font-bold mb-3 group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
          <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
          Kembali
        </button>
        <h1 class="text-4xl font-black tracking-tight text-white" style="font-family: 'Outfit', sans-serif;">Keranjang Belanja</h1>
      </div>
    </div>
  </div>

  <main class="max-w-[1000px] mx-auto w-full px-6 pb-20 flex-grow" style="padding-top: 160px;">

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
              <p class="text-[9px] font-black uppercase tracking-[0.2em] mb-1" style="color: #c19a51;">{{ item.brand || 'Optik Medio' }}</p>
              <h3 class="font-black text-base leading-snug mb-1 line-clamp-2" style="color: #1a1209; font-family: 'Outfit', sans-serif;">{{ item.name }}</h3>
              <p class="text-xs mb-3" style="color: #8a7a60;">{{ item.variant?.color }} {{ item.variant?.size ? `· ${item.variant.size}` : '' }}</p>
              <p class="font-black text-lg" style="color: #7a6230;">Rp {{ item.price.toLocaleString('id-ID') }}</p>

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

      <!-- Order Summary -->
      <div class="w-full md:w-72 shrink-0">
        <div class="rounded-none border p-6 sticky top-24" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
          <h2 class="font-black text-lg mb-6" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Ringkasan</h2>

          <div class="flex flex-col gap-3 text-sm mb-6">
            <div class="flex justify-between">
              <span style="color: #8a7a60;">Subtotal</span>
              <span class="font-bold" style="color: #1a1209;">Rp {{ cartStore.cartTotal.toLocaleString('id-ID') }}</span>
            </div>
            <div class="flex justify-between">
              <span style="color: #8a7a60;">Ongkos Kirim</span>
              <span style="color: #8a7a60;">Dihitung saat checkout</span>
            </div>
          </div>

          <div class="h-px mb-6" style="background: rgba(193,154,81,0.2);"></div>

          <div class="flex justify-between items-end mb-8">
            <span class="text-sm font-bold" style="color: #5a5248;">Estimasi Total</span>
            <span class="text-2xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Rp {{ cartStore.cartTotal.toLocaleString('id-ID') }}</span>
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