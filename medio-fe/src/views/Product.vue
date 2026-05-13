<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { productRepository, type Category, type ProductFilters } from '../repositories/ProductRepository';
import type { Product } from '../types';
import { resolveImageUrl } from '../core/utils/image';
import { settingRepository, type Testimonial } from '../repositories/SettingRepository';
import { bannerRepository, type BannerItem } from '../repositories/BannerRepository';
import { useWishlistStore } from '../stores/wishlistStore';
import { useCartStore } from '../stores/cartStore';
import { useCompareStore } from '../stores/compareStore';
import { useToast } from '../composables/useToast';

const route = useRoute();
const router = useRouter();
const wishlistStore = useWishlistStore();
const cartStore = useCartStore();
const compareStore = useCompareStore();
const { showToast } = useToast();
const products = ref<Product[]>([]);
const lensShowcaseProducts = ref<Product[]>([]);
const categories = ref<Category[]>([]);
const brands = ref<string[]>([]);
const productFilters = ref<ProductFilters | null>(null);
const isLoading = ref(true);
const isLoadingLensShowcase = ref(false);
const hasError = ref(false);
const categorySlug = ref(route.params.slug as string);
const searchQuery = ref(route.query.search as string || '');
const showFilterPanel = ref(false);

const currentPage = ref(1);
const lastPage = ref(1);
const totalProducts = ref(0);
const isLoadingMore = ref(false);
const selectedBrand = ref<string>(route.query.brand as string || '');
const selectedGender = ref<string>(route.query.gender as string || '');
const selectedFrameShape = ref<string>(route.query.frame_shape as string || '');
const selectedFrameMaterial = ref<string>(route.query.frame_material as string || '');
const selectedFrameColor = ref<string>(route.query.frame_color as string || '');
const selectedFaceSizeFit = ref<string>(route.query.face_size_fit as string || '');
const inStockOnly = ref(route.query.in_stock_only === 'true');
const prescriptionSupported = ref(route.query.prescription_supported === 'true');
const minPrice = ref(route.query.min_price as string || '');
const maxPrice = ref(route.query.max_price as string || '');
const selectedSort = ref(route.query.sort as string || 'latest');
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

const availableBrands = computed(() => productFilters.value?.brands?.length ? productFilters.value.brands : brands.value);
const availableGenders = computed(() => productFilters.value?.genders || []);
const availableFrameShapes = computed(() => productFilters.value?.frame_shapes || []);
const availableFrameMaterials = computed(() => productFilters.value?.frame_materials || []);
const availableFrameColors = computed(() => productFilters.value?.frame_colors || []);
const availableFaceSizeFits = computed(() => productFilters.value?.face_size_fits || []);

const formatFilterLabel = (value: string) => {
  const labels: Record<string, string> = {
    men: 'Pria',
    women: 'Wanita',
    unisex: 'Unisex',
    kids: 'Anak',
    small: 'Small',
    medium: 'Medium',
    large: 'Large',
    price_low: 'Harga Terendah',
    price_high: 'Harga Tertinggi',
    best_seller: 'Terlaris',
    rating: 'Rating',
    popular: 'Populer',
  };

  return labels[value] || value
    .split(/[-_]/)
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
};

const activeFilterChips = computed(() => {
  const chips: Array<{ key: string; label: string; value: string }> = [];

  if (selectedBrand.value) chips.push({ key: 'brand', label: 'Merek', value: selectedBrand.value });
  if (selectedGender.value) chips.push({ key: 'gender', label: 'Gender', value: formatFilterLabel(selectedGender.value) });
  if (selectedFrameShape.value) chips.push({ key: 'frame_shape', label: 'Bentuk', value: formatFilterLabel(selectedFrameShape.value) });
  if (selectedFrameMaterial.value) chips.push({ key: 'frame_material', label: 'Material', value: formatFilterLabel(selectedFrameMaterial.value) });
  if (selectedFrameColor.value) chips.push({ key: 'frame_color', label: 'Warna', value: formatFilterLabel(selectedFrameColor.value) });
  if (selectedFaceSizeFit.value) chips.push({ key: 'face_size_fit', label: 'Fit', value: formatFilterLabel(selectedFaceSizeFit.value) });
  if (inStockOnly.value) chips.push({ key: 'in_stock_only', label: 'Stok', value: 'Tersedia' });
  if (prescriptionSupported.value) chips.push({ key: 'prescription_supported', label: 'Resep', value: 'Didukung' });
  if (hasPromo.value) chips.push({ key: 'promo', label: 'Promo', value: activePromoName.value || 'Aktif' });
  if (selectedSort.value !== 'latest') {
    chips.push({ key: 'sort', label: 'Urutkan', value: formatFilterLabel(selectedSort.value) });
  }
  if (minPrice.value || maxPrice.value) {
    chips.push({
      key: 'price',
      label: 'Harga',
      value: `${minPrice.value || '0'} - ${maxPrice.value || 'maks'}`,
    });
  }

  return chips;
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
    const params: any = {
      page: currentPage.value,
      exclude_not_for_sale: 'true',
      prioritize_glasses: 'true',
    };
    if (categorySlug.value) {
      params.category = categorySlug.value;
    }
    if (selectedBrand.value) {
      params.brand = selectedBrand.value;
    }
    if (selectedGender.value) {
      params.gender = selectedGender.value;
    }
    if (selectedFrameShape.value) {
      params.frame_shape = selectedFrameShape.value;
    }
    if (selectedFrameMaterial.value) {
      params.frame_material = selectedFrameMaterial.value;
    }
    if (selectedFrameColor.value) {
      params.frame_color = selectedFrameColor.value;
    }
    if (selectedFaceSizeFit.value) {
      params.face_size_fit = selectedFaceSizeFit.value;
    }
    if (inStockOnly.value) {
      params.in_stock_only = 'true';
    }
    if (prescriptionSupported.value) {
      params.prescription_supported = 'true';
    }
    if (minPrice.value) {
      params.min_price = minPrice.value;
    }
    if (maxPrice.value) {
      params.max_price = maxPrice.value;
    }
    if (selectedSort.value) {
      params.sort = selectedSort.value;
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

const fetchLensShowcaseProducts = async () => {
  try {
    isLoadingLensShowcase.value = true;
    const response = await productRepository.getProducts({
      category: 'lensa-kacamata',
      only_not_for_sale: 'true',
      per_page: 12,
      sort: 'popular',
    });

    lensShowcaseProducts.value = response.data || response;
  } catch (error) {
    console.warn('Could not load lens showcase products', error);
    lensShowcaseProducts.value = [];
  } finally {
    isLoadingLensShowcase.value = false;
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

const fetchFilterMetadata = async () => {
  try {
    productFilters.value = await productRepository.getFilters();
    brands.value = productFilters.value.brands || [];
  } catch (e) {
    console.warn('Could not load product filters', e);
    fetchBrands();
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
  fetchFilterMetadata();
  fetchLensShowcaseProducts();
  
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

watch([
  selectedBrand,
  selectedGender,
  selectedFrameShape,
  selectedFrameMaterial,
  selectedFrameColor,
  selectedFaceSizeFit,
  inStockOnly,
  prescriptionSupported,
  minPrice,
  maxPrice,
  selectedSort,
], () => {
  fetchProducts(false);
});

const clearFilter = (key: string) => {
  if (key === 'brand') selectedBrand.value = '';
  if (key === 'gender') selectedGender.value = '';
  if (key === 'frame_shape') selectedFrameShape.value = '';
  if (key === 'frame_material') selectedFrameMaterial.value = '';
  if (key === 'frame_color') selectedFrameColor.value = '';
  if (key === 'face_size_fit') selectedFaceSizeFit.value = '';
  if (key === 'in_stock_only') inStockOnly.value = false;
  if (key === 'prescription_supported') prescriptionSupported.value = false;
  if (key === 'price') {
    minPrice.value = '';
    maxPrice.value = '';
  }
  if (key === 'sort') selectedSort.value = 'latest';
  if (key === 'promo') {
    router.push({ query: { ...route.query, has_promo: undefined, promo_id: undefined } });
  }
};

const clearAllFilters = () => {
  selectedBrand.value = '';
  selectedGender.value = '';
  selectedFrameShape.value = '';
  selectedFrameMaterial.value = '';
  selectedFrameColor.value = '';
  selectedFaceSizeFit.value = '';
  inStockOnly.value = false;
  prescriptionSupported.value = false;
  minPrice.value = '';
  maxPrice.value = '';
  selectedSort.value = 'latest';

  if (hasPromo.value || promoId.value) {
    router.push({ query: { ...route.query, has_promo: undefined, promo_id: undefined } });
  }
};

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

const toggleCompare = (product: Product) => {
  const result = compareStore.toggle(product);
  if (result === 'full') {
    showToast('Compare maksimal 4 produk.', 'error');
    return;
  }
  showToast(result === 'added' ? 'Produk ditambahkan ke compare.' : 'Produk dihapus dari compare.', 'success');
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

      <div class="flex flex-wrap items-center gap-3">
        <button
          @click="showFilterPanel = !showFilterPanel"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-none text-xs font-black uppercase tracking-wider border transition-all active:scale-95"
          :style="showFilterPanel || activeFilterChips.length > 0
            ? 'background: #1a1209; color: white; border-color: #1a1209;'
            : 'background: white; color: #1a1209; border-color: #d6cbbb;'"
        >
          <span class="material-symbols-outlined text-sm">tune</span>
          Filter
          <span
            v-if="activeFilterChips.length > 0"
            class="min-w-5 h-5 px-1.5 inline-flex items-center justify-center text-[10px] font-black"
            style="background: #c19a51; color: white;"
          >
            {{ activeFilterChips.length }}
          </span>
        </button>

        <span class="text-xs font-bold uppercase tracking-widest text-stone-500">Merek:</span>
        <div class="relative">
          <select 
            v-model="selectedBrand" 
            class="appearance-none bg-white border border-stone-300 px-4 py-2 pr-10 rounded-none text-sm font-medium focus:outline-none focus:border-amber-700 cursor-pointer shadow-sm"
            style="color: #1a1209;"
          >
            <option value="">Semua Merek</option>
            <option v-for="brand in availableBrands" :key="brand" :value="brand">{{ brand }}</option>
          </select>
          <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-stone-400 pointer-events-none text-sm">
            expand_more
          </span>
        </div>

        <span class="text-xs font-bold uppercase tracking-widest text-stone-500">Urut:</span>
        <div class="relative">
          <select
            v-model="selectedSort"
            class="appearance-none bg-white border border-stone-300 px-4 py-2 pr-10 rounded-none text-sm font-medium focus:outline-none focus:border-amber-700 cursor-pointer shadow-sm"
            style="color: #1a1209;"
          >
            <option value="latest">Terbaru</option>
            <option value="price_low">Harga Terendah</option>
            <option value="price_high">Harga Tertinggi</option>
            <option value="best_seller">Terlaris</option>
            <option value="rating">Rating</option>
            <option value="popular">Populer</option>
          </select>
          <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-stone-400 pointer-events-none text-sm">
            expand_more
          </span>
        </div>
      </div>
    </div>

    <div v-if="activeFilterChips.length > 0" class="flex flex-wrap items-center gap-2 mb-5">
      <button
        v-for="chip in activeFilterChips"
        :key="chip.key"
        @click="clearFilter(chip.key)"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-none text-[11px] font-bold border transition-all hover:border-stone-900"
        style="background: #f8f5ef; color: #1a1209; border-color: #e4d8c8;"
      >
        <span class="uppercase tracking-wider text-stone-500">{{ chip.label }}</span>
        <span>{{ chip.value }}</span>
        <span class="material-symbols-outlined text-sm">close</span>
      </button>
      <button
        @click="clearAllFilters"
        class="px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-stone-500 hover:text-stone-900"
      >
        Reset
      </button>
    </div>

    <div
      v-if="showFilterPanel"
      class="mb-7 border border-stone-200 bg-white shadow-sm"
    >
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4 p-5">
        <label class="block">
          <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Gender</span>
          <select v-model="selectedGender" class="w-full border border-stone-300 bg-white px-3 py-2 text-sm focus:outline-none focus:border-amber-700">
            <option value="">Semua</option>
            <option v-for="item in availableGenders" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Bentuk Frame</span>
          <select v-model="selectedFrameShape" class="w-full border border-stone-300 bg-white px-3 py-2 text-sm focus:outline-none focus:border-amber-700">
            <option value="">Semua</option>
            <option v-for="item in availableFrameShapes" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Material</span>
          <select v-model="selectedFrameMaterial" class="w-full border border-stone-300 bg-white px-3 py-2 text-sm focus:outline-none focus:border-amber-700">
            <option value="">Semua</option>
            <option v-for="item in availableFrameMaterials" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Warna</span>
          <select v-model="selectedFrameColor" class="w-full border border-stone-300 bg-white px-3 py-2 text-sm focus:outline-none focus:border-amber-700">
            <option value="">Semua</option>
            <option v-for="item in availableFrameColors" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Fit Wajah</span>
          <select v-model="selectedFaceSizeFit" class="w-full border border-stone-300 bg-white px-3 py-2 text-sm focus:outline-none focus:border-amber-700">
            <option value="">Semua</option>
            <option v-for="item in availableFaceSizeFits" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
          </select>
        </label>

        <div class="grid grid-cols-2 gap-2">
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Min</span>
            <input v-model="minPrice" inputmode="numeric" placeholder="Rp" class="w-full border border-stone-300 bg-white px-3 py-2 text-sm focus:outline-none focus:border-amber-700" />
          </label>
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Max</span>
            <input v-model="maxPrice" inputmode="numeric" placeholder="Rp" class="w-full border border-stone-300 bg-white px-3 py-2 text-sm focus:outline-none focus:border-amber-700" />
          </label>
        </div>

        <label class="flex items-center gap-2 text-sm font-bold text-stone-700">
          <input v-model="inStockOnly" type="checkbox" class="w-4 h-4 accent-amber-700" />
          Stok tersedia
        </label>

        <label class="flex items-center gap-2 text-sm font-bold text-stone-700">
          <input v-model="prescriptionSupported" type="checkbox" class="w-4 h-4 accent-amber-700" />
          Bisa resep
        </label>

        <div class="flex items-end">
          <button
            @click="clearAllFilters"
            class="w-full px-4 py-2 text-xs font-black uppercase tracking-wider border border-stone-300 text-stone-700 hover:border-stone-900 hover:text-stone-900 transition-all"
          >
            Reset Filter
          </button>
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
            loading="lazy"
            decoding="async"
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

          <button
            class="absolute top-14 right-3 w-9 h-9 rounded-none flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 shadow-md"
            :style="compareStore.isCompared(product.id)
              ? 'background: rgba(26,18,9,0.9); backdrop-filter: blur(8px); opacity: 1; color: white;'
              : 'background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); color: #7a6230;'"
            @click.stop="toggleCompare(product)"
          >
            <span class="material-symbols-outlined text-base">compare_arrows</span>
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

    <div
      v-if="compareStore.count > 0"
      class="fixed left-4 right-4 md:left-1/2 md:-translate-x-1/2 md:w-[720px] bottom-5 z-40 bg-white border border-stone-200 shadow-2xl p-3 flex items-center justify-between gap-3"
    >
      <div class="flex items-center gap-3 min-w-0">
        <span class="material-symbols-outlined text-xl shrink-0" style="color: #c19a51;">compare_arrows</span>
        <div class="min-w-0">
          <p class="text-xs font-black uppercase tracking-widest text-stone-900">{{ compareStore.count }}/4 produk</p>
          <p class="text-[11px] text-stone-500 truncate">{{ compareStore.items.map(item => item.name).join(', ') }}</p>
        </div>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <button @click="compareStore.clear()" class="px-3 py-2 text-xs font-bold text-stone-600 border border-stone-200">
          Reset
        </button>
        <button
          @click="router.push('/compare')"
          :disabled="!compareStore.canCompare"
          class="px-4 py-2 text-xs font-black uppercase tracking-widest text-white disabled:opacity-50"
          style="background: #1a1209;"
        >
          Compare
        </button>
      </div>
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

    <section v-if="lensShowcaseProducts.length > 0 || isLoadingLensShowcase" class="mt-20 mb-12">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-8">
        <div>
          <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-3" style="color: #c19a51;">Pilihan Lensa Resep</p>
          <h2 class="text-3xl md:text-4xl font-black tracking-tight" style="font-family: 'Outfit', sans-serif; color: #1a1209;">Merek Lensa yang Tersedia</h2>
          <p class="text-sm text-stone-500 mt-3 max-w-2xl leading-relaxed">
            Produk berikut bersifat katalog informasi. Pemilihan dan pembelian lensa dilakukan bersama frame melalui konsultasi resep di Optik Medio.
          </p>
        </div>
        <router-link to="/appointment" class="inline-flex items-center gap-2 px-5 py-3 text-xs font-black uppercase tracking-widest text-white transition-all hover:shadow-lg" style="background: #1a1209;">
          Konsultasi Lensa
          <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </router-link>
      </div>

      <div v-if="isLoadingLensShowcase" class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="animate-pulse border border-stone-100 p-5" style="background: #fffdf7;">
          <div class="aspect-[4/3] mb-4" style="background: #e8e2d8;"></div>
          <div class="h-3 w-1/2 mb-3" style="background: #d4cdc0;"></div>
          <div class="h-4 w-3/4" style="background: #dcd7ce;"></div>
        </div>
      </div>

      <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <article
          v-for="lens in lensShowcaseProducts"
          :key="lens.id"
          class="group border border-stone-100 overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-xl"
          style="background: #fffdf7;"
          @click="goToDetail(lens.slug)"
        >
          <div class="aspect-[4/3] p-5 flex items-center justify-center" style="background: linear-gradient(145deg, #f5f2ee, #ede7dc);">
            <img
              :src="resolveImageUrl(lens)"
              :alt="lens.name"
              class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-105"
              loading="lazy"
              decoding="async"
            />
          </div>
          <div class="p-4">
            <p class="text-[9px] font-black uppercase tracking-[0.2em] mb-2" style="color: #8a7a60;">{{ lens.brand || 'Lensa' }}</p>
            <h3 class="font-bold text-sm leading-tight line-clamp-2 min-h-[2.5rem]" style="color: #1a1209;">{{ lens.name }}</h3>
            <div class="mt-4 inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest" style="color: #c19a51;">
              Informasi
              <span class="material-symbols-outlined text-sm">visibility</span>
            </div>
          </div>
        </article>
      </div>
    </section>

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
