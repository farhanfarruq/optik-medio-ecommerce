<script setup lang="ts">
import { logger } from '../core/utils/logger';
import { computed, onMounted, ref, watch } from 'vue';
import { useCartStore } from '../stores/cartStore';
import { useRouter } from 'vue-router';
import { resolveImageUrl } from '../core/utils/image';
import { useToast } from '../composables/useToast';
import { masterDataRepository } from '../repositories/MasterDataRepository';
import { formatMoney } from '../composables/useFormatMoney';
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
    logger.warn('Could not load store status', e);
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
    attached_lens: currentItems.find((item: any) => item.parent_item_id === frame.cart_id),
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
  if (promoId !== null) {
    const promo = cartStore.applicablePromos.find((p: any) => p.id === promoId);
    if (promo && !(promo as any).eligible) {
      showToast((promo as any).reason || 'Promo belum bisa digunakan.', 'error');
      return;
    }
  }
  try {
    await cartStore.setPromo(promoId);
    if (promoId) showToast('Promo berhasil diterapkan!', 'success');
    else showToast('Promo dilepas.', 'info');
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Gagal menerapkan promo.';
    showToast(msg, 'error');
  }
};

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
  return `Hemat ${formatMoney(Number(promo.discount_value || 0))}`;
};

const promoRequirementText = (promo: any): string => {
  if (promo.type === 'transaction_discount' && Number(promo.min_transaction_amount || 0) > 0) {
    return `Min. transaksi ${formatMoney(Number(promo.min_transaction_amount))}`;
  }
  if (promo.type === 'buy_x_get_y' && Number(promo.buy_quantity || 0) > 0) {
    return `Syarat: beli min. ${promo.buy_quantity} item yang sesuai`;
  }
  return '';
};

const getItemDiscount = (item: any) => {
  if (!cartStore.calculatedData?.items) return { isDiscounted: false, discountedPrice: item.price };

  const calcItem = cartStore.calculatedData.items.find((i: any) =>
    i.product_id === item.id && !i.is_free,
  );

  return {
    isDiscounted: !!calcItem?.is_discounted,
    discountedPrice: calcItem?.discounted_price || item.price,
  };
};

const summarySubtotal = computed(() =>
  cartStore.calculatedData ? cartStore.calculatedData.subtotal : cartStore.cartTotal,
);
const summaryTotal = computed(() =>
  cartStore.calculatedData ? cartStore.calculatedData.total_price : cartStore.cartTotal,
);
const couponDiscount = computed(() => cartStore.calculatedData?.discount_amount || 0);
const promoDiscount = computed(() => cartStore.calculatedData?.promo_discount_amount || 0);

const isCheckoutDisabled = computed(() =>
  cartStore.items.length === 0 || !!storeStatus.value?.is_closed,
);
</script>

<template>
  <PageHero
    title="Keranjang Belanja"
    subtitle="Tinjau item, promo, dan kesiapan checkout sebelum melanjutkan pembayaran."
    :breadcrumbs="[{ label: 'Keranjang Belanja' }]"
    back-to="/products"
    back-label="Kembali Belanja"
  />

  <main class="cart-main container-commerce">
    <!-- Store closed alert -->
    <div v-if="storeStatus?.is_closed" class="cart-alert">
      <span class="material-symbols-outlined" aria-hidden="true">store</span>
      <div>
        <p class="cart-alert__title">Toko Sedang Tutup</p>
        <p v-if="storeStatus.current_close?.reason" class="cart-alert__sub">{{ storeStatus.current_close.reason }}</p>
        <p class="cart-alert__sub">Checkout tidak dapat diproses saat ini.</p>
      </div>
    </div>

    <!-- Empty cart -->
    <div v-if="cartStore.items.length === 0" class="empty-state cart-empty">
      <span class="material-symbols-outlined cart-empty__icon" aria-hidden="true">shopping_bag</span>
      <h2 class="editorial-h2">Keranjang kosong</h2>
      <p>Temukan koleksi kacamata premium kami untuk dimasukkan ke keranjang.</p>
      <router-link to="/products" class="btn-primary btn-lg">
        <span class="material-symbols-outlined" aria-hidden="true">storefront</span>
        Jelajahi Koleksi
      </router-link>
    </div>

    <!-- Cart layout -->
    <div v-else class="cart-grid">
      <!-- ───────────── Items column ───────────── -->
      <section class="cart-items" aria-label="Item keranjang">
        <header class="cart-section-head">
          <p class="eyebrow">Item Keranjang</p>
          <h2 class="editorial-h3">{{ groupedCart.length }} produk</h2>
        </header>

        <ul class="cart-items__list">
          <li
            v-for="item in groupedCart"
            :key="item.cart_id"
            class="cart-item"
          >
            <div class="cart-item__row">
              <div class="cart-item__media">
                <img
                  :src="resolveImageUrl(item.images || item.image_url, item.name)"
                  :alt="item.name"
                  class="cart-item__image"
                  loading="lazy"
                  decoding="async"
                />
              </div>

              <div class="cart-item__body">
                <p class="text-meta cart-item__brand">{{ item.name }}</p>
                <h3 class="cart-item__name">{{ item.brand || 'Optik Medio' }}</h3>
                <p v-if="item.variant?.color || item.variant?.size" class="cart-item__variant">
                  <span v-if="item.variant?.color">{{ item.variant.color }}</span>
                  <span v-if="item.variant?.color && item.variant?.size"> · </span>
                  <span v-if="item.variant?.size">{{ item.variant.size }}</span>
                </p>

                <div class="cart-item__price-row">
                  <span v-if="getItemDiscount(item).isDiscounted" class="cart-item__price-strike">
                    {{ formatMoney(item.price) }}
                  </span>
                  <span class="cart-item__price">{{ formatMoney(getItemDiscount(item).discountedPrice) }}</span>
                </div>
              </div>

              <button
                type="button"
                class="cart-item__remove"
                :aria-label="`Hapus ${item.brand || item.name} dari keranjang`"
                @click="cartStore.removeFromCart(item.cart_id)"
              >
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
              </button>
            </div>

            <!-- Prescription -->
            <div v-if="item.prescription" class="cart-item__rx">
              <p class="cart-item__rx-title">Resep Optik Tercantum</p>
              <p>OD: SPH {{ item.prescription.od.sph }}, CYL {{ item.prescription.od.cyl }}, Axis {{ item.prescription.od.axis || '—' }}</p>
              <p>OS: SPH {{ item.prescription.os.sph }}, CYL {{ item.prescription.os.cyl }}, Axis {{ item.prescription.os.axis || '—' }}</p>
            </div>

            <!-- Attached lens -->
            <div v-if="item.attached_lens" class="cart-item__attach">
              <span class="cart-item__attach-label">
                <span class="material-symbols-outlined" aria-hidden="true">lens</span>
                {{ item.attached_lens.name }}
              </span>
              <span class="cart-item__attach-price">+ {{ formatMoney(item.attached_lens.price) }}</span>
            </div>
          </li>
        </ul>

        <!-- Free bonus items -->
        <section v-if="cartFreeItems.length > 0" class="cart-free" aria-label="Bonus dari promo">
          <header class="cart-free__head">
            <span class="material-symbols-outlined" aria-hidden="true">card_giftcard</span>
            <p class="eyebrow text-gold">Bonus Gratis dari Promo</p>
          </header>
          <ul class="cart-free__list">
            <li
              v-for="freeItem in cartFreeItems"
              :key="`${freeItem.product_id}-free`"
              class="cart-free__item"
            >
              <div class="cart-free__media">
                <img
                  v-if="freeItem.image"
                  :src="resolveImageUrl(freeItem.image, freeItem.name || freeItem.product_name)"
                  :alt="freeItem.name || freeItem.product_name"
                  loading="lazy"
                  decoding="async"
                />
                <span v-else class="material-symbols-outlined" aria-hidden="true">card_giftcard</span>
              </div>
              <div class="cart-free__body">
                <p class="text-meta text-gold">Item Gratis</p>
                <p class="cart-free__name">{{ freeItem.name || freeItem.product_name }}</p>
                <p class="cart-free__qty">Qty: {{ freeItem.quantity }}</p>
              </div>
              <div class="cart-free__price">
                <span class="cart-free__strike">{{ formatMoney(Number(freeItem.original_price || 0)) }}</span>
                <span class="cart-free__free">Rp 0</span>
              </div>
            </li>
          </ul>
        </section>
      </section>

      <!-- ───────────── Summary column ───────────── -->
      <aside class="cart-summary" aria-label="Ringkasan pesanan">
        <!-- Promo -->
        <section class="cart-promo">
          <header class="cart-promo__head">
            <span class="material-symbols-outlined" aria-hidden="true">sell</span>
            <h2 class="editorial-h3">Promo & Voucher</h2>
          </header>

          <ul v-if="cartStore.applicablePromos.length > 0" class="cart-promo__list">
            <li
              v-for="promo in cartStore.applicablePromos"
              :key="promo.id"
            >
              <button
                type="button"
                class="cart-promo-item"
                :class="{
                  'cart-promo-item--active': cartStore.appliedPromoId === promo.id,
                  'cart-promo-item--locked': !(promo as any).eligible,
                }"
                :disabled="!(promo as any).eligible"
                :aria-pressed="cartStore.appliedPromoId === promo.id"
                @click="(promo as any).eligible && handleSetPromo(promo.id === cartStore.appliedPromoId ? null : promo.id)"
              >
                <div class="cart-promo-item__body">
                  <span class="cart-promo-item__type">{{ promoTypeLabel(promo) }}</span>
                  <p class="cart-promo-item__name">{{ promo.name }}</p>
                  <p class="cart-promo-item__benefit">{{ promoBenefitText(promo) }}</p>
                  <p v-if="promo.description" class="cart-promo-item__desc">{{ formatPromoDescription(promo.description) }}</p>
                  <p v-if="(promo as any).reason" class="cart-promo-item__warn">⚠ {{ (promo as any).reason }}</p>
                  <p v-else-if="promoRequirementText(promo)" class="cart-promo-item__req">{{ promoRequirementText(promo) }}</p>
                </div>
                <span
                  v-if="cartStore.appliedPromoId === promo.id"
                  class="material-symbols-outlined cart-promo-item__icon cart-promo-item__icon--active"
                  aria-hidden="true"
                >check_circle</span>
                <span
                  v-else-if="(promo as any).eligible"
                  class="material-symbols-outlined cart-promo-item__icon"
                  aria-hidden="true"
                >add_circle</span>
                <span
                  v-else
                  class="material-symbols-outlined cart-promo-item__icon cart-promo-item__icon--locked"
                  aria-hidden="true"
                >lock</span>
              </button>
            </li>
          </ul>

          <div v-else class="cart-promo__empty">
            <p>Tidak ada promo tersedia saat ini.</p>
          </div>

          <!-- Active promo summary -->
          <div v-if="cartPromoSummary" class="cart-promo__active">
            <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
            <div>
              <p class="text-meta">Promo Aktif</p>
              <p class="cart-promo__active-name">{{ cartPromoSummary.name }}</p>
              <p v-if="cartPromoSummary.discount_amount > 0" class="cart-promo__active-save">
                Hemat {{ formatMoney(Number(cartPromoSummary.discount_amount)) }}
              </p>
              <ul v-if="cartFreeItems.length > 0" class="cart-promo__active-free">
                <p class="text-meta">Produk bonus:</p>
                <li v-for="fi in cartFreeItems" :key="fi.product_id">
                  {{ fi.quantity }}× {{ fi.name || fi.product_name }}
                </li>
              </ul>
            </div>
          </div>

          <div v-else-if="cartStore.calculatedData?.applied_promo && !cartStore.appliedPromoId" class="cart-promo__auto">
            <span class="material-symbols-outlined" aria-hidden="true">auto_awesome</span>
            <p>Promo Otomatis Terpasang</p>
          </div>
        </section>

        <!-- Order summary -->
        <section class="cart-order surface-elevated">
          <h2 class="editorial-h3 cart-order__title">Ringkasan</h2>

          <ul class="cart-order__list">
            <li>
              <span>Subtotal</span>
              <strong>{{ formatMoney(summarySubtotal) }}</strong>
            </li>
            <li v-if="couponDiscount > 0" class="cart-order__discount">
              <span>Diskon Kupon</span>
              <strong>− {{ formatMoney(couponDiscount) }}</strong>
            </li>
            <li v-if="promoDiscount > 0" class="cart-order__discount">
              <span>{{ cartPromoSummary?.label || 'Promo Eksklusif' }}</span>
              <strong>− {{ formatMoney(promoDiscount) }}</strong>
            </li>
            <li>
              <span>Ongkos Kirim</span>
              <em class="cart-order__hint">Dihitung saat checkout</em>
            </li>
          </ul>

          <div class="divider-rule cart-order__divider"></div>

          <div class="cart-order__total">
            <span>Estimasi Total</span>
            <strong class="price-display">{{ formatMoney(summaryTotal) }}</strong>
          </div>

          <button
            type="button"
            class="btn-primary btn-lg cart-order__cta"
            :disabled="isCheckoutDisabled"
            @click="handleCheckout"
          >
            <span>Lanjut ke Checkout</span>
            <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
          </button>

          <ul class="cart-order__trust">
            <li>
              <span class="material-symbols-outlined" aria-hidden="true">lock</span>
              <span>Aman</span>
            </li>
            <li>
              <span class="material-symbols-outlined" aria-hidden="true">verified</span>
              <span>Terpercaya</span>
            </li>
          </ul>
        </section>
      </aside>
    </div>

    <!-- Sticky CTA mobile -->
    <div
      v-if="cartStore.items.length > 0"
      class="cart-sticky-cta sticky-cta-mobile"
    >
      <div class="cart-sticky-cta__price">
        <span class="text-meta">Total</span>
        <strong>{{ formatMoney(summaryTotal) }}</strong>
      </div>
      <button
        type="button"
        class="btn-primary cart-sticky-cta__btn"
        :disabled="isCheckoutDisabled"
        @click="handleCheckout"
      >
        Checkout
        <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
      </button>
    </div>
  </main>
</template>

<style scoped>
.cart-main {
  padding-top: clamp(24px, 3vw, 40px);
  padding-bottom: clamp(96px, 8vw, 120px);
  flex-grow: 1;
}

/* Alert */
.cart-alert {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 20px;
  border-radius: 8px;
  border: 1px solid rgba(220, 38, 38, 0.30);
  background: rgba(220, 38, 38, 0.06);
}

.cart-alert .material-symbols-outlined { color: #dc2626; flex-shrink: 0; margin-top: 2px; }
.cart-alert__title { font-size: 14px; font-weight: 700; color: #dc2626; }
.cart-alert__sub { margin-top: 2px; font-size: 12px; color: #b91c1c; line-height: 1.5; }

/* Empty state */
.cart-empty {
  margin: 24px 0;
  padding: clamp(40px, 6vw, 80px) 24px;
}

.cart-empty__icon {
  font-size: 56px;
  color: rgba(184, 138, 68, 0.45);
  margin-bottom: 8px;
}

/* Layout */
.cart-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: clamp(20px, 3vw, 32px);
}

@media (min-width: 1024px) {
  .cart-grid {
    grid-template-columns: 1fr 360px;
    gap: 40px;
    align-items: start;
  }
}

/* Items column */
.cart-items {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cart-section-head {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.cart-items__list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.cart-item {
  position: relative;
  background: #fff;
  border: 1px solid var(--mist);
  border-radius: 10px;
  overflow: hidden;
  transition: box-shadow var(--motion-base), border-color var(--motion-base);
}

.cart-item:hover {
  border-color: rgba(184, 138, 68, 0.45);
  box-shadow: var(--shadow-card);
}

.cart-item__row {
  display: flex;
  gap: 12px;
  padding: 14px;
  align-items: stretch;
}

@media (min-width: 768px) { .cart-item__row { padding: 18px; gap: 16px; } }

.cart-item__media {
  flex-shrink: 0;
  width: 88px;
  height: 88px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid rgba(184, 138, 68, 0.10);
  background: linear-gradient(145deg, var(--ivory), var(--mist));
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
}

@media (min-width: 768px) { .cart-item__media { width: 112px; height: 112px; padding: 8px; } }

.cart-item__image {
  width: 100%;
  height: 100%;
  object-fit: contain;
  mix-blend-mode: multiply;
}

.cart-item__body {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding-right: 36px;
}

.cart-item__brand {
  font-size: 9px;
  letter-spacing: 0.18em;
  color: var(--gold);
}

.cart-item__name {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: clamp(14px, 0.85rem + 0.4vw, 18px);
  font-weight: 600;
  color: var(--ink);
  line-height: 1.2;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.cart-item__variant {
  font-size: 11px;
  color: rgba(43, 41, 38, 0.62);
}

.cart-item__price-row {
  margin-top: 6px;
  display: flex;
  align-items: baseline;
  gap: 8px;
  flex-wrap: wrap;
}

.cart-item__price-strike {
  font-size: 12px;
  font-weight: 500;
  color: rgba(43, 41, 38, 0.42);
  text-decoration: line-through;
}

.cart-item__price {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: clamp(15px, 0.85rem + 0.5vw, 18px);
  font-weight: 700;
  color: #6F4E1D;
}

.cart-item__remove {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(220, 38, 38, 0.08);
  color: #dc2626;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background-color var(--motion-base), transform var(--motion-fast);
}

.cart-item__remove:hover { background: rgba(220, 38, 38, 0.14); }
.cart-item__remove:active { transform: scale(0.94); }
.cart-item__remove .material-symbols-outlined { font-size: 16px; }

/* Prescription block */
.cart-item__rx {
  margin: 0 14px 12px;
  padding: 10px 12px;
  border-radius: 6px;
  border: 1px solid rgba(184, 138, 68, 0.18);
  background: rgba(184, 138, 68, 0.06);
  font-size: 11px;
  line-height: 1.55;
  color: rgba(43, 41, 38, 0.78);
}

@media (min-width: 768px) { .cart-item__rx { margin: 0 18px 14px; } }

.cart-item__rx-title {
  font-weight: 700;
  color: #6F4E1D;
  margin-bottom: 4px;
}

/* Attached lens */
.cart-item__attach {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 14px 12px;
  border-top: 1px solid rgba(184, 138, 68, 0.14);
  font-size: 12px;
}

@media (min-width: 768px) { .cart-item__attach { padding: 12px 18px 16px; } }

.cart-item__attach-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 700;
  color: var(--ink);
  min-width: 0;
}

.cart-item__attach-label .material-symbols-outlined {
  color: var(--gold);
  font-size: 14px;
  flex-shrink: 0;
}

.cart-item__attach-price {
  font-weight: 700;
  color: var(--gold);
  white-space: nowrap;
}

/* Free items */
.cart-free {
  margin-top: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--mist);
}

.cart-free__head {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 12px;
}

.cart-free__head .material-symbols-outlined { color: var(--gold); font-size: 18px; }

.cart-free__list { display: flex; flex-direction: column; gap: 8px; }

.cart-free__item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid rgba(184, 138, 68, 0.22);
  background: rgba(184, 138, 68, 0.04);
}

.cart-free__media {
  flex-shrink: 0;
  width: 56px;
  height: 56px;
  border-radius: 6px;
  border: 1px solid rgba(184, 138, 68, 0.18);
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
}

.cart-free__media img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  mix-blend-mode: multiply;
}

.cart-free__media .material-symbols-outlined { color: var(--gold); font-size: 22px; }

.cart-free__body { flex: 1 1 auto; min-width: 0; }
.cart-free__name { font-size: 13px; font-weight: 700; color: var(--ink); line-height: 1.3; }
.cart-free__qty { margin-top: 2px; font-size: 11px; color: rgba(43, 41, 38, 0.62); }

.cart-free__price { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; flex-shrink: 0; }
.cart-free__strike {
  font-size: 11px;
  text-decoration: line-through;
  color: rgba(43, 41, 38, 0.52);
}
.cart-free__free { font-size: 13px; font-weight: 700; color: #16a34a; }

/* Summary */
.cart-summary {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

@media (min-width: 1024px) {
  .cart-summary {
    position: sticky;
    top: calc(var(--header-height, 72px) + 24px);
  }
}

/* Promo card */
.cart-promo {
  padding: 18px;
  border-radius: 10px;
  border: 1px solid var(--mist);
  background: #fff;
}

.cart-promo__head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
}

.cart-promo__head .material-symbols-outlined { color: var(--gold); font-size: 20px; }
.cart-promo__head h2 { margin: 0; font-size: 14px; }

.cart-promo__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 320px;
  overflow-y: auto;
  padding-right: 4px;
}

.cart-promo-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid var(--mist);
  background: #fff;
  width: 100%;
  text-align: left;
  transition: border-color var(--motion-base), background-color var(--motion-base);
  cursor: pointer;
}

.cart-promo-item:hover { background: var(--ivory); }

.cart-promo-item--active {
  border-color: var(--gold);
  background: rgba(184, 138, 68, 0.06);
  box-shadow: 0 0 0 1px rgba(184, 138, 68, 0.30);
}

.cart-promo-item--locked {
  cursor: not-allowed;
  opacity: 0.6;
}

.cart-promo-item--locked:hover { background: #fff; }

.cart-promo-item__body { flex: 1 1 auto; min-width: 0; }

.cart-promo-item__type {
  display: inline-block;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  padding: 2px 6px;
  border-radius: 4px;
  background: rgba(184, 138, 68, 0.12);
  color: #6F4E1D;
  margin-bottom: 6px;
}

.cart-promo-item__name {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1.3;
}

.cart-promo-item--active .cart-promo-item__name { color: #6F4E1D; }

.cart-promo-item__benefit {
  margin-top: 4px;
  font-size: 11px;
  font-weight: 700;
  color: #16a34a;
}

.cart-promo-item__desc {
  margin-top: 4px;
  font-size: 10px;
  color: rgba(43, 41, 38, 0.62);
  line-height: 1.45;
}

.cart-promo-item__req {
  margin-top: 4px;
  font-size: 10px;
  color: rgba(43, 41, 38, 0.62);
}

.cart-promo-item__warn {
  margin-top: 4px;
  font-size: 10px;
  font-weight: 700;
  color: #d97706;
}

.cart-promo-item__icon {
  font-size: 18px;
  color: rgba(43, 41, 38, 0.30);
  flex-shrink: 0;
  margin-top: 2px;
}

.cart-promo-item:hover .cart-promo-item__icon { color: rgba(43, 41, 38, 0.55); }
.cart-promo-item__icon--active { color: var(--gold); }
.cart-promo-item__icon--locked { color: #d97706; }

.cart-promo__empty {
  padding: 20px 0;
  text-align: center;
  font-size: 11px;
  color: rgba(43, 41, 38, 0.45);
  border: 1px dashed var(--mist);
  border-radius: 6px;
}

.cart-promo__active {
  margin-top: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid rgba(22, 163, 74, 0.28);
  background: rgba(22, 163, 74, 0.05);
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.cart-promo__active .material-symbols-outlined { color: #16a34a; font-size: 18px; flex-shrink: 0; margin-top: 2px; }
.cart-promo__active-name { font-size: 12px; font-weight: 700; color: var(--ink); margin-top: 2px; }
.cart-promo__active-save {
  margin-top: 4px;
  font-size: 12px;
  font-weight: 700;
  color: #16a34a;
}

.cart-promo__active-free {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cart-promo__active-free li {
  font-size: 11px;
  font-weight: 700;
  color: var(--ink);
}

.cart-promo__auto {
  margin-top: 12px;
  padding: 8px 12px;
  border-radius: 6px;
  background: rgba(86, 96, 75, 0.10);
  border: 1px solid rgba(86, 96, 75, 0.22);
  display: flex;
  align-items: center;
  gap: 6px;
}

.cart-promo__auto .material-symbols-outlined { color: var(--olive); font-size: 14px; }
.cart-promo__auto p {
  font-size: 9px;
  font-weight: 700;
  color: var(--olive);
  text-transform: uppercase;
  letter-spacing: 0.10em;
}

/* Order summary */
.cart-order { padding: 22px; }

.cart-order__title { margin: 0 0 18px; font-size: 16px; }

.cart-order__list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  font-size: 13px;
}

.cart-order__list li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.cart-order__list li > span { color: rgba(43, 41, 38, 0.65); }
.cart-order__list li > strong { color: var(--ink); font-weight: 700; }

.cart-order__discount > span,
.cart-order__discount > strong {
  color: var(--olive);
}

.cart-order__hint {
  font-style: normal;
  font-size: 11px;
  color: rgba(43, 41, 38, 0.55);
}

.cart-order__divider { margin: 18px 0; }

.cart-order__total {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 22px;
}

.cart-order__total > span {
  font-size: 13px;
  font-weight: 600;
  color: rgba(43, 41, 38, 0.78);
}

.cart-order__total .price-display {
  font-size: clamp(20px, 1.2rem + 0.6vw, 28px);
  color: var(--ink);
}

.cart-order__cta {
  width: 100%;
  letter-spacing: 0.10em;
  text-transform: uppercase;
  font-size: 12px;
}

.cart-order__cta:disabled { background: var(--mist); color: rgba(43, 41, 38, 0.55); box-shadow: none; }
.cart-order__cta:disabled:hover { background: var(--mist); }

.cart-order__trust {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-top: 16px;
}

.cart-order__trust li {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.10em;
  color: rgba(43, 41, 38, 0.62);
}

.cart-order__trust .material-symbols-outlined { color: var(--gold); font-size: 14px; }

/* Sticky CTA mobile */
.cart-sticky-cta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.cart-sticky-cta__price {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-width: 0;
}

.cart-sticky-cta__price .text-meta {
  font-size: 9px;
  letter-spacing: 0.14em;
}

.cart-sticky-cta__price strong {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: 22px;
  font-weight: 700;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.05;
}

.cart-sticky-cta__btn {
  flex: 0 0 auto;
  width: auto;
  min-height: 46px;
  padding: 11px 16px;
  font-size: 11px;
  letter-spacing: 0.08em;
  white-space: nowrap;
}

.cart-sticky-cta__btn:disabled { background: var(--mist); color: rgba(43, 41, 38, 0.55); box-shadow: none; }
.cart-sticky-cta__btn .material-symbols-outlined { font-size: 18px; flex-shrink: 0; }

@media (max-width: 359.98px) {
  .cart-sticky-cta { gap: 8px; }
  .cart-sticky-cta__price strong { font-size: 18px; }
  .cart-sticky-cta__btn { padding: 11px 12px; font-size: 10px; }
}

@media (min-width: 768px) { .cart-sticky-cta { display: none; } }
</style>
