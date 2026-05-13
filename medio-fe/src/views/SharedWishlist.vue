<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { productRepository } from '../repositories/ProductRepository';
import { resolveImageUrl } from '../core/utils/image';
import type { Product } from '../types';

const route = useRoute();
const router = useRouter();
const products = ref<Product[]>([]);
const isLoading = ref(true);
const errorMessage = ref('');

onMounted(async () => {
  try {
    const token = route.params.token as string;
    const response = await productRepository.getSharedWishlist(token);
    products.value = response.products || [];
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Link wishlist tidak bisa dibuka.';
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <main class="min-h-screen bg-[#f5f2ee] pb-24" :style="{ paddingTop: 'calc(var(--header-height, 96px) + 40px)' }">
    <section class="max-w-[1440px] mx-auto px-6 md:px-12">
      <div class="mb-10">
        <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-3" style="color: #c19a51;">Shared Wishlist</p>
        <h1 class="text-3xl md:text-5xl font-black tracking-tight" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Wishlist Pilihan</h1>
      </div>

      <div v-if="isLoading" class="grid grid-cols-2 md:grid-cols-4 gap-5">
        <div v-for="item in 4" :key="item" class="animate-pulse bg-white border border-stone-100">
          <div class="aspect-[4/5] bg-stone-100"></div>
          <div class="p-4 space-y-3">
            <div class="h-3 bg-stone-100 w-1/2"></div>
            <div class="h-4 bg-stone-100 w-4/5"></div>
          </div>
        </div>
      </div>

      <div v-else-if="errorMessage" class="bg-white border border-red-100 p-10 text-center">
        <span class="material-symbols-outlined text-5xl mb-4 block text-red-500">link_off</span>
        <h2 class="text-xl font-black mb-2" style="color: #1a1209;">Link tidak valid</h2>
        <p class="text-sm text-stone-500 mb-6">{{ errorMessage }}</p>
        <button @click="router.push('/products')" class="px-6 py-3 text-xs font-black uppercase tracking-widest text-white" style="background: #1a1209;">
          Lihat Produk
        </button>
      </div>

      <div v-else-if="products.length === 0" class="bg-white border border-stone-100 p-10 text-center">
        <span class="material-symbols-outlined text-5xl mb-4 block" style="color: #c19a51;">favorite</span>
        <h2 class="text-xl font-black mb-2" style="color: #1a1209;">Wishlist kosong</h2>
        <p class="text-sm text-stone-500">Produk pada link ini tidak tersedia.</p>
      </div>

      <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-5">
        <article
          v-for="product in products"
          :key="product.id"
          @click="router.push(`/products/${product.slug}`)"
          class="group bg-white border border-stone-100 cursor-pointer transition-all hover:-translate-y-1 hover:shadow-xl"
        >
          <div class="aspect-[4/5] bg-stone-50 flex items-center justify-center p-5">
            <img :src="resolveImageUrl(product)" :alt="product.name" class="w-full h-full object-contain transition-transform group-hover:scale-105" />
          </div>
          <div class="p-4">
            <p class="text-[10px] font-black uppercase tracking-widest text-stone-500 mb-1">{{ product.brand || 'Optik Medio' }}</p>
            <h2 class="text-sm font-black line-clamp-2 min-h-10" style="color: #1a1209;">{{ product.name }}</h2>
            <p class="mt-3 text-sm font-black" style="color: #7a6230;">Rp {{ product.price.toLocaleString('id-ID') }}</p>
          </div>
        </article>
      </div>
    </section>
  </main>
</template>
