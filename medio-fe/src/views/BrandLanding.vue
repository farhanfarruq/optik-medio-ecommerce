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
    <div class="relative overflow-hidden" style="height: 240px; background: linear-gradient(135deg, var(--ink) 0%, var(--graphite) 100%);">
      <div class="absolute inset-0" style="background: url('/gambar/hero-bg.jpeg') center/cover; opacity: 0.15;"></div>
      <div class="relative z-10 h-full container-commerce flex flex-col justify-end pb-10 pt-20">
        <nav class="flex items-center gap-2 text-xs mb-3" style="color: rgba(255,255,255,0.5);">
          <router-link to="/" class="hover:text-white">Beranda</router-link>
          <span>›</span>
          <router-link to="/products" class="hover:text-white">Produk</router-link>
          <span>›</span>
          <span class="text-white">{{ brand }}</span>
        </nav>
        <h1 class="text-4xl font-black text-white" style="font-family: 'Cormorant Garamond', serif;">{{ brand }}</h1>
        <p class="text-xs mt-2" style="color: rgba(184,138,68,0.8);">{{ totalProducts }} produk</p>
      </div>
    </div>

    <main class="container-commerce py-12">
      <div v-if="isLoading" class="flex justify-center py-20">
        <span class="material-symbols-outlined animate-spin text-4xl" style="color: var(--gold);">sync</span>
      </div>

      <div v-else-if="products.length === 0" class="text-center py-20">
        <p class="text-graphite/65">Belum ada produk untuk merek ini.</p>
        <router-link to="/products" class="mt-4 inline-block text-sm font-bold" style="color: var(--gold);">← Lihat Semua Produk</router-link>
      </div>

      <div v-else class="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
        <router-link
          v-for="product in products"
          :key="product.id"
          :to="`/products/${product.slug}`"
          class="product-card-base group transition-colors hover:border-gold/40"
        >
          <div class="product-image-frame flex items-center justify-center p-4">
            <img :src="resolveImageUrl(product)" :alt="product.name" class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-[1.03]" loading="lazy" decoding="async" />
          </div>
          <div class="p-4">
            <p class="text-sm font-bold line-clamp-2" style="color: var(--ink);">{{ product.name }}</p>
            <p class="text-base font-black mt-2" style="color: var(--gold);">Rp {{ product.price?.toLocaleString('id-ID') }}</p>
          </div>
        </router-link>
      </div>
    </main>
  </div>
</template>
