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
const isLoading = ref(true);
const hasError = ref(false);
const categorySlug = ref(route.params.slug as string);
const showFilterPanel = ref(false);

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

const fetchProducts = async () => {
  try {
    isLoading.value = true;
    hasError.value = false;
    const params: any = {};
    if (categorySlug.value) {
      params.category = categorySlug.value;
    }
    const response = await productRepository.getProducts(params);
    products.value = response.data || response;
  } catch (error) {
    console.error('Failed to fetch products', error);
    hasError.value = true;
    products.value = [];
  } finally {
    isLoading.value = false;
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
  fetchProducts();
});

watch(() => route.params.slug, (newSlug) => {
  categorySlug.value = (newSlug as string) || '';
  fetchProducts();
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
        class="flex items-center gap-2 px-4 py-2 rounded-full text-xs font-black uppercase tracking-wider transition-all hover:shadow-md active:scale-95"
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
        class="flex items-center gap-2 px-4 py-2 rounded-full text-xs font-black uppercase tracking-wider transition-all hover:shadow-md active:scale-95"
        :style="categorySlug === cat.slug
          ? 'background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white; box-shadow: 0 4px 14px rgba(26,18,9,0.25);'
          : 'background: rgba(193,154,81,0.08); color: #7a6230; border: 1px solid rgba(193,154,81,0.3);'"
      >
        {{ cat.name }}
        <span
          v-if="cat.products_count !== undefined"
          class="text-[9px] px-1.5 py-0.5 rounded-full"
          :style="categorySlug === cat.slug ? 'background: rgba(255,255,255,0.2); color: rgba(255,255,255,0.8);' : 'background: rgba(193,154,81,0.15); color: #c19a51;'"
        >{{ cat.products_count }}</span>
      </button>
    </div>

    <!-- Product count info -->
    <div class="flex justify-between items-center mb-6">
      <p class="text-sm font-medium" style="color: #8a7a60;">
        <span v-if="!isLoading && !hasError">
          Menampilkan <strong style="color: #1a1209;">{{ products.length }}</strong> produk
        </span>
        <span v-else-if="isLoading">Memuat produk...</span>
      </p>
    </div>

    <!-- Error State -->
    <div v-if="hasError" class="text-center py-24 rounded-3xl border border-dashed" style="border-color: rgba(220,38,38,0.25); background: rgba(220,38,38,0.03);">
      <span class="material-symbols-outlined text-5xl mb-4 block" style="color: rgba(220,38,38,0.5);">wifi_off</span>
      <h2 class="text-xl font-bold text-stone-800 mb-2">Gagal memuat produk</h2>
      <p class="text-stone-500 mb-6">Terjadi kesalahan server. Silakan coba lagi.</p>
      <button @click="fetchProducts" class="px-6 py-3 rounded-xl font-bold text-white text-sm shadow-lg transition-all active:scale-95"
        style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);">
        Coba Lagi
      </button>
    </div>

    <!-- Loading Skeleton -->
    <div v-else-if="isLoading" class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 md:gap-7">
      <div v-for="i in 12" :key="i" class="animate-pulse rounded-2xl overflow-hidden" style="background: rgba(245,242,238,0.9);">
        <div class="aspect-[4/5]" style="background: linear-gradient(135deg, #e8e2d8, #d4cdc0);"></div>
        <div class="p-5 space-y-3">
          <div class="h-3 rounded-full w-1/3" style="background: #d4cdc0;"></div>
          <div class="h-4 rounded-full w-3/4" style="background: #dcd7ce;"></div>
          <div class="h-3 rounded-full w-1/2" style="background: #d4cdc0;"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="products.length === 0" class="text-center py-32 rounded-3xl border border-dashed" style="border-color: rgba(193,154,81,0.25); background: rgba(193,154,81,0.04);">
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
        class="group relative flex flex-col cursor-pointer rounded-2xl overflow-hidden transition-all duration-500 hover:-translate-y-1.5 hover:shadow-2xl"
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
            class="absolute top-3 right-3 w-9 h-9 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 shadow-md"
            style="background: rgba(255,255,255,0.95); backdrop-filter: blur(8px);"
            @click.stop
          >
            <span class="material-symbols-outlined text-base" style="color: #c19a51;">favorite</span>
          </button>

          <!-- Best Seller Badge -->
          <div
            v-if="product.is_best_seller"
            class="absolute top-3 left-3 flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider text-white"
            style="background: linear-gradient(135deg, #e67e22, #f39c12); box-shadow: 0 4px 15px rgba(230,126,34,0.45);"
          >
            <span class="material-symbols-outlined text-xs">bolt</span>
            Terlaris
          </div>

          <!-- Out of Stock -->
          <div
            v-if="product.stock <= 0"
            class="absolute inset-0 flex items-center justify-center"
            style="background: rgba(255,255,255,0.15); backdrop-filter: blur(2px);"
          >
            <span class="text-[10px] md:text-xs font-black uppercase tracking-widest px-4 py-2 rounded"
              style="background: rgba(15,10,5,0.85); color: rgba(255,255,255,0.9);">
              Stok Habis
            </span>
          </div>
        </div>

        <!-- Card Body -->
        <div class="p-4 md:p-5 flex flex-col flex-grow">
          <span class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] mb-1.5" style="color: #c19a51;">
            {{ product.brand || 'Optik Medio' }}
          </span>
          <h3
            class="font-bold text-sm md:text-base leading-snug mb-3 transition-colors duration-300 line-clamp-2"
            style="color: #1a1209; font-family: 'Outfit', sans-serif;"
            :class="{ 'group-hover:text-amber-800': product.stock > 0 }"
          >
            {{ product.name }}
          </h3>
          <div class="flex justify-between items-center mt-auto">
            <p class="text-sm md:text-base font-black" style="color: #1a1209;">
              Rp {{ product.price.toLocaleString('id-ID') }}
            </p>
            <span v-if="product.stock > 0" class="flex items-center gap-1 text-[9px] font-bold" style="color: #16a34a;">
              <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
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
              class="w-full py-2.5 rounded-xl text-xs font-black uppercase tracking-wider text-white transition-all active:scale-95"
              style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
            >
              Lihat Detail
            </button>
          </div>
        </div>
      </article>
    </div>
  </main>
</template>
