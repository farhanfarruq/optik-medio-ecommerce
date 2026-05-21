<script setup lang="ts">
// ─────────────────────────────────────────────────────────────────────────
// FIXME P1-12 (Phase 3): God component — refactor ke sub-tree masih
// belum dilakukan. Phase 4+5 redesign hanya re-layout template, semua
// state/watch/computed/fetch logic dipertahankan persis. Lihat:
// medio-fe/src/views/REFACTOR_PLAN.md
// ─────────────────────────────────────────────────────────────────────────
import { logger } from '../core/utils/logger';
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { productRepository, type Category, type ProductFilters } from '../repositories/ProductRepository';
import { useSeoMeta } from '../composables/useSeoMeta';
import { formatMoney } from '../composables/useFormatMoney';
import type { Product } from '../types';
import { resolveImageUrl } from '../core/utils/image';
import { settingRepository, type Testimonial } from '../repositories/SettingRepository';
import { bannerRepository, type BannerItem } from '../repositories/BannerRepository';
import { useWishlistStore } from '../stores/wishlistStore';
import { useCartStore } from '../stores/cartStore';
import { useCompareStore } from '../stores/compareStore';
import { useToast } from '../composables/useToast';
import PageHero from '../components/layout/PageHero.vue';

const route = useRoute();
const router = useRouter();
const wishlistStore = useWishlistStore();
const cartStore = useCartStore();
const compareStore = useCompareStore();
const { showToast } = useToast();
const products = ref<Product[]>([]);
const lensShowcaseProducts = ref<Product[]>([]);
const categories = ref<Category[]>([]);

// Toggle kategori tersembunyi (Home page)
const showAllCategories = ref(false);
const isMobileView = ref(typeof window !== 'undefined' ? window.innerWidth < 768 : false);
const handleResize = () => {
  isMobileView.value = window.innerWidth < 768;
};

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
const isHomePage = computed(() => route.name === 'Home');
const isCatalogPage = computed(() => route.name === 'Products' || route.name === 'ProductsByCategory');
const catalogHeroTitle = computed(() => (route.name === 'Products' ? 'Katalog Produk' : categoryTitle.value));
const catalogHeroSubtitle = computed(() => (route.name === 'Products'
  ? 'Jelajahi frame, lensa, dan koleksi optik yang siap difilter sesuai kebutuhan.'
  : 'Temukan produk pilihan dari kategori ini dengan filter katalog yang lebih rapi.'));
const catalogHeroBreadcrumbs = computed(() => route.name === 'Products'
  ? [{ label: 'Katalog Produk' }]
  : [{ label: 'Katalog Produk', to: '/products' }, { label: categoryTitle.value }]);

const resolveCategoryImage = (category?: Pick<Category, 'name' | 'slug' | 'image'> | null): string | null => {
  if (!category?.image) return null;
  const img = category.image;
  if (img.startsWith('http')) return img;
  const apiUrl = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api').replace('/api', '');
  return `${apiUrl}/storage/${img}`;
};

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
  if (searchQuery.value) return 'Menampilkan produk yang cocok dengan pencarian Anda.';
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
    chips.push({ key: 'price', label: 'Harga', value: `${minPrice.value || '0'} - ${maxPrice.value || 'maks'}` });
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
    const params: Record<string, string | number> = {
      page: currentPage.value,
      exclude_not_for_sale: 'true',
      prioritize_glasses: 'true',
    };
    if (categorySlug.value) params.category = categorySlug.value;
    if (selectedBrand.value) params.brand = selectedBrand.value;
    if (selectedGender.value) params.gender = selectedGender.value;
    if (selectedFrameShape.value) params.frame_shape = selectedFrameShape.value;
    if (selectedFrameMaterial.value) params.frame_material = selectedFrameMaterial.value;
    if (selectedFrameColor.value) params.frame_color = selectedFrameColor.value;
    if (selectedFaceSizeFit.value) params.face_size_fit = selectedFaceSizeFit.value;
    if (inStockOnly.value) params.in_stock_only = 'true';
    if (prescriptionSupported.value) params.prescription_supported = 'true';
    if (minPrice.value) params.min_price = minPrice.value;
    if (maxPrice.value) params.max_price = maxPrice.value;
    if (selectedSort.value) params.sort = selectedSort.value;
    if (searchQuery.value) params.search = searchQuery.value;
    if (hasPromo.value) params.has_promo = 'true';
    if (promoId.value) params.promo_id = promoId.value;

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
    logger.error('Failed to fetch products', error);
    hasError.value = true;
    if (!isLoadMore) products.value = [];
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
    logger.warn('Could not load lens showcase products', error);
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
  try { brands.value = await productRepository.getBrands(); }
  catch (e) { logger.warn('Could not load brands', e); }
};

const fetchFilterMetadata = async () => {
  try {
    productFilters.value = await productRepository.getFilters();
    brands.value = productFilters.value.brands || [];
  } catch (e) {
    logger.warn('Could not load product filters', e);
    fetchBrands();
  }
};

const fetchCategories = async () => {
  try { categories.value = await productRepository.getCategories(); }
  catch (e) { logger.warn('Could not load categories', e); }
};

onMounted(() => {
  const { setSeo } = useSeoMeta();
  setSeo({
    title: 'Belanja Kacamata & Lensa Premium',
    description: 'Optik Medio menyediakan eyewear premium, lensa optik berkualitas, dan layanan profesional. Pilih frame favorit Anda dengan harga terbaik.',
    ogTitle: 'Optik Medio — Curated Lens Experience',
    ogDescription: 'Belanja eyewear premium, promo optik, dan konsultasi visual di Optik Medio.',
    ogType: 'website',
  });

  fetchCategories();
  fetchFilterMetadata();
  fetchLensShowcaseProducts();

  if (cartStore.activePromos.length === 0) cartStore.fetchPromos();

  bannerRepository.getBanners().then(data => {
    banners.value = data;
    if (data.length > 1) {
      bannerTimer = setInterval(() => {
        currentBannerIndex.value = (currentBannerIndex.value + 1) % banners.value.length;
      }, 4000);
    }
  });
  settingRepository.getSettings().then(data => {
    if (data.store_testimonials) testimonials.value = data.store_testimonials;
  });

  window.addEventListener('resize', handleResize);
});

watch(() => route.params.slug, (newSlug) => {
  categorySlug.value = (newSlug as string) || '';
  fetchProducts(false);
});

const getProductPromos = (product: Product) => {
  const buyPromos = [...(product.buy_promos || []), ...(product.buy_promos_many || [])];
  const discountPromos = [...(product.discount_promos || []), ...(product.discount_promos_many || [])];

  if (product.brand && cartStore.activePromos.length > 0) {
    cartStore.activePromos.forEach(promo => {
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
      activePromoName.value = 'Promo Spesial';
    } catch (e) { logger.warn('Failed to fetch promo data', e); }
  } else {
    activePromoName.value = '';
  }
  fetchProducts(false);
});

watch([
  selectedBrand, selectedGender, selectedFrameShape, selectedFrameMaterial,
  selectedFrameColor, selectedFaceSizeFit, inStockOnly, prescriptionSupported,
  minPrice, maxPrice, selectedSort,
], () => { fetchProducts(false); });

const clearFilter = (key: string) => {
  if (key === 'brand') selectedBrand.value = '';
  if (key === 'gender') selectedGender.value = '';
  if (key === 'frame_shape') selectedFrameShape.value = '';
  if (key === 'frame_material') selectedFrameMaterial.value = '';
  if (key === 'frame_color') selectedFrameColor.value = '';
  if (key === 'face_size_fit') selectedFaceSizeFit.value = '';
  if (key === 'in_stock_only') inStockOnly.value = false;
  if (key === 'prescription_supported') prescriptionSupported.value = false;
  if (key === 'price') { minPrice.value = ''; maxPrice.value = ''; }
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
  if (!slug) router.push('/products');
  else router.push(`/products/category/${slug}`);
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
  showToast(added ? 'Produk ditambahkan ke wishlist.' : 'Produk dihapus dari wishlist.', 'success');
};

const toggleCompare = (product: Product) => {
  const result = compareStore.toggle(product);
  if (result === 'full') {
    showToast('Compare maksimal 4 produk.', 'error');
    return;
  }
  showToast(result === 'added' ? 'Produk ditambahkan ke compare.' : 'Produk dihapus dari compare.', 'success');
};

// Visible categories on storefront chip rail (Home).
const visibleCategoriesCount = computed(() => isMobileView.value ? 6 : 9);
const visibleCategories = computed(() => {
  if (showAllCategories.value) return categories.value;
  return categories.value.slice(0, visibleCategoriesCount.value);
});
const hiddenCategoryCount = computed(() => Math.max(0, categories.value.length - visibleCategoriesCount.value));

const productPromoBuyLabel = (product: Product): string | null => {
  const { buyPromos } = getProductPromos(product);
  if (!buyPromos.length) return null;
  const p = buyPromos[0];
  if (p.buy_quantity && p.get_quantity) return `Beli ${p.buy_quantity} Gratis ${p.get_quantity}`;
  return 'Promo Spesial';
};

const productPromoDiscountLabel = (product: Product): string | null => {
  const { discountPromos } = getProductPromos(product);
  if (!discountPromos.length) return null;
  const p = discountPromos[0];
  if (p.discount_type === 'percentage') return `Diskon ${Math.round(Number(p.discount_value || 0))}%`;
  return `Diskon ${formatMoney(Number(p.discount_value || 0))}`;
};

onUnmounted(() => {
  if (bannerTimer) clearInterval(bannerTimer);
  window.removeEventListener('resize', handleResize);
});
</script>

<template>
  <div>
    <!-- ════════════════════════════════════════════════════════════════
         HOMEPAGE — editorial commerce storefront
         ════════════════════════════════════════════════════════════════ -->
    <header v-if="isHomePage" class="storefront-hero">
      <div class="storefront-hero__media">
        <img
          src="/gambar/hero-bg.jpeg"
          alt=""
          class="storefront-hero__image"
          loading="eager"
          decoding="async"
        />
        <div class="storefront-hero__scrim" aria-hidden="true"></div>
        <div class="storefront-hero__rule" aria-hidden="true"></div>
        <div class="storefront-hero__fade" aria-hidden="true"></div>
      </div>

      <div class="container-premium storefront-hero__inner">
        <div class="storefront-hero__copy">
          <p class="eyebrow text-gold">Optik Medio · Curated Eyewear</p>
          <h1 class="storefront-hero__title editorial-display">
            Frame premium, lensa presisi,<br class="hidden sm:inline" /> dipilih bersama optometri.
          </h1>
          <p class="storefront-hero__lede">
            Belanja eyewear yang dirancang nyaman dipakai harian — dengan opsi konsultasi resep, garansi, dan pickup di toko.
          </p>
          <div class="storefront-hero__cta-row">
            <router-link to="/products" class="btn-gold btn-lg">
              <span>Belanja Sekarang</span>
              <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
            </router-link>
            <router-link to="/face-shape-quiz" class="btn-outline btn-lg storefront-hero__cta-secondary">
              <span class="material-symbols-outlined text-base" aria-hidden="true">quiz</span>
              <span>Quiz Bentuk Wajah</span>
            </router-link>
          </div>
        </div>
      </div>
    </header>

    <!-- ════════════════════════════════════════════════════════════════
         CATALOG — PageHero
         ════════════════════════════════════════════════════════════════ -->
    <PageHero
      v-else-if="isCatalogPage"
      :title="catalogHeroTitle"
      :subtitle="catalogHeroSubtitle"
      :breadcrumbs="catalogHeroBreadcrumbs"
      back-to="/products"
      back-label="Kembali ke Katalog"
    />

    <!-- ════════════════════════════════════════════════════════════════
         MAIN — shared sections (Home: full layout, Catalog: toolbar+grid)
         ════════════════════════════════════════════════════════════════ -->
    <main class="container-premium" :class="isHomePage ? 'storefront-main' : 'catalog-main'">

      <!-- ─── HOME-only sections (atas) ─────────────────────────────── -->
      <template v-if="isHomePage">
        <!-- Quick category rail -->
        <section v-if="categories.length" class="storefront-section">
          <div class="storefront-section__head">
            <div>
              <p class="eyebrow">Telusuri Koleksi</p>
              <h2 class="editorial-h2 storefront-section__title">Kategori Populer</h2>
            </div>
            <button
              v-if="hiddenCategoryCount > 0"
              type="button"
              class="btn-ghost btn-sm hidden sm:inline-flex"
              @click="showAllCategories = !showAllCategories"
            >
              {{ showAllCategories ? 'Tampilkan Sedikit' : `Lihat Semua (${categories.length})` }}
              <span class="material-symbols-outlined text-base" aria-hidden="true">{{ showAllCategories ? 'expand_less' : 'expand_more' }}</span>
            </button>
          </div>

          <div class="storefront-categories">
            <button
              type="button"
              class="cat-tile"
              :class="{ 'cat-tile--active': !categorySlug && !hasPromo }"
              @click="goToCategory(null)"
            >
              <span class="cat-tile__icon-wrap">
                <span class="material-symbols-outlined cat-tile__icon" aria-hidden="true">apps</span>
              </span>
              <span class="cat-tile__label">Semua</span>
            </button>

            <button
              type="button"
              class="cat-tile cat-tile--accent"
              :class="{ 'cat-tile--active': hasPromo }"
              @click="togglePromoFilter"
            >
              <span class="cat-tile__icon-wrap cat-tile__icon-wrap--accent">
                <span class="material-symbols-outlined cat-tile__icon" aria-hidden="true">local_offer</span>
              </span>
              <span class="cat-tile__label">Promo</span>
            </button>

            <button
              v-for="cat in visibleCategories"
              :key="cat.id"
              type="button"
              class="cat-tile"
              :class="{ 'cat-tile--active': categorySlug === cat.slug }"
              @click="goToCategory(cat.slug)"
            >
              <span class="cat-tile__icon-wrap">
                <img
                  v-if="resolveCategoryImage(cat)"
                  :src="resolveCategoryImage(cat)!"
                  alt=""
                  class="cat-tile__image"
                  loading="lazy"
                  decoding="async"
                />
                <span v-else class="material-symbols-outlined cat-tile__icon" aria-hidden="true">category</span>
              </span>
              <span class="cat-tile__label">{{ cat.name }}</span>
            </button>

            <button
              v-if="hiddenCategoryCount > 0"
              type="button"
              class="cat-tile cat-tile--more sm:hidden"
              @click="showAllCategories = !showAllCategories"
            >
              <span class="cat-tile__icon-wrap">
                <span class="material-symbols-outlined cat-tile__icon" aria-hidden="true">{{ showAllCategories ? 'expand_less' : 'expand_more' }}</span>
              </span>
              <span class="cat-tile__label">{{ showAllCategories ? 'Tutup' : `+${hiddenCategoryCount}` }}</span>
            </button>
          </div>
        </section>

        <!-- Banner -->
        <section v-if="activeBanner" class="storefront-section">
          <article class="storefront-banner">
            <img
              v-if="activeBanner.image_path"
              :src="resolveImageUrl(activeBanner.image_path)"
              :alt="activeBanner.title || 'Banner Optik Medio'"
              class="storefront-banner__image"
              loading="lazy"
              decoding="async"
            />
            <div class="storefront-banner__scrim" aria-hidden="true"></div>
            <div class="storefront-banner__copy">
              <p class="eyebrow text-gold">Penawaran Spesial</p>
              <h3 class="storefront-banner__title editorial-display">{{ activeBanner.title }}</h3>
              <p v-if="activeBanner.subtitle" class="storefront-banner__sub">{{ activeBanner.subtitle }}</p>
              <a
                v-if="activeBanner.cta_label"
                :href="activeBanner.external_url || (activeBanner.product ? `/products/${activeBanner.product.slug}` : activeBanner.category ? `/products/category/${activeBanner.category.slug}` : '#')"
                class="storefront-banner__cta"
              >
                {{ activeBanner.cta_label }}
                <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
              </a>
            </div>
            <div v-if="banners.length > 1" class="storefront-banner__dots" aria-label="Pilih banner">
              <button
                v-for="(_, idx) in banners"
                :key="idx"
                type="button"
                class="storefront-banner__dot"
                :class="{ 'storefront-banner__dot--active': idx === currentBannerIndex }"
                :aria-label="`Banner ${idx + 1}`"
                @click="currentBannerIndex = idx"
              ></button>
            </div>
          </article>
        </section>

        <!-- Trust band -->
        <section class="storefront-section">
          <ul class="storefront-trust">
            <li class="trust-tile">
              <span class="material-symbols-outlined" aria-hidden="true">verified</span>
              <div>
                <p class="trust-tile__title">Produk Original</p>
                <p class="trust-tile__meta">Distribusi resmi & kartu garansi</p>
              </div>
            </li>
            <li class="trust-tile">
              <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
              <div>
                <p class="trust-tile__title">Konsultasi Resep</p>
                <p class="trust-tile__meta">Refraksi & rekomendasi lensa</p>
              </div>
            </li>
            <li class="trust-tile">
              <span class="material-symbols-outlined" aria-hidden="true">workspace_premium</span>
              <div>
                <p class="trust-tile__title">Garansi Lensa</p>
                <p class="trust-tile__meta">Klaim cepat di toko atau online</p>
              </div>
            </li>
            <li class="trust-tile">
              <span class="material-symbols-outlined" aria-hidden="true">storefront</span>
              <div>
                <p class="trust-tile__title">Pickup di Toko</p>
                <p class="trust-tile__meta">Fitting & ambil sendiri</p>
              </div>
            </li>
          </ul>
        </section>

        <!-- Featured product grid (shared markup with catalog grid) -->
        <section class="storefront-section">
          <div class="storefront-section__head">
            <div>
              <p class="eyebrow">Pilihan Editor</p>
              <h2 class="editorial-h2 storefront-section__title">{{ categoryTitle }}</h2>
              <p class="text-lede storefront-section__sub">{{ categoryDescription }}</p>
            </div>
            <router-link to="/products" class="btn-ghost btn-sm hidden sm:inline-flex">
              Lihat Semua
              <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
            </router-link>
          </div>
        </section>
      </template>

      <!-- ─── CATALOG-only toolbar + chips + filter ──────────────────── -->
      <template v-if="isCatalogPage">
        <!-- Sticky toolbar -->
        <div class="catalog-toolbar">
          <div class="catalog-toolbar__count" aria-live="polite">
            <span v-if="!isLoading && !hasError">
              <strong>{{ totalProducts }}</strong> produk
            </span>
            <span v-else-if="isLoading" class="text-graphite/60">Memuat...</span>
          </div>

          <div class="catalog-toolbar__actions">
            <button
              type="button"
              class="catalog-toolbar__btn"
              :class="{ 'catalog-toolbar__btn--active': showFilterPanel || activeFilterChips.length > 0 }"
              :aria-haspopup="'dialog'"
              :aria-expanded="showFilterPanel"
              @click="showFilterPanel = !showFilterPanel"
            >
              <span class="material-symbols-outlined" aria-hidden="true">tune</span>
              <span class="hidden sm:inline">Filter</span>
              <span
                v-if="activeFilterChips.length > 0"
                class="catalog-toolbar__count-pill"
                :aria-label="`${activeFilterChips.length} filter aktif`"
              >{{ activeFilterChips.length }}</span>
            </button>

            <label class="catalog-toolbar__select">
              <span class="sr-only">Merek</span>
              <select v-model="selectedBrand">
                <option value="">Semua Merek</option>
                <option v-for="brand in availableBrands" :key="brand" :value="brand">{{ brand }}</option>
              </select>
              <span class="material-symbols-outlined" aria-hidden="true">expand_more</span>
            </label>

            <label class="catalog-toolbar__select">
              <span class="sr-only">Urutkan</span>
              <select v-model="selectedSort">
                <option value="latest">Terbaru</option>
                <option value="price_low">Harga Terendah</option>
                <option value="price_high">Harga Tertinggi</option>
                <option value="best_seller">Terlaris</option>
                <option value="rating">Rating</option>
                <option value="popular">Populer</option>
              </select>
              <span class="material-symbols-outlined" aria-hidden="true">expand_more</span>
            </label>
          </div>
        </div>

        <!-- Quick category chips -->
        <nav class="catalog-cats" aria-label="Kategori cepat">
          <button
            type="button"
            class="chip"
            :class="{ 'chip-active': !categorySlug && !hasPromo }"
            @click="goToCategory(null)"
          >Semua</button>
          <button
            type="button"
            class="chip"
            :class="hasPromo ? 'chip-active' : 'chip-gold'"
            @click="togglePromoFilter"
          >
            <span class="material-symbols-outlined text-base" aria-hidden="true">local_offer</span>
            Promo
          </button>
          <button
            v-for="cat in categories"
            :key="cat.id"
            type="button"
            class="chip"
            :class="{ 'chip-active': categorySlug === cat.slug }"
            @click="goToCategory(cat.slug)"
          >{{ cat.name }}</button>
        </nav>

        <!-- Applied filter chips -->
        <div v-if="activeFilterChips.length > 0" class="catalog-applied" aria-label="Filter aktif">
          <button
            v-for="chip in activeFilterChips"
            :key="chip.key"
            type="button"
            class="chip chip-removable"
            @click="clearFilter(chip.key)"
          >
            <span class="catalog-applied__label">{{ chip.label }}:</span>
            <span>{{ chip.value }}</span>
            <span class="material-symbols-outlined text-base" aria-hidden="true">close</span>
          </button>
          <button type="button" class="btn-ghost btn-sm" @click="clearAllFilters">Reset semua</button>
        </div>

        <!-- Filter sheet -->
        <Transition name="fade">
          <div
            v-if="showFilterPanel"
            class="catalog-filter-backdrop md:hidden"
            role="button"
            tabindex="-1"
            aria-label="Tutup filter"
            @click="showFilterPanel = false"
            @keydown.enter="showFilterPanel = false"
            @keydown.escape="showFilterPanel = false"
          />
        </Transition>

        <Transition name="sheet">
          <section
            v-if="showFilterPanel"
            class="catalog-filter"
            role="dialog"
            aria-label="Filter produk"
            aria-modal="true"
          >
            <span class="bottom-sheet-handle md:hidden" aria-hidden="true"></span>
            <div class="catalog-filter__head">
              <h3 class="editorial-h3 catalog-filter__title">Filter Produk</h3>
              <button
                type="button"
                class="btn-icon-ghost"
                aria-label="Tutup filter"
                @click="showFilterPanel = false"
              >
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
              </button>
            </div>

            <div class="catalog-filter__grid">
              <label class="catalog-filter__field">
                <span class="text-meta">Gender</span>
                <select v-model="selectedGender" class="input-field">
                  <option value="">Semua</option>
                  <option v-for="item in availableGenders" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
                </select>
              </label>

              <label class="catalog-filter__field">
                <span class="text-meta">Bentuk Frame</span>
                <select v-model="selectedFrameShape" class="input-field">
                  <option value="">Semua</option>
                  <option v-for="item in availableFrameShapes" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
                </select>
              </label>

              <label class="catalog-filter__field">
                <span class="text-meta">Material</span>
                <select v-model="selectedFrameMaterial" class="input-field">
                  <option value="">Semua</option>
                  <option v-for="item in availableFrameMaterials" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
                </select>
              </label>

              <label class="catalog-filter__field">
                <span class="text-meta">Warna</span>
                <select v-model="selectedFrameColor" class="input-field">
                  <option value="">Semua</option>
                  <option v-for="item in availableFrameColors" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
                </select>
              </label>

              <label class="catalog-filter__field">
                <span class="text-meta">Fit Wajah</span>
                <select v-model="selectedFaceSizeFit" class="input-field">
                  <option value="">Semua</option>
                  <option v-for="item in availableFaceSizeFits" :key="item" :value="item">{{ formatFilterLabel(item) }}</option>
                </select>
              </label>

              <fieldset class="catalog-filter__field catalog-filter__field--price">
                <legend class="text-meta">Harga (Rp)</legend>
                <div class="catalog-filter__price">
                  <input v-model="minPrice" inputmode="numeric" placeholder="Min" class="input-field" aria-label="Harga minimum" />
                  <span class="catalog-filter__price-sep" aria-hidden="true">—</span>
                  <input v-model="maxPrice" inputmode="numeric" placeholder="Max" class="input-field" aria-label="Harga maksimum" />
                </div>
              </fieldset>

              <label class="catalog-filter__check">
                <input v-model="inStockOnly" type="checkbox" />
                <span>Stok tersedia</span>
              </label>

              <label class="catalog-filter__check">
                <input v-model="prescriptionSupported" type="checkbox" />
                <span>Bisa dengan resep optik</span>
              </label>
            </div>

            <footer class="catalog-filter__foot">
              <button type="button" class="btn-outline" @click="clearAllFilters">Reset</button>
              <button type="button" class="btn-primary" @click="showFilterPanel = false">
                Tampilkan {{ totalProducts }} produk
              </button>
            </footer>
          </section>
        </Transition>
      </template>

      <!-- ─── SHARED grid + states (Home & Catalog) ─────────────────── -->
      <div v-if="hasError" class="empty-state empty-state--error">
        <span class="material-symbols-outlined text-4xl" aria-hidden="true">wifi_off</span>
        <h2 class="editorial-h3">Gagal memuat produk</h2>
        <p>Terjadi kesalahan server. Silakan coba lagi.</p>
        <button type="button" class="btn-primary" @click="fetchProducts(false)">Coba Lagi</button>
      </div>

      <div v-else-if="isLoading" class="product-grid">
        <div v-for="i in 12" :key="i" class="product-card-skeleton">
          <div class="skeleton product-card-skeleton__image"></div>
          <div class="product-card-skeleton__body">
            <div class="skeleton h-3 w-1/3"></div>
            <div class="skeleton h-4 w-3/4"></div>
            <div class="skeleton h-3 w-1/2"></div>
          </div>
        </div>
      </div>

      <div v-else-if="products.length === 0" class="empty-state">
        <span class="material-symbols-outlined text-4xl text-gold" aria-hidden="true">inventory_2</span>
        <h2 class="editorial-h3">Produk tidak ditemukan</h2>
        <p>Coba pilih kategori lain atau hapus beberapa filter.</p>
        <button v-if="activeFilterChips.length > 0" type="button" class="btn-outline btn-sm" @click="clearAllFilters">Reset Filter</button>
      </div>

      <div v-else class="product-grid">
        <article
          v-for="product in products"
          :key="product.id"
          class="product-card group"
          tabindex="0"
          role="link"
          :aria-label="`Lihat detail ${product.name}`"
          @click="goToDetail(product.slug)"
          @keydown.enter="goToDetail(product.slug)"
          @keydown.space.prevent="goToDetail(product.slug)"
        >
          <div class="product-card__media">
            <img
              :src="resolveImageUrl(product)"
              :alt="product.name"
              class="product-card__image"
              :class="{ 'product-card__image--out': product.stock <= 0 }"
              loading="lazy"
              decoding="async"
            />

            <div class="product-card__quick">
              <button
                type="button"
                class="product-card__quick-btn"
                :class="{ 'product-card__quick-btn--active': wishlistStore.isWishlisted(product.id) }"
                :aria-label="wishlistStore.isWishlisted(product.id) ? 'Hapus dari wishlist' : 'Tambah ke wishlist'"
                @click.stop="toggleWishlist(product)"
              >
                <span class="material-symbols-outlined text-base" aria-hidden="true">favorite</span>
              </button>
              <button
                type="button"
                class="product-card__quick-btn"
                :class="{ 'product-card__quick-btn--active': compareStore.isCompared(product.id) }"
                :aria-label="compareStore.isCompared(product.id) ? 'Hapus dari compare' : 'Tambah ke compare'"
                @click.stop="toggleCompare(product)"
              >
                <span class="material-symbols-outlined text-base" aria-hidden="true">compare_arrows</span>
              </button>
            </div>

            <div class="product-card__badges">
              <span v-if="product.is_best_seller" class="product-badge product-badge--ink">
                <span class="material-symbols-outlined text-base" aria-hidden="true">trending_up</span>
                Terlaris
              </span>
              <span v-if="productPromoBuyLabel(product)" class="product-badge product-badge--gold">
                <span class="material-symbols-outlined text-base" aria-hidden="true">redeem</span>
                {{ productPromoBuyLabel(product) }}
              </span>
              <span v-if="productPromoDiscountLabel(product)" class="product-badge product-badge--red">
                <span class="material-symbols-outlined text-base" aria-hidden="true">percent</span>
                {{ productPromoDiscountLabel(product) }}
              </span>
              <span v-if="product.is_not_for_sale" class="product-badge product-badge--gold">Informasi</span>
            </div>

            <div v-if="product.stock <= 0 && !product.is_not_for_sale" class="product-card__out-overlay">
              <span class="product-card__out-pill">Stok Habis</span>
            </div>
          </div>

          <div class="product-card__body">
            <p class="product-card__brand">{{ product.brand || 'Optik Medio' }}</p>
            <h3 class="product-card__name">{{ product.name }}</h3>
            <ul class="product-card__meta">
              <li>
                <span class="material-symbols-outlined text-base" aria-hidden="true">star</span>
                {{ Number(product.avg_rating || 0).toFixed(1) }} · {{ product.review_count || 0 }} ulasan
              </li>
              <li>
                <span class="material-symbols-outlined text-base" aria-hidden="true">shopping_bag</span>
                {{ Number(product.purchase_count || 0) }} terjual
              </li>
            </ul>
            <div class="product-card__foot">
              <p v-if="!product.is_not_for_sale" class="product-card__price">{{ formatMoney(product.price) }}</p>
              <p v-else class="product-card__price product-card__price--info">Katalog Informasi</p>
              <span v-if="product.stock > 0 && !product.is_not_for_sale" class="product-card__stock">
                <span class="product-card__stock-dot" aria-hidden="true"></span>
                Tersedia
              </span>
            </div>
          </div>
        </article>
      </div>

      <!-- Compare bar (shared) -->
      <div v-if="compareStore.count > 0" class="compare-bar">
        <div class="compare-bar__info">
          <span class="material-symbols-outlined" aria-hidden="true">compare_arrows</span>
          <div>
            <p class="compare-bar__title">{{ compareStore.count }}/4 produk</p>
            <p class="compare-bar__list">{{ compareStore.items.map(item => item.name).join(', ') }}</p>
          </div>
        </div>
        <div class="compare-bar__actions">
          <button type="button" class="btn-outline btn-sm" @click="compareStore.clear()">Reset</button>
          <button
            type="button"
            class="btn-primary btn-sm"
            :disabled="!compareStore.canCompare"
            @click="router.push('/compare')"
          >Compare</button>
        </div>
      </div>

      <!-- Load more / end -->
      <footer class="catalog-foot">
        <div v-if="isLoadingMore" class="catalog-foot__loading">
          <span class="material-symbols-outlined animate-spin" aria-hidden="true">sync</span>
          <span>Memuat lebih banyak...</span>
        </div>
        <button
          v-else-if="currentPage < lastPage"
          type="button"
          class="btn-primary btn-lg"
          @click="handleLoadMore"
        >
          Tampilkan Lebih Banyak
          <span class="material-symbols-outlined text-base" aria-hidden="true">expand_more</span>
        </button>
        <p v-else-if="!isLoading && products.length > 0" class="catalog-foot__end">
          <span class="divider-mute" aria-hidden="true"></span>
          <span class="text-meta">Semua {{ totalProducts }} produk telah ditampilkan</span>
        </p>
      </footer>

      <!-- ─── HOME-only sections (bawah grid) ───────────────────────── -->
      <template v-if="isHomePage">
        <!-- Lens showcase -->
        <section v-if="lensShowcaseProducts.length > 0 || isLoadingLensShowcase" class="storefront-section">
          <div class="storefront-section__head">
            <div>
              <p class="eyebrow">Pilihan Lensa Resep</p>
              <h2 class="editorial-h2 storefront-section__title">Merek Lensa yang Tersedia</h2>
              <p class="text-lede storefront-section__sub">
                Produk berikut bersifat katalog informasi. Pemilihan dan pembelian lensa dilakukan bersama frame melalui konsultasi resep di Optik Medio.
              </p>
            </div>
            <router-link to="/appointment" class="btn-primary btn-sm hidden sm:inline-flex">
              Konsultasi Lensa
              <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
            </router-link>
          </div>

          <div v-if="isLoadingLensShowcase" class="lens-grid">
            <div v-for="i in 4" :key="i" class="skeleton lens-skel"></div>
          </div>
          <div v-else class="lens-grid">
            <article
              v-for="lens in lensShowcaseProducts"
              :key="lens.id"
              class="lens-card group"
              tabindex="0"
              role="link"
              :aria-label="`Lihat detail ${lens.name}`"
              @click="goToDetail(lens.slug)"
              @keydown.enter="goToDetail(lens.slug)"
              @keydown.space.prevent="goToDetail(lens.slug)"
            >
              <div class="lens-card__media">
                <img
                  :src="resolveImageUrl(lens)"
                  :alt="lens.name"
                  class="lens-card__image"
                  loading="lazy"
                  decoding="async"
                />
              </div>
              <div class="lens-card__body">
                <p class="text-meta">{{ lens.brand || 'Lensa' }}</p>
                <h3 class="lens-card__title">{{ lens.name }}</h3>
                <span class="lens-card__cta">
                  Informasi
                  <span class="material-symbols-outlined text-base" aria-hidden="true">visibility</span>
                </span>
              </div>
            </article>
          </div>
        </section>

        <!-- Blog -->
        <section class="storefront-section">
          <div class="storefront-section__head storefront-section__head--center">
            <p class="eyebrow">Wawasan & Tips</p>
            <h2 class="editorial-h2 storefront-section__title">Blog & Edukasi</h2>
            <p class="text-lede storefront-section__sub">Tips memilih frame, lensa, dan tren eyewear terbaru.</p>
            <router-link to="/blog" class="btn-ghost btn-sm storefront-section__head-cta">
              Lihat Semua Artikel
              <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
            </router-link>
          </div>

          <div class="blog-grid">
            <article
              class="blog-card group"
              tabindex="0"
              role="link"
              aria-label="Artikel: Cara Memilih Frame Sesuai Bentuk Wajah"
              @click="router.push('/blog/cara-memilih-frame-sesuai-bentuk-wajah')"
              @keydown.enter="router.push('/blog/cara-memilih-frame-sesuai-bentuk-wajah')"
            >
              <div class="blog-card__media">
                <picture>
                  <source srcset="/blog_feature_1_face_shape_1777451535680.webp" type="image/webp" />
                  <img
                    src="/blog_feature_1_face_shape_1777451535680.png"
                    alt="Panduan memilih frame kacamata sesuai bentuk wajah"
                    class="blog-card__image"
                    loading="lazy"
                    decoding="async"
                    width="800"
                    height="600"
                  />
                </picture>
              </div>
              <h3 class="blog-card__title">Cara Memilih Frame Sesuai Bentuk Wajah</h3>
              <p class="blog-card__meta">Panduan lengkap menemukan kacamata yang pas dan menunjang penampilan.</p>
            </article>

            <article
              class="blog-card group"
              tabindex="0"
              role="link"
              aria-label="Artikel: Pentingnya Perlindungan Lensa Blueray"
              @click="router.push('/blog/pentingnya-perlindungan-lensa-blueray')"
              @keydown.enter="router.push('/blog/pentingnya-perlindungan-lensa-blueray')"
            >
              <div class="blog-card__media">
                <picture>
                  <source srcset="/blog_feature_2_blueray_lens_1777451550672.webp" type="image/webp" />
                  <img
                    src="/blog_feature_2_blueray_lens_1777451550672.png"
                    alt="Lensa blueray melindungi mata dari radiasi layar"
                    class="blog-card__image"
                    loading="lazy"
                    decoding="async"
                    width="800"
                    height="600"
                  />
                </picture>
              </div>
              <h3 class="blog-card__title">Pentingnya Perlindungan Lensa Blueray</h3>
              <p class="blog-card__meta">Lindungi mata dari radiasi layar digital dengan teknologi lensa terkini.</p>
            </article>

            <article
              class="blog-card blog-card--feature group"
              tabindex="0"
              role="link"
              aria-label="Artikel: Update Tren Kacamata 2026"
              @click="router.push('/blog/update-tren-kacamata-2026')"
              @keydown.enter="router.push('/blog/update-tren-kacamata-2026')"
            >
              <div class="blog-card__media blog-card__media--wide">
                <picture>
                  <source srcset="/blog_feature_3_trends_2026_1777451566973.webp" type="image/webp" />
                  <img
                    src="/blog_feature_3_trends_2026_1777451566973.png"
                    alt="Tren kacamata 2026 — gaya retro hingga futuristik"
                    class="blog-card__image"
                    loading="lazy"
                    decoding="async"
                    width="1200"
                    height="800"
                  />
                </picture>
              </div>
              <h3 class="blog-card__title">Update Tren Kacamata 2026</h3>
              <p class="blog-card__meta">Jelajahi gaya terbaru yang akan mendominasi tahun ini.</p>
            </article>
          </div>
        </section>

        <!-- Testimonials -->
        <section v-if="testimonials.length > 0" class="storefront-section">
          <div class="storefront-section__head storefront-section__head--center">
            <p class="eyebrow">Apa Kata Pelanggan</p>
            <h2 class="editorial-h2 storefront-section__title">Review Google Maps</h2>
          </div>

          <div class="testimonial-rail" role="list">
            <article
              v-for="(t, idx) in testimonials"
              :key="idx"
              class="testimonial-card"
              role="listitem"
            >
              <span class="testimonial-card__quote material-symbols-outlined" aria-hidden="true">format_quote</span>
              <div class="testimonial-card__stars" :aria-label="`Rating ${t.rating} bintang`">
                <span v-for="star in t.rating" :key="star" class="material-symbols-outlined" aria-hidden="true">star</span>
              </div>
              <p class="testimonial-card__body">"{{ t.review }}"</p>
              <footer class="testimonial-card__foot">
                <span class="testimonial-card__avatar">{{ t.name.charAt(0) }}</span>
                <div>
                  <p class="testimonial-card__name">{{ t.name }}</p>
                  <p class="testimonial-card__role">Google Reviewer</p>
                </div>
              </footer>
            </article>
          </div>
        </section>
      </template>
    </main>
  </div>
</template>

<style scoped>
/* ════════════════════════════════════════════════════════════════════════
   STOREFRONT (Home)
   ════════════════════════════════════════════════════════════════════════ */
.storefront-hero {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  min-height: clamp(360px, 56vw, 560px);
  padding-top: calc(var(--header-height, 72px) + clamp(40px, 6vw, 80px));
  padding-bottom: clamp(80px, 10vw, 120px);
  margin-bottom: -64px;
  color: #fff;
}

.storefront-hero__media { position: absolute; inset: 0; z-index: -1; }

.storefront-hero__image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center 38%;
  transform: scale(1.04);
}

.storefront-hero__scrim {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(160deg, rgba(10, 8, 5, 0.55) 0%, rgba(30, 20, 10, 0.32) 60%, transparent 100%),
    linear-gradient(180deg, rgba(10, 8, 5, 0.10) 0%, rgba(10, 8, 5, 0.42) 100%);
}

.storefront-hero__rule {
  position: absolute;
  left: 0;
  right: 0;
  bottom: clamp(80px, 10vw, 120px);
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(184, 138, 68, 0.55), transparent);
}

.storefront-hero__fade {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: clamp(80px, 10vw, 120px);
  background: linear-gradient(to bottom, transparent 0%, var(--ivory) 100%);
}

.storefront-hero__inner {
  position: relative;
  display: flex;
  align-items: flex-end;
  height: 100%;
}

.storefront-hero__copy { max-width: 64ch; }

.storefront-hero__title {
  margin-top: 14px;
  color: #fff;
  font-size: clamp(2rem, 1.4rem + 3.4vw, 4rem);
  line-height: 1.04;
  text-shadow: 0 4px 24px rgba(0, 0, 0, 0.30);
}

.storefront-hero__lede {
  margin-top: 16px;
  max-width: 56ch;
  font-size: clamp(13px, 1.1vw, 16px);
  line-height: 1.65;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.84);
}

.storefront-hero__cta-row {
  margin-top: 28px;
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.storefront-hero__cta-secondary {
  background: rgba(252, 250, 246, 0.92);
  border-color: transparent;
}

.storefront-main {
  position: relative;
  z-index: 1;
  padding-top: clamp(48px, 6vw, 80px);
  padding-bottom: clamp(48px, 6vw, 96px);
  display: flex;
  flex-direction: column;
  gap: clamp(56px, 7vw, 96px);
}

.catalog-main {
  position: relative;
  z-index: 1;
  padding-top: clamp(8px, 1.4vw, 24px);
  padding-bottom: clamp(48px, 6vw, 96px);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.storefront-section { scroll-margin-top: calc(var(--header-height, 72px) + 24px); }

.storefront-section__head {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: clamp(20px, 2.4vw, 32px);
}

@media (min-width: 768px) {
  .storefront-section__head {
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
  }
  .storefront-section__head > div { max-width: 64ch; }
}

.storefront-section__head--center {
  align-items: center;
  text-align: center;
  flex-direction: column !important;
}

.storefront-section__head--center > div { max-width: none; }

.storefront-section__title { margin-top: 6px; }
.storefront-section__sub { margin-top: 8px; }
.storefront-section__head-cta { margin-top: 12px; }

/* Categories — chip rail */
.storefront-categories {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

@media (min-width: 480px) { .storefront-categories { grid-template-columns: repeat(4, 1fr); } }
@media (min-width: 768px) { .storefront-categories { grid-template-columns: repeat(6, 1fr); gap: 14px; } }
@media (min-width: 1024px) { .storefront-categories { grid-template-columns: repeat(9, 1fr); } }

.cat-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 8px;
  border-radius: 8px;
  border: 1px solid var(--mist);
  background: var(--porcelain);
  color: var(--graphite);
  min-height: 96px;
  transition: border-color var(--motion-base), box-shadow var(--motion-base), transform var(--motion-fast), background-color var(--motion-base);
}

.cat-tile:hover { border-color: rgba(184, 138, 68, 0.45); box-shadow: var(--shadow-card); }
.cat-tile:active { transform: scale(0.98); }

.cat-tile--active {
  border-color: var(--gold);
  background: var(--gold-soft);
  color: #6F4E1D;
  box-shadow: 0 0 0 1px rgba(184, 138, 68, 0.35);
}

.cat-tile--accent {
  border-color: rgba(184, 138, 68, 0.45);
  background: rgba(184, 138, 68, 0.08);
  color: #6F4E1D;
}

.cat-tile__icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 999px;
  background: var(--ivory);
  color: var(--graphite);
}

.cat-tile--active .cat-tile__icon-wrap { background: rgba(184, 138, 68, 0.18); color: var(--gold); }
.cat-tile__icon-wrap--accent { background: rgba(184, 138, 68, 0.18); color: var(--gold); }

.cat-tile__icon { font-size: 22px; }
.cat-tile__image { width: 30px; height: 30px; object-fit: contain; mix-blend-mode: multiply; }

.cat-tile__label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  text-align: center;
  line-height: 1.2;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.cat-tile--more { background: var(--ivory); }

/* Banner */
.storefront-banner {
  position: relative;
  display: block;
  overflow: hidden;
  border-radius: 12px;
  background: var(--graphite);
  aspect-ratio: 16 / 9;
  border: 1px solid var(--mist);
  box-shadow: var(--shadow-card);
}

@media (min-width: 768px) { .storefront-banner { aspect-ratio: 16 / 6; } }

.storefront-banner__image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: right center;
}

.storefront-banner__scrim {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg, rgba(0, 0, 0, 0.65) 0%, rgba(0, 0, 0, 0.30) 45%, rgba(0, 0, 0, 0) 80%);
}

.storefront-banner__copy {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 8px;
  padding: clamp(20px, 4vw, 56px);
  max-width: min(82%, 640px);
  color: #fff;
}

.storefront-banner__title {
  color: #fff;
  font-size: clamp(1.25rem, 1rem + 1.4vw, 2.25rem);
  line-height: 1.06;
}

.storefront-banner__sub {
  font-size: clamp(12px, 1vw, 14px);
  line-height: 1.5;
  color: rgba(255, 255, 255, 0.86);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.storefront-banner__cta {
  margin-top: 8px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.42);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: #fff;
  align-self: flex-start;
  transition: background-color var(--motion-base);
}

.storefront-banner__cta:hover { background: rgba(255, 255, 255, 0.12); }

.storefront-banner__dots {
  position: absolute;
  bottom: 16px;
  right: 16px;
  display: flex;
  gap: 6px;
}

.storefront-banner__dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.45);
  transition: width var(--motion-base), background-color var(--motion-base);
}

.storefront-banner__dot--active { width: 22px; background: var(--gold); }

/* Trust band */
.storefront-trust {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

@media (min-width: 768px) {
  .storefront-trust { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
}

.trust-tile__title {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
  line-height: 1.3;
}

.trust-tile__meta {
  margin-top: 2px;
  font-size: 11px;
  color: rgba(43, 41, 38, 0.62);
  line-height: 1.4;
}

/* Lens showcase */
.lens-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

@media (min-width: 768px) { .lens-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; } }

.lens-skel { aspect-ratio: 4 / 5; border-radius: 8px; }

.lens-card {
  cursor: pointer;
  display: flex;
  flex-direction: column;
  background: var(--porcelain);
  border: 1px solid var(--mist);
  border-radius: 8px;
  overflow: hidden;
  transition: transform var(--motion-base), box-shadow var(--motion-base), border-color var(--motion-base);
}

.lens-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-soft);
  border-color: rgba(184, 138, 68, 0.45);
}

.lens-card__media {
  aspect-ratio: 4 / 3;
  background: linear-gradient(145deg, var(--ivory), var(--mist));
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 18px;
}

.lens-card__image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  transition: transform var(--motion-slow) var(--easing-standard);
}

.lens-card:hover .lens-card__image { transform: scale(1.05); }

.lens-card__body {
  padding: 14px 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.lens-card__title {
  font-size: 14px;
  font-weight: 600;
  color: var(--ink);
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 36px;
}

.lens-card__cta {
  margin-top: 10px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--gold);
}

/* Blog */
.blog-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

@media (min-width: 768px) { .blog-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; } }

.blog-card { cursor: pointer; }
.blog-card--feature { grid-column: span 2; }
@media (min-width: 768px) { .blog-card--feature { grid-column: auto; } }

.blog-card__media {
  aspect-ratio: 4 / 3;
  overflow: hidden;
  border-radius: 8px;
  margin-bottom: 12px;
  position: relative;
}

.blog-card__media--wide { aspect-ratio: 16 / 7; }
@media (min-width: 768px) { .blog-card__media--wide { aspect-ratio: 4 / 3; } }

.blog-card__image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform var(--motion-slow) var(--easing-standard);
}

.blog-card:hover .blog-card__image { transform: scale(1.04); }

.blog-card__title {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: clamp(0.95rem, 0.85rem + 0.4vw, 1.25rem);
  font-weight: 600;
  color: var(--ink);
  line-height: 1.25;
  margin-bottom: 6px;
  transition: color var(--motion-base);
}

.blog-card:hover .blog-card__title { color: var(--gold); }

.blog-card__meta {
  font-size: 13px;
  color: rgba(43, 41, 38, 0.60);
  line-height: 1.55;
  display: none;
}

@media (min-width: 768px) {
  .blog-card__meta {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
}

/* Testimonials */
.testimonial-rail {
  display: flex;
  gap: 14px;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  padding-bottom: 8px;
  scrollbar-width: thin;
  -webkit-overflow-scrolling: touch;
}

@media (min-width: 768px) {
  .testimonial-rail {
    overflow-x: visible;
    scroll-snap-type: none;
    flex-wrap: wrap;
    justify-content: center;
    gap: 20px;
  }
}

.testimonial-card {
  flex-shrink: 0;
  width: 78vw;
  max-width: 320px;
  scroll-snap-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 24px 20px 20px;
  background: var(--porcelain);
  border: 1px solid var(--mist);
  border-radius: 8px;
}

@media (min-width: 768px) { .testimonial-card { width: clamp(280px, 28vw, 320px); } }

.testimonial-card__quote {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 999px;
  background: var(--graphite);
  color: var(--gold);
  font-size: 18px;
}

.testimonial-card__stars {
  margin-top: 12px;
  display: flex;
  gap: 2px;
  color: #fbbf24;
  font-size: 14px;
}

.testimonial-card__body {
  margin-top: 10px;
  font-style: italic;
  font-size: 13px;
  line-height: 1.55;
  color: rgba(43, 41, 38, 0.78);
  display: -webkit-box;
  -webkit-line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.testimonial-card__foot {
  margin-top: 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.testimonial-card__avatar {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  background: var(--ivory);
  color: var(--gold);
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.testimonial-card__name { font-size: 12px; font-weight: 600; color: var(--ink); }

.testimonial-card__role {
  font-size: 9px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: rgba(43, 41, 38, 0.45);
}

/* ════════════════════════════════════════════════════════════════════════
   CATALOG — toolbar, chips, filter sheet
   ════════════════════════════════════════════════════════════════════════ */
.catalog-toolbar {
  position: sticky;
  top: var(--header-height, 72px);
  z-index: 30;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  margin-top: 12px;
  border-radius: 12px;
  border: 1px solid var(--mist);
  background: rgba(252, 250, 246, 0.96);
  backdrop-filter: blur(12px);
  box-shadow: var(--shadow-card);
}

.catalog-toolbar__count { font-size: 13px; color: var(--graphite); }
.catalog-toolbar__count strong { color: var(--ink); font-weight: 700; }

.catalog-toolbar__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.catalog-toolbar__btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 999px;
  border: 1px solid var(--mist);
  background: var(--porcelain);
  color: var(--ink);
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.10em;
  min-height: 36px;
  transition: background-color var(--motion-base), border-color var(--motion-base), color var(--motion-base);
}

.catalog-toolbar__btn:hover { border-color: var(--ink); }
.catalog-toolbar__btn--active {
  background: var(--ink);
  color: var(--ivory);
  border-color: var(--ink);
}

.catalog-toolbar__count-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--gold);
  color: var(--ink);
  font-size: 10px;
  font-weight: 700;
  line-height: 1;
}

.catalog-toolbar__select {
  position: relative;
  display: inline-flex;
  align-items: center;
}

.catalog-toolbar__select select {
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  padding: 8px 36px 8px 14px;
  border-radius: 999px;
  border: 1px solid var(--mist);
  background: var(--porcelain);
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
  min-height: 36px;
  cursor: pointer;
}

.catalog-toolbar__select select:hover { border-color: rgba(184, 138, 68, 0.45); }
.catalog-toolbar__select select:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(184, 138, 68, 0.13); }

.catalog-toolbar__select .material-symbols-outlined {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: rgba(43, 41, 38, 0.55);
  font-size: 18px;
}

@media (max-width: 480px) {
  .catalog-toolbar { padding: 10px 10px; gap: 8px; }
  .catalog-toolbar__btn { padding: 8px 10px; }
  .catalog-toolbar__select select { padding: 8px 28px 8px 10px; font-size: 11px; max-width: 132px; }
}

.catalog-cats {
  display: flex;
  flex-wrap: nowrap;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 4px;
  margin: 0 -16px;
  padding-left: 16px;
  padding-right: 16px;
  scrollbar-width: thin;
  -webkit-overflow-scrolling: touch;
}

@media (min-width: 768px) {
  .catalog-cats {
    flex-wrap: wrap;
    overflow-x: visible;
    margin: 0;
    padding-left: 0;
    padding-right: 0;
  }
}

.catalog-cats .chip { white-space: nowrap; flex-shrink: 0; }

.catalog-applied {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.catalog-applied__label {
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: rgba(43, 41, 38, 0.62);
  margin-right: 4px;
}

.catalog-filter-backdrop {
  position: fixed;
  inset: 0;
  z-index: 60;
  background: rgba(21, 18, 14, 0.55);
  backdrop-filter: blur(2px);
}

.catalog-filter {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 70;
  max-height: 88vh;
  overflow-y: auto;
  padding: 0 16px 16px;
  background: var(--porcelain);
  border-top-left-radius: 16px;
  border-top-right-radius: 16px;
  box-shadow: 0 -20px 60px rgba(21, 18, 14, 0.18);
}

@media (min-width: 768px) {
  .catalog-filter {
    position: relative;
    inset: auto;
    margin: 8px 0 0;
    border-radius: 12px;
    border: 1px solid var(--mist);
    box-shadow: var(--shadow-soft);
    padding: 20px 24px 24px;
    max-height: none;
    z-index: 1;
  }
}

.catalog-filter__head {
  position: sticky;
  top: 0;
  background: var(--porcelain);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0 14px;
  border-bottom: 1px solid var(--mist);
  margin-bottom: 16px;
}

.catalog-filter__title { margin: 0; }

.catalog-filter__grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}

@media (min-width: 480px) { .catalog-filter__grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1024px) { .catalog-filter__grid { grid-template-columns: repeat(3, 1fr); } }

.catalog-filter__field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.catalog-filter__field--price legend { padding: 0; margin-bottom: 6px; }

.catalog-filter__price {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 8px;
}

.catalog-filter__price-sep {
  color: rgba(43, 41, 38, 0.55);
  font-weight: 700;
}

.catalog-filter__check {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  font-weight: 600;
  color: var(--graphite);
  padding: 12px 14px;
  border: 1px solid var(--mist);
  border-radius: 8px;
  background: var(--porcelain);
  min-height: var(--tap-target);
  cursor: pointer;
}

.catalog-filter__check input[type="checkbox"] {
  width: 18px;
  height: 18px;
  accent-color: var(--gold);
}

.catalog-filter__foot {
  position: sticky;
  bottom: 0;
  background: var(--porcelain);
  padding-top: 14px;
  margin-top: 18px;
  border-top: 1px solid var(--mist);
  display: grid;
  grid-template-columns: 1fr 2fr;
  gap: 10px;
}

@media (min-width: 768px) {
  .catalog-filter__foot {
    position: static;
    grid-template-columns: auto 1fr;
    justify-content: flex-end;
  }
}

/* ════════════════════════════════════════════════════════════════════════
   SHARED — product grid, card, compare bar, footer
   ════════════════════════════════════════════════════════════════════════ */
.product-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

@media (min-width: 768px) { .product-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; } }
@media (min-width: 1024px) { .product-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
@media (min-width: 1280px) { .product-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
@media (min-width: 1536px) { .product-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); } }

.product-card {
  position: relative;
  display: flex;
  flex-direction: column;
  background: #fff;
  border: 1px solid var(--mist);
  border-radius: 10px;
  overflow: hidden;
  cursor: pointer;
  transition: border-color var(--motion-base), box-shadow var(--motion-base);
}

.product-card:hover {
  border-color: rgba(184, 138, 68, 0.45);
  box-shadow: var(--shadow-soft);
}

.product-card__media {
  position: relative;
  aspect-ratio: 4 / 5;
  overflow: hidden;
  background: linear-gradient(145deg, var(--ivory), var(--mist));
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px;
}

@media (min-width: 768px) { .product-card__media { padding: 24px; } }

.product-card__image {
  width: 100%;
  height: 100%;
  object-fit: contain;
  mix-blend-mode: multiply;
  transition: transform var(--motion-slow) var(--easing-standard);
}

.product-card:hover .product-card__image { transform: scale(1.04); }
.product-card__image--out { opacity: 0.40; filter: grayscale(1); }

.product-card__quick {
  position: absolute;
  top: 8px;
  right: 8px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  opacity: 1;
  transition: opacity var(--motion-base);
}

@media (min-width: 768px) {
  .product-card__quick { opacity: 0; }
  .product-card:hover .product-card__quick,
  .product-card:focus-within .product-card__quick { opacity: 1; }
}

.product-card__quick-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.95);
  color: var(--gold);
  border: 1px solid rgba(184, 138, 68, 0.20);
  backdrop-filter: blur(6px);
  box-shadow: var(--shadow-card);
  transition: background-color var(--motion-base), color var(--motion-base);
}

.product-card__quick-btn:hover { background: var(--gold-soft); }
.product-card__quick-btn--active {
  background: var(--gold-soft);
  color: var(--gold);
  border-color: rgba(184, 138, 68, 0.50);
}

.product-card__badges {
  position: absolute;
  top: 8px;
  left: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: flex-start;
  pointer-events: none;
}

.product-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  line-height: 1;
  border: 1px solid transparent;
}

.product-badge .material-symbols-outlined { font-size: 12px; }

.product-badge--ink {
  background: rgba(21, 18, 14, 0.86);
  color: #fff;
  border-color: rgba(184, 138, 68, 0.30);
  backdrop-filter: blur(4px);
}

.product-badge--gold { background: var(--gold); color: var(--ink); }
.product-badge--red { background: #dc2626; color: #fff; }

.product-card__out-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.18);
  backdrop-filter: blur(2px);
}

.product-card__out-pill {
  padding: 6px 16px;
  border-radius: 6px;
  background: rgba(15, 10, 5, 0.86);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.18em;
}

.product-card__body {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex-grow: 1;
}

@media (min-width: 768px) { .product-card__body { padding: 16px; } }

.product-card__brand {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  color: rgba(43, 41, 38, 0.62);
}

.product-card__name {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: clamp(14px, 0.85rem + 0.4vw, 18px);
  font-weight: 600;
  color: var(--ink);
  line-height: 1.2;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  min-height: 2.4em;
}

.product-card__meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 11px;
  color: rgba(43, 41, 38, 0.65);
}

.product-card__meta li { display: inline-flex; align-items: center; gap: 6px; }
.product-card__meta .material-symbols-outlined { color: var(--gold); font-size: 14px; }

.product-card__foot {
  margin-top: auto;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 8px;
}

.product-card__price {
  font-size: clamp(12px, 0.78rem + 0.3vw, 16px);
  font-weight: 700;
  color: var(--ink);
}

.product-card__price--info {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.10em;
  color: var(--gold);
}

.product-card__stock {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 9px;
  font-weight: 700;
  color: var(--olive);
}

.product-card__stock-dot {
  width: 6px;
  height: 6px;
  border-radius: 999px;
  background: var(--olive);
}

/* Skeleton */
.product-card-skeleton {
  border-radius: 10px;
  border: 1px solid var(--mist);
  overflow: hidden;
  background: var(--porcelain);
}

.product-card-skeleton__image { aspect-ratio: 4 / 5; border-radius: 0; }

.product-card-skeleton__body {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

/* Compare bar */
.compare-bar {
  position: fixed;
  z-index: 40;
  left: 16px;
  right: 16px;
  bottom: calc(80px + env(safe-area-inset-bottom, 0px));
  padding: 12px 14px;
  border-radius: 12px;
  border: 1px solid var(--mist);
  background: var(--porcelain);
  box-shadow: var(--shadow-soft);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

@media (min-width: 768px) {
  .compare-bar {
    left: 50%;
    right: auto;
    transform: translateX(-50%);
    bottom: 24px;
    width: min(720px, 96vw);
  }
}

.compare-bar__info { display: flex; align-items: center; gap: 10px; min-width: 0; }
.compare-bar__info .material-symbols-outlined { color: var(--gold); flex-shrink: 0; }

.compare-bar__title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: var(--ink);
}

.compare-bar__list {
  margin-top: 2px;
  font-size: 11px;
  color: rgba(43, 41, 38, 0.65);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 40vw;
}

.compare-bar__actions { display: flex; gap: 8px; flex-shrink: 0; }

/* Catalog footer */
.catalog-foot {
  margin-top: clamp(24px, 3vw, 40px);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding-bottom: 16px;
}

.catalog-foot__loading {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 600;
  color: var(--graphite);
}

.catalog-foot__loading .material-symbols-outlined { color: var(--gold); }

.catalog-foot__end {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.catalog-foot__end .divider-mute { width: 56px; }

/* Empty state error variant */
.empty-state--error {
  border-color: rgba(220, 38, 38, 0.30);
  background: rgba(220, 38, 38, 0.04);
  color: var(--graphite);
}

.empty-state--error .material-symbols-outlined { color: rgba(220, 38, 38, 0.55); }

/* sr-only */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

/* Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity var(--motion-base) var(--easing-standard); }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.sheet-enter-active, .sheet-leave-active { transition: transform var(--motion-slow) var(--easing-standard); }
.sheet-enter-from, .sheet-leave-to { transform: translateY(100%); }

@media (min-width: 768px) {
  .sheet-enter-from, .sheet-leave-to { transform: none; opacity: 0; }
}
</style>
