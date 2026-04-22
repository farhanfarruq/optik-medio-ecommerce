import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { CartItem, Prescription } from '../types';

export const useCartStore = defineStore('cart', () => {
  const items = ref<CartItem[]>([]);

  const cartTotal = computed(() => 
    items.value.reduce((total: number, item: CartItem) => total + (item.price * (item.quantity || 1)), 0)
  );

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
  }

  function removeFromCart(cartId: string) {
    // Cari apakah item ini adalah parent (Frame)
    const isParent = items.value.some((item: CartItem) => item.cart_id === cartId && !item.parent_item_id);
    
    if (isParent) {
      // Hapus Frame beserta semua Lensa yang memiliki parent_item_id yang sama
      items.value = items.value.filter((item: CartItem) => item.cart_id !== cartId && item.parent_item_id !== cartId);
    } else {
      // Hanya hapus Lensa (jika user hanya ingin membatalkan upgrade lensa)
      items.value = items.value.filter((item: CartItem) => item.cart_id !== cartId);
    }
  }

  function clearCart() {
    items.value = [];
  }

  return { items, cartTotal, addToCart, removeFromCart, clearCart };
});