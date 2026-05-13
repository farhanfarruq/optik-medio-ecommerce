import { defineStore } from 'pinia';
import { computed, ref, watch } from 'vue';
import type { Product } from '../types';

const STORAGE_KEY = 'medio_compare_products';

const loadInitialItems = (): Product[] => {
  if (typeof window === 'undefined') return [];
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed.slice(0, 4) : [];
  } catch {
    return [];
  }
};

export const useCompareStore = defineStore('compare', () => {
  const items = ref<Product[]>(loadInitialItems());
  const ids = computed(() => new Set(items.value.map(item => item.id)));
  const count = computed(() => items.value.length);
  const canCompare = computed(() => items.value.length >= 2);

  const isCompared = (productId: number) => ids.value.has(productId);

  const add = (product: Product) => {
    if (isCompared(product.id)) return true;
    if (items.value.length >= 4) return false;
    items.value = [...items.value, { ...product }];
    return true;
  };

  const remove = (productId: number) => {
    items.value = items.value.filter(item => item.id !== productId);
  };

  const toggle = (product: Product) => {
    if (isCompared(product.id)) {
      remove(product.id);
      return 'removed';
    }
    return add(product) ? 'added' : 'full';
  };

  const clear = () => {
    items.value = [];
  };

  watch(items, (value) => {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value.slice(0, 4)));
  }, { deep: true });

  return {
    items,
    count,
    canCompare,
    isCompared,
    add,
    remove,
    toggle,
    clear,
  };
});
