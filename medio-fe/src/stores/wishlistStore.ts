import { defineStore } from 'pinia';
import { computed, ref, watch } from 'vue';
import type { Product } from '../types';
import { apiClient } from '../core/api/axiosclient';
import { useAuthStore } from './authStore';

export const useWishlistStore = defineStore('wishlist', () => {
  const items = ref<Product[]>([]);
  const authStore = useAuthStore();
  const isLoading = ref(false);

  const ids = computed(() => new Set(items.value.map((item) => item.id)));

  const isWishlisted = (productId: number) => ids.value.has(productId);

  const fetchWishlist = async () => {
    if (!authStore.isAuthenticated) return;
    try {
      isLoading.value = true;
      const response = await apiClient.get('/wishlist');
      // Backend returns the collection directly as JSON array
      const data = response.data;
      items.value = Array.isArray(data) ? data : (data.data || []);
    } catch (error) {
      console.error('Failed to fetch wishlist', error);
    } finally {
      isLoading.value = false;
    }
  };

  const toggleWishlist = async (product: Product) => {
    const wasWishlisted = isWishlisted(product.id);
    
    // Optimistic update
    if (wasWishlisted) {
      items.value = items.value.filter((item) => item.id !== product.id);
    } else {
      items.value = [{ ...product }, ...items.value];
    }

    if (authStore.isAuthenticated) {
      try {
        await apiClient.post('/wishlist/toggle', { product_id: product.id });
      } catch (error) {
        console.error('Failed to toggle wishlist', error);
        // Revert on error
        if (wasWishlisted) {
          items.value = [{ ...product }, ...items.value];
        } else {
          items.value = items.value.filter((item) => item.id !== product.id);
        }
      }
    }
    
    return !wasWishlisted;
  };

  const removeFromWishlist = async (productId: number) => {
    const productToRestore = items.value.find((item) => item.id === productId);
    items.value = items.value.filter((item) => item.id !== productId);
    
    if (authStore.isAuthenticated) {
      try {
        await apiClient.post('/wishlist/toggle', { product_id: productId });
      } catch (error) {
        console.error('Failed to remove from wishlist', error);
        // Revert on error
        if (productToRestore) {
          items.value = [productToRestore, ...items.value];
        }
      }
    }
  };

  // Sync when auth state changes
  watch(() => authStore.isAuthenticated, (isAuthenticated) => {
    if (isAuthenticated) {
      fetchWishlist();
    } else {
      items.value = [];
    }
  });

  return { items, isLoading, isWishlisted, toggleWishlist, removeFromWishlist, fetchWishlist };
}, {
  persist: {
    key: 'optik-medio-wishlist',
    storage: localStorage,
  },
});
