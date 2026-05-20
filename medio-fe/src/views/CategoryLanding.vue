<script setup lang="ts">
import { logger } from '../core/utils/logger';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { productRepository } from '../repositories/ProductRepository';
import { useSeoMeta } from '../composables/useSeoMeta';
import { resolveImageUrl } from '../core/utils/image';
import PageHero from '../components/layout/PageHero.vue';

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
    logger.error('Failed to load category', e);
  } finally {
    isLoading.value = false;
  }
};

watch(slug, loadCategory);
onMounted(loadCategory);
</script>

<template>
  <div>
    <PageHero
      :title="category?.name || slug"
      :subtitle="category?.description || (String(totalProducts) + ' produk tersedia di kategori ini.')"
      :breadcrumbs="[{ label: 'Katalog Produk', to: '/products' }, { label: category?.name || slug }]"
      back-to="/products"
      back-label="Kembali ke Katalog"
    />

    <main class="container-commerce pt-40 pb-12">
      <div v-if="isLoading" class="flex justify-center py-20">
        <span class="material-symbols-outlined animate-spin text-4xl" style="color: var(--gold);">sync</span>
      </div>

      <div v-else-if="products.length === 0" class="text-center py-20">
        <p class="text-graphite/65">Belum ada produk di kategori ini.</p>
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
            <img
              :src="resolveImageUrl(product)"
              :alt="product.name"
              class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-[1.03]"
              loading="lazy"
              decoding="async"
            />
          </div>
          <div class="p-4">
            <p class="text-xs font-bold uppercase tracking-wider mb-1" style="color: #5c4a3a;">{{ product.brand || 'Optik Medio' }}</p>
            <p class="text-sm font-bold line-clamp-2" style="color: var(--ink);">{{ product.name }}</p>
            <p class="text-base font-black mt-2" style="color: var(--gold);">Rp {{ product.price?.toLocaleString('id-ID') }}</p>
          </div>
        </router-link>
      </div>

      <div class="mt-8 text-center">
        <router-link
          :to="`/products/category/${slug}`"
          class="btn-outline inline-flex items-center gap-2 px-6 py-3 text-sm uppercase tracking-[0.12em]"
        >
          Lihat Semua Produk {{ category?.name }}
          <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </router-link>
      </div>
    </main>
  </div>
</template>
