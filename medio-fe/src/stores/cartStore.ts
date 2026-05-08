import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { CartItem, Prescription, Promo } from '../types';
import { apiClient } from '../core/api/axiosclient';

export const useCartStore = defineStore('cart', () => {
  const items = ref<CartItem[]>([]);
  const activePromos = ref<Promo[]>([]);
  const appliedPromoId = ref<number | null>(null);
  const calculatedData = ref<any>(null);
  const isCalculating = ref(false);
  const isPromoBannerDismissed = ref(false);
  const isPromoExplicitlyCleared = ref(false);

  const cartTotal = computed(() =>
    items.value.reduce((total: number, item: CartItem) => total + (item.price * (item.quantity || 1)), 0)
  );

  const isPromoBannerVisible = computed(() => {
    if (!activePromos.value || isPromoBannerDismissed.value) return false;
    // Only show banner if there is at least one promo marked for it
    return activePromos.value.some((p: any) => p.is_banner_active);
  });

  const applicablePromos = computed(() => {
    if (items.value.length === 0) return [];
    
    return activePromos.value.filter(promo => {
      // 1. Transaction Discount
      if (promo.type === 'transaction_discount') {
        const minAmount = Number(promo.min_transaction_amount) || 0;
        return cartTotal.value >= minAmount;
      }

      // 2. Buy X Get Y or Product Discount
      // Collect all target product IDs
      const targetProductIds: number[] = [];
      if (promo.buy_product_id) targetProductIds.push(promo.buy_product_id);
      if (promo.discount_product_id) targetProductIds.push(promo.discount_product_id);
      
      if (promo.buy_products) {
        promo.buy_products.forEach((p: any) => targetProductIds.push(p.id));
      }
      if (promo.discount_products) {
        promo.discount_products.forEach((p: any) => targetProductIds.push(p.id));
      }

      // Collect all target brands
      const targetBrands: string[] = [];
      if (promo.buy_brands) targetBrands.push(...promo.buy_brands);
      if (promo.discount_brands) targetBrands.push(...promo.discount_brands);

      // If no specific targets are defined, it might be a general promo
      if (targetProductIds.length === 0 && targetBrands.length === 0) {
        return true;
      }

      // Check if any item in cart matches target products or brands
      return items.value.some((item: CartItem) => {
        const matchesProduct = targetProductIds.includes(item.id);
        const matchesBrand = item.brand ? targetBrands.includes(item.brand) : false;
        return matchesProduct || matchesBrand;
      });
    });
  });

  function addToCart(frame: CartItem, prescription?: Prescription, lens?: CartItem) {
    const frameCartId = crypto.randomUUID();
    items.value.push({
      ...frame,
      cart_id: frameCartId,
      quantity: 1,
      prescription: prescription || null
    });

    if (lens) {
      items.value.push({
        ...lens,
        cart_id: crypto.randomUUID(),
        parent_item_id: frameCartId,
        quantity: 1
      });
    }
    
    isPromoExplicitlyCleared.value = false;
    calculateCart();
  }

  function updateQuantity(cartId: string, delta: number) {
    const item = items.value.find((i: CartItem) => i.cart_id === cartId);
    if (!item) return;

    const newQty = item.quantity + delta;

    if (newQty < 1) return;
    if (newQty > item.stock) return;

    item.quantity = newQty;
    calculateCart();
  }

  function removeFromCart(cartId: string) {
    const isParent = items.value.some((item: CartItem) => item.cart_id === cartId && !item.parent_item_id);

    if (isParent) {
      items.value = items.value.filter((item: CartItem) => item.cart_id !== cartId && item.parent_item_id !== cartId);
    } else {
      items.value = items.value.filter((item: CartItem) => item.cart_id !== cartId);
    }
    
    calculateCart();
  }

  function clearCart() {
    items.value = [];
    appliedPromoId.value = null;
    calculatedData.value = null;
  }

  async function fetchPromos() {
    try {
      const response = await apiClient.get('/promos');
      activePromos.value = response.data;
      // Always reset dismissed state on refresh/new fetch to ensure banner reappears as requested
      isPromoBannerDismissed.value = false;
    } catch (err) {
      console.error('Failed to fetch promos', err);
    }
  }

  async function calculateCart(discountId?: number, shippingCost?: number) {
    if (items.value.length === 0) {
      calculatedData.value = null;
      return;
    }
    
    isCalculating.value = true;
    try {
      const payloadItems = items.value.map(item => ({
        product_id: item.id,
        quantity: item.quantity,
        variant: item.variant || null
      }));

      const payload: any = {
        items: payloadItems,
        promo_id: appliedPromoId.value
      };

      if (discountId) payload.discount_id = discountId;
      if (shippingCost !== undefined) payload.shipping_cost = shippingCost;

      const response = await apiClient.post('/orders/calculate', payload);
      calculatedData.value = response.data;

      // Only auto-set promo if none is currently applied AND user hasn't explicitly cleared it
      if (response.data.applied_promo && !appliedPromoId.value && !isPromoExplicitlyCleared.value) {
        appliedPromoId.value = response.data.applied_promo.id;
      }
    } catch (err: any) {
      console.error('Calculate failed', err);
      if (err.response?.status === 422) {
        throw err;
      }
    } finally {
      isCalculating.value = false;
    }
  }

  async function setPromo(promoId: number | null, discountId?: number, shippingCost?: number) {
    const previousPromoId = appliedPromoId.value;
    appliedPromoId.value = promoId;
    isPromoExplicitlyCleared.value = (promoId === null);
    
    try {
      await calculateCart(discountId, shippingCost);
    } catch (err) {
      appliedPromoId.value = previousPromoId;
      throw err;
    }
  }

  function dismissPromoBanner() {
    isPromoBannerDismissed.value = true;
  }

  return { 
    items, 
    cartTotal, 
    activePromos, 
    appliedPromoId, 
    calculatedData, 
    isCalculating,
    isPromoBannerDismissed,
    isPromoBannerVisible,
    applicablePromos,
    addToCart, 
    updateQuantity, 
    removeFromCart, 
    clearCart, 
    fetchPromos, 
    calculateCart, 
    setPromo,
    dismissPromoBanner
  };
}, {
  persist: {
    key: 'optik-medio-cart',
    storage: localStorage,
    paths: ['items', 'activePromos', 'appliedPromoId', 'calculatedData'],
  },
});