<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { productRepository, type Category } from '../repositories/ProductRepository';
import type { Product } from '../types';
import { resolveImageUrl } from '../core/utils/image';

const route = useRoute();
const router = useRouter();
const products = ref<Product[]>([]);
const categories = ref<Category[]>([]);
const brands = ref<string[]>([]);
const isLoading = ref(true);
const hasError = ref(false);
const categorySlug = ref(route.params.slug as string);
const showFilterPanel = ref(false);

const currentPage = ref(1);
const lastPage = ref(1);
const totalProducts = ref(0);
const isLoadingMore = ref(false);
const selectedBrand = ref<string>('');
const loadMoreTrigger = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const categoryTitle = computed(() => {
  if (!categorySlug.value) return 'Koleksi Kami';
  const found = categories.value.find(c => c.slug === categorySlug.value);
  if (found) return found.name;
  return categorySlug.value
    .split('-')
    .map((w: string) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ');
});

const categoryDescription = computed(() => {
  if (!categorySlug.value) return 'Temukan koleksi kacamata premium kami, dibuat untuk kenyamanan dan gaya terbaik Anda.';
  const found = categories.value.find(c => c.slug === categorySlug.value);
  if (found?.description) return found.description;
  const map: Record<string, string> = {
    'kacamata-baca': 'Kacamata baca berkualitas tinggi untuk kenyamanan mata Anda sehari-hari.',
    'kacamata-hitam': 'Koleksi sunglasses stylish dengan perlindungan UV terbaik.',
    'lensa-kacamata': 'Lensa presisi tinggi untuk penglihatan yang jernih dan tajam.',
    'frame-kacamata': 'Frame premium dari berbagai material pilihan terbaik.',
  };
  return map[categorySlug.value] || `Jelajahi koleksi ${categoryTitle.value} terbaik kami.`;
});

const fetchProducts = async (isLoadMore = false) => {
  try {
    if (isLoadMore) {
      isLoadingMore.value = true;
    } else {
      isLoading.value = true;
      currentPage.value = 1;
    }
    
    hasError.value = false;
    const params: any = { page: currentPage.value };
    if (categorySlug.value) {
      params.category = categorySlug.value;
    }
    if (selectedBrand.value) {
      params.brand = selectedBrand.value;
    }
    
    const response = await productRepository.getProducts(params);
    
    if (isLoadMore) {
      products.value = [...products.value, ...(response.data || response)];
    } else {
      products.value = response.data || response;
    }
    
    if (response.last_page) {
      lastPage.value = response.last_page;
      totalProducts.value = response.total || products.value.length;
    } else {
      lastPage.value = 1;
      totalProducts.value = products.value.length;
    }
  } catch (error) {
    console.error('Failed to fetch products', error);
    hasError.value = true;
    if (!isLoadMore) {
      products.value = [];
    }
  } finally {
    isLoading.value = false;
    isLoadingMore.value = false;
  }
};

const setupObserver = () => {
  if (observer) observer.disconnect();
  
  observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting && !isLoading.value && !isLoadingMore.value && currentPage.value < lastPage.value) {
      // Tambahkan delay sedikit agar infinite scroll terasa ada jeda
      setTimeout(() => {
        if (!isLoadingMore.value) {
          currentPage.value++;
          fetchProducts(true);
        }
      }, 300);
    }
  }, { rootMargin: '100px' });
  
  if (loadMoreTrigger.value) {
    observer.observe(loadMoreTrigger.value);
  }
};

watch(loadMoreTrigger, (newVal) => {
  if (newVal) setupObserver();
});

const fetchBrands = async () => {
  try {
    brands.value = await productRepository.getBrands();
  } catch (e) {
    console.warn('Could not load brands', e);
  }
};

const fetchCategories = async () => {
  try {
    categories.value = await productRepository.getCategories();
  } catch (e) {
    console.warn('Could not load categories', e);
  }
};

onMounted(() => {
  fetchCategories();
  fetchBrands();
  fetchProducts(false);
});

watch(() => route.params.slug, (newSlug) => {
  categorySlug.value = (newSlug as string) || '';
  fetchProducts(false);
});

watch(selectedBrand, () => {
  fetchProducts(false);
});

const goToCategory = (slug: string | null) => {
  showFilterPanel.value = false;
  if (!slug) {
    router.push('/products');
  } else {
    router.push(`/products/category/${slug}`);
  }
};

const goToDetail = (slug: string) => {
  router.push(`/products/${slug}`);
};
</script>

<template>
  <!-- ╔══════════════════════════════════════════╗ -->
  <!-- ║  HERO + GRADIENT BLEED INTO CONTENT     ║ -->
  <!-- ╚══════════════════════════════════════════╝ -->
  <div class="relative w-full" style="margin-bottom: -80px;">
    <!-- Hero Image Panel — taller -->
    <section class="relative w-full overflow-hidden" style="height: 480px;">
      <img
        src="/gambar/hero-bg.jpeg"
        alt="Optik Medio hero"
        class="absolute inset-0 w-full h-full object-cover object-center"
        style="transform: scale(1.04); object-position: center 40%;"
      />
      <!-- Dark overlay -->
      <div class="absolute inset-0" style="background: linear-gradient(160deg, rgba(10,8,5,0.82) 0%, rgba(30,20,10,0.70) 55%, rgba(60,40,10,0.35) 100%);"></div>
      <!-- Bottom gradient fade into page bg -->
      <div class="absolute bottom-0 left-0 right-0" style="height: 180px; background: linear-gradient(to bottom, transparent 0%, #F5F2EE 100%);"></div>
      <!-- Gold accent line -->
      <div class="absolute" style="bottom: 180px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.5), transparent);"></div>

      <!-- Hero Content -->
      <div class="relative z-10 h-full max-w-[1440px] mx-auto px-6 md:px-12 flex flex-col justify-end pb-28">
        <p class="text-xs font-bold uppercase tracking-[0.3em] mb-3" style="color: rgba(193,154,81,0.95);">
          Optik Medio {{ categorySlug ? '· ' + categoryTitle : '' }}
        </p>
        <h1 class="text-5xl md:text-7xl font-black tracking-tight leading-none text-white mb-5" style="font-family: 'Outfit', sans-serif; text-shadow: 0 4px 24px rgba(0,0,0,0.3);">
          {{ categoryTitle }}
        </h1>
        <p class="text-base md:text-lg max-w-xl leading-relaxed" style="color: rgba(255,255,255,0.72);">
          {{ categoryDescription }}
        </p>
      </div>
    </section>
  </div>

  <!-- ╔══════════════════════════════════════════╗ -->
  <!-- ║  CATEGORY FILTER CHIPS + PRODUCT GRID   ║ -->
  <!-- ╚══════════════════════════════════════════╝ -->
  <main class="max-w-[1440px] mx-auto px-6 md:px-12 pt-4 pb-16 w-full flex-grow relative z-10">

    <!-- Category Filter Chips -->
    <div class="flex flex-wrap items-center gap-2.5 mb-8" style="padding-top: 80px;">
      <!-- All categories chip -->
      <button
        @click="goToCategory(null)"
        class="flex items-center gap-2 px-4 py-2 rounded-none text-xs font-black uppercase tracking-wider transition-all hover:shadow-md active:scale-95"
        :style="!categorySlug
          ? 'background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white; box-shadow: 0 4px 14px rgba(26,18,9,0.25);'
          : 'background: rgba(193,154,81,0.08); color: #7a6230; border: 1px solid rgba(193,154,81,0.3);'"
      >
        <span class="material-symbols-outlined text-sm">apps</span>
        Semua
      </button>

      <button
        v-for="cat in categories"
        :key="cat.id"
        @click="goToCategory(cat.slug)"
        class="flex items-center gap-2 px-4 py-2 rounded-none text-xs font-black uppercase tracking-wider transition-all hover:shadow-md active:scale-95"
        :style="categorySlug === cat.slug
          ? 'background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white; box-shadow: 0 4px 14px rgba(26,18,9,0.25);'
          : 'background: rgba(193,154,81,0.08); color: #7a6230; border: 1px solid rgba(193,154,81,0.3);'"
      >
        {{ cat.name }}
        <span
          v-if="cat.products_count !== undefined"
          class="text-[9px] px-1.5 py-0.5 rounded-none"
          :style="categorySlug === cat.slug ? 'background: rgba(255,255,255,0.2); color: rgba(255,255,255,0.8);' : 'background: rgba(193,154,81,0.15); color: #c19a51;'"
        >{{ cat.products_count }}</span>
      </button>
    </div>

    <!-- Filter Bar (Brand & Info) -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6 pb-4 border-b border-stone-200">
      <p class="text-sm font-medium" style="color: #8a7a60;">
        <span v-if="!isLoading && !hasError">
          Menampilkan <strong style="color: #1a1209;">{{ totalProducts }}</strong> produk
        </span>
        <span v-else-if="isLoading">Memuat produk...</span>
      </p>

      <div class="flex items-center gap-3">
        <span class="text-xs font-bold uppercase tracking-widest text-stone-500">Merek:</span>
        <div class="relative">
          <select 
            v-model="selectedBrand" 
            class="appearance-none bg-white border border-stone-300 px-4 py-2 pr-10 rounded-none text-sm font-medium focus:outline-none focus:border-amber-700 cursor-pointer shadow-sm"
            style="color: #1a1209;"
          >
            <option value="">Semua Merek</option>
            <option v-for="brand in brands" :key="brand" :value="brand">{{ brand }}</option>
          </select>
          <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-stone-400 pointer-events-none text-sm">
            expand_more
          </span>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="hasError" class="text-center py-24 rounded-none border border-dashed" style="border-color: rgba(220,38,38,0.25); background: rgba(220,38,38,0.03);">
      <span class="material-symbols-outlined text-5xl mb-4 block" style="color: rgba(220,38,38,0.5);">wifi_off</span>
      <h2 class="text-xl font-bold text-stone-800 mb-2">Gagal memuat produk</h2>
      <p class="text-stone-500 mb-6">Terjadi kesalahan server. Silakan coba lagi.</p>
      <button @click="() => fetchProducts(false)" class="px-6 py-3 rounded-none font-bold text-white text-sm shadow-lg transition-all active:scale-95"
        style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);">
        Coba Lagi
      </button>
    </div>

    <!-- Loading Skeleton -->
    <div v-else-if="isLoading" class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 md:gap-7">
      <div v-for="i in 12" :key="i" class="animate-pulse rounded-none overflow-hidden" style="background: rgba(245,242,238,0.9);">
        <div class="aspect-[4/5]" style="background: linear-gradient(135deg, #e8e2d8, #d4cdc0);"></div>
        <div class="p-5 space-y-3">
          <div class="h-3 rounded-none w-1/3" style="background: #d4cdc0;"></div>
          <div class="h-4 rounded-none w-3/4" style="background: #dcd7ce;"></div>
          <div class="h-3 rounded-none w-1/2" style="background: #d4cdc0;"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="products.length === 0" class="text-center py-32 rounded-none border border-dashed" style="border-color: rgba(193,154,81,0.25); background: rgba(193,154,81,0.04);">
      <span class="material-symbols-outlined text-7xl mb-6 block" style="color: rgba(193,154,81,0.4);">inventory_2</span>
      <h2 class="text-2xl font-bold text-stone-700 mb-3" style="font-family: 'Outfit', sans-serif;">Produk tidak ditemukan</h2>
      <p class="text-stone-500">Coba pilih kategori lain atau kembali lagi nanti.</p>
    </div>

    <!-- Product Grid -->
    <div v-else class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
      <article
        v-for="product in products"
        :key="product.id"
        @click="goToDetail(product.slug)"
        class="group relative flex flex-col cursor-pointer rounded-none overflow-hidden transition-all duration-500 hover:-translate-y-1.5 hover:shadow-2xl"
        style="background: white; box-shadow: 0 2px 12px rgba(0,0,0,0.06);"
      >
        <!-- Image Wrapper -->
        <div class="relative aspect-[4/5] overflow-hidden flex items-center justify-center p-4 md:p-8"
          style="background: linear-gradient(145deg, #f5f2ee, #ede7dc);">

          <img
            :src="resolveImageUrl(product)"
            :alt="product.name"
            class="object-contain w-full h-full transition-transform duration-700 ease-out group-hover:scale-110"
            :class="{ 'opacity-40 grayscale': product.stock <= 0 }"
          />

          <!-- Wishlist Button -->
          <button
            class="absolute top-3 right-3 w-9 h-9 rounded-none flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 shadow-md"
            style="background: rgba(255,255,255,0.95); backdrop-filter: blur(8px);"
            @click.stop
          >
            <span class="material-symbols-outlined text-base" style="color: #c19a51;">favorite</span>
          </button>

          <!-- Best Seller Badge -->
          <div
            v-if="product.is_best_seller"
            class="absolute top-3 left-3 flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white"
            style="background: rgba(26,18,9,0.8); backdrop-filter: blur(4px); border: 1px solid rgba(193,154,81,0.3);"
          >
            <span class="material-symbols-outlined text-[10px]" style="color: #c19a51;">trending_up</span>
            Terlaris
          </div>

          <!-- Out of Stock -->
          <div
            v-if="product.stock <= 0 && !product.is_not_for_sale"
            class="absolute inset-0 flex items-center justify-center"
            style="background: rgba(255,255,255,0.15); backdrop-filter: blur(2px);"
          >
            <span class="text-[10px] md:text-xs font-black uppercase tracking-widest px-4 py-2 rounded"
              style="background: rgba(15,10,5,0.85); color: rgba(255,255,255,0.9);">
              Stok Habis
            </span>
          </div>

          <!-- Info Only Badge -->
          <div
            v-if="product.is_not_for_sale"
            class="absolute top-3 right-3 flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white"
            style="background: rgba(193,154,81,0.9); backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.2);"
          >
            Informasi
          </div>
        </div>

        <!-- Card Body -->
        <div class="p-4 md:p-5 flex flex-col flex-grow">
          <span class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] mb-1" style="color: #8a7a60;">
            {{ product.name }}
          </span>
          <h3
            class="font-bold text-base md:text-lg leading-tight mb-3 transition-colors duration-300 line-clamp-1"
            style="color: #1a1209; font-family: 'Outfit', sans-serif; letter-spacing: -0.01em;"
            :class="{ 'group-hover:text-amber-800': product.stock > 0 }"
          >
            {{ product.brand || 'Optik Medio' }}
          </h3>
          <div class="flex justify-between items-center mt-auto">
            <div v-if="!product.is_not_for_sale">
              <p class="text-sm md:text-base font-black" style="color: #1a1209;">
                Rp {{ product.price.toLocaleString('id-ID') }}
              </p>
            </div>
            <div v-else>
              <p class="text-xs font-bold uppercase tracking-tight" style="color: #c19a51;">
                Katalog Informasi
              </p>
            </div>
            <span v-if="product.stock > 0 && !product.is_not_for_sale" class="flex items-center gap-1 text-[9px] font-bold" style="color: #16a34a;">
              <span class="w-1.5 h-1.5 rounded-none bg-green-500 inline-block"></span>
              Tersedia
            </span>
          </div>
        </div>

        <!-- Hover CTA -->
        <div
          class="overflow-hidden transition-all duration-300 ease-out"
          :class="product.stock > 0 ? 'max-h-0 group-hover:max-h-12' : 'max-h-0'"
        >
          <div class="px-4 md:px-5 pb-4">
            <button
              class="w-full py-2.5 rounded-none text-xs font-black uppercase tracking-wider text-white transition-all active:scale-95"
              style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
            >
              Lihat Detail
            </button>
          </div>
        </div>
      </article>
    </div>

    <!-- Infinite Scroll Trigger -->
    <div ref="loadMoreTrigger" class="w-full h-16 mt-8 mb-8 flex justify-center items-center">
      <div v-if="isLoadingMore" class="flex items-center gap-3 text-stone-500">
        <span class="material-symbols-outlined animate-spin text-2xl" style="color: #c19a51;">sync</span>
        <span class="text-xs font-bold uppercase tracking-widest" style="color: #1a1209;">Memuat lebih banyak...</span>
      </div>
      <div v-else-if="!isLoading && products.length > 0 && currentPage >= lastPage" class="text-stone-400 text-xs font-bold uppercase tracking-widest">
        — Semua produk telah ditampilkan —
      </div>
    </div>
  </main>
</template>
