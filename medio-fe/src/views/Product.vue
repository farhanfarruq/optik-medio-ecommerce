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

// State untuk toggle kategori tersembunyi
const showAllCategories = ref(false);
const isMobileView = ref(window.innerWidth < 768);

// Update isMobileView saat resize
if (typeof window !== 'undefined') {
  window.addEventListener('resize', () => {
    isMobileView.value = window.innerWidth < 768;
  });
}
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
const activeBanner = computed(() => banners.value[currentBannerIndex.value] || null);
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
      <div class="absolute bottom-0 left-0 right-0" style="height: 180px; background: linear-gradient(to bottom, transparent 0%, var(--ivory) 100%);"></div>
      <div class="absolute" style="bottom: 180px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(184,138,68,0.5), transparent);"></div>

      <div class="relative z-10 h-full container-premium flex flex-col justify-end pb-20 pt-32">
        <p v-if="categorySlug || searchQuery" class="text-xs font-bold uppercase tracking-[0.3em] mb-3" style="color: rgba(184,138,68,0.95);">
          {{ searchQuery ? 'Pencarian' : categoryTitle }}
        </p>
        <h1 class="text-4xl md:text-6xl font-black tracking-normal leading-tight text-white mb-4" style="font-family: 'Cormorant Garamond', serif; text-shadow: 0 4px 24px rgba(0,0,0,0.3);">
          {{ categoryTitle }}
        </h1>
        <p class="text-sm md:text-base max-w-xl leading-relaxed" style="color: rgba(255,255,255,0.72);">
          {{ categoryDescription }}
        </p>
      </div>
    </section>
  </div>

  <main class="container-premium pt-4 pb-16 w-full flex-grow relative z-10">

    <!-- Banner Carousel Dinamis -->
    <div v-if="activeBanner" class="relative mb-8 w-full overflow-hidden" style="border-radius: 0; margin-top: 85px;">
      <div class="relative w-full overflow-hidden bg-graphite shadow-soft">
        <img
          v-if="activeBanner.image_path"
          :src="resolveImageUrl(activeBanner.image_path)"
          :alt="activeBanner.title || 'Banner Optik Medio'"
          class="block w-full h-auto object-contain"
        />
        <div v-else class="aspect-[16/7] w-full bg-graphite"></div>
        <!-- Subtle gradient for text readability without cropping the image -->
        <div class="absolute inset-0 pointer-events-none" style="background: linear-gradient(90deg, rgba(0,0,0,0.58) 0%, rgba(0,0,0,0.22) 42%, rgba(0,0,0,0.04) 100%);"></div>
        <div class="absolute inset-y-0 left-0 z-10 flex max-w-2xl items-center px-8 py-8 md:px-16">
          <div>
            <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-2" style="color: var(--gold);">Penawaran Spesial</p>
            <h3 class="text-2xl md:text-3xl font-black text-white mb-2" style="font-family: Outfit, sans-serif;">{{ activeBanner.title }}</h3>
            <p v-if="activeBanner.subtitle" class="text-sm text-white mb-4">{{ activeBanner.subtitle }}</p>
            <a
              v-if="activeBanner.cta_label"
              :href="activeBanner.external_url || (activeBanner.product ? `/products/${activeBanner.product.slug}` : activeBanner.category ? `/products/category/${activeBanner.category.slug}` : '#')"
              class="inline-flex items-center gap-2 px-6 py-2 text-xs font-black uppercase tracking-wider text-white border border-white/30 hover:bg-porcelain/10 transition-all"
            >{{ activeBanner.cta_label }}</a>
          </div>
        </div>
      </div>
      <!-- Dots navigation -->
      <div v-if="banners.length > 1" class="absolute bottom-3 right-4 z-20 flex gap-2">
        <button v-for="(_, idx) in banners" :key="idx" @click="currentBannerIndex = idx"
          class="w-2 h-2 rounded-full transition-all"
          :style="idx === currentBannerIndex ? 'background: var(--gold); width: 20px;' : 'background: rgba(255,255,255,0.5);'"
        ></button>
      </div>
    </div>

    <div class="mb-8" style="padding-top: 80px;">
      <!-- Baris utama: Semua + Promo + kategori yang terlihat -->
      <div class="flex flex-wrap items-center gap-2.5">
        <button
          @click="goToCategory(null)"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-black uppercase tracking-wider transition-all hover:shadow-card active:scale-95"
          :style="!categorySlug
            ? 'background: linear-gradient(135deg, var(--ink), #3d2c0e); color: white; box-shadow: 0 4px 14px rgba(26,18,9,0.25);'
            : 'background: rgba(184,138,68,0.08); color: #6F4E1D; border: 1px solid rgba(184,138,68,0.3);'"
        >
          <span class="material-symbols-outlined text-sm">apps</span>
          Semua
        </button>

        <button
          @click="togglePromoFilter"
          class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-black uppercase tracking-wider transition-all hover:shadow-card active:scale-95"
          :style="hasPromo
            ? 'background: linear-gradient(135deg, #ef4444, #991b1b); color: white; box-shadow: 0 4px 14px rgba(239,68,68,0.25);'
            : 'background: rgba(184,138,68,0.08); color: #ef4444; border: 1px solid rgba(239,68,68,0.3);'"
        >
          <span class="material-symbols-outlined text-sm">sell</span>
          Promo %
        </button>

        <!-- Kategori yang selalu terlihat (aktif atau 4 pertama di desktop, 2 di mobile) -->
        <template v-for="(cat, idx) in categories" :key="cat.id">
          <button
            v-if="categorySlug === cat.slug || (showAllCategories ? true : idx < (isMobileView ? 2 : 4))"
            @click="goToCategory(cat.slug)"
            class="flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-black uppercase tracking-wider transition-all hover:shadow-card active:scale-95"
            :style="categorySlug === cat.slug
              ? 'background: linear-gradient(135deg, var(--ink), #3d2c0e); color: white; box-shadow: 0 4px 14px rgba(26,18,9,0.25);'
              : 'background: rgba(184,138,68,0.08); color: #6F4E1D; border: 1px solid rgba(184,138,68,0.3);'"
          >
            {{ cat.name }}
            <span
              v-if="cat.products_count !== undefined"
              class="text-[9px] px-1.5 py-0.5 rounded-lg"
              :style="categorySlug === cat.slug ? 'background: rgba(255,255,255,0.2); color: rgba(255,255,255,0.8);' : 'background: rgba(184,138,68,0.15); color: var(--gold);'"
            >{{ cat.products_count }}</span>
          </button>
        </template>

        <!-- Tombol toggle semua kategori -->
        <button
          v-if="categories.length > (isMobileView ? 2 : 4)"
          @click="showAllCategories = !showAllCategories"
          class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-black uppercase tracking-wider transition-all hover:shadow-card active:scale-95"
          style="background: rgba(184,138,68,0.08); color: #6F4E1D; border: 1px solid rgba(184,138,68,0.3);"
        >
          <span class="material-symbols-outlined text-sm transition-transform" :style="showAllCategories ? 'transform: rotate(180deg)' : ''">expand_more</span>
          {{ showAllCategories ? 'Sembunyikan' : `+${categories.length - (isMobileView ? 2 : 4)} Kategori` }}
        </button>
      </div>
    </div>

    <div class="-mx-4 mb-6 flex flex-col gap-4 border-y border-mist bg-ivory px-4 py-4 md:mx-0 md:flex-row md:items-center md:justify-between md:rounded-lg md:border">
      <p class="text-sm font-medium" style="color: var(--taupe);">
        <span v-if="!isLoading && !hasError">
          Menampilkan <strong style="color: var(--ink);">{{ totalProducts }}</strong> produk
        </span>
        <span v-else-if="isLoading">Memuat produk...</span>
      </p>

      <div class="flex flex-wrap items-center gap-3">
        <button
          @click="showFilterPanel = !showFilterPanel"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-black uppercase tracking-wider border transition-all active:scale-95"
          :style="showFilterPanel || activeFilterChips.length > 0
            ? 'background: var(--ink); color: white; border-color: var(--ink);'
            : 'background: white; color: var(--ink); border-color: #d6cbbb;'"
        >
          <span class="material-symbols-outlined text-sm">tune</span>
          Filter
          <span
            v-if="activeFilterChips.length > 0"
            class="min-w-5 h-5 px-1.5 inline-flex items-center justify-center text-[10px] font-black"
            style="background: var(--gold); color: white;"
          >
            {{ activeFilterChips.length }}
          </span>
        </button>

        <span class="text-xs font-bold uppercase tracking-widest text-graphite/65">Merek:</span>
        <div class="relative">
          <select 
            v-model="selectedBrand" 
            class="appearance-none bg-porcelain border border-mist px-4 py-2 pr-10 rounded-lg text-sm font-medium focus:outline-none focus:border-gold cursor-pointer shadow-sm"
            style="color: var(--ink);"
          >
            <option value="">Semua Merek</option>
            <option v-for="brand in availableBrands" :key="brand" :value="brand">{{ brand }}</option>
          </select>
          <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-graphite/45 pointer-events-none text-sm">
            expand_more
          </span>
        </div>

        <span class="text-xs font-bold uppercase tracking-widest text-graphite/65">Urut:</span>
        <div class="relative">
          <select
            v-model="selectedSort"
            class="appearance-none bg-porcelain border border-mist px-4 py-2 pr-10 rounded-lg text-sm font-medium focus:outline-none focus:border-gold cursor-pointer shadow-sm"
            style="color: var(--ink);"
          >
            <option value="latest">Terbaru</option>
            <option value="price_low">Harga Terendah</option>
            <option value="price_high">Harga Tertinggi</option>
            <option value="best_seller">Terlaris</option>
            <option value="rating">Rating</option>
            <option value="popular">Populer</option>
          </select>
          <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-graphite/45 pointer-events-none text-sm">
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
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[11px] font-bold border transition-all hover:border-ink"
        style="background: #f8f5ef; color: var(--ink); border-color: #e4d8c8;"
      >
        <span class="uppercase tracking-wider text-graphite/65">{{ chip.label }}</span>
        <span>{{ chip.value }}</span>
        <span class="material-symbols-outlined text-sm">close</span>
      </button>
      <button
        @click="clearAllFilters"
        class="px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-graphite/65 hover:text-ink"
      >
        Reset
      </button>
    </div>

    <div
      v-if="showFilterPanel"
      class="mb-7 border border-mist bg-porcelain shadow-sm"
    >
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4 p-5">
        <label class="block">
          <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Gender</span>
          <select v-model="selectedGender" class="input-field py-2 text-sm">
            <option value="">Semua</option>
            <option v-for="item in availableGenders" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Bentuk Frame</span>
          <select v-model="selectedFrameShape" class="input-field py-2 text-sm">
            <option value="">Semua</option>
            <option v-for="item in availableFrameShapes" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Material</span>
          <select v-model="selectedFrameMaterial" class="input-field py-2 text-sm">
            <option value="">Semua</option>
            <option v-for="item in availableFrameMaterials" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Warna</span>
          <select v-model="selectedFrameColor" class="input-field py-2 text-sm">
            <option value="">Semua</option>
            <option v-for="item in availableFrameColors" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
          </select>
        </label>

        <label class="block">
          <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Fit Wajah</span>
          <select v-model="selectedFaceSizeFit" class="input-field py-2 text-sm">
            <option value="">Semua</option>
            <option v-for="item in availableFaceSizeFits" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
          </select>
        </label>

        <div class="grid grid-cols-2 gap-2">
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Min</span>
            <input v-model="minPrice" inputmode="numeric" placeholder="Rp" class="input-field py-2 text-sm" />
          </label>
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Max</span>
            <input v-model="maxPrice" inputmode="numeric" placeholder="Rp" class="input-field py-2 text-sm" />
          </label>
        </div>

        <label class="flex items-center gap-2 text-sm font-bold text-graphite">
          <input v-model="inStockOnly" type="checkbox" class="w-4 h-4 accent-gold" />
          Stok tersedia
        </label>

        <label class="flex items-center gap-2 text-sm font-bold text-graphite">
          <input v-model="prescriptionSupported" type="checkbox" class="w-4 h-4 accent-gold" />
          Bisa resep
        </label>

        <div class="flex items-end">
          <button
            @click="clearAllFilters"
            class="w-full px-4 py-2 text-xs font-black uppercase tracking-wider border border-mist text-graphite hover:border-ink hover:text-ink transition-all"
          >
            Reset Filter
          </button>
        </div>
      </div>
    </div>

    <div v-if="hasError" class="text-center py-24 rounded-lg border border-dashed" style="border-color: rgba(220,38,38,0.25); background: rgba(220,38,38,0.03);">
      <span class="material-symbols-outlined text-5xl mb-4 block" style="color: rgba(220,38,38,0.5);">wifi_off</span>
      <h2 class="text-xl font-bold text-ink mb-2">Gagal memuat produk</h2>
      <p class="text-graphite/65 mb-6">Terjadi kesalahan server. Silakan coba lagi.</p>
      <button @click="() => fetchProducts(false)" class="btn-primary"
        style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);">
        Coba Lagi
      </button>
    </div>

    <div v-else-if="isLoading" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-7">
      <div v-for="i in 12" :key="i" class="animate-pulse rounded-lg overflow-hidden" style="background: rgba(245,242,238,0.9);">
        <div class="aspect-[4/5]" style="background: linear-gradient(135deg, var(--mist), var(--taupe));"></div>
        <div class="p-5 space-y-3">
          <div class="h-3 rounded-lg w-1/3" style="background: var(--taupe);"></div>
          <div class="h-4 rounded-lg w-3/4" style="background: var(--mist);"></div>
          <div class="h-3 rounded-lg w-1/2" style="background: var(--taupe);"></div>
        </div>
      </div>
    </div>

    <div v-else-if="products.length === 0" class="text-center py-32 rounded-lg border border-dashed" style="border-color: rgba(184,138,68,0.25); background: rgba(184,138,68,0.04);">
      <span class="material-symbols-outlined text-7xl mb-6 block" style="color: rgba(184,138,68,0.4);">inventory_2</span>
      <h2 class="text-2xl font-bold text-graphite mb-3" style="font-family: 'Cormorant Garamond', serif;">Produk tidak ditemukan</h2>
      <p class="text-graphite/65">Coba pilih kategori lain atau kembali lagi nanti.</p>
    </div>

    <div v-else class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
      <article
        v-for="product in products"
        :key="product.id"
        @click="goToDetail(product.slug)"
        class="group relative flex flex-col cursor-pointer rounded-lg overflow-hidden transition-all duration-500 hover:-translate-y-1.5 hover:shadow-soft"
        style="background: white; box-shadow: 0 2px 12px rgba(0,0,0,0.06);"
      >
        <div class="relative aspect-[4/5] overflow-hidden flex items-center justify-center p-3 md:p-8"
          style="background: linear-gradient(145deg, var(--ivory), var(--mist));">

          <img
            :src="resolveImageUrl(product)"
            :alt="product.name"
            class="object-contain w-full h-full mix-blend-multiply transition-transform duration-700 ease-out group-hover:scale-105"
            :class="{ 'opacity-40 grayscale': product.stock <= 0 }"
            loading="lazy"
            decoding="async"
          />

          <button
            class="absolute top-3 right-3 w-9 h-9 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 shadow-card"
            :style="wishlistStore.isWishlisted(product.id)
              ? 'background: rgba(184,138,68,0.18); backdrop-filter: blur(8px); opacity: 1;'
              : 'background: rgba(255,255,255,0.95); backdrop-filter: blur(8px);'"
            @click.stop="toggleWishlist(product)"
          >
            <span class="material-symbols-outlined text-base" :style="wishlistStore.isWishlisted(product.id) ? 'color: var(--gold);' : 'color: var(--gold);'">favorite</span>
          </button>

          <button
            class="absolute top-14 right-3 w-9 h-9 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-300 shadow-card"
            :style="compareStore.isCompared(product.id)
              ? 'background: rgba(26,18,9,0.9); backdrop-filter: blur(8px); opacity: 1; color: white;'
              : 'background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); color: #6F4E1D;'"
            @click.stop="toggleCompare(product)"
          >
            <span class="material-symbols-outlined text-base">compare_arrows</span>
          </button>

          <!-- Best Seller Badge -->
          <div
            v-if="product.is_best_seller"
            class="absolute top-3 left-3 flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white shadow-sm"
            style="background: rgba(26,18,9,0.8); backdrop-filter: blur(4px); border: 1px solid rgba(184,138,68,0.3);"
          >
            <span class="material-symbols-outlined text-[10px]" style="color: var(--gold);">trending_up</span>
            Terlaris
          </div>

          <!-- Promo Badge (Buy X Get Y) -->
          <div
            v-if="getProductPromos(product).buyPromos.length > 0"
            class="absolute top-[44px] left-3 flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white shadow-card"
            style="background: var(--gold); border: 1px solid rgba(255,255,255,0.2);"
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
            class="absolute top-[44px] left-3 flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white shadow-card"
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
            style="background: rgba(184,138,68,0.9); backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.2);"
          >
            Informasi
          </div>
        </div>

        <div class="p-3 md:p-5 flex flex-col flex-grow min-w-0">
          <span class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] mb-1" style="color: var(--taupe);">
            {{ product.name }}
          </span>
          <h3
            class="font-bold text-sm md:text-lg leading-tight mb-2 md:mb-3 transition-colors duration-300 line-clamp-2 min-h-[2.5rem] md:min-h-[3.5rem]"
            style="color: var(--ink); font-family: 'Cormorant Garamond', serif; letter-spacing: -0.01em;"
            :class="{ 'group-hover:text-gold': product.stock > 0 }"
          >
            {{ product.brand || 'Optik Medio' }}
          </h3>
          <div class="grid grid-cols-1 gap-1.5 mb-2 md:mb-3 text-[10px] md:text-[11px]" style="color: var(--taupe);">
            <span class="flex items-center gap-1.5 min-w-0">
              <span class="material-symbols-outlined text-sm" style="color: var(--gold);">star</span>
              {{ Number(product.avg_rating || 0).toFixed(1) }} · {{ product.review_count || 0 }} ulasan
            </span>
            <span class="flex items-center gap-1.5 min-w-0">
              <span class="material-symbols-outlined text-sm" style="color: var(--gold);">shopping_bag</span>
              {{ Number(product.purchase_count || 0) }} terjual
            </span>
          </div>
          <div class="flex items-start justify-between gap-3 mt-auto">
            <div v-if="!product.is_not_for_sale">
              <p class="text-xs md:text-base font-black" style="color: var(--ink);">
                Rp {{ product.price.toLocaleString('id-ID') }}
              </p>
            </div>
            <div v-else>
              <p class="text-[10px] md:text-xs font-bold uppercase tracking-normal" style="color: var(--gold);">
                Katalog Informasi
              </p>
            </div>
            <span v-if="product.stock > 0 && !product.is_not_for_sale" class="shrink-0 flex items-center gap-1 text-[9px] font-bold text-right" style="color: #16a34a;">
              <span class="w-1.5 h-1.5 rounded-lg bg-olive inline-block"></span>
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
              class="w-full py-2.5 rounded-lg text-xs font-black uppercase tracking-wider text-white transition-all active:scale-95"
              style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
            >
              Lihat Detail
            </button>
          </div>
        </div>
      </article>
    </div>

    <div
      v-if="compareStore.count > 0"
      class="fixed left-4 right-4 bottom-24 z-40 bg-porcelain border border-mist shadow-soft p-3 flex items-center justify-between gap-3 md:bottom-5 md:left-1/2 md:w-[720px] md:-translate-x-1/2"
    >
      <div class="flex items-center gap-3 min-w-0">
        <span class="material-symbols-outlined text-xl shrink-0" style="color: var(--gold);">compare_arrows</span>
        <div class="min-w-0">
          <p class="text-xs font-black uppercase tracking-widest text-ink">{{ compareStore.count }}/4 produk</p>
          <p class="text-[11px] text-graphite/65 truncate">{{ compareStore.items.map(item => item.name).join(', ') }}</p>
        </div>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <button @click="compareStore.clear()" class="px-3 py-2 text-xs font-bold text-graphite/80 border border-mist">
          Reset
        </button>
        <button
          @click="router.push('/compare')"
          :disabled="!compareStore.canCompare"
          class="px-4 py-2 text-xs font-black uppercase tracking-widest text-white disabled:opacity-50"
          style="background: var(--ink);"
        >
          Compare
        </button>
      </div>
    </div>

    <div class="w-full mt-12 mb-8 flex flex-col items-center gap-6">
      <div v-if="isLoadingMore" class="flex items-center gap-3 text-graphite/65">
        <span class="material-symbols-outlined animate-spin text-2xl" style="color: var(--gold);">sync</span>
        <span class="text-xs font-bold uppercase tracking-widest" style="color: var(--ink);">Memuat lebih banyak...</span>
      </div>
      
      <div v-else-if="currentPage < lastPage" class="w-full flex justify-center">
        <button 
          @click="handleLoadMore"
          class="group relative px-10 py-4 overflow-hidden transition-all duration-300 hover:shadow-[0_8px_30px_rgb(193,154,81,0.2)] active:scale-95"
          style="background: var(--ink);"
        >
          <div class="absolute inset-0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000" style="background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);"></div>
          
          <div class="flex items-center gap-3 relative z-10">
            <span class="text-xs font-black uppercase tracking-[0.3em] text-white">Tampilkan Lebih Banyak</span>
            <span class="material-symbols-outlined text-sm text-gold group-hover:translate-y-1 transition-transform">expand_more</span>
          </div>
        </button>
      </div>

      <div v-else-if="!isLoading && products.length > 0" class="flex flex-col items-center gap-2">
        <div class="w-12 h-[1px] bg-mist"></div>
        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-graphite/45">
          Semua {{ totalProducts }} produk telah ditampilkan
        </span>
      </div>
    </div>

    <section v-if="lensShowcaseProducts.length > 0 || isLoadingLensShowcase" class="mt-20 mb-12">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 mb-8">
        <div>
          <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-3" style="color: var(--gold);">Pilihan Lensa Resep</p>
          <h2 class="text-3xl md:text-4xl font-black tracking-normal" style="font-family: 'Cormorant Garamond', serif; color: var(--ink);">Merek Lensa yang Tersedia</h2>
          <p class="text-sm text-graphite/65 mt-3 max-w-2xl leading-relaxed">
            Produk berikut bersifat katalog informasi. Pemilihan dan pembelian lensa dilakukan bersama frame melalui konsultasi resep di Optik Medio.
          </p>
        </div>
        <router-link to="/appointment" class="inline-flex items-center gap-2 px-5 py-3 text-xs font-black uppercase tracking-widest text-white transition-all hover:shadow-card" style="background: var(--ink);">
          Konsultasi Lensa
          <span class="material-symbols-outlined text-sm">arrow_forward</span>
        </router-link>
      </div>

      <div v-if="isLoadingLensShowcase" class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="animate-pulse border border-mist p-5" style="background: var(--porcelain);">
          <div class="aspect-[4/3] mb-4" style="background: var(--mist);"></div>
          <div class="h-3 w-1/2 mb-3" style="background: var(--taupe);"></div>
          <div class="h-4 w-3/4" style="background: var(--mist);"></div>
        </div>
      </div>

      <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <article
          v-for="lens in lensShowcaseProducts"
          :key="lens.id"
          class="group border border-mist overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-soft"
          style="background: var(--porcelain);"
          @click="goToDetail(lens.slug)"
        >
          <div class="aspect-[4/3] p-5 flex items-center justify-center" style="background: linear-gradient(145deg, var(--ivory), var(--mist));">
            <img
              :src="resolveImageUrl(lens)"
              :alt="lens.name"
              class="w-full h-full object-contain transition-transform duration-500 group-hover:scale-105"
              loading="lazy"
              decoding="async"
            />
          </div>
          <div class="p-4">
            <p class="text-[9px] font-black uppercase tracking-[0.2em] mb-2" style="color: var(--taupe);">{{ lens.brand || 'Lensa' }}</p>
            <h3 class="font-bold text-sm leading-tight line-clamp-2 min-h-[2.5rem]" style="color: var(--ink);">{{ lens.name }}</h3>
            <div class="mt-4 inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest" style="color: var(--gold);">
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
          <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-3" style="color: var(--gold);">Wawasan & Tips</p>
          <h2 class="text-3xl md:text-4xl font-black tracking-normal" style="font-family: 'Cormorant Garamond', serif; color: var(--ink);">Blog & Edukasi</h2>
          <div class="w-12 h-1 bg-gold mt-4"></div>
        </div>
        <router-link to="/blog" class="text-xs font-black uppercase tracking-widest text-gold hover:text-gold transition-all flex items-center gap-2 group">
          Lihat Semua Artikel
          <span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">arrow_forward</span>
        </router-link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="group cursor-pointer" @click="router.push('/blog/cara-memilih-frame-sesuai-bentuk-wajah')">
          <div class="aspect-video overflow-hidden mb-5 relative">
            <img src="/blog_feature_1_face_shape_1777451535680.png" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
            <div class="absolute inset-0 bg-graphite/20 group-hover:bg-transparent transition-all"></div>
          </div>
          <h3 class="font-bold text-lg mb-2 group-hover:text-gold transition-colors" style="font-family: 'Cormorant Garamond', serif;">Cara Memilih Frame Sesuai Bentuk Wajah</h3>
          <p class="text-sm text-graphite/65 leading-relaxed line-clamp-2">Temukan panduan lengkap untuk mendapatkan kacamata yang paling pas dan menunjang penampilan Anda.</p>
        </div>
        <div class="group cursor-pointer" @click="router.push('/blog/pentingnya-perlindungan-lensa-blueray')">
          <div class="aspect-video overflow-hidden mb-5 relative">
            <img src="/blog_feature_2_blueray_lens_1777451550672.png" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
            <div class="absolute inset-0 bg-graphite/20 group-hover:bg-transparent transition-all"></div>
          </div>
          <h3 class="font-bold text-lg mb-2 group-hover:text-gold transition-colors" style="font-family: 'Cormorant Garamond', serif;">Pentingnya Perlindungan Lensa Blueray</h3>
          <p class="text-sm text-graphite/65 leading-relaxed line-clamp-2">Lindungi mata Anda dari radiasi layar digital dengan teknologi lensa terkini dari Optik Medio.</p>
        </div>
        <div class="group cursor-pointer" @click="router.push('/blog/update-tren-kacamata-2026')">
          <div class="aspect-video overflow-hidden mb-5 relative">
            <img src="/blog_feature_3_trends_2026_1777451566973.png" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
            <div class="absolute inset-0 bg-graphite/20 group-hover:bg-transparent transition-all"></div>
          </div>
          <h3 class="font-bold text-lg mb-2 group-hover:text-gold transition-colors" style="font-family: 'Cormorant Garamond', serif;">Update Tren Kacamata 2026</h3>
          <p class="text-sm text-graphite/65 leading-relaxed line-clamp-2">Jelajahi gaya terbaru yang akan mendominasi tahun ini, mulai dari gaya retro hingga futuristik.</p>
        </div>
      </div>
    </section>

    <section v-if="testimonials.length > 0" class="mt-24 mb-16 px-6 md:px-0">
      <div class="text-center mb-12">
        <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-3" style="color: var(--gold);">Apa Kata Pelanggan Kami</p>
        <h2 class="text-3xl md:text-4xl font-black tracking-normal" style="font-family: 'Cormorant Garamond', serif; color: var(--ink);">Review Google Maps</h2>
        <div class="w-12 h-1 bg-gold mx-auto mt-4"></div>
      </div>

      <div class="flex overflow-x-auto md:grid md:grid-cols-2 gap-6 md:gap-8 max-w-4xl mx-auto pb-4 md:pb-0 snap-x snap-mandatory scrollbar-hide">
        <div 
          v-for="(t, idx) in testimonials" 
          :key="idx"
          class="min-w-[85vw] md:min-w-0 p-8 relative group transition-all duration-500 hover:shadow-soft border border-mist snap-center"
          style="background: white;"
        >
          <div class="absolute -top-4 -left-4 w-12 h-12 flex items-center justify-center bg-graphite text-gold">
            <span class="material-symbols-outlined">format_quote</span>
          </div>
          
          <div class="flex gap-1 mb-4">
            <span v-for="star in t.rating" :key="star" class="material-symbols-outlined text-sm" style="color: #fbbf24;">star</span>
          </div>
          
          <p class="text-graphite/80 italic leading-relaxed mb-6">"{{ t.review }}"</p>
          
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center font-black text-xs" style="background: var(--ivory); color: var(--gold);">
              {{ t.name.charAt(0) }}
            </div>
            <div>
              <h4 class="font-bold text-sm text-ink">{{ t.name }}</h4>
              <p class="text-[10px] uppercase tracking-widest text-graphite/45">Google Reviewer</p>
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
