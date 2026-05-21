import { defineStore } from 'pinia';
import { computed, ref, watch } from 'vue';
import type { Product } from '../types';

const STORAGE_KEY = 'medio_recently_viewed';

const loadInitialItems = (): Product[] => {
  if (typeof window === 'undefined') return [];
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed.slice(0, 12) : [];
  } catch {
    return [];
  }
};

export const useRecentlyViewedStore = defineStore('recentlyViewed', () => {
  const items = ref<Product[]>(loadInitialItems());

  const add = (product: Product) => {
    items.value = [
      { ...product },
      ...items.value.filter(item => item.id !== product.id),
    ].slice(0, 12);
  };

  const without = (productId: number) => computed(() => items.value.filter(item => item.id !== productId));

  const clear = () => {
    items.value = [];
  };

  watch(items, (value) => {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value.slice(0, 12)));
  }, { deep: true });

  return {
    items,
    add,
    without,
    clear,
  };
});
