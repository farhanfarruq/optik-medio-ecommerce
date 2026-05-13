<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { productRepository } from '../repositories/ProductRepository';
import { useSeoMeta } from '../composables/useSeoMeta';
import { resolveImageUrl } from '../core/utils/image';

const route  = useRoute();
const { setSeo, setJsonLd } = useSeoMeta();

const category  = ref<any>(null);
const products  = ref<any[]>([]);
const isLoading = ref(true);
const totalProducts = ref(0);

const slug = computed(() => route.params.slug as string);

const loadCategory = async () => {
  isLoading.value = true;
  try {
    const [cats, prods] = await Promise.all([
      productRepository.getCategories(),
      productRepository.getProducts({ category: slug.value, per_page: 24 }),
    ]);

    category.value = cats.find((c: any) => c.slug === slug.value) || null;
    products.value = prods.data || prods;
    totalProducts.value = prods.total || products.value.length;

    if (category.value) {
      setSeo({
        title: category.value.meta_title || category.value.name,
        description: category.value.meta_description || category.value.description || `Temukan koleksi ${category.value.name} terbaik di Optik Medio.`,
        ogImage: category.value.og_image || undefined,
        ogType: 'website',
        ogUrl: window.location.href,
      });

      // BreadcrumbList JSON-LD
      setJsonLd({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
          { '@type': 'ListItem', position: 1, name: 'Beranda', item: window.location.origin },
          { '@type': 'ListItem', position: 2, name: 'Produk', item: `${window.location.origin}/products` },
          { '@type': 'ListItem', position: 3, name: category.value.name, item: window.location.href },
        ],
      });
    }
  } catch (e) {
    console.error('Failed to load category', e);
  } finally {
    isLoading.value = false;
  }
};

watch(slug, loadCategory);
onMounted(loadCategory);
</script>

<template>
  <div>
    <!-- Hero -->
    <div class="relative overflow-hidden" style="height: 280px; background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);">
      <div class="absolute inset-0" style="background: url('/gambar/hero-bg.jpeg') center/cover; opacity: 0.2;"></div>
      <div class="relative z-10 h-full max-w-[1440px] mx-auto px-6 md:px-12 flex flex-col justify-end pb-12 pt-24">
        <nav class="flex items-center gap-2 text-xs mb-3" style="color: rgba(255,255,255,0.5);">
          <router-link to="/" class="hover:text-white">Beranda</router-link>
          <span>›</span>
          <router-link to="/products" class="hover:text-white">Produk</router-link>
          <span>›</span>
          <span class="text-white">{{ category?.name || slug }}</span>
        </nav>
        <h1 class="text-4xl font-black text-white" style="font-family: 'Outfit', sans-serif;">
          {{ category?.name || slug }}
        </h1>
        <p v-if="category?.description" class="text-sm mt-2" style="color: rgba(255,255,255,0.7);">
          {{ category.description }}
        </p>
        <p class="text-xs mt-2" style="color: rgba(193,154,81,0.8);">{{ totalProducts }} produk</p>
      </div>
    </div>

    <main class="max-w-[1440px] mx-auto px-6 md:px-12 py-12">
      <div v-if="isLoading" class="flex justify-center py-20">
        <span class="material-symbols-outlined animate-spin text-4xl" style="color: #c19a51;">sync</span>
      </div>

      <div v-else-if="products.length === 0" class="text-center py-20">
        <p class="text-stone-500">Belum ada produk di kategori ini.</p>
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
            <img
              :src="resolveImageUrl(product)"
              :alt="product.name"
              class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-105"
              loading="lazy"
              decoding="async"
            />
          </div>
          <div class="p-4">
            <p class="text-xs font-bold uppercase tracking-wider mb-1" style="color: #8a7a60;">{{ product.brand || 'Optik Medio' }}</p>
            <p class="text-sm font-bold line-clamp-2" style="color: #1a1209;">{{ product.name }}</p>
            <p class="text-base font-black mt-2" style="color: #c19a51;">Rp {{ product.price?.toLocaleString('id-ID') }}</p>
          </div>
        </router-link>
      </div>

      <div class="mt-8 text-center">
        <router-link
          :to="`/products/category/${slug}`"
          class="inline-flex items-center gap-2 px-6 py-3 border text-sm font-black uppercase tracking-wider transition-all hover:bg-stone-50"
          style="border-color: rgba(193,154,81,0.3); color: #8a7a60;"
        >
          Lihat Semua Produk {{ category?.name }}
          <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </router-link>
      </div>
    </main>
  </div>
</template>
