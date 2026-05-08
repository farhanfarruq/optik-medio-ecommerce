<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { productRepository, type Category } from '../repositories/ProductRepository';
import type { Product } from '../types';
import { resolveImageUrl } from '../core/utils/image';
import { settingRepository, type Testimonial } from '../repositories/SettingRepository';
import { bannerRepository, type BannerItem } from '../repositories/BannerRepository';
import { useWishlistStore } from '../stores/wishlistStore';
import { useCartStore } from '../stores/cartStore';
import { useToast } from '../composables/useToast';

const route = useRoute();
const router = useRouter();
const wishlistStore = useWishlistStore();
const cartStore = useCartStore();
const { showToast } = useToast();
const products = ref<Product[]>([]);
const categories = ref<Category[]>([]);
const brands = ref<string[]>([]);
const isLoading = ref(true);
const hasError = ref(false);
const categorySlug = ref(route.params.slug as string);
const searchQuery = ref(route.query.search as string || '');
const showFilterPanel = ref(false);

const currentPage = ref(1);
const lastPage = ref(1);
const totalProducts = ref(0);
const isLoadingMore = ref(false);
const selectedBrand = ref<string>('');
const hasPromo = ref(route.query.has_promo === 'true');
const promoId = ref(route.query.promo_id as string || '');
const activePromoName = ref('');
const testimonials = ref<Testimonial[]>([]);
const banners = ref<BannerItem[]>([]);
const currentBannerIndex = ref(0);
let bannerTimer: ReturnType<typeof setInterval> | null = null;

const categoryTitle = computed(() => {
  if (searchQuery.value) return `Hasil Pencarian: "${searchQuery.value}"`;
  if (hasPromo.value) return 'Produk Promo';
  if (!categorySlug.value) return 'Koleksi Kami';
  const found = categories.value.find(c => c.slug === categorySlug.value);
  if (found) return found.name;
  return categorySlug.value
    .split('-')
    .map((w: string) => w.charAt(0).toUpperCase() + w.slice(1))
    .join(' ');
});

const categoryDescription = computed(() => {
  if (searchQuery.value) return `Menampilkan produk yang cocok dengan pencarian Anda.`;
  if (hasPromo.value) return 'Temukan semua produk dengan penawaran dan promo terbaik yang sedang berlaku.';
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
    if (searchQuery.value) {
      params.search = searchQuery.value;
    }
    if (hasPromo.value) {
      params.has_promo = 'true';
    }
    if (promoId.value) {
      params.promo_id = promoId.value;
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

const handleLoadMore = () => {
  if (!isLoadingMore.value && currentPage.value < lastPage.value) {
    currentPage.value++;
    fetchProducts(true);
  }
};

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
  
  if (cartStore.activePromos.length === 0) {
    cartStore.fetchPromos();
  }
  
  // Fetch testimonials from settings
  bannerRepository.getBanners().then(data => { banners.value = data; if (data.length > 1) { bannerTimer = setInterval(() => { currentBannerIndex.value = (currentBannerIndex.value + 1) % banners.value.length; }, 4000); } });
  settingRepository.getSettings().then(data => {
    if (data.store_testimonials) {
      testimonials.value = data.store_testimonials;
    }
  });
});

watch(() => route.params.slug, (newSlug) => {
  categorySlug.value = (newSlug as string) || '';
  fetchProducts(false);
});

const getProductPromos = (product: Product) => {
  const buyPromos = [...(product.buy_promos || []), ...(product.buy_promos_many || [])];
  const discountPromos = [...(product.discount_promos || []), ...(product.discount_promos_many || [])];

  // Add brand-based promos from store
  if (product.brand && cartStore.activePromos.length > 0) {
    cartStore.activePromos.forEach(promo => {
      // Check if already in list to avoid duplicates
      const isDuplicate = [...buyPromos, ...discountPromos].some(p => p.id === promo.id);
      if (isDuplicate) return;

      if (promo.type === 'buy_x_get_y' && promo.buy_brands?.includes(product.brand)) {
        buyPromos.push(promo);
      } else if (promo.type === 'product_discount' && promo.discount_brands?.includes(product.brand)) {
        discountPromos.push(promo);
      }
    });
  }

  return { buyPromos, discountPromos };
};

watch(() => route.query.search, (newSearch) => {
  searchQuery.value = (newSearch as string) || '';
  fetchProducts(false);
}, { immediate: true });

watch(() => route.query.has_promo, (val) => {
  hasPromo.value = val === 'true';
  fetchProducts(false);
});

watch(() => route.query.promo_id, async (val) => {
  promoId.value = (val as string) || '';
  if (promoId.value) {
    try {
      await productRepository.getProducts({ promo_id: promoId.value, per_page: 1 });
      // Usually the backend response for a specific promo_id filter might not give the promo object directly in the current getAll setup
      // So we might need to fetch promos separately or just show "Promo Aktif"
      // For now, let's keep it simple since we already have the ID
      activePromoName.value = 'Promo Spesial';
    } catch (e) {}
  } else {
    activePromoName.value = '';
  }
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

const togglePromoFilter = () => {
  if (hasPromo.value) {
    router.push({ query: { ...route.query, has_promo: undefined } });
  } else {
    router.push({ query: { ...route.query, has_promo: 'true', promo_id: undefined } });
  }
};


const toggleWishlist = async (product: Product) => {
  const added = await wishlistStore.toggleWishlist(product);
  showToast(
    added ? 'Produk ditambahkan ke wishlist.' : 'Produk dihapus dari wishlist.',
    'success',
  );
};

onUnmounted(() => {
  if (bannerTimer) clearInterval(bannerTimer);
});
</script>

<template>
  <div class="relative w-full" style="margin-bottom: -80px;">
    <section class="relative w-full overflow-hidden" style="height: 520px;">
      <img
        src="/gambar/hero-bg.jpeg"
        alt="Optik Medio hero"
        class="absolute inset-0 w-full h-full object-cover object-center"
        style="transform: scale(1.04); object-position: center 40%;"
      />
      <div class="absolute inset-0" style="background: linear-gradient(160deg, rgba(10,8,5,0.45) 0%, rgba(30,20,10,0.25) 60%, transparent 100%);"></div>
      <div class="absolute bottom-0 left-0 right-0" style="height: 180px; background: linear-gradient(to bottom, transparent 0%, #F5F2EE 100%);"></div>
      <div class="absolute" style="bottom: 180px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.5), transparent);"></div>

      <div class="relative z-10 h-full max-w-[1440px] mx-auto px-6 md:px-12 flex flex-col justify-end pb-20 pt-32">
        <p v-if="categorySlug || searchQuery" class="text-xs font-bold uppercase tracking-[0.3em] mb-3" style="color: rgba(193,154,81,0.95);">
          {{ searchQuery ? 'Pencarian' : categoryTitle }}
        </p>
        <h1 class="text-4xl md:text-6xl font-black tracking-tight leading-tight text-white mb-4" style="font-family: 'Outfit', sans-serif; text-shadow: 0 4px 24px rgba(0,0,0,0.3);">
          {{ categoryTitle }}
        </h1>
        <p class="text-sm md:text-base max-w-xl leading-relaxed" style="color: rgba(255,255,255,0.72);">
          {{ categoryDescription }}
        </p>
      </div>
    </section>
  </div>

  <main class="max-w-[1440px] mx-auto px-6 md:px-12 pt-4 pb-16 w-full flex-grow relative z-10">

    <!-- Banner Carousel Dinamis -->
    <div v-if="banners.length > 0" class="relative overflow-hidden mb-8 w-full" style="border-radius: 0; margin-top: 85px;">
      <div class="relative w-full aspect-[4/5] sm:aspect-[16/9] md:aspect-[21/9] lg:aspect-[21/7] max-h-[600px] overflow-hidden bg-stone-900 shadow-xl">
        <transition-group name="banner-fade" tag="div" class="relative h-full w-full">
            <div
              v-for="(banner, idx) in banners"
              :key="banner.id"
              v-show="idx === currentBannerIndex"
              class="absolute inset-0 flex items-center overflow-hidden"
            >
              <img v-if="banner.image_path" :src="resolveImageUrl(banner.image_path)" class="absolute inset-0 w-full h-full object-cover" />
              <!-- Subtle gradient for text readability -->
              <div class="absolute inset-0" style="background: linear-gradient(90deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0.2) 40%, transparent 100%);"></div>
            <div class="relative z-10 px-8 md:px-16 py-8">
              <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-2" style="color: #c19a51;">Penawaran Spesial</p>
              <h3 class="text-2xl md:text-3xl font-black text-white mb-2" style="font-family: Outfit, sans-serif;">{{ banner.title }}</h3>
              <p v-if="banner.subtitle" class="text-sm text-stone-300 mb-4">{{ banner.subtitle }}</p>
              <a
                v-if="banner.cta_label"
                :href="banner.external_url || (banner.product ? `/products/${banner.product.slug}` : banner.category ? `/products/category/${banner.category.slug}` : '#')"
                class="inline-flex items-center gap-2 px-6 py-2 text-xs font-black uppercase tracking-wider text-white border border-white/30 hover:bg-white/10 transition-all"
              >{{ banner.cta_label }}</a>
            </div>
          </div>
        </transition-group>
      </div>
      <!-- Dots navigation -->
      <div v-if="banners.length > 1" class="absolute bottom-3 right-4 flex gap-2">
        <button v-for="(_, idx) in banners" :key="idx" @click="currentBannerIndex = idx"
          class="w-2 h-2 rounded-full transition-all"
          :style="idx === currentBannerIndex ? 'background: #c19a51; width: 20px;' : 'background: rgba(255,255,255,0.4);'"
        ></button>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2.5 mb-8" style="padding-top: 80px;">
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
        @click="togglePromoFilter"
        class="flex items-center gap-2 px-4 py-2 rounded-none text-xs font-black uppercase tracking-wider transition-all hover:shadow-md active:scale-95"
        :style="hasPromo
          ? 'background: linear-gradient(135deg, #ef4444, #991b1b); color: white; box-shadow: 0 4px 14px rgba(239,68,68,0.25);'
          : 'background: rgba(193,154,81,0.08); color: #ef4444; border: 1px solid rgba(239,68,68,0.3);'"
      >
        <span class="material-symbols-outlined text-sm">sell</span>
        Promo %
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

    <div v-if="hasError" class="text-center py-24 rounded-none border border-dashed" style="border-color: rgba(220,38,38,0.25); background: rgba(220,38,38,0.03);">
      <span class="material-symbols-outlined text-5xl mb-4 block" style="color: rgba(220,38,38,0.5);">wifi_off</span>
      <h2 class="text-xl font-bold text-stone-800 mb-2">Gagal memuat produk</h2>
      <p class="text-stone-500 mb-6">Terjadi kesalahan server. Silakan coba lagi.</p>
      <button @click="() => fetchProducts(false)" class="px-6 py-3 rounded-none font-bold text-white text-sm shadow-lg transition-all active:scale-95"
        style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);">
        Coba Lagi
      </button>
    </div>

    <div v-else-if="isLoading" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-7">
      <div v-for="i in 12" :key="i" class="animate-pulse rounded-none overflow-hidden" style="background: rgba(245,242,238,0.9);">
        <div class="aspect-[4/5]" style="background: linear-gradient(135deg, #e8e2d8, #d4cdc0);"></div>
        <div class="p-5 space-y-3">
          <div class="h-3 rounded-none w-1/3" style="background: #d4cdc0;"></div>
          <div class="h-4 rounded-none w-3/4" style="background: #dcd7ce;"></div>
          <div class="h-3 rounded-none w-1/2" style="background: #d4cdc0;"></div>
        </div>
      </div>
    </div>

    <div v-else-if="products.length === 0" class="text-center py-32 rounded-none border border-dashed" style="border-color: rgba(193,154,81,0.25); background: rgba(193,154,81,0.04);">
      <span class="material-symbols-outlined text-7xl mb-6 block" style="color: rgba(193,154,81,0.4);">inventory_2</span>
      <h2 class="text-2xl font-bold text-stone-700 mb-3" style="font-family: 'Outfit', sans-serif;">Produk tidak ditemukan</h2>
      <p class="text-stone-500">Coba pilih kategori lain atau kembali lagi nanti.</p>
    </div>

    <div v-else class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
      <article
        v-for="product in products"
        :key="product.id"
        @click="goToDetail(product.slug)"
        class="group relative flex flex-col cursor-pointer rounded-none overflow-hidden transition-all duration-500 hover:-translate-y-1.5 hover:shadow-2xl"
        style="background: white; box-shadow: 0 2px 12px rgba(0,0,0,0.06);"
      >
        <div class="relative aspect-[4/5] overflow-hidden flex items-center justify-center p-3 md:p-8"
          style="background: linear-gradient(145deg, #f5f2ee, #ede7dc);">

          <img
            :src="resolveImageUrl(product)"
            :alt="product.name"
            class="object-contain w-full h-full transition-transform duration-700 ease-out group-hover:scale-110"
            :class="{ 'opacity-40 grayscale': product.stock <= 0 }"
          />

          <button
            class="absolute top-3 right-3 w-9 h-9 rounded-none flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 shadow-md"
            :style="wishlistStore.isWishlisted(product.id)
              ? 'background: rgba(193,154,81,0.18); backdrop-filter: blur(8px); opacity: 1;'
              : 'background: rgba(255,255,255,0.95); backdrop-filter: blur(8px);'"
            @click.stop="toggleWishlist(product)"
          >
            <span class="material-symbols-outlined text-base" :style="wishlistStore.isWishlisted(product.id) ? 'color: #b45309;' : 'color: #c19a51;'">favorite</span>
          </button>

          <!-- Best Seller Badge -->
          <div
            v-if="product.is_best_seller"
            class="absolute top-3 left-3 flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white shadow-sm"
            style="background: rgba(26,18,9,0.8); backdrop-filter: blur(4px); border: 1px solid rgba(193,154,81,0.3);"
          >
            <span class="material-symbols-outlined text-[10px]" style="color: #c19a51;">trending_up</span>
            Terlaris
          </div>

          <!-- Promo Badge (Buy X Get Y) -->
          <div
            v-if="getProductPromos(product).buyPromos.length > 0"
            class="absolute top-[44px] left-3 flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white shadow-md"
            style="background: #c19a51; border: 1px solid rgba(255,255,255,0.2);"
          >
            <span class="material-symbols-outlined text-[10px]">redeem</span>
            {{ 
              getProductPromos(product).buyPromos[0]
                ? `Beli ${getProductPromos(product).buyPromos[0].buy_quantity} Gratis ${getProductPromos(product).buyPromos[0].get_quantity}` 
                : 'Promo Spesial' 
            }}
          </div>

          <!-- Promo Badge (Product Discount) -->
          <div
            v-if="getProductPromos(product).discountPromos.length > 0"
            class="absolute top-[44px] left-3 flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white shadow-md"
            style="background: #ef4444; border: 1px solid rgba(255,255,255,0.2);"
            :style="getProductPromos(product).buyPromos.length > 0 ? 'top: 74px;' : ''"
          >
            <span class="material-symbols-outlined text-[10px]">percent</span>
            {{ 
              getProductPromos(product).discountPromos[0]
                ? `Diskon ${getProductPromos(product).discountPromos[0].discount_type === 'percentage' ? Math.round(Number(getProductPromos(product).discountPromos[0].discount_value)) + '%' : 'Rp ' + Number(getProductPromos(product).discountPromos[0].discount_value).toLocaleString('id-ID')}` 
                : 'Diskon Spesial' 
            }}
          </div>

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

          <div
            v-if="product.is_not_for_sale"
            class="absolute top-3 right-3 flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white"
            style="background: rgba(193,154,81,0.9); backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.2);"
          >
            Informasi
          </div>
        </div>

        <div class="p-3 md:p-5 flex flex-col flex-grow min-w-0">
          <span class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] mb-1" style="color: #8a7a60;">
            {{ product.name }}
          </span>
          <h3
            class="font-bold text-sm md:text-lg leading-tight mb-2 md:mb-3 transition-colors duration-300 line-clamp-2 min-h-[2.5rem] md:min-h-[3.5rem]"
            style="color: #1a1209; font-family: 'Outfit', sans-serif; letter-spacing: -0.01em;"
            :class="{ 'group-hover:text-amber-800': product.stock > 0 }"
          >
            {{ product.brand || 'Optik Medio' }}
          </h3>
          <div class="grid grid-cols-1 gap-1.5 mb-2 md:mb-3 text-[10px] md:text-[11px]" style="color: #8a7a60;">
            <span class="flex items-center gap-1.5 min-w-0">
              <span class="material-symbols-outlined text-sm" style="color: #c19a51;">star</span>
              {{ Number(product.avg_rating || 0).toFixed(1) }} · {{ product.review_count || 0 }} ulasan
            </span>
            <span class="flex items-center gap-1.5 min-w-0">
              <span class="material-symbols-outlined text-sm" style="color: #c19a51;">shopping_bag</span>
              {{ Number(product.purchase_count || 0) }} terjual
            </span>
          </div>
          <div class="flex items-start justify-between gap-3 mt-auto">
            <div v-if="!product.is_not_for_sale">
              <p class="text-xs md:text-base font-black" style="color: #1a1209;">
                Rp {{ product.price.toLocaleString('id-ID') }}
              </p>
            </div>
            <div v-else>
              <p class="text-[10px] md:text-xs font-bold uppercase tracking-tight" style="color: #c19a51;">
                Katalog Informasi
              </p>
            </div>
            <span v-if="product.stock > 0 && !product.is_not_for_sale" class="shrink-0 flex items-center gap-1 text-[9px] font-bold text-right" style="color: #16a34a;">
              <span class="w-1.5 h-1.5 rounded-none bg-green-500 inline-block"></span>
              Tersedia
            </span>
          </div>
        </div>

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

    <div class="w-full mt-12 mb-8 flex flex-col items-center gap-6">
      <div v-if="isLoadingMore" class="flex items-center gap-3 text-stone-500">
        <span class="material-symbols-outlined animate-spin text-2xl" style="color: #c19a51;">sync</span>
        <span class="text-xs font-bold uppercase tracking-widest" style="color: #1a1209;">Memuat lebih banyak...</span>
      </div>
      
      <div v-else-if="currentPage < lastPage" class="w-full flex justify-center">
        <button 
          @click="handleLoadMore"
          class="group relative px-10 py-4 overflow-hidden transition-all duration-300 hover:shadow-[0_8px_30px_rgb(193,154,81,0.2)] active:scale-95"
          style="background: #1a1209;"
        >
          <div class="absolute inset-0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000" style="background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);"></div>
          
          <div class="flex items-center gap-3 relative z-10">
            <span class="text-xs font-black uppercase tracking-[0.3em] text-white">Tampilkan Lebih Banyak</span>
            <span class="material-symbols-outlined text-sm text-amber-500 group-hover:translate-y-1 transition-transform">expand_more</span>
          </div>
        </button>
      </div>

      <div v-else-if="!isLoading && products.length > 0" class="flex flex-col items-center gap-2">
        <div class="w-12 h-[1px] bg-stone-200"></div>
        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-stone-400">
          Semua {{ totalProducts }} produk telah ditampilkan
        </span>
      </div>
    </div>

    <section class="mt-24 mb-12">
      <div class="flex flex-col md:flex-row justify-between items-end mb-10 gap-4">
        <div class="text-left">
          <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-3" style="color: #c19a51;">Wawasan & Tips</p>
          <h2 class="text-3xl md:text-4xl font-black tracking-tight" style="font-family: 'Outfit', sans-serif; color: #1a1209;">Blog & Edukasi</h2>
          <div class="w-12 h-1 bg-amber-600 mt-4"></div>
        </div>
        <router-link to="/blog" class="text-xs font-black uppercase tracking-widest text-amber-700 hover:text-amber-800 transition-all flex items-center gap-2 group">
          Lihat Semua Artikel
          <span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">arrow_forward</span>
        </router-link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="group cursor-pointer" @click="router.push('/blog/cara-memilih-frame-sesuai-bentuk-wajah')">
          <div class="aspect-video overflow-hidden mb-5 relative">
            <img src="/blog_feature_1_face_shape_1777451535680.png" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
            <div class="absolute inset-0 bg-stone-900/20 group-hover:bg-transparent transition-all"></div>
          </div>
          <h3 class="font-bold text-lg mb-2 group-hover:text-amber-700 transition-colors" style="font-family: 'Outfit', sans-serif;">Cara Memilih Frame Sesuai Bentuk Wajah</h3>
          <p class="text-sm text-stone-500 leading-relaxed line-clamp-2">Temukan panduan lengkap untuk mendapatkan kacamata yang paling pas dan menunjang penampilan Anda.</p>
        </div>
        <div class="group cursor-pointer" @click="router.push('/blog/pentingnya-perlindungan-lensa-blueray')">
          <div class="aspect-video overflow-hidden mb-5 relative">
            <img src="/blog_feature_2_blueray_lens_1777451550672.png" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
            <div class="absolute inset-0 bg-stone-900/20 group-hover:bg-transparent transition-all"></div>
          </div>
          <h3 class="font-bold text-lg mb-2 group-hover:text-amber-700 transition-colors" style="font-family: 'Outfit', sans-serif;">Pentingnya Perlindungan Lensa Blueray</h3>
          <p class="text-sm text-stone-500 leading-relaxed line-clamp-2">Lindungi mata Anda dari radiasi layar digital dengan teknologi lensa terkini dari Optik Medio.</p>
        </div>
        <div class="group cursor-pointer" @click="router.push('/blog/update-tren-kacamata-2026')">
          <div class="aspect-video overflow-hidden mb-5 relative">
            <img src="/blog_feature_3_trends_2026_1777451566973.png" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
            <div class="absolute inset-0 bg-stone-900/20 group-hover:bg-transparent transition-all"></div>
          </div>
          <h3 class="font-bold text-lg mb-2 group-hover:text-amber-700 transition-colors" style="font-family: 'Outfit', sans-serif;">Update Tren Kacamata 2026</h3>
          <p class="text-sm text-stone-500 leading-relaxed line-clamp-2">Jelajahi gaya terbaru yang akan mendominasi tahun ini, mulai dari gaya retro hingga futuristik.</p>
        </div>
      </div>
    </section>

    <section v-if="testimonials.length > 0" class="mt-24 mb-16 px-6 md:px-0">
      <div class="text-center mb-12">
        <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-3" style="color: #c19a51;">Apa Kata Pelanggan Kami</p>
        <h2 class="text-3xl md:text-4xl font-black tracking-tight" style="font-family: 'Outfit', sans-serif; color: #1a1209;">Review Google Maps</h2>
        <div class="w-12 h-1 bg-amber-600 mx-auto mt-4"></div>
      </div>

      <div class="flex overflow-x-auto md:grid md:grid-cols-2 gap-6 md:gap-8 max-w-4xl mx-auto pb-4 md:pb-0 snap-x snap-mandatory scrollbar-hide">
        <div 
          v-for="(t, idx) in testimonials" 
          :key="idx"
          class="min-w-[85vw] md:min-w-0 p-8 relative group transition-all duration-500 hover:shadow-xl border border-stone-100 snap-center"
          style="background: white;"
        >
          <div class="absolute -top-4 -left-4 w-12 h-12 flex items-center justify-center bg-stone-900 text-amber-500">
            <span class="material-symbols-outlined">format_quote</span>
          </div>
          
          <div class="flex gap-1 mb-4">
            <span v-for="star in t.rating" :key="star" class="material-symbols-outlined text-sm" style="color: #fbbf24;">star</span>
          </div>
          
          <p class="text-stone-600 italic leading-relaxed mb-6">"{{ t.review }}"</p>
          
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center font-black text-xs" style="background: #f5f2ee; color: #c19a51;">
              {{ t.name.charAt(0) }}
            </div>
            <div>
              <h4 class="font-bold text-sm text-stone-900">{{ t.name }}</h4>
              <p class="text-[10px] uppercase tracking-widest text-stone-400">Google Reviewer</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
}
</style>
