<script setup lang="ts">
// ─────────────────────────────────────────────────────────────────────────
// FIXME P1-11 (Phase 3): God component. Phase 6 redesign — re-layout
// template, semua state/watch/computed/method dipertahankan persis.
// Lihat: medio-fe/src/views/REFACTOR_PLAN.md
// ─────────────────────────────────────────────────────────────────────────
import { logger } from '../core/utils/logger';
import { computed, ref, reactive, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCartStore } from '../stores/cartStore';
import { useWishlistStore } from '../stores/wishlistStore';
import { useCompareStore } from '../stores/compareStore';
import { useAuthStore } from '../stores/authStore';
import { productRepository } from '../repositories/ProductRepository';
import { reviewRepository, type Review } from '../repositories/ReviewRepository';
import { opticalRepository, type LensCoating } from '../repositories/OpticalRepository';
import { prescriptionRepository, type PrescriptionProfile } from '../repositories/PrescriptionRepository';
import type { LensOption } from '../repositories/ProductRepository';
import type { Product } from '../types';
import PageHero from '../components/layout/PageHero.vue';

import { resolveImageUrl } from '../core/utils/image';
import { useToast } from '../composables/useToast';
import { useSeoMeta } from '../composables/useSeoMeta';
import { useAnalytics } from '../composables/useAnalytics';
import { formatMoney } from '../composables/useFormatMoney';

const { showToast } = useToast();

const route = useRoute();
const router = useRouter();
const cartStore = useCartStore();
const wishlistStore = useWishlistStore();
const compareStore = useCompareStore();
const authStore = useAuthStore();
const { setSeo, setJsonLd, buildProductJsonLd } = useSeoMeta();
const { trackProductViewed } = useAnalytics();

const product = ref<Product | null>(null);
const isLoading = ref(true);
const lenses = ref<Product[]>([]);
const isLensesLoading = ref(false);
const similarFrames = ref<Product[]>([]);
const compatibleLenses = ref<Product[]>([]);
const relatedProducts = ref<Product[]>([]);
const productReviews = ref<Review[]>([]);
const reviewSummary = ref({ avg_rating: 0, total_reviews: 0 });

const formState = reactive({
  color: null as any,
  size: '',
  pdType: 'single',
  prescription: {
    od: { sph: '0.00', cyl: '0.00', axis: '', add: '0.00' },
    os: { sph: '0.00', cyl: '0.00', axis: '', add: '0.00' },
    pdRight: '',
    pdLeft: '',
    pdSingle: '',
  },
});

const isLensModalOpen = ref(false);
const isLensChoiceModalOpen = ref(false);

const allCoatings = ref<LensCoating[]>([]);
const isCoatingsLoading = ref(false);
const selectedLensOption = ref<LensOption | null>(null);
const selectedCoating = ref<LensCoating | null>(null);
const configuratorStep = ref<'lens' | 'coating'>('lens');
const configuratorTotalPrice = computed(() => {
  if (!product.value) return 0;
  const base = product.value.price || 0;
  const lensPrice = selectedLensOption.value?.base_price || 0;
  const coatingPrice = selectedCoating.value?.price || 0;
  return base + lensPrice + coatingPrice;
});

const loadCoatings = async () => {
  if (allCoatings.value.length > 0) return;
  isCoatingsLoading.value = true;
  try {
    allCoatings.value = await opticalRepository.getLensCoatings();
  } catch (e) {
    logger.error('Failed to load coatings', e);
  } finally {
    isCoatingsLoading.value = false;
  }
};

const openLensConfigurator = async () => {
  selectedLensOption.value = null;
  selectedCoating.value = null;
  configuratorStep.value = 'lens';
  await loadCoatings();
  isLensModalOpen.value = true;
};

const selectLensOption = (opt: LensOption) => {
  selectedLensOption.value = opt;
  configuratorStep.value = 'coating';
};

const skipCoating = () => {
  selectedCoating.value = null;
  confirmLensConfiguration();
};

const confirmLensConfiguration = () => {
  executeAddToCart();
};

const supportsAddInConfigurator = computed(() => selectedLensOption.value?.type === 'progressive');
const usesOdAxis = computed(() => Number(formState.prescription.od.cyl || 0) !== 0);
const usesOsAxis = computed(() => Number(formState.prescription.os.cyl || 0) !== 0);
const activeImage = ref(0);
const addedToCart = ref(false);

const productCategoryContext = computed(() => {
  const category = (product.value as any)?.category;
  return `${category?.slug ?? ''} ${category?.name ?? ''}`.trim().toLowerCase();
});

const isFrameProduct = computed(() => {
  if (!product.value) return false;
  return productCategoryContext.value.includes('frame')
    || Boolean(
      product.value.frame_shape
      || product.value.frame_material
      || product.value.frame_color
      || product.value.face_size_fit
      || product.value.lens_width
      || product.value.bridge_width
      || product.value.temple_length
      || product.value.frame_width,
    );
});

const isStandaloneLensProduct = computed(() => {
  if (!product.value) return false;
  return !isFrameProduct.value
    && (
      productCategoryContext.value.includes('lensa')
      || productCategoryContext.value.includes('softlens')
    );
});

const primaryRecommendations = computed(() => (
  isFrameProduct.value ? similarFrames.value : relatedProducts.value
));

const primaryRecommendationTitle = computed(() => (
  isFrameProduct.value ? 'Frame Serupa' : 'Produk Serupa'
));

const showCompatibleLenses = computed(() => (
  isFrameProduct.value && compatibleLenses.value.length > 0
));

const hasRecommendationSection = computed(() => (
  primaryRecommendations.value.length > 0 || showCompatibleLenses.value
));

const isAppointmentProduct = computed(() => {
  if (!product.value) return false;
  const category = (product.value as any)?.category;
  const categoryContext = `${category?.slug ?? ''} ${category?.name ?? ''}`.toLowerCase();
  const productContext = `${product.value.slug} ${product.value.name}`.toLowerCase();
  return categoryContext.includes('paket-pemeriksaan')
    || productContext.includes('paket-pemeriksaan')
    || productContext.includes('pemeriksaan-mata')
    || productContext.includes('konsultasi');
});

onMounted(async () => {
  const slug = route.params.slug as string;
  try {
    const data = await productRepository.getProductBySlug(slug);
    product.value = data;

    const resolvedImages = (data as any).resolved_images || [];
    const firstImage = resolvedImages[0]?.url || resolvedImages[0] || null;
    setSeo({
      title: (data as any).meta_title || data.name,
      description: (data as any).meta_description || (data.description ? data.description.substring(0, 155) : undefined),
      ogType: 'product',
      ogImage: (data as any).og_image || firstImage || undefined,
      ogUrl: window.location.href,
    });
    setJsonLd(buildProductJsonLd({
      name: data.name,
      description: data.description,
      slug: data.slug,
      price: data.price,
      stock: data.stock,
      brand: (data as any).brand,
      sku: (data as any).sku,
      gtin: (data as any).gtin,
      images: resolvedImages.map((img: any) => img?.url || img),
      rating: reviewSummary.value.avg_rating || undefined,
      reviewCount: reviewSummary.value.total_reviews || undefined,
    }));

    trackProductViewed(data.id, data.slug, data.name);
    try {
      const reviews = await reviewRepository.getProductReviews(slug);
      productReviews.value = reviews.reviews;
      reviewSummary.value = {
        avg_rating: reviews.avg_rating,
        total_reviews: reviews.total_reviews,
      };
    } catch (reviewError) {
      logger.warn('Failed to fetch reviews', reviewError);
    }

    if (data.variants) {
      if (data.variants.colors && data.variants.colors.length > 0) {
        formState.color = data.variants.colors[0];
      }
      if (data.variants.sizes && data.variants.sizes.length > 0) {
        formState.size = data.variants.sizes[0];
      }
    }
    if (data.is_prescription_required) {
      fetchLenses();
      loadPrescriptions();
    }
    fetchRecommendations(slug);
  } catch (error) {
    logger.error('Failed to fetch product', error);
    router.push('/products');
  } finally {
    isLoading.value = false;
  }
});

const fetchLenses = async () => {
  try {
    isLensesLoading.value = true;
    const response = await productRepository.getProducts({ category: 'lensa-kacamata' });
    lenses.value = response.data || response;
  } catch (error) {
    logger.error('Failed to fetch lenses', error);
  } finally {
    isLensesLoading.value = false;
  }
};

const fetchRecommendations = async (slug: string) => {
  try {
    const recommendations = await productRepository.getRecommendations(slug);
    similarFrames.value = recommendations.similar_frames || [];
    compatibleLenses.value = recommendations.compatible_lenses || [];
    relatedProducts.value = recommendations.related_products || [];
    if (product.value && recommendations.compatible_lens_options) {
      (product.value as any).compatible_lens_options = recommendations.compatible_lens_options;
    }
  } catch (error) {
    logger.warn('Failed to fetch product recommendations', error);
  }
};

const handleAddToCartClick = () => {
  if (!product.value) return;

  if (isAppointmentProduct.value) {
    router.push({
      path: '/appointment',
      query: {
        service: 'eye_test',
        source_product: product.value.slug,
        source_label: product.value.name,
      },
    });
    return;
  }

  if (productCategoryContext.value.includes('softlens') || productCategoryContext.value.includes('lensa-kontak')) {
    executeAddToCart();
    return;
  }

  if (isStandaloneLensProduct.value && product.value.is_prescription_required) {
    isLensChoiceModalOpen.value = true;
    return;
  }

  if (isFrameProduct.value && product.value.is_prescription_required) {
    openLensConfigurator();
    return;
  }

  if (isFrameProduct.value && (product.value as any).compatible_lens_options?.length > 0) {
    openLensConfigurator();
    return;
  }

  executeAddToCart();
};

const executeAddToCart = (selectedLens: any = null) => {
  if (!product.value) return;

  if (product.value.is_prescription_required) {
    const prescriptionError = validatePrescriptionInput();
    if (prescriptionError) {
      showToast(prescriptionError, 'error');
      return;
    }
  }

  const cartItem = {
    ...product.value,
    variant: {
      color: formState.color?.name,
      size: formState.size,
    },
    lens_option_id: selectedLensOption.value?.id ?? null,
    lens_coating_id: selectedCoating.value?.id ?? null,
    prescription_profile_id: selectedPrescriptionProfileId.value ?? null,
  };

  cartStore.addToCart(
    cartItem as any,
    product.value.is_prescription_required ? formState.prescription : undefined,
    selectedLens,
  );

  isLensModalOpen.value = false;
  isLensChoiceModalOpen.value = false;
  selectedLensOption.value = null;
  selectedCoating.value = null;
  addedToCart.value = true;
  showToast('Produk berhasil ditambahkan ke keranjang!', 'success');
  setTimeout(() => { addedToCart.value = false; }, 2500);
};

const validatePrescriptionInput = () => {
  if (usesOdAxis.value) {
    const axis = Number(formState.prescription.od.axis);
    if (!Number.isInteger(axis) || axis < 1 || axis > 180) {
      return 'Axis kanan wajib diisi dengan angka 1 sampai 180 jika CYL kanan diisi.';
    }
  }

  if (usesOsAxis.value) {
    const axis = Number(formState.prescription.os.axis);
    if (!Number.isInteger(axis) || axis < 1 || axis > 180) {
      return 'Axis kiri wajib diisi dengan angka 1 sampai 180 jika CYL kiri diisi.';
    }
  }

  if (formState.pdType === 'single') {
    const pdSingle = Number(formState.prescription.pdSingle);
    if (!Number.isFinite(pdSingle) || pdSingle < 50 || pdSingle > 75) {
      return 'PD tunggal wajib diisi dalam rentang 50 sampai 75 mm.';
    }
  } else {
    const pdRight = Number(formState.prescription.pdRight);
    const pdLeft = Number(formState.prescription.pdLeft);

    if (!Number.isFinite(pdRight) || pdRight < 25 || pdRight > 38) {
      return 'PD kanan wajib diisi dalam rentang 25 sampai 38 mm.';
    }

    if (!Number.isFinite(pdLeft) || pdLeft < 25 || pdLeft > 38) {
      return 'PD kiri wajib diisi dalam rentang 25 sampai 38 mm.';
    }
  }

  if (!supportsAddInConfigurator.value) {
    formState.prescription.od.add = '0.00';
    formState.prescription.os.add = '0.00';
  }

  return null;
};

const chooseFrameBeforeCheckout = () => {
  isLensChoiceModalOpen.value = false;
  router.push('/products');
};

const isWishlisted = computed(() => product.value ? wishlistStore.isWishlisted(product.value.id) : false);
const isCompared = computed(() => product.value ? compareStore.isCompared(product.value.id) : false);

const toggleWishlist = async () => {
  if (!product.value) return;
  const added = await wishlistStore.toggleWishlist(product.value);
  showToast(
    added ? 'Produk ditambahkan ke wishlist.' : 'Produk dihapus dari wishlist.',
    'success',
  );
};

const toggleCompare = () => {
  if (!product.value) return;
  const result = compareStore.toggle(product.value);
  if (result === 'full') {
    showToast('Maksimal 4 produk untuk dibandingkan.', 'error');
    return;
  }
  showToast(result === 'added' ? 'Produk ditambahkan ke compare.' : 'Produk dihapus dari compare.', 'success');
};

const sphOptions = ['-2.00', '-1.75', '-1.50', '-1.25', '-1.00', '-0.75', '-0.50', '-0.25', '0.00', '+0.25', '+0.50', '+0.75', '+1.00', '+1.25', '+1.50', '+1.75', '+2.00'];

const formatSphValue = (val: number | string | null | undefined): string => {
  if (val == null || val === '') return '0.00';
  const num = parseFloat(String(val));
  if (isNaN(num)) return '0.00';
  if (num === 0) return '0.00';
  const formatted = Math.abs(num).toFixed(2);
  return num > 0 ? `+${formatted}` : `-${formatted}`;
};

// Saved prescription profiles
const prescriptions = ref<PrescriptionProfile[]>([]);
const selectedPrescriptionProfileId = ref<number | null>(null);

const loadPrescriptions = async () => {
  if (!authStore.user) return;
  try {
    prescriptions.value = await prescriptionRepository.list();
  } catch {
    // silent — user mungkin belum login atau belum punya resep
  }
};

const applyPrescriptionProfile = (profile: PrescriptionProfile) => {
  if (selectedPrescriptionProfileId.value === profile.id) {
    selectedPrescriptionProfileId.value = null;
    return;
  }
  selectedPrescriptionProfileId.value = profile.id;
  formState.prescription.od.sph = formatSphValue(profile.right_sphere);
  formState.prescription.od.cyl = formatSphValue(profile.right_cylinder);
  formState.prescription.od.axis = profile.right_axis != null ? String(profile.right_axis) : '';
  formState.prescription.od.add = formatSphValue(profile.right_add);
  formState.prescription.os.sph = formatSphValue(profile.left_sphere);
  formState.prescription.os.cyl = formatSphValue(profile.left_cylinder);
  formState.prescription.os.axis = profile.left_axis != null ? String(profile.left_axis) : '';
  formState.prescription.os.add = formatSphValue(profile.left_add);
  if (profile.pd_right != null && profile.pd_left != null) {
    formState.pdType = 'dual';
    formState.prescription.pdRight = String(profile.pd_right);
    formState.prescription.pdLeft = String(profile.pd_left);
  } else if (profile.pd_single != null) {
    formState.pdType = 'single';
    formState.prescription.pdSingle = String(profile.pd_single);
  }
};

watch(() => formState.prescription.od.cyl, (cylinder) => {
  if (Number(cylinder || 0) === 0) formState.prescription.od.axis = '';
});

watch(() => formState.prescription.os.cyl, (cylinder) => {
  if (Number(cylinder || 0) === 0) formState.prescription.os.axis = '';
});

watch(() => supportsAddInConfigurator.value, (supportsAdd) => {
  if (!supportsAdd) {
    formState.prescription.od.add = '0.00';
    formState.prescription.os.add = '0.00';
  }
});

watch(() => formState.pdType, (pdType) => {
  if (pdType === 'single') {
    formState.prescription.pdRight = '';
    formState.prescription.pdLeft = '';
    return;
  }
  formState.prescription.pdSingle = '';
});

// Lock body scroll when modal open
watch([isLensModalOpen, isLensChoiceModalOpen], ([a, b]) => {
  if (a || b) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});

// Esc key handler
const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isLensModalOpen.value) isLensModalOpen.value = false;
    else if (isLensChoiceModalOpen.value) isLensChoiceModalOpen.value = false;
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  document.body.style.overflow = '';
});

const getProductPromos = (p: Product | null) => {
  if (!p) return { buyPromos: [], discountPromos: [] };

  const buyPromos = [...(p.buy_promos || []), ...(p.buy_promos_many || [])];
  const discountPromos = [...(p.discount_promos || []), ...(p.discount_promos_many || [])];

  if (p.brand && cartStore.activePromos.length > 0) {
    cartStore.activePromos.forEach(promo => {
      const isDuplicate = [...buyPromos, ...discountPromos].some(item => item.id === promo.id);
      if (isDuplicate) return;
      if (promo.type === 'buy_x_get_y' && promo.buy_brands?.includes(p.brand)) {
        buyPromos.push(promo);
      } else if (promo.type === 'product_discount' && promo.discount_brands?.includes(p.brand)) {
        discountPromos.push(promo);
      }
    });
  }

  return { buyPromos, discountPromos };
};

const formatPromoDescription = (desc: string) => {
  if (!desc) return '';
  return desc.replace(/(\d+)\.00%/g, '$1%');
};

const formatProductLabel = (value: string | number | null | undefined) => {
  if (value === null || value === undefined || value === '') return '';

  const stringValue = String(value);
  const labels: Record<string, string> = {
    men: 'Pria',
    women: 'Wanita',
    unisex: 'Unisex',
    kids: 'Anak',
    small: 'Small',
    medium: 'Medium',
    large: 'Large',
  };

  return labels[stringValue] || stringValue
    .split(/[-_]/)
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
};

const frameSizeRows = computed(() => {
  const p = product.value;
  if (!p) return [];
  return [
    { label: 'Lebar Lensa', value: p.lens_width },
    { label: 'Bridge', value: p.bridge_width },
    { label: 'Panjang Gagang', value: p.temple_length },
    { label: 'Lebar Frame', value: p.frame_width },
  ].filter(row => row.value !== null && row.value !== undefined);
});

const frameProfileRows = computed(() => {
  const p = product.value;
  if (!p) return [];
  return [
    { label: 'Bentuk', value: p.frame_shape },
    { label: 'Material', value: p.frame_material },
    { label: 'Warna', value: p.frame_color },
    { label: 'Gender', value: p.gender },
    { label: 'Fit Wajah', value: p.face_size_fit },
  ].filter(row => row.value !== null && row.value !== undefined && row.value !== '');
});

const hasFrameGuide = computed(() => frameSizeRows.value.length > 0 || frameProfileRows.value.length > 0);

// Promo display helpers (memoised — avoid 3x getProductPromos in template)
const productPromos = computed(() => getProductPromos(product.value));
const primaryBuyPromo = computed(() => productPromos.value.buyPromos[0]);
const primaryDiscountPromo = computed(() => productPromos.value.discountPromos[0]);

// Accordion state
const openSpec = ref(true);
const openShipping = ref(false);
const openWarranty = ref(false);

// Add to cart label
const addToCartLabel = computed(() => {
  if (addedToCart.value) return 'Ditambahkan!';
  if (isAppointmentProduct.value) return 'Booking Jadwal Konsultasi';
  if (!product.value || product.value.stock <= 0) return 'Stok Habis';
  if (isStandaloneLensProduct.value) return 'Lanjutkan Pembelian Lensa';
  if (isFrameProduct.value && (product.value as any).compatible_lens_options?.length > 0) {
    return selectedLensOption.value ? 'Tambah ke Keranjang' : 'Pilih Lensa & Coating';
  }
  return 'Tambah ke Keranjang';
});

// Mobile-friendly shorter label untuk sticky CTA bar
const addToCartLabelMobile = computed(() => {
  if (addedToCart.value) return 'Ditambahkan!';
  if (isAppointmentProduct.value) return 'Booking Konsultasi';
  if (!product.value || product.value.stock <= 0) return 'Stok Habis';
  if (isStandaloneLensProduct.value) return 'Beli Lensa';
  if (isFrameProduct.value && (product.value as any).compatible_lens_options?.length > 0) {
    return selectedLensOption.value ? '+ Keranjang' : 'Pilih Lensa';
  }
  return '+ Keranjang';
});

const addToCartIcon = computed(() => {
  if (addedToCart.value) return 'check_circle';
  if (isAppointmentProduct.value) return 'calendar_today';
  if (!product.value || product.value.stock <= 0) return 'block';
  return 'shopping_bag';
});

const canAddToCart = computed(() => {
  if (!product.value) return false;
  if (product.value.is_not_for_sale && !isAppointmentProduct.value) return false;
  return isAppointmentProduct.value || product.value.stock > 0;
});

const goPrevImage = () => {
  if (activeImage.value > 0) activeImage.value--;
};

const goNextImage = () => {
  if (product.value && product.value.images && activeImage.value < product.value.images.length - 1) {
    activeImage.value++;
  }
};
</script>

<template>
  <!-- Loading -->
  <main v-if="isLoading" class="pdp-loading">
    <div class="pdp-spinner"></div>
    <p>Memuat produk...</p>
  </main>

  <main v-else-if="product" class="pdp">
    <PageHero
      :title="product.name"
      :subtitle="product.brand ? (product.brand + ' — Detail produk dan pilihan lensa.') : 'Detail produk dan pilihan lensa.'"
      :breadcrumbs="[{ label: 'Katalog Produk', to: '/products' }, { label: product.brand || 'Produk' }]"
      back-to="/products"
      back-label="Kembali ke Koleksi"
    />

    <div class="container-premium pdp__main">
      <div class="pdp__layout">
        <!-- ──────────────── LEFT: Gallery ──────────────── -->
        <section class="pdp__gallery" aria-label="Galeri produk">
          <div class="pdp-gallery__main">
            <img
              :src="resolveImageUrl(product.images?.[activeImage])"
              :alt="product.name"
              class="pdp-gallery__image"
              loading="eager"
              decoding="async"
            />

            <button
              v-if="product.images?.length > 1"
              type="button"
              class="pdp-gallery__nav pdp-gallery__nav--prev"
              :class="{ 'pdp-gallery__nav--disabled': activeImage === 0 }"
              :disabled="activeImage === 0"
              aria-label="Gambar sebelumnya"
              @click="goPrevImage"
            >
              <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
            </button>
            <button
              v-if="product.images?.length > 1"
              type="button"
              class="pdp-gallery__nav pdp-gallery__nav--next"
              :class="{ 'pdp-gallery__nav--disabled': activeImage >= (product.images?.length ?? 1) - 1 }"
              :disabled="activeImage >= (product.images?.length ?? 1) - 1"
              aria-label="Gambar berikutnya"
              @click="goNextImage"
            >
              <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
            </button>

            <div v-if="product.images?.length > 1" class="pdp-gallery__counter" aria-live="polite">
              {{ activeImage + 1 }} / {{ product.images.length }}
            </div>
          </div>

          <div v-if="product.images?.length > 1" class="pdp-gallery__thumbs">
            <button
              v-for="(img, index) in product.images"
              :key="index"
              type="button"
              class="pdp-gallery__thumb"
              :class="{ 'pdp-gallery__thumb--active': activeImage === index }"
              :aria-label="`Lihat gambar ${index + 1}`"
              @click="activeImage = index"
            >
              <img
                :src="resolveImageUrl(img)"
                alt=""
                class="pdp-gallery__thumb-img"
                loading="lazy"
                decoding="async"
              />
            </button>
          </div>
        </section>

        <!-- ──────────────── RIGHT: Product Info ──────────────── -->
        <section class="pdp__info" aria-label="Detail produk">
          <header class="pdp__heading">
            <p class="eyebrow">
              Koleksi {{ (product as any).category?.name || 'Optik' }}
            </p>

            <div class="pdp__badges-row">
              <span v-if="product.is_best_seller" class="pdp-badge pdp-badge--ink">
                <span class="material-symbols-outlined" aria-hidden="true">trending_up</span>
                Terlaris
              </span>
              <span v-if="primaryBuyPromo" class="pdp-badge pdp-badge--gold">
                <span class="material-symbols-outlined" aria-hidden="true">redeem</span>
                {{ primaryBuyPromo.buy_quantity && primaryBuyPromo.get_quantity
                  ? `Beli ${primaryBuyPromo.buy_quantity} Gratis ${primaryBuyPromo.get_quantity}`
                  : 'Promo Spesial' }}
              </span>
              <span v-if="primaryDiscountPromo" class="pdp-badge pdp-badge--red">
                <span class="material-symbols-outlined" aria-hidden="true">percent</span>
                {{ primaryDiscountPromo.discount_type === 'percentage'
                  ? `Diskon ${Math.round(Number(primaryDiscountPromo.discount_value))}%`
                  : `Diskon ${formatMoney(Number(primaryDiscountPromo.discount_value))}` }}
              </span>
            </div>

            <h1 class="pdp__title editorial-display">{{ product.brand || 'Optik Medio' }}</h1>
            <p class="pdp__subtitle">{{ product.name }}</p>

            <ul class="pdp__meta">
              <li>
                <span class="material-symbols-outlined" aria-hidden="true">star</span>
                {{ Number(reviewSummary.avg_rating || product.avg_rating || 0).toFixed(1) }}
                · {{ reviewSummary.total_reviews || product.review_count || 0 }} ulasan
              </li>
              <li>
                <span class="material-symbols-outlined" aria-hidden="true">shopping_bag</span>
                {{ Number(product.purchase_count || 0) }} terjual
              </li>
            </ul>

            <div class="pdp__price-row">
              <p v-if="!product.is_not_for_sale" class="pdp__price price-display">
                {{ formatMoney(product.price) }}
              </p>
              <p v-else class="pdp__price-info">Katalog Informasi</p>

              <span v-if="!product.is_not_for_sale" class="pdp__stock"
                :class="product.stock > 0 ? 'pdp__stock--available' : 'pdp__stock--out'">
                <span class="pdp__stock-dot" aria-hidden="true"></span>
                {{ product.stock > 0 ? `Stok: ${product.stock}` : 'Stok Habis' }}
              </span>
            </div>

            <p v-if="primaryBuyPromo?.description || primaryDiscountPromo?.description"
               class="pdp__promo-desc">
              {{ formatPromoDescription(primaryBuyPromo?.description || primaryDiscountPromo?.description || '') }}
            </p>
          </header>

          <!-- Wishlist + Compare row -->
          <div class="pdp__quick-row">
            <button
              type="button"
              class="pdp-quick-btn"
              :class="{ 'pdp-quick-btn--active': isWishlisted }"
              :aria-pressed="isWishlisted"
              @click="toggleWishlist"
            >
              <span class="material-symbols-outlined" aria-hidden="true">
                {{ isWishlisted ? 'favorite' : 'favorite_border' }}
              </span>
              <span>{{ isWishlisted ? 'Tersimpan di Wishlist' : 'Wishlist' }}</span>
            </button>
            <button
              type="button"
              class="pdp-quick-btn"
              :class="{ 'pdp-quick-btn--active pdp-quick-btn--compared': isCompared }"
              :aria-pressed="isCompared"
              @click="toggleCompare"
            >
              <span class="material-symbols-outlined" aria-hidden="true">compare_arrows</span>
              <span>{{ isCompared ? 'Ada di Compare' : 'Bandingkan' }}</span>
            </button>
          </div>

          <!-- Description -->
          <p v-if="product.description" class="pdp__description">
            {{ product.description }}
          </p>

          <!-- Color variants -->
          <div v-if="product.variants?.colors?.length && !product.is_not_for_sale" class="pdp__variant">
            <p class="pdp__variant-label">
              <span>Warna</span>
              <strong>{{ formState.color?.name }}</strong>
            </p>
            <div class="pdp__color-row" role="radiogroup" aria-label="Pilih warna">
              <button
                v-for="color in product.variants.colors"
                :key="color.name"
                type="button"
                class="pdp-color-swatch"
                :class="{ 'pdp-color-swatch--active': formState.color?.name === color.name }"
                :style="{ backgroundColor: color.hex }"
                :aria-label="`Warna ${color.name}`"
                :aria-checked="formState.color?.name === color.name"
                role="radio"
                @click="formState.color = color"
              ></button>
            </div>
          </div>

          <!-- Size variants -->
          <div v-if="product.variants?.sizes?.length && !product.is_not_for_sale" class="pdp__variant">
            <p class="pdp__variant-label"><span>Ukuran</span></p>
            <div class="pdp__size-row" role="radiogroup" aria-label="Pilih ukuran">
              <button
                v-for="size in product.variants.sizes"
                :key="size"
                type="button"
                class="pdp-size-pill"
                :class="{ 'pdp-size-pill--active': formState.size === size }"
                :aria-checked="formState.size === size"
                role="radio"
                @click="formState.size = size"
              >{{ size }}</button>
            </div>
          </div>

          <!-- Prescription notice -->
          <div v-if="product.is_prescription_required && !product.is_not_for_sale" class="alert-base alert-info">
            <span class="material-symbols-outlined pdp__alert-icon" aria-hidden="true">info</span>
            <div>
              <p class="pdp__alert-title">Membutuhkan Resep Optik</p>
              <p class="pdp__alert-body">Produk ini memerlukan resep optik yang valid untuk diproses.</p>
            </div>
          </div>

          <!-- Info-only notice -->
          <div v-if="product.is_not_for_sale" class="surface-elevated pdp__info-only">
            <header>
              <span class="material-symbols-outlined" aria-hidden="true">menu_book</span>
              <p>Katalog Brand Lensa</p>
            </header>
            <p>
              Informasi produk ini merupakan bagian dari katalog brand lensa yang kami gunakan di Optik Medio.
              Produk ini tidak dijual secara terpisah. Untuk konsultasi lebih lanjut mengenai lensa terbaik untuk kebutuhan mata Anda, silakan hubungi tim ahli kami.
            </p>
            <button type="button" class="btn-primary btn-sm">Hubungi CS Optik Medio</button>
          </div>

          <!-- Prescription form -->
          <section
            v-if="product.is_prescription_required && !product.is_not_for_sale"
            class="pdp-rx"
            aria-label="Resep kacamata"
          >
            <header class="pdp-rx__head">
              <h2 class="editorial-h3">Resep Kacamata Anda</h2>
              <p class="text-meta">Isi data resep optik atau pilih dari resep tersimpan.</p>
            </header>

            <!-- Saved profiles -->
            <div v-if="authStore.user && prescriptions.length > 0" class="pdp-rx__profiles">
              <p class="text-meta">Resep Tersimpan</p>
              <ul class="pdp-rx__profile-list">
                <li v-for="profile in prescriptions" :key="profile.id">
                  <button
                    type="button"
                    class="pdp-rx-profile"
                    :class="{ 'pdp-rx-profile--active': selectedPrescriptionProfileId === profile.id }"
                    @click="applyPrescriptionProfile(profile)"
                  >
                    <div class="pdp-rx-profile__body">
                      <p class="pdp-rx-profile__label">{{ profile.label }}</p>
                      <p class="pdp-rx-profile__meta">
                        OD {{ profile.right_sphere ?? '—' }} / {{ profile.right_cylinder ?? '—' }} / {{ profile.right_axis ?? '—' }}
                        ·
                        OS {{ profile.left_sphere ?? '—' }} / {{ profile.left_cylinder ?? '—' }} / {{ profile.left_axis ?? '—' }}
                      </p>
                    </div>
                    <div class="pdp-rx-profile__status">
                      <span v-if="profile.verification_status === 'approved'" class="badge badge-success">Terverifikasi</span>
                      <span v-else-if="profile.verification_status === 'pending'" class="badge badge-gold">Menunggu</span>
                      <span class="material-symbols-outlined pdp-rx-profile__check" aria-hidden="true">
                        {{ selectedPrescriptionProfileId === profile.id ? 'check_circle' : 'radio_button_unchecked' }}
                      </span>
                    </div>
                  </button>
                </li>
              </ul>
              <div class="pdp-rx__divider">
                <span class="divider-rule"></span>
                <span class="text-meta">atau isi manual</span>
                <span class="divider-rule"></span>
              </div>
            </div>

            <!-- Manual prescription input -->
            <div class="pdp-rx__form">
              <div class="pdp-rx__grid"
                   :class="supportsAddInConfigurator ? 'pdp-rx__grid--with-add' : ''">
                <span></span>
                <span class="text-meta">SPH</span>
                <span class="text-meta">CYL</span>
                <span class="text-meta">Axis</span>
                <span v-if="supportsAddInConfigurator" class="text-meta">ADD</span>

                <span class="pdp-rx__row-label">OD</span>
                <select v-model="formState.prescription.od.sph" class="pdp-rx__select" aria-label="OD SPH">
                  <option v-for="opt in sphOptions" :key="opt" :value="opt">{{ opt }}</option>
                </select>
                <select v-model="formState.prescription.od.cyl" class="pdp-rx__select" aria-label="OD CYL">
                  <option v-for="opt in sphOptions" :key="opt" :value="opt">{{ opt }}</option>
                </select>
                <input
                  v-model="formState.prescription.od.axis"
                  :disabled="!usesOdAxis"
                  type="number"
                  min="1"
                  max="180"
                  class="pdp-rx__select pdp-rx__axis"
                  aria-label="OD Axis"
                />
                <select
                  v-if="supportsAddInConfigurator"
                  v-model="formState.prescription.od.add"
                  class="pdp-rx__select"
                  aria-label="OD ADD"
                >
                  <option v-for="opt in sphOptions.filter((o) => !String(o).startsWith('-'))" :key="opt" :value="opt">{{ opt }}</option>
                </select>

                <span class="pdp-rx__row-label">OS</span>
                <select v-model="formState.prescription.os.sph" class="pdp-rx__select" aria-label="OS SPH">
                  <option v-for="opt in sphOptions" :key="opt" :value="opt">{{ opt }}</option>
                </select>
                <select v-model="formState.prescription.os.cyl" class="pdp-rx__select" aria-label="OS CYL">
                  <option v-for="opt in sphOptions" :key="opt" :value="opt">{{ opt }}</option>
                </select>
                <input
                  v-model="formState.prescription.os.axis"
                  :disabled="!usesOsAxis"
                  type="number"
                  min="1"
                  max="180"
                  class="pdp-rx__select pdp-rx__axis"
                  aria-label="OS Axis"
                />
                <select
                  v-if="supportsAddInConfigurator"
                  v-model="formState.prescription.os.add"
                  class="pdp-rx__select"
                  aria-label="OS ADD"
                >
                  <option v-for="opt in sphOptions.filter((o) => !String(o).startsWith('-'))" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </div>

              <div class="pdp-rx__pd">
                <div class="pdp-rx__pd-toggle">
                  <label class="pdp-rx__radio">
                    <input type="radio" v-model="formState.pdType" value="single" />
                    <span>PD Tunggal</span>
                  </label>
                  <label class="pdp-rx__radio">
                    <input type="radio" v-model="formState.pdType" value="dual" />
                    <span>PD Ganda</span>
                  </label>
                </div>

                <div v-if="formState.pdType === 'dual'" class="pdp-rx__pd-grid">
                  <label>
                    <span class="text-meta">PD Kanan (mm)</span>
                    <input v-model="formState.prescription.pdRight" type="number" min="25" max="38" class="input-field" />
                  </label>
                  <label>
                    <span class="text-meta">PD Kiri (mm)</span>
                    <input v-model="formState.prescription.pdLeft" type="number" min="25" max="38" class="input-field" />
                  </label>
                </div>
                <label v-else>
                  <span class="text-meta">PD (mm)</span>
                  <input v-model="formState.prescription.pdSingle" type="number" min="50" max="75" class="input-field" />
                </label>
              </div>
            </div>
          </section>

          <!-- Lens configuration summary -->
          <div
            v-if="isFrameProduct && (product as any).compatible_lens_options?.length > 0 && (selectedLensOption || selectedCoating)"
            class="pdp-config-summary"
          >
            <header>
              <p class="text-meta">Konfigurasi Lensa</p>
              <button type="button" class="btn-ghost btn-sm" @click="openLensConfigurator">Ubah</button>
            </header>
            <ul>
              <li v-if="selectedLensOption">
                <span>{{ selectedLensOption.name }}</span>
                <span class="pdp-config-summary__price">+{{ formatMoney(selectedLensOption.base_price || 0) }}</span>
              </li>
              <li v-if="selectedCoating">
                <span>{{ selectedCoating.name }}</span>
                <span class="pdp-config-summary__price">+{{ formatMoney(selectedCoating.price || 0) }}</span>
              </li>
            </ul>
          </div>

          <div
            v-else-if="isFrameProduct && (product as any).compatible_lens_options?.length > 0 && !selectedLensOption"
            class="alert-base alert-info pdp-config-hint"
          >
            <span class="material-symbols-outlined" aria-hidden="true">info</span>
            <p>Klik tombol di bawah untuk memilih jenis lensa dan coating yang sesuai.</p>
          </div>

          <!-- Add-to-cart desktop CTA -->
          <button
            v-if="!product.is_not_for_sale || isAppointmentProduct"
            type="button"
            class="pdp-cta pdp-cta--desktop"
            :class="{ 'pdp-cta--success': addedToCart, 'pdp-cta--disabled': !canAddToCart }"
            :disabled="!canAddToCart"
            @click="handleAddToCartClick"
          >
            <span class="material-symbols-outlined" aria-hidden="true">{{ addToCartIcon }}</span>
            <span>{{ addToCartLabel }}</span>
          </button>

          <!-- Trust badges -->
          <ul class="pdp__trust">
            <li>
              <span class="material-symbols-outlined" aria-hidden="true">verified</span>
              <span>Produk Asli</span>
            </li>
            <li>
              <span class="material-symbols-outlined" aria-hidden="true">local_shipping</span>
              <span>Pengiriman Cepat</span>
            </li>
            <li>
              <span class="material-symbols-outlined" aria-hidden="true">support_agent</span>
              <span>Garansi Resmi</span>
            </li>
          </ul>

          <!-- Accordion: spec / shipping / warranty -->
          <details class="pdp-acc" :open="openSpec" @toggle="(e: Event) => (openSpec = (e.target as HTMLDetailsElement).open)">
            <summary>
              <span class="material-symbols-outlined pdp-acc__icon" aria-hidden="true">straighten</span>
              <span class="pdp-acc__label">Spesifikasi & Ukuran Frame</span>
              <span class="material-symbols-outlined pdp-acc__chevron" aria-hidden="true">expand_more</span>
            </summary>
            <div class="pdp-acc__body">
              <div v-if="hasFrameGuide">
                <div v-if="frameSizeRows.length > 0" class="pdp-spec-grid">
                  <div v-for="row in frameSizeRows" :key="row.label" class="pdp-spec-cell">
                    <p class="text-meta">{{ row.label }}</p>
                    <p class="pdp-spec-cell__value">{{ row.value }}<span> mm</span></p>
                  </div>
                </div>
                <div v-if="frameProfileRows.length > 0" class="pdp-spec-list">
                  <div v-for="row in frameProfileRows" :key="row.label" class="pdp-spec-row">
                    <span>{{ row.label }}</span>
                    <strong>{{ formatProductLabel(row.value) }}</strong>
                  </div>
                </div>
              </div>
              <p v-else class="text-graphite/65 text-sm">
                Spesifikasi detail untuk produk ini belum tersedia.
              </p>
            </div>
          </details>

          <details class="pdp-acc" :open="openShipping" @toggle="(e: Event) => (openShipping = (e.target as HTMLDetailsElement).open)">
            <summary>
              <span class="material-symbols-outlined pdp-acc__icon" aria-hidden="true">local_shipping</span>
              <span class="pdp-acc__label">Pengiriman & Pickup</span>
              <span class="material-symbols-outlined pdp-acc__chevron" aria-hidden="true">expand_more</span>
            </summary>
            <div class="pdp-acc__body">
              <ul class="pdp-spec-list">
                <li>Pengiriman via JNE/POS/TIKI ke seluruh Indonesia.</li>
                <li>Pickup langsung di Optik Medio Lampung Tengah.</li>
                <li>Estimasi 1–4 hari kerja tergantung lokasi.</li>
                <li>Pesanan dengan resep akan di-review tim optik sebelum dikirim.</li>
              </ul>
            </div>
          </details>

          <details class="pdp-acc" :open="openWarranty" @toggle="(e: Event) => (openWarranty = (e.target as HTMLDetailsElement).open)">
            <summary>
              <span class="material-symbols-outlined pdp-acc__icon" aria-hidden="true">workspace_premium</span>
              <span class="pdp-acc__label">Garansi & Servis</span>
              <span class="material-symbols-outlined pdp-acc__chevron" aria-hidden="true">expand_more</span>
            </summary>
            <div class="pdp-acc__body">
              <ul class="pdp-spec-list">
                <li>Garansi resmi distributor untuk frame & lensa.</li>
                <li>Servis fitting & adjustment gratis di toko.</li>
                <li>Klaim garansi dapat dilakukan via halaman <router-link to="/warranty" class="pdp-acc__link">Warranty</router-link>.</li>
              </ul>
            </div>
          </details>
        </section>
      </div>
    </div>

    <!-- Recommendations -->
    <section v-if="hasRecommendationSection" class="container-premium pdp__recos">
      <header class="pdp__recos-head">
        <div>
          <p class="eyebrow">Rekomendasi Optik</p>
          <h2 class="editorial-h2 pdp__recos-title">Pilihan yang Cocok</h2>
        </div>
        <router-link to="/products" class="btn-ghost btn-sm">
          Lihat Koleksi
          <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
        </router-link>
      </header>

      <div v-if="primaryRecommendations.length > 0" class="pdp__recos-block">
        <h3 class="text-meta pdp__recos-block-title">{{ primaryRecommendationTitle }}</h3>
        <div class="pdp-reco-grid">
          <article
            v-for="item in primaryRecommendations.slice(0, 4)"
            :key="item.id"
            class="pdp-reco-card"
            tabindex="0"
            role="link"
            :aria-label="`Lihat detail ${item.name}`"
            @click="router.push(`/products/${item.slug}`)"
            @keydown.enter="router.push(`/products/${item.slug}`)"
          >
            <div class="pdp-reco-card__media">
              <img :src="resolveImageUrl(item)" :alt="item.name" loading="lazy" decoding="async" />
            </div>
            <div class="pdp-reco-card__body">
              <p class="text-meta">{{ item.name }}</p>
              <h4>{{ item.brand || 'Optik Medio' }}</h4>
              <p class="pdp-reco-card__price">{{ formatMoney(item.price) }}</p>
            </div>
          </article>
        </div>
      </div>

      <div v-if="showCompatibleLenses" class="pdp__recos-block">
        <h3 class="text-meta pdp__recos-block-title">Lensa Kompatibel</h3>
        <div class="pdp-reco-grid">
          <article
            v-for="item in compatibleLenses.slice(0, 4)"
            :key="item.id"
            class="pdp-reco-card"
            tabindex="0"
            role="link"
            :aria-label="`Lihat detail ${item.name}`"
            @click="router.push(`/products/${item.slug}`)"
            @keydown.enter="router.push(`/products/${item.slug}`)"
          >
            <div class="pdp-reco-card__media">
              <img :src="resolveImageUrl(item)" :alt="item.name" loading="lazy" decoding="async" />
            </div>
            <div class="pdp-reco-card__body">
              <p class="text-meta">{{ item.brand || 'Lensa' }}</p>
              <h4>{{ item.name }}</h4>
              <p class="pdp-reco-card__price">{{ formatMoney(item.price) }}</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- Reviews -->
    <section class="container-premium pdp__reviews">
      <div class="surface-elevated pdp__reviews-card">
        <header class="pdp__reviews-head">
          <div>
            <p class="eyebrow">Customer Reviews</p>
            <h2 class="editorial-h2 pdp__reviews-title">Ulasan Produk</h2>
          </div>
          <p class="pdp__reviews-summary">
            Rating rata-rata
            <strong>{{ Number(reviewSummary.avg_rating || product.avg_rating || 0).toFixed(1) }}</strong>
            dari {{ reviewSummary.total_reviews || product.review_count || 0 }} ulasan
          </p>
        </header>

        <p v-if="productReviews.length === 0" class="pdp__reviews-empty">
          Belum ada ulasan untuk produk ini.
        </p>

        <div v-else class="pdp__reviews-grid">
          <article v-for="review in productReviews" :key="review.id" class="pdp-review">
            <header>
              <p class="pdp-review__name">{{ review.user_name }}</p>
              <span class="pdp-review__date">{{ review.created_at }}</span>
            </header>
            <div class="pdp-review__stars" :aria-label="`Rating ${review.rating} dari 5`">
              <span
                v-for="star in 5"
                :key="star"
                class="material-symbols-outlined"
                :class="{ 'pdp-review__star--filled': star <= review.rating }"
                aria-hidden="true"
              >star</span>
            </div>
            <p class="pdp-review__body">
              {{ review.comment || 'Customer tidak menambahkan komentar tertulis.' }}
            </p>
          </article>
        </div>
      </div>
    </section>

    <!-- ───────────────────────────── Sticky CTA mobile ───────────────────────────── -->
    <div
      v-if="!product.is_not_for_sale || isAppointmentProduct"
      class="pdp-sticky-cta sticky-cta-mobile"
    >
      <div class="pdp-sticky-cta__price">
        <p class="text-meta">Total</p>
        <p v-if="!product.is_not_for_sale" class="pdp-sticky-cta__amount">{{ formatMoney(product.price) }}</p>
        <p v-else class="pdp-sticky-cta__amount">Konsultasi</p>
      </div>
      <button
        type="button"
        class="pdp-cta pdp-cta--mobile"
        :class="{ 'pdp-cta--success': addedToCart, 'pdp-cta--disabled': !canAddToCart }"
        :disabled="!canAddToCart"
        @click="handleAddToCartClick"
      >
        <span class="material-symbols-outlined" aria-hidden="true">{{ addToCartIcon }}</span>
        <span>{{ addToCartLabelMobile }}</span>
      </button>
    </div>

    <!-- ───────────────────────────── MODALS ───────────────────────────── -->
    <Teleport to="body">
      <!-- Lens Choice Modal (lensa standalone dengan resep: lensa saja vs frame dulu) -->
      <Transition name="fade">
        <div
          v-if="isLensChoiceModalOpen"
          class="pdp-modal-backdrop"
          role="presentation"
          @click.self="isLensChoiceModalOpen = false"
        >
          <div
            class="pdp-modal pdp-modal--md"
            role="dialog"
            aria-modal="true"
            aria-labelledby="lens-choice-modal-title"
          >
            <header class="pdp-modal__head">
              <h2 id="lens-choice-modal-title" class="editorial-h2">Lanjutkan Pembelian Lensa</h2>
              <button
                type="button"
                class="btn-icon-ghost"
                aria-label="Tutup dialog"
                @click="isLensChoiceModalOpen = false"
              >
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
              </button>
            </header>

            <p class="pdp-modal__lede">
              Resep sudah siap. Anda bisa lanjut beli lensa ini saja, atau pilih frame terlebih dulu bila ingin dipasangkan dalam satu pesanan.
            </p>

            <div class="pdp-choice-grid">
              <button
                type="button"
                class="pdp-choice-card"
                @click="executeAddToCart()"
              >
                <span class="material-symbols-outlined pdp-choice-card__icon" aria-hidden="true">shopping_bag</span>
                <p class="text-meta">Tanpa Frame</p>
                <h3>Beli Lensa Saja</h3>
                <p class="pdp-choice-card__body">
                  Tambahkan lensa ini ke keranjang dengan resep yang sudah Anda isi.
                </p>
              </button>

              <button
                type="button"
                class="pdp-choice-card"
                @click="chooseFrameBeforeCheckout"
              >
                <span class="material-symbols-outlined pdp-choice-card__icon" aria-hidden="true">visibility</span>
                <p class="text-meta">Dengan Frame</p>
                <h3>Pilih Frame Dulu</h3>
                <p class="pdp-choice-card__body">
                  Lanjut ke katalog untuk memilih frame sebelum checkout.
                </p>
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <!-- Lens Configurator Modal (Step 1: lens, Step 2: coating) -->
      <Transition name="fade">
        <div
          v-if="isLensModalOpen"
          class="pdp-modal-backdrop"
          role="presentation"
          @click.self="isLensModalOpen = false"
        >
          <div
            class="pdp-modal pdp-modal--lg"
            role="dialog"
            aria-modal="true"
            aria-labelledby="lens-modal-title"
          >
            <header class="pdp-modal__head">
              <div>
                <p class="eyebrow">{{ configuratorStep === 'lens' ? 'Langkah 1 dari 2' : 'Langkah 2 dari 2' }}</p>
                <h2 id="lens-modal-title" class="editorial-h2">
                  {{ configuratorStep === 'lens' ? 'Pilih Jenis Lensa' : 'Pilih Coating Lensa' }}
                </h2>
                <p class="pdp-modal__sub">
                  {{ configuratorStep === 'lens'
                    ? 'Pilih jenis lensa yang sesuai dengan kebutuhan penglihatan Anda.'
                    : 'Tambahkan lapisan pelindung untuk kenyamanan dan ketahanan lensa.' }}
                </p>
              </div>
              <button
                type="button"
                class="btn-icon-ghost"
                aria-label="Tutup dialog"
                @click="isLensModalOpen = false"
              >
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
              </button>
            </header>

            <!-- Step indicator -->
            <div class="pdp-stepper" aria-label="Progres konfigurasi lensa">
              <div class="pdp-stepper__bar pdp-stepper__bar--active"></div>
              <div class="pdp-stepper__bar" :class="{ 'pdp-stepper__bar--active': configuratorStep === 'coating' }"></div>
            </div>

            <!-- Running price summary -->
            <aside class="pdp-modal__price">
              <div class="pdp-modal__price-row">
                <span>Frame</span>
                <strong>{{ formatMoney(product?.price || 0) }}</strong>
              </div>
              <div v-if="selectedLensOption" class="pdp-modal__price-row">
                <span>{{ selectedLensOption.name }}</span>
                <strong>+{{ formatMoney(selectedLensOption.base_price || 0) }}</strong>
              </div>
              <div v-if="selectedCoating" class="pdp-modal__price-row">
                <span>{{ selectedCoating.name }}</span>
                <strong>+{{ formatMoney(selectedCoating.price || 0) }}</strong>
              </div>
              <div class="pdp-modal__price-total">
                <span class="text-meta">Total</span>
                <strong class="price-display">{{ formatMoney(configuratorTotalPrice) }}</strong>
              </div>
            </aside>

            <!-- Step 1: lens options -->
            <div v-if="configuratorStep === 'lens'" class="pdp-modal__body">
              <div v-if="isLensesLoading" class="pdp-modal__loading">
                <div class="pdp-spinner pdp-spinner--sm"></div>
              </div>

              <div v-else>
                <div v-if="(product as any)?.compatible_lens_options?.length" class="pdp-option-grid">
                  <button
                    v-for="opt in (product as any)?.compatible_lens_options || []"
                    :key="opt.id"
                    type="button"
                    class="pdp-option-card"
                    @click="selectLensOption(opt)"
                  >
                    <header>
                      <h3>{{ opt.name }}</h3>
                      <span class="badge">{{ opt.type?.replace('_', ' ') }}</span>
                    </header>
                    <p class="pdp-option-card__price">+{{ formatMoney(opt.base_price || 0) }}</p>
                  </button>
                </div>

                <div v-else class="empty-state">
                  <span class="material-symbols-outlined text-3xl text-gold" aria-hidden="true">info</span>
                  <p class="pdp-modal__empty-title">Lensa belum dikonfigurasi</p>
                  <p>Admin belum mengatur pilihan lensa untuk frame ini. Anda tetap bisa melanjutkan — tim kami akan menghubungi untuk konfirmasi lensa.</p>
                  <button type="button" class="btn-primary btn-sm" @click="skipCoating">
                    Lanjutkan Tanpa Pilih Lensa
                  </button>
                </div>
              </div>

              <footer class="pdp-modal__foot">
                <button type="button" class="btn-outline" @click="isLensModalOpen = false">Batal</button>
              </footer>
            </div>

            <!-- Step 2: coatings -->
            <div v-if="configuratorStep === 'coating'" class="pdp-modal__body">
              <div v-if="isCoatingsLoading" class="pdp-modal__loading">
                <div class="pdp-spinner pdp-spinner--sm"></div>
              </div>

              <div v-else class="pdp-option-grid">
                <button
                  v-for="coating in allCoatings"
                  :key="coating.id"
                  type="button"
                  class="pdp-option-card"
                  :class="{ 'pdp-option-card--active': selectedCoating?.id === coating.id }"
                  @click="selectedCoating = coating; confirmLensConfiguration()"
                >
                  <header>
                    <h3>{{ coating.name }}</h3>
                  </header>
                  <p v-if="coating.description" class="pdp-option-card__desc">{{ coating.description }}</p>
                  <p class="pdp-option-card__price">+{{ formatMoney(coating.price || 0) }}</p>
                </button>
              </div>

              <footer class="pdp-modal__foot pdp-modal__foot--stack">
                <button type="button" class="btn-primary" @click="skipCoating">
                  Lanjutkan Tanpa Coating
                </button>
                <button type="button" class="btn-outline" @click="configuratorStep = 'lens'">
                  <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
                  Kembali Pilih Lensa
                </button>
              </footer>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </main>
</template>

<style scoped>
/* Loading state */
.pdp-loading {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 16px;
  padding: 80px 24px;
  background: var(--ivory);
}

.pdp-loading p {
  font-size: 13px;
  font-weight: 500;
  color: rgba(43, 41, 38, 0.62);
}

.pdp-spinner {
  width: 56px;
  height: 56px;
  border-radius: 999px;
  border: 4px solid rgba(184, 138, 68, 0.22);
  border-top-color: var(--gold);
  animation: pdp-spin 0.9s linear infinite;
}
.pdp-spinner--sm { width: 36px; height: 36px; border-width: 3px; }

@keyframes pdp-spin {
  to { transform: rotate(360deg); }
}

/* Layout */
.pdp { background: var(--ivory); flex-grow: 1; }

.pdp__main {
  padding-top: clamp(24px, 3vw, 40px);
  padding-bottom: clamp(48px, 6vw, 96px);
}

.pdp__layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: clamp(24px, 3vw, 48px);
}

@media (min-width: 1024px) {
  .pdp__layout {
    grid-template-columns: 7fr 5fr;
    gap: 56px;
    align-items: start;
  }
}

.pdp__gallery {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

@media (min-width: 1024px) {
  .pdp__gallery {
    position: sticky;
    top: calc(var(--header-height, 72px) + 24px);
  }
}

.pdp-gallery__main {
  position: relative;
  aspect-ratio: 1 / 1;
  width: 100%;
  max-width: 640px;
  margin: 0 auto;
  overflow: hidden;
  border-radius: 12px;
  border: 1px solid rgba(184, 138, 68, 0.18);
  background: linear-gradient(145deg, var(--ivory), var(--mist));
  box-shadow: var(--shadow-card);
}

.pdp-gallery__image {
  width: 100%;
  height: 100%;
  object-fit: contain;
  padding: clamp(16px, 3vw, 36px);
  mix-blend-mode: multiply;
}

.pdp-gallery__nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 40px;
  height: 40px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  color: var(--ink);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: var(--shadow-card);
  transition: background-color var(--motion-base), box-shadow var(--motion-base);
}

.pdp-gallery__nav:hover { background: #fff; box-shadow: var(--shadow-soft); }
.pdp-gallery__nav--disabled { opacity: 0.45; cursor: not-allowed; }
.pdp-gallery__nav--prev { left: 12px; }
.pdp-gallery__nav--next { right: 12px; }

.pdp-gallery__counter {
  position: absolute;
  bottom: 12px;
  right: 12px;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(21, 18, 14, 0.78);
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.pdp-gallery__thumbs {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 8px;
  width: 100%;
  max-width: 640px;
  margin: 0 auto;
}

@media (min-width: 480px) { .pdp-gallery__thumbs { gap: 10px; } }

.pdp-gallery__thumb {
  aspect-ratio: 1 / 1;
  overflow: hidden;
  border-radius: 8px;
  border: 2px solid transparent;
  background: linear-gradient(145deg, var(--ivory), var(--mist));
  padding: 6px;
  opacity: 0.6;
  transition: border-color var(--motion-base), opacity var(--motion-base);
  cursor: pointer;
}

.pdp-gallery__thumb:hover { opacity: 1; }
.pdp-gallery__thumb--active { border-color: var(--gold); opacity: 1; }

.pdp-gallery__thumb-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  mix-blend-mode: multiply;
}

/* INFO COLUMN */
.pdp__info {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.pdp__heading {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pdp__badges-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.pdp-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.10em;
  line-height: 1;
  border: 1px solid transparent;
}

.pdp-badge .material-symbols-outlined { font-size: 12px; }
.pdp-badge--ink { background: rgba(21, 18, 14, 0.86); color: #fff; border-color: rgba(184, 138, 68, 0.30); }
.pdp-badge--gold { background: var(--gold); color: var(--ink); }
.pdp-badge--red { background: #dc2626; color: #fff; }

.pdp__title {
  margin-top: 4px;
  font-size: clamp(1.75rem, 1.4rem + 1.6vw, 2.75rem);
  letter-spacing: -0.01em;
  line-height: 1.04;
}

.pdp__subtitle {
  font-size: 13px;
  font-weight: 600;
  color: rgba(43, 41, 38, 0.78);
  letter-spacing: 0.04em;
}

.pdp__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  font-size: 13px;
  color: rgba(43, 41, 38, 0.70);
  margin-top: 4px;
}

.pdp__meta li { display: inline-flex; align-items: center; gap: 6px; }
.pdp__meta .material-symbols-outlined { color: var(--gold); font-size: 18px; }

.pdp__price-row {
  margin-top: 12px;
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
}

.pdp__price { color: #6F4E1D; }
.pdp__price-info {
  font-size: 14px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: var(--gold);
}

.pdp__stock {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
}

.pdp__stock-dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
}

.pdp__stock--available { color: #15803d; }
.pdp__stock--available .pdp__stock-dot {
  background: #16a34a;
  box-shadow: 0 0 8px rgba(22, 163, 74, 0.5);
}

.pdp__stock--out { color: #dc2626; }
.pdp__stock--out .pdp__stock-dot { background: #dc2626; }

.pdp__promo-desc {
  margin-top: 6px;
  font-size: 12px;
  color: rgba(43, 41, 38, 0.72);
  line-height: 1.5;
}

/* Quick row (wishlist + compare) */
.pdp__quick-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.pdp-quick-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid var(--mist);
  background: #fff;
  color: var(--graphite);
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  min-height: var(--tap-target);
  transition: background-color var(--motion-base), border-color var(--motion-base), color var(--motion-base);
}

.pdp-quick-btn:hover { border-color: rgba(184, 138, 68, 0.45); }
.pdp-quick-btn--active {
  background: var(--gold-soft);
  border-color: rgba(184, 138, 68, 0.45);
  color: #6F4E1D;
}

.pdp-quick-btn--compared {
  background: rgba(63, 111, 143, 0.10);
  border-color: rgba(63, 111, 143, 0.32);
  color: var(--optical-blue);
}

.pdp-quick-btn .material-symbols-outlined { font-size: 18px; }

/* Description */
.pdp__description {
  font-size: 14px;
  line-height: 1.7;
  color: rgba(43, 41, 38, 0.78);
}

/* Variants */
.pdp__variant {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pdp__variant-label {
  display: flex;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  color: rgba(43, 41, 38, 0.68);
}

.pdp__variant-label strong { color: var(--ink); font-weight: 700; }

.pdp__color-row { display: flex; flex-wrap: wrap; gap: 10px; }

.pdp-color-swatch {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  border: 3px solid transparent;
  cursor: pointer;
  transition: transform var(--motion-base), border-color var(--motion-base), box-shadow var(--motion-base);
}

.pdp-color-swatch:hover { transform: scale(1.05); }
.pdp-color-swatch--active {
  transform: scale(1.10);
  border-color: var(--gold);
  box-shadow: 0 0 0 2px rgba(184, 138, 68, 0.42);
}

.pdp__size-row { display: flex; flex-wrap: wrap; gap: 6px; }

.pdp-size-pill {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid rgba(184, 138, 68, 0.25);
  background: transparent;
  color: var(--graphite);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.04em;
  min-height: var(--tap-target);
  transition: background-color var(--motion-base), color var(--motion-base), border-color var(--motion-base), box-shadow var(--motion-base);
}

.pdp-size-pill:hover { border-color: var(--ink); }
.pdp-size-pill--active {
  background: var(--ink);
  color: var(--ivory);
  border-color: var(--ink);
  box-shadow: 0 4px 12px rgba(21, 18, 14, 0.18);
}

/* Alert variants used inline */
.alert-base {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.pdp__alert-icon { color: var(--gold); flex-shrink: 0; margin-top: 2px; }
.pdp__alert-title { font-size: 13px; font-weight: 700; color: #6F4E1D; }
.pdp__alert-body { margin-top: 2px; font-size: 12px; line-height: 1.55; color: rgba(43, 41, 38, 0.72); }

/* Info-only catalog notice */
.pdp__info-only {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  border-left: 4px solid var(--gold);
}

.pdp__info-only header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pdp__info-only header .material-symbols-outlined { color: var(--gold); }
.pdp__info-only header p { font-size: 14px; font-weight: 700; color: var(--ink); }

.pdp__info-only > p {
  font-size: 13px;
  line-height: 1.65;
  color: rgba(43, 41, 38, 0.74);
}

/* Prescription form */
.pdp-rx {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-top: 12px;
  border-top: 1px solid rgba(184, 138, 68, 0.18);
}

.pdp-rx__head h2 { margin: 0; }
.pdp-rx__head p { margin-top: 4px; }

.pdp-rx__profiles {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pdp-rx__profile-list { display: flex; flex-direction: column; gap: 8px; }

.pdp-rx-profile {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid rgba(184, 138, 68, 0.20);
  background: #fff;
  text-align: left;
  transition: border-color var(--motion-base), background-color var(--motion-base), box-shadow var(--motion-base);
  cursor: pointer;
}

.pdp-rx-profile:hover { border-color: rgba(184, 138, 68, 0.45); }

.pdp-rx-profile--active {
  border-color: var(--gold);
  background: rgba(184, 138, 68, 0.06);
  box-shadow: 0 0 0 2px rgba(184, 138, 68, 0.25);
}

.pdp-rx-profile__body { min-width: 0; }
.pdp-rx-profile__label { font-size: 13px; font-weight: 700; color: var(--ink); }
.pdp-rx-profile__meta { margin-top: 2px; font-size: 11px; color: rgba(43, 41, 38, 0.62); }

.pdp-rx-profile__status {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.pdp-rx-profile__check { color: rgba(184, 138, 68, 0.30); }
.pdp-rx-profile--active .pdp-rx-profile__check { color: var(--gold); }

.pdp-rx__divider {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 8px;
  align-items: center;
}

.pdp-rx__form {
  padding: 14px;
  border-radius: 8px;
  border: 1px solid rgba(184, 138, 68, 0.22);
  background: rgba(255, 255, 255, 0.82);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.pdp-rx__grid {
  display: grid;
  grid-template-columns: 32px repeat(3, 1fr);
  gap: 8px;
  align-items: center;
}

.pdp-rx__grid--with-add { grid-template-columns: 32px repeat(4, 1fr); }

.pdp-rx__grid > .text-meta {
  text-align: center;
  font-size: 10px;
}

.pdp-rx__row-label {
  font-weight: 700;
  font-size: 12px;
  color: var(--ink);
  text-align: right;
  padding-right: 4px;
}

.pdp-rx__select {
  width: 100%;
  padding: 8px;
  border-radius: 6px;
  border: 1px solid rgba(184, 138, 68, 0.24);
  background: #fff;
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
}

.pdp-rx__axis { text-align: center; }
.pdp-rx__select:disabled {
  background: var(--mist);
  color: rgba(43, 41, 38, 0.55);
  cursor: not-allowed;
}

.pdp-rx__pd {
  padding-top: 12px;
  border-top: 1px solid rgba(184, 138, 68, 0.18);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pdp-rx__pd-toggle { display: flex; gap: 16px; }

.pdp-rx__radio {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 700;
  color: var(--graphite);
  cursor: pointer;
}

.pdp-rx__radio input[type="radio"] { accent-color: var(--gold); }

.pdp-rx__pd-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

/* Lens config summary */
.pdp-config-summary {
  padding: 14px;
  border-radius: 8px;
  border: 1px solid rgba(184, 138, 68, 0.28);
  background: rgba(255, 255, 255, 0.82);
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.pdp-config-summary header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.pdp-config-summary ul { display: flex; flex-direction: column; gap: 4px; }

.pdp-config-summary li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  color: rgba(43, 41, 38, 0.78);
}

.pdp-config-summary__price { font-weight: 700; color: var(--ink); }

.pdp-config-hint .material-symbols-outlined { color: var(--gold); }
.pdp-config-hint p { margin: 0; font-size: 13px; color: rgba(43, 41, 38, 0.78); }

/* CTA buttons */
.pdp-cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 20px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.14em;
  background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);
  color: #fff;
  box-shadow: 0 8px 25px rgba(21, 18, 14, 0.22);
  transition: filter var(--motion-base), transform var(--motion-fast);
  min-height: 52px;
  width: 100%;
}

.pdp-cta:hover { filter: brightness(1.08); }
.pdp-cta:active { transform: scale(0.98); }

.pdp-cta--success {
  background: linear-gradient(135deg, #15803d, #16a34a);
  box-shadow: 0 8px 25px rgba(22, 163, 74, 0.30);
}

.pdp-cta--disabled {
  background: rgba(245, 242, 238, 0.86);
  color: rgba(160, 144, 128, 0.92);
  cursor: not-allowed;
  box-shadow: none;
}

.pdp-cta--disabled:hover { filter: none; }

.pdp-cta--desktop { display: none; }
@media (min-width: 768px) {
  .pdp-cta--desktop { display: inline-flex; }
}

/* Trust strip */
.pdp__trust {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  padding-top: 4px;
}

.pdp__trust li {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  text-align: center;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.10em;
  color: rgba(43, 41, 38, 0.68);
}

.pdp__trust .material-symbols-outlined { color: var(--gold); font-size: 22px; }

/* Accordion */
.pdp-acc {
  border: 1px solid var(--mist);
  border-radius: 8px;
  background: #fff;
  overflow: hidden;
}

.pdp-acc + .pdp-acc { margin-top: 8px; }

.pdp-acc summary {
  list-style: none;
  cursor: pointer;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
  transition: background-color var(--motion-base);
}

.pdp-acc summary::-webkit-details-marker { display: none; }
.pdp-acc summary:hover { background: var(--surface-container-low); }

.pdp-acc__icon { color: var(--gold); flex-shrink: 0; font-size: 20px; }
.pdp-acc__label { flex: 1 1 auto; min-width: 0; }
.pdp-acc__chevron {
  color: rgba(43, 41, 38, 0.55);
  font-size: 22px;
  transition: transform var(--motion-base);
}

.pdp-acc[open] .pdp-acc__chevron { transform: rotate(180deg); }

.pdp-acc__body {
  padding: 4px 16px 18px;
  border-top: 1px solid var(--mist);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.pdp-acc__link { color: var(--gold); font-weight: 700; }
.pdp-acc__link:hover { color: var(--ink); }

.pdp-spec-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-top: 8px;
}

@media (min-width: 480px) { .pdp-spec-grid { grid-template-columns: repeat(4, 1fr); } }

.pdp-spec-cell {
  padding: 10px 12px;
  border: 1px solid var(--mist);
  border-radius: 6px;
  background: var(--porcelain);
}

.pdp-spec-cell__value {
  margin-top: 4px;
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
}

.pdp-spec-cell__value span {
  margin-left: 4px;
  font-size: 11px;
  font-weight: 600;
  color: rgba(43, 41, 38, 0.62);
}

.pdp-spec-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  line-height: 1.6;
  color: rgba(43, 41, 38, 0.78);
}

.pdp-spec-list li { padding-left: 16px; position: relative; }
.pdp-spec-list li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 9px;
  width: 6px;
  height: 6px;
  border-radius: 999px;
  background: var(--gold);
}

.pdp-spec-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-radius: 6px;
  background: var(--porcelain);
  font-size: 12px;
}

.pdp-spec-row + .pdp-spec-row { margin-top: 4px; }
.pdp-spec-row span { color: rgba(43, 41, 38, 0.65); font-weight: 600; }
.pdp-spec-row strong { color: var(--ink); font-weight: 700; }

/* Recommendations */
.pdp__recos { padding-bottom: clamp(48px, 6vw, 80px); }

.pdp__recos-head {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 24px;
}

@media (min-width: 768px) {
  .pdp__recos-head {
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
  }
}

.pdp__recos-title { margin-top: 4px; }

.pdp__recos-block + .pdp__recos-block { margin-top: 32px; }
.pdp__recos-block-title { margin-bottom: 12px; }

.pdp-reco-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

@media (min-width: 768px) { .pdp-reco-grid { grid-template-columns: repeat(4, 1fr); } }
@media (min-width: 1280px) { .pdp-reco-grid { grid-template-columns: repeat(5, 1fr); } }

.pdp-reco-card {
  cursor: pointer;
  border: 1px solid var(--mist);
  border-radius: 8px;
  overflow: hidden;
  background: var(--porcelain);
  transition: border-color var(--motion-base), box-shadow var(--motion-base), transform var(--motion-base);
}

.pdp-reco-card:hover {
  border-color: rgba(184, 138, 68, 0.45);
  box-shadow: var(--shadow-card);
  transform: translateY(-2px);
}

.pdp-reco-card__media {
  aspect-ratio: 1 / 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 14px;
  background: linear-gradient(145deg, var(--ivory), var(--mist));
}

.pdp-reco-card__media img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  mix-blend-mode: multiply;
}

.pdp-reco-card__body {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.pdp-reco-card__body h4 {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.3;
}

.pdp-reco-card__price {
  margin-top: 4px;
  font-size: 13px;
  font-weight: 700;
  color: #6F4E1D;
}

/* Reviews */
.pdp__reviews { padding-bottom: clamp(48px, 6vw, 80px); }

.pdp__reviews-card {
  padding: clamp(20px, 3vw, 32px);
}

.pdp__reviews-head {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
}

@media (min-width: 768px) {
  .pdp__reviews-head {
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
  }
}

.pdp__reviews-title { margin-top: 4px; }

.pdp__reviews-summary {
  font-size: 13px;
  color: rgba(43, 41, 38, 0.72);
}

.pdp__reviews-summary strong { color: var(--ink); font-weight: 700; }

.pdp__reviews-empty {
  font-size: 13px;
  color: rgba(43, 41, 38, 0.62);
  text-align: center;
  padding: 32px 0;
}

.pdp__reviews-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

@media (min-width: 768px) { .pdp__reviews-grid { grid-template-columns: 1fr 1fr; gap: 16px; } }

.pdp-review {
  padding: 16px;
  border-radius: 8px;
  border: 1px solid rgba(184, 138, 68, 0.18);
  background: rgba(255, 255, 255, 0.86);
}

.pdp-review header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 10px;
}

.pdp-review__name { font-size: 13px; font-weight: 700; color: var(--ink); }
.pdp-review__date { font-size: 11px; color: rgba(43, 41, 38, 0.58); }

.pdp-review__stars {
  display: flex;
  gap: 2px;
  margin-bottom: 10px;
}

.pdp-review__stars .material-symbols-outlined {
  font-size: 16px;
  color: rgba(184, 138, 68, 0.25);
}

.pdp-review__star--filled { color: var(--gold) !important; }

.pdp-review__body {
  font-size: 12px;
  line-height: 1.6;
  color: rgba(43, 41, 38, 0.72);
}

/* Sticky mobile CTA */
.pdp-sticky-cta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.pdp-sticky-cta__price {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1 1 auto;
  min-width: 0;
}

.pdp-sticky-cta__price .text-meta {
  font-size: 9px;
  letter-spacing: 0.14em;
}

.pdp-sticky-cta__amount {
  font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;
  font-size: 22px;
  font-weight: 700;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.05;
}

.pdp-cta--mobile {
  flex: 0 0 auto;
  /* Auto-width minimal; tidak grow */
  width: auto;
  padding: 11px 16px;
  min-height: 46px;
  font-size: 11px;
  letter-spacing: 0.08em;
  white-space: nowrap;
}

.pdp-cta--mobile .material-symbols-outlined {
  font-size: 18px;
  flex-shrink: 0;
}

@media (max-width: 359.98px) {
  .pdp-sticky-cta { gap: 8px; }
  .pdp-sticky-cta__amount { font-size: 18px; }
  .pdp-cta--mobile { padding: 11px 12px; font-size: 10px; }
}

/* Hide sticky on desktop */
@media (min-width: 768px) {
  .pdp-sticky-cta { display: none; }
}

/* MODALS */
.pdp-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 80;
  background: rgba(10, 8, 5, 0.65);
  backdrop-filter: blur(14px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: clamp(12px, 2vw, 24px);
  overflow-y: auto;
}

.pdp-modal {
  width: 100%;
  background: #faf8f5;
  border: 1px solid rgba(184, 138, 68, 0.22);
  border-radius: 12px;
  box-shadow: 0 30px 80px rgba(0, 0, 0, 0.32);
  max-height: calc(100vh - 32px);
  overflow-y: auto;
}

.pdp-modal--md { max-width: 440px; }
.pdp-modal--lg { max-width: 640px; }

.pdp-modal__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 20px 20px 0;
}

@media (min-width: 768px) { .pdp-modal__head { padding: 24px 28px 0; } }

.pdp-modal__sub {
  margin-top: 8px;
  font-size: 12px;
  line-height: 1.5;
  color: rgba(43, 41, 38, 0.65);
}

.pdp-modal__lede {
  margin: 16px 20px 0;
  font-size: 14px;
  line-height: 1.65;
  color: rgba(43, 41, 38, 0.74);
}

@media (min-width: 768px) { .pdp-modal__lede { margin: 16px 28px 0; } }

/* Stepper */
.pdp-stepper {
  display: flex;
  gap: 6px;
  padding: 16px 20px 0;
}

@media (min-width: 768px) { .pdp-stepper { padding: 16px 28px 0; } }

.pdp-stepper__bar {
  flex: 1;
  height: 3px;
  border-radius: 999px;
  background: rgba(184, 138, 68, 0.2);
  transition: background-color var(--motion-base);
}

.pdp-stepper__bar--active { background: var(--gold); }

/* Modal price */
.pdp-modal__price {
  margin: 16px 20px 0;
  padding: 14px 16px;
  border-radius: 8px;
  border: 1px solid rgba(184, 138, 68, 0.22);
  background: rgba(184, 138, 68, 0.05);
  display: flex;
  flex-direction: column;
  gap: 6px;
}

@media (min-width: 768px) { .pdp-modal__price { margin: 16px 28px 0; } }

.pdp-modal__price-row {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
}

.pdp-modal__price-row span { color: rgba(43, 41, 38, 0.72); }
.pdp-modal__price-row strong { color: var(--ink); font-weight: 700; }

.pdp-modal__price-total {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 8px;
  margin-top: 4px;
  border-top: 1px solid rgba(184, 138, 68, 0.22);
}

.pdp-modal__price-total .price-display {
  font-size: 20px;
  color: #6F4E1D;
}

.pdp-modal__body {
  padding: 16px 20px 20px;
}

@media (min-width: 768px) { .pdp-modal__body { padding: 16px 28px 28px; } }

.pdp-modal__loading {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 0;
}

.pdp-modal__empty-title { font-size: 13px; font-weight: 700; color: var(--ink); margin: 4px 0; }

.pdp-modal__foot {
  margin-top: 16px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}

.pdp-modal__foot--stack {
  grid-template-columns: 1fr;
  gap: 8px;
}

.pdp-modal__foot button { width: 100%; }

.pdp-option-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

@media (min-width: 480px) { .pdp-option-grid { grid-template-columns: repeat(2, 1fr); } }

.pdp-option-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border-radius: 8px;
  border: 1px solid rgba(184, 138, 68, 0.20);
  background: #fff;
  text-align: left;
  transition: border-color var(--motion-base), box-shadow var(--motion-base), transform var(--motion-fast);
  cursor: pointer;
}

.pdp-option-card:hover { border-color: rgba(184, 138, 68, 0.45); box-shadow: var(--shadow-card); }
.pdp-option-card:active { transform: scale(0.98); }

.pdp-option-card--active {
  border-color: var(--gold);
  background: rgba(184, 138, 68, 0.06);
  box-shadow: 0 0 0 2px rgba(184, 138, 68, 0.30);
}

.pdp-option-card header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.pdp-option-card h3 {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1.3;
}

.pdp-option-card__desc {
  font-size: 12px;
  line-height: 1.5;
  color: rgba(43, 41, 38, 0.65);
}

.pdp-option-card__price {
  margin-top: 4px;
  font-size: 14px;
  font-weight: 700;
  color: #6F4E1D;
}

/* Choice modal */
.pdp-choice-grid {
  margin: 20px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

@media (min-width: 480px) { .pdp-choice-grid { grid-template-columns: 1fr 1fr; } }
@media (min-width: 768px) { .pdp-choice-grid { margin: 20px 28px 28px; } }

.pdp-choice-card {
  padding: 18px;
  border-radius: 8px;
  border: 1px solid rgba(184, 138, 68, 0.20);
  background: #fff;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 6px;
  transition: border-color var(--motion-base), box-shadow var(--motion-base), transform var(--motion-base);
  cursor: pointer;
}

.pdp-choice-card:hover {
  border-color: rgba(184, 138, 68, 0.45);
  box-shadow: var(--shadow-card);
  transform: translateY(-2px);
}

.pdp-choice-card__icon {
  color: var(--gold);
  font-size: 28px;
  margin-bottom: 4px;
}

.pdp-choice-card h3 { font-size: 16px; font-weight: 700; color: var(--ink); }
.pdp-choice-card__body {
  margin-top: 4px;
  font-size: 13px;
  line-height: 1.55;
  color: rgba(43, 41, 38, 0.72);
}

/* Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity var(--motion-base) var(--easing-standard); }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
