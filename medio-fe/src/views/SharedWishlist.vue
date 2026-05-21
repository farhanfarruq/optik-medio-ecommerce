<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { productRepository } from '../repositories/ProductRepository';
import { resolveImageUrl } from '../core/utils/image';
import type { Product } from '../types';
import PageHero from '../components/layout/PageHero.vue';

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
  <main class="min-h-screen bg-[var(--ivory)] pb-24 pt-8">
    <PageHero
      title="Wishlist Pilihan"
      :breadcrumbs="[{ label: 'Wishlist Dibagikan' }]"
      backTo="/products"
      backLabel="Lihat Produk"
    />

    <section class="container-premium pt-8 pb-10">

      <div v-if="isLoading" class="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-4">
        <div v-for="item in 4" :key="item" class="animate-pulse bg-white border border-mist">
          <div class="product-image-frame"></div>
          <div class="p-4 space-y-3">
            <div class="h-3 bg-mist w-1/2"></div>
            <div class="h-4 bg-mist w-4/5"></div>
          </div>
        </div>
      </div>

      <div v-else-if="errorMessage" class="alert-error p-8 text-center">
        <span class="material-symbols-outlined text-5xl mb-4 block text-red-500">link_off</span>
        <h2 class="text-xl font-black mb-2" style="color: var(--ink);">Link tidak valid</h2>
        <p class="text-sm text-graphite/65 mb-6">{{ errorMessage }}</p>
        <button @click="router.push('/products')" class="btn-primary px-6 py-3 text-xs uppercase tracking-[0.12em]">
          Lihat Produk
        </button>
      </div>

      <div v-else-if="products.length === 0" class="premium-card p-8 text-center">
        <span class="material-symbols-outlined text-5xl mb-4 block" style="color: var(--gold);">favorite</span>
        <h2 class="text-xl font-black mb-2" style="color: var(--ink);">Wishlist kosong</h2>
        <p class="text-sm text-graphite/65">Produk pada link ini tidak tersedia.</p>
      </div>

      <div v-else class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-5">
        <article
          v-for="product in products"
          :key="product.id"
          @click="router.push(`/products/${product.slug}`)"
          class="group bg-white border border-mist cursor-pointer transition-all hover:-translate-y-1 hover:shadow-soft"
        >
          <div class="aspect-[4/5] bg-ivory flex items-center justify-center p-5">
            <img :src="resolveImageUrl(product)" :alt="product.name" class="w-full h-full object-contain transition-transform group-hover:scale-105" loading="lazy" decoding="async" />
          </div>
          <div class="p-4">
            <p class="text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-1">{{ product.brand || 'Optik Medio' }}</p>
            <h2 class="text-sm font-black line-clamp-2 min-h-10" style="color: var(--ink);">{{ product.name }}</h2>
            <p class="mt-3 text-sm font-black" style="color: #7a6230;">Rp {{ product.price.toLocaleString('id-ID') }}</p>
          </div>
        </article>
      </div>
    </section>
  </main>
</template>
