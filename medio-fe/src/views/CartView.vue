<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useCartStore } from '../stores/cartStore';
import { useRouter } from 'vue-router';
import { resolveImageUrl } from '../core/utils/image';
import { useToast } from '../composables/useToast';
import { masterDataRepository } from '../repositories/MasterDataRepository';
import PageHero from '../components/layout/PageHero.vue';

const { showToast } = useToast();

const cartStore = useCartStore();
const router = useRouter();

const storeStatus = ref<{ is_closed: boolean; current_close: any | null } | null>(null);

const refreshCartCalculation = async () => {
  try {
    await cartStore.calculateCart();
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Gagal menghitung keranjang.';
    showToast(msg, 'error');
  }
};

onMounted(async () => {
  await cartStore.fetchPromos();
  await refreshCartCalculation();
  try {
    storeStatus.value = await masterDataRepository.getStoreStatus();
  } catch (e) {
    console.warn('Could not load store status', e);
  }
});

watch(() => cartStore.items.length, () => {
  void refreshCartCalculation();
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
  // Cek apakah promo eligible sebelum apply
  if (promoId !== null) {
    const promo = cartStore.applicablePromos.find((p: any) => p.id === promoId);
    if (promo && !(promo as any).eligible) {
      showToast((promo as any).reason || 'Promo belum bisa digunakan.', 'error');
      return;
    }
  }
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

// ── Promo display helpers ──────────────────────────────────────────────────
const cartFreeItems = computed(() => {
  if (!cartStore.calculatedData?.items) return [];
  return cartStore.calculatedData.items.filter((i: any) => i.is_free);
});

const cartPromoSummary = computed(() => cartStore.calculatedData?.promo_summary || null);

const promoTypeLabel = (promo: any): string => {
  if (promo.type === 'buy_x_get_y') return 'Bonus Produk';
  if (promo.type === 'transaction_discount') return 'Diskon Transaksi';
  if (promo.type === 'product_discount') return 'Diskon Produk';
  return 'Promo';
};

const promoBenefitText = (promo: any): string => {
  if (promo.type === 'buy_x_get_y') {
    const buyQty = Number(promo.buy_quantity || 0);
    const getQty = Number(promo.get_quantity || 0);
    const freeName = promo.get_product?.name || 'produk pilihan';
    return buyQty && getQty ? `Beli ${buyQty}, gratis ${getQty} ${freeName}` : 'Bonus produk gratis otomatis';
  }
  if (promo.discount_type === 'percentage') {
    return `Hemat ${Number(promo.discount_value || 0)}%`;
  }
  return `Hemat Rp ${Number(promo.discount_value || 0).toLocaleString('id-ID')}`;
};

const promoRequirementText = (promo: any): string => {
  if (promo.type === 'transaction_discount' && Number(promo.min_transaction_amount || 0) > 0) {
    return `Min. transaksi Rp ${Number(promo.min_transaction_amount).toLocaleString('id-ID')}`;
  }
  if (promo.type === 'buy_x_get_y' && Number(promo.buy_quantity || 0) > 0) {
    return `Syarat: beli min. ${promo.buy_quantity} item yang sesuai`;
  }
  return '';
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
  <PageHero
    title="Keranjang Belanja"
    subtitle="Tinjau item, promo, dan kesiapan checkout sebelum melanjutkan pembayaran."
    :breadcrumbs="[{ label: 'Keranjang Belanja' }]"
    back-to="/products"
    back-label="Kembali Belanja"
  />

  <main class="container-commerce pt-40 pb-20 flex-grow">

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
    <div v-if="cartStore.items.length === 0" class="text-center py-24 rounded-lg border border-dashed" style="border-color: rgba(184,138,68,0.25); background: rgba(184,138,68,0.04);">
      <span class="material-symbols-outlined text-6xl block mb-4" style="color: rgba(184,138,68,0.4);">shopping_bag</span>
      <h2 class="text-2xl font-black mb-3" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Keranjang kosong</h2>
      <p class="text-sm mb-8" style="color: #5c4a3a;">Temukan koleksi kacamata premium kami.</p>
      <router-link
        to="/products"
        class="inline-flex items-center gap-2 px-8 py-3.5 rounded-lg font-black text-sm text-white transition-all hover:shadow-soft active:scale-95"
        style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
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
          class="relative rounded-lg border overflow-hidden transition-all hover:shadow-card"
          style="background: white; border-color: rgba(184,138,68,0.12);"
        >
          <div class="flex gap-5 p-5">
            <!-- Image -->
            <div class="w-28 h-28 rounded-lg overflow-hidden shrink-0 flex items-center justify-center p-2 border" style="background: linear-gradient(145deg, var(--ivory), var(--mist)); border-color: rgba(184,138,68,0.1);">
              <img :src="resolveImageUrl(item.images || item.image_url, item.name)" class="w-full h-full object-contain mix-blend-multiply" />
            </div>

            <!-- Info -->
            <div class="flex flex-col flex-grow min-w-0">
              <p class="text-[9px] font-black uppercase tracking-[0.2em] mb-1" style="color: var(--gold);">{{ item.name }}</p>
              <h3 class="font-black text-base leading-snug mb-1 line-clamp-2" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">{{ item.brand || 'Optik Medio' }}</h3>
              <p class="text-xs mb-3" style="color: #5c4a3a;">{{ item.variant?.color }} {{ item.variant?.size ? `· ${item.variant.size}` : '' }}</p>
              <div class="flex items-center gap-2">
                <p v-if="getItemDiscount(item).isDiscounted" class="font-normal text-sm line-through text-graphite/45">Rp {{ item.price.toLocaleString('id-ID') }}</p>
                <p class="font-black text-lg" style="color: #6F4E1D;">Rp {{ getItemDiscount(item).discountedPrice.toLocaleString('id-ID') }}</p>
              </div>

              <!-- Prescription -->
              <div v-if="item.prescription" class="mt-3 p-3 rounded-lg text-xs flex flex-col gap-1" style="background: rgba(184,138,68,0.06); border: 1px solid rgba(184,138,68,0.12);">
                <span class="font-black" style="color: #6F4E1D;">Resep Optik Tercantum</span>
                <span style="color: #5c4a3a;">OD: SPH {{ item.prescription.od.sph }}, CYL {{ item.prescription.od.cyl }}, Axis {{ item.prescription.od.axis }}</span>
                <span style="color: #5c4a3a;">OS: SPH {{ item.prescription.os.sph }}, CYL {{ item.prescription.os.cyl }}, Axis {{ item.prescription.os.axis }}</span>
              </div>

              <!-- Attached Lens -->
              <div v-if="item.attached_lens" class="mt-3 pt-3 flex justify-between items-center border-t" style="border-color: rgba(184,138,68,0.12);">
                <div class="flex items-center gap-2">
                  <span class="material-symbols-outlined text-sm" style="color: var(--gold);">lens</span>
                  <span class="text-xs font-bold" style="color: var(--ink);">{{ item.attached_lens.name }}</span>
                </div>
                <span class="text-xs font-black" style="color: var(--gold);">+ Rp {{ item.attached_lens.price.toLocaleString('id-ID') }}</span>
              </div>
            </div>
          </div>

          <!-- Remove Button -->
          <button
            @click="cartStore.removeFromCart(item.cart_id)"
            class="absolute top-3 right-3 w-8 h-8 rounded-lg flex items-center justify-center transition-all hover:scale-110"
            style="background: rgba(220,38,38,0.08); color: #dc2626;"
          >
            <span class="material-symbols-outlined text-sm">close</span>
          </button>
        </div>

        <!-- ── Free Items dari Promo (Bundle/Bonus) ──────────────────────── -->
        <div v-if="cartFreeItems.length > 0" class="mt-4">
          <div class="flex items-center gap-2 mb-3">
            <span class="material-symbols-outlined text-base" style="color: var(--gold);">card_giftcard</span>
            <p class="text-[10px] font-black uppercase tracking-[0.18em]" style="color: var(--gold);">Bonus Gratis dari Promo</p>
          </div>
          <div class="flex flex-col gap-3">
            <div
              v-for="freeItem in cartFreeItems"
              :key="freeItem.product_id + '-free'"
              class="flex items-center gap-4 p-4 rounded-lg border"
              style="background: rgba(184,138,68,0.04); border-color: rgba(184,138,68,0.2);"
            >
              <div class="w-16 h-16 rounded-lg overflow-hidden shrink-0 flex items-center justify-center p-1 border" style="background: white; border-color: rgba(184,138,68,0.15);">
                <img v-if="freeItem.image" :src="resolveImageUrl(freeItem.image, freeItem.name || freeItem.product_name)" class="w-full h-full object-contain mix-blend-multiply" />
                <span v-else class="material-symbols-outlined text-2xl" style="color: var(--gold);">card_giftcard</span>
              </div>
              <div class="flex-grow min-w-0">
                <p class="text-[9px] font-black uppercase tracking-[0.18em] mb-1" style="color: var(--gold);">Item Gratis</p>
                <p class="font-black text-sm leading-snug text-ink">{{ freeItem.name || freeItem.product_name }}</p>
                <p class="text-xs mt-1" style="color: #5c4a3a;">Qty: {{ freeItem.quantity }}</p>
              </div>
              <div class="shrink-0 text-right">
                <p class="text-xs line-through" style="color: #5c4a3a;">Rp {{ Number(freeItem.original_price || 0).toLocaleString('id-ID') }}</p>
                <p class="font-black text-sm" style="color: #16a34a;">Rp 0</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Order Summary -->
      <div class="w-full md:w-80 shrink-0">
        <!-- Promos Section -->
        <div class="rounded-lg border p-5 mb-5" style="background: #fff; border-color: rgba(184,138,68,0.15);">
          <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-lg" style="color: var(--gold);">sell</span>
            <h2 class="font-black text-sm uppercase tracking-wider" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Promo & Voucher</h2>
          </div>

          <div v-if="cartStore.applicablePromos.length > 0" class="flex flex-col gap-2">
            <div 
              v-for="promo in cartStore.applicablePromos" 
              :key="promo.id"
              @click="(promo as any).eligible ? handleSetPromo(promo.id === cartStore.appliedPromoId ? null : promo.id) : null"
              class="p-3 border transition-all group"
              :class="(promo as any).eligible ? 'cursor-pointer hover:bg-ivory' : 'cursor-not-allowed opacity-60'"
              :style="{
                borderColor: cartStore.appliedPromoId === promo.id ? 'var(--gold)' : 'rgba(184,138,68,0.1)',
                background: cartStore.appliedPromoId === promo.id ? 'rgba(184,138,68,0.05)' : 'white'
              }"
            >
              <div class="flex justify-between items-start gap-2">
                <div class="flex-grow min-w-0">
                  <!-- Tipe badge -->
                  <span class="inline-block text-[8px] font-black uppercase tracking-[0.12em] px-1.5 py-0.5 mb-1.5 rounded"
                    style="background: rgba(184,138,68,0.12); color: #6F4E1D;">
                    {{ promoTypeLabel(promo) }}
                  </span>
                  <p class="font-black text-[11px] leading-tight mb-1" :style="{ color: cartStore.appliedPromoId === promo.id ? '#6F4E1D' : 'var(--ink)' }">{{ promo.name }}</p>
                  <!-- Benefit text -->
                  <p class="text-[10px] font-bold mb-0.5" style="color: #16a34a;">{{ promoBenefitText(promo) }}</p>
                  <p v-if="promo.description" class="text-[9px] text-graphite/65">{{ formatPromoDescription(promo.description) }}</p>
                  <!-- Requirement / not-eligible reason -->
                  <p v-if="(promo as any).reason" class="text-[9px] font-bold" style="color: #d97706;">⚠ {{ (promo as any).reason }}</p>
                  <p v-else-if="promoRequirementText(promo)" class="text-[9px]" style="color: #5c4a3a;">{{ promoRequirementText(promo) }}</p>
                </div>
                <span v-if="cartStore.appliedPromoId === promo.id" class="material-symbols-outlined text-sm shrink-0" style="color: var(--gold);">check_circle</span>
                <span v-else-if="(promo as any).eligible" class="material-symbols-outlined text-sm shrink-0 opacity-25 group-hover:opacity-50 transition-opacity">add_circle</span>
                <span v-else class="material-symbols-outlined text-sm shrink-0 opacity-30" style="color: #d97706;">lock</span>
              </div>
            </div>
          </div>
          <div v-else class="py-4 text-center border border-dashed border-mist">
            <p class="text-[10px] text-graphite/45">Tidak ada promo tersedia saat ini</p>
          </div>

          <!-- Active promo summary -->
          <div v-if="cartPromoSummary" class="mt-3 p-3 rounded-lg border" style="background: rgba(22,163,74,0.05); border-color: rgba(22,163,74,0.2);">
            <div class="flex items-start gap-2">
              <span class="material-symbols-outlined text-sm mt-0.5 shrink-0" style="color: #16a34a;">check_circle</span>
              <div class="flex-grow min-w-0">
                <p class="text-[10px] font-black uppercase tracking-[0.12em] mb-0.5" style="color: #16a34a;">Promo Aktif</p>
                <p class="text-xs font-bold text-ink">{{ cartPromoSummary.name }}</p>
                <p v-if="cartPromoSummary.discount_amount > 0" class="text-xs font-black mt-1" style="color: #16a34a;">
                  Hemat Rp {{ Number(cartPromoSummary.discount_amount).toLocaleString('id-ID') }}
                </p>
                <!-- Free items preview -->
                <div v-if="cartFreeItems.length > 0" class="mt-2 flex flex-col gap-1">
                  <p class="text-[9px] font-bold" style="color: #5c4a3a;">Produk bonus:</p>
                  <p v-for="fi in cartFreeItems" :key="fi.product_id" class="text-[10px] font-bold text-ink">
                    {{ fi.quantity }}× {{ fi.name || fi.product_name }}
                  </p>
                </div>
              </div>
            </div>
          </div>
          <!-- Auto-applied indicator (buy_x_get_y tanpa explicit select) -->
          <div v-else-if="cartStore.calculatedData?.applied_promo && !cartStore.appliedPromoId" class="mt-3 p-2 bg-olive/10 border border-olive/20 flex items-center gap-2">
            <span class="material-symbols-outlined text-xs text-olive">auto_awesome</span>
            <p class="text-[9px] font-bold text-olive uppercase tracking-normal">Promo Otomatis Terpasang</p>
          </div>
        </div>

        <div class="premium-card p-6" style="background: white; border-color: rgba(184,138,68,0.15); box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
          <h2 class="font-black text-lg mb-6" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Ringkasan</h2>

          <div class="flex flex-col gap-3 text-sm mb-6">
            <div class="flex justify-between">
              <span style="color: #5c4a3a;">Subtotal</span>
              <span class="font-bold" style="color: var(--ink);">Rp {{ (cartStore.calculatedData ? cartStore.calculatedData.subtotal : cartStore.cartTotal).toLocaleString('id-ID') }}</span>
            </div>
            <div v-if="cartStore.calculatedData?.discount_amount > 0" class="flex justify-between text-olive">
              <span>Diskon Kupon</span>
              <span class="font-bold">- Rp {{ cartStore.calculatedData.discount_amount.toLocaleString('id-ID') }}</span>
            </div>
            <div v-if="cartStore.calculatedData?.promo_discount_amount > 0" class="flex justify-between text-olive">
              <span>{{ cartPromoSummary?.label || 'Promo Eksklusif' }}</span>
              <span class="font-bold">- Rp {{ cartStore.calculatedData.promo_discount_amount.toLocaleString('id-ID') }}</span>
            </div>
            <div class="flex justify-between">
              <span style="color: #5c4a3a;">Ongkos Kirim</span>
              <span style="color: #5c4a3a;">Dihitung saat checkout</span>
            </div>
          </div>

          <div class="h-px mb-6" style="background: rgba(184,138,68,0.2);"></div>

          <div class="flex justify-between items-end mb-8">
            <span class="text-sm font-bold" style="color: var(--graphite);">Estimasi Total</span>
            <span class="text-2xl font-black" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Rp {{ (cartStore.calculatedData ? cartStore.calculatedData.total_price : cartStore.cartTotal).toLocaleString('id-ID') }}</span>
          </div>

          <button
            @click="handleCheckout"
            class="w-full py-4 rounded-lg font-black text-sm text-white uppercase tracking-wider flex items-center justify-center gap-2 transition-all hover:shadow-soft active:scale-95"
            style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
          >
            Lanjut ke Checkout
            <span class="material-symbols-outlined text-base">arrow_forward</span>
          </button>

          <!-- Trust -->
          <div class="flex justify-center gap-4 mt-5">
            <div class="flex items-center gap-1 text-[9px] font-bold uppercase tracking-wide" style="color: #5c4a3a;">
              <span class="material-symbols-outlined text-sm" style="color: var(--gold);">lock</span>
              Aman
            </div>
            <div class="flex items-center gap-1 text-[9px] font-bold uppercase tracking-wide" style="color: #5c4a3a;">
              <span class="material-symbols-outlined text-sm" style="color: var(--gold);">verified</span>
              Terpercaya
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>
