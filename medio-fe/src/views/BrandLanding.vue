<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { productRepository } from '../repositories/ProductRepository';
import { useSeoMeta } from '../composables/useSeoMeta';
import { resolveImageUrl } from '../core/utils/image';

const route  = useRoute();
const { setSeo } = useSeoMeta();

const products      = ref<any[]>([]);
const isLoading     = ref(true);
const totalProducts = ref(0);

const brand = computed(() => decodeURIComponent(route.params.brand as string));

const loadBrand = async () => {
  isLoading.value = true;
  try {
    const prods = await productRepository.getProducts({ brand: brand.value, per_page: 24 });
    products.value = prods.data || prods;
    totalProducts.value = prods.total || products.value.length;

    setSeo({
      title: `${brand.value} — Koleksi Kacamata`,
      description: `Temukan koleksi kacamata ${brand.value} terlengkap di Optik Medio. ${totalProducts.value} produk tersedia.`,
      ogType: 'website',
      ogUrl: window.location.href,
    });
  } catch (e) {
    console.error('Failed to load brand', e);
  } finally {
    isLoading.value = false;
  }
};

watch(brand, loadBrand);
onMounted(loadBrand);
</script>

<template>
  <div>
    <!-- Hero -->
    <div class="relative overflow-hidden" style="height: 240px; background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);">
      <div class="absolute inset-0" style="background: url('/gambar/hero-bg.jpeg') center/cover; opacity: 0.15;"></div>
      <div class="relative z-10 h-full max-w-[1440px] mx-auto px-6 md:px-12 flex flex-col justify-end pb-10 pt-20">
        <nav class="flex items-center gap-2 text-xs mb-3" style="color: rgba(255,255,255,0.5);">
          <router-link to="/" class="hover:text-white">Beranda</router-link>
          <span>›</span>
          <router-link to="/products" class="hover:text-white">Produk</router-link>
          <span>›</span>
          <span class="text-white">{{ brand }}</span>
        </nav>
        <h1 class="text-4xl font-black text-white" style="font-family: 'Outfit', sans-serif;">{{ brand }}</h1>
        <p class="text-xs mt-2" style="color: rgba(193,154,81,0.8);">{{ totalProducts }} produk</p>
      </div>
    </div>

    <main class="max-w-[1440px] mx-auto px-6 md:px-12 py-12">
      <div v-if="isLoading" class="flex justify-center py-20">
        <span class="material-symbols-outlined animate-spin text-4xl" style="color: #c19a51;">sync</span>
      </div>

      <div v-else-if="products.length === 0" class="text-center py-20">
        <p class="text-stone-500">Belum ada produk untuk merek ini.</p>
        <router-link to="/products" class="mt-4 inline-block text-sm font-bold" style="color: #c19a51;">← Lihat Semua Produk</router-link>
      </div>

      <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <router-link
          v-for="product in products"
          :key="product.id"
          :to="`/products/${product.slug}`"
          class="group border transition-all hover:shadow-md"
          style="background: white; border-color: rgba(193,154,81,0.15);"
        >
          <div class="aspect-square overflow-hidden flex items-center justify-center p-4" style="background: linear-gradient(145deg, #f5f2ee, #ede7dc);">
            <img :src="resolveImageUrl(product)" :alt="product.name" class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-105" loading="lazy" decoding="async" />
          </div>
          <div class="p-4">
            <p class="text-sm font-bold line-clamp-2" style="color: #1a1209;">{{ product.name }}</p>
            <p class="text-base font-black mt-2" style="color: #c19a51;">Rp {{ product.price?.toLocaleString('id-ID') }}</p>
          </div>
        </router-link>
      </div>
    </main>
  </div>
</template>
