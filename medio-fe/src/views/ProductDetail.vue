<script setup lang="ts">
// ─────────────────────────────────────────────────────────────────────────
// FIXME P1-11 (Phase 3): God component (1.330 LOC) — refactor ke sub-tree.
// Lihat: medio-fe/src/views/REFACTOR_PLAN.md untuk migration plan lengkap.
// Composables baru tersedia: useFormatMoney.
// ─────────────────────────────────────────────────────────────────────────
import { logger } from '../core/utils/logger';
import { computed, ref, reactive, onMounted, watch } from 'vue';
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
    pdSingle: ''
  }
});

const isLensModalOpen = ref(false);
const isLensChoiceModalOpen = ref(false);

// ── Lens Configurator state ──────────────────────────────────────────────────
const allCoatings = ref<LensCoating[]>([]);
const isCoatingsLoading = ref(false);
const selectedLensOption = ref<LensOption | null>(null);
const selectedCoating = ref<LensCoating | null>(null);
// Step: 'lens' = pilih lens option, 'coating' = pilih coating
const configuratorStep = ref<'lens' | 'coating'>('lens');
// Computed harga total dengan lens + coating
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

    // Inject SEO meta + JSON-LD
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

    // Track product view
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
      loadPrescriptions(); // muat resep tersimpan user jika login
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
    // Assign compatible_lens_options ke product agar bisa diakses di template dan modal
    if (product.value && recommendations.compatible_lens_options) {
      (product.value as any).compatible_lens_options = recommendations.compatible_lens_options;
    }
  } catch (error) {
    logger.warn('Failed to fetch product recommendations', error);
  }
};

const handleAddToCartClick = () => {
  if (!product.value) return;

  // 1. Produk appointment → redirect ke halaman appointment
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

  // 2. Softlens / lensa kontak → langsung ke keranjang (dengan resep jika butuh)
  if (productCategoryContext.value.includes('softlens') || productCategoryContext.value.includes('lensa-kontak')) {
    executeAddToCart();
    return;
  }

  // 3. Lensa standalone (bukan softlens, bukan frame) dengan resep
  //    → tampilkan pilihan: beli lensa saja atau pilih frame dulu
  if (isStandaloneLensProduct.value && product.value.is_prescription_required) {
    isLensChoiceModalOpen.value = true;
    return;
  }

  // 4. Frame yang butuh resep → WAJIB buka lens configurator (pilih lens + coating)
  //    Ini berlaku meski compatible_lens_options kosong (akan tampil pesan di modal)
  if (isFrameProduct.value && product.value.is_prescription_required) {
    openLensConfigurator();
    return;
  }

  // 5. Frame tanpa resep tapi punya lens options → buka lens configurator
  if (isFrameProduct.value && (product.value as any).compatible_lens_options?.length > 0) {
    openLensConfigurator();
    return;
  }

  // 6. Produk lainnya → langsung ke keranjang
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
      size: formState.size
    },
    // Sertakan lens option & coating yang dipilih dari configurator
    lens_option_id: selectedLensOption.value?.id ?? null,
    lens_coating_id: selectedCoating.value?.id ?? null,
    // Sertakan prescription profile jika user memilih resep tersimpan
    prescription_profile_id: selectedPrescriptionProfileId.value ?? null,
  };

  cartStore.addToCart(
    cartItem as any,
    product.value.is_prescription_required ? formState.prescription : undefined,
    selectedLens
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

// Format nilai numerik dari DB agar cocok dengan format sphOptions ('+0.50', '-1.25', '0.00')
const formatSphValue = (val: number | string | null | undefined): string => {
  if (val == null || val === '') return '0.00';
  const num = parseFloat(String(val));
  if (isNaN(num)) return '0.00';
  if (num === 0) return '0.00';
  const formatted = Math.abs(num).toFixed(2);
  return num > 0 ? `+${formatted}` : `-${formatted}`;
};

// ── Prescription Profile (resep tersimpan dari profil user) ──────────────────
const prescriptions = ref<PrescriptionProfile[]>([]);
const selectedPrescriptionProfileId = ref<number | null>(null);

const loadPrescriptions = async () => {
  if (!authStore.user) return;
  try {
    prescriptions.value = await prescriptionRepository.list();
  } catch (e) {
    // silent — user mungkin belum login atau belum punya resep
  }
};

const applyPrescriptionProfile = (profile: PrescriptionProfile) => {
  // Toggle: klik lagi untuk deselect
  if (selectedPrescriptionProfileId.value === profile.id) {
    selectedPrescriptionProfileId.value = null;
    return;
  }
  selectedPrescriptionProfileId.value = profile.id;
  // Isi form resep dari data profil — format nilai agar cocok dengan sphOptions
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

watch(
  () => formState.prescription.od.cyl,
  (cylinder) => {
    if (Number(cylinder || 0) === 0) {
      formState.prescription.od.axis = '';
    }
  },
);

watch(
  () => formState.prescription.os.cyl,
  (cylinder) => {
    if (Number(cylinder || 0) === 0) {
      formState.prescription.os.axis = '';
    }
  },
);

watch(
  () => supportsAddInConfigurator.value,
  (supportsAdd) => {
    if (!supportsAdd) {
      formState.prescription.od.add = '0.00';
      formState.prescription.os.add = '0.00';
    }
  },
);

watch(
  () => formState.pdType,
  (pdType) => {
    if (pdType === 'single') {
      formState.prescription.pdRight = '';
      formState.prescription.pdLeft = '';
      return;
    }

    formState.prescription.pdSingle = '';
  },
);

const getProductPromos = (p: Product | null) => {
  if (!p) return { buyPromos: [], discountPromos: [] };
  
  const buyPromos = [...(p.buy_promos || []), ...(p.buy_promos_many || [])];
  const discountPromos = [...(p.discount_promos || []), ...(p.discount_promos_many || [])];

  // Add brand-based promos from store
  if (p.brand && cartStore.activePromos.length > 0) {
    cartStore.activePromos.forEach(promo => {
      // Check if already in list to avoid duplicates
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
  // Match patterns like "15.00%" and turn them into "15%"
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
</script>

<template>
  <!-- Loading State -->
  <main v-if="isLoading" class="flex-grow flex items-center justify-center bg-ivory py-32">
    <div class="flex flex-col items-center gap-4">
      <div class="w-14 h-14 rounded-lg border-4 border-t-transparent animate-spin" style="border-color: rgba(184,138,68,0.25); border-top-color: var(--gold);"></div>
      <p class="text-sm font-medium text-graphite/65">Memuat produk...</p>
    </div>
  </main>

  <main v-else-if="product" class="flex-grow w-full bg-ivory">
    <PageHero
      :title="product.name"
      :subtitle="product.brand ? (product.brand + ' - Detail produk dan pilihan lensa.') : 'Detail produk dan pilihan lensa.'"
      :breadcrumbs="[{ label: 'Katalog Produk', to: '/products' }, { label: product.brand || 'Produk' }]"
      back-to="/products"
      back-label="Kembali ke Koleksi"
    />

    <div class="container-premium pt-32 pb-8 md:pt-36 md:pb-10">
      <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:gap-8">

        <!-- ── Left: Image Gallery ── -->
        <div class="flex flex-col gap-3 lg:col-span-6 lg:self-start">
          <!-- Main Image -->
          <div
            class="relative mx-auto flex aspect-square w-full max-w-[560px] items-center justify-center overflow-hidden rounded-lg border border-mist bg-porcelain shadow-card"
            style="background: linear-gradient(145deg, var(--ivory), var(--mist)); border-color: rgba(184,138,68,0.15);"
          >
            <img
              :src="resolveImageUrl(product.images?.[activeImage])"
              class="h-full w-full object-contain p-3 mix-blend-multiply sm:p-4 md:p-5"
              alt="Product" loading="lazy" decoding="async" />
            <!-- Image Nav Arrows (if multiple) -->
            <button
              v-if="product.images?.length > 1 && activeImage > 0"
              @click="activeImage--"
              class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-lg flex items-center justify-center transition-all opacity-0 group-hover:opacity-100"
              style="background: rgba(255,255,255,0.9); box-shadow: 0 2px 12px rgba(0,0,0,0.1);"
            >
              <span class="material-symbols-outlined text-lg" style="color: var(--ink);">chevron_left</span>
            </button>
            <button
              v-if="product.images?.length > 1 && activeImage < product.images.length - 1"
              @click="activeImage++"
              class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-lg flex items-center justify-center transition-all opacity-0 group-hover:opacity-100"
              style="background: rgba(255,255,255,0.9); box-shadow: 0 2px 12px rgba(0,0,0,0.1);"
            >
              <span class="material-symbols-outlined text-lg" style="color: var(--ink);">chevron_right</span>
            </button>
          </div>

          <!-- Thumbnails -->
          <div v-if="product.images?.length > 1" class="mx-auto grid w-full max-w-[560px] grid-cols-5 gap-2 sm:gap-3">
            <button
              v-for="(img, index) in product.images"
              :key="index"
              @click="activeImage = index"
              class="aspect-square overflow-hidden rounded-lg border-2 p-1.5 transition-all sm:p-2"
              :style="activeImage === index
                ? 'border-color: var(--gold); opacity: 1; background: linear-gradient(145deg, var(--ivory), var(--mist));'
                : 'border-color: transparent; opacity: 0.6; background: linear-gradient(145deg, var(--ivory), var(--mist));'"
              :class="{ 'hover:opacity-100': activeImage !== index }"
            >
              <img alt="" :src="resolveImageUrl(img)" class="w-full h-full object-contain mix-blend-multiply" loading="lazy" decoding="async" />
            </button>
          </div>
        </div>

        <!-- ── Right: Product Info ── -->
        <div class="flex flex-col gap-3 lg:col-span-6 lg:self-start">

          <!-- Category + Badges -->
          <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
              <p class="text-[10px] font-black uppercase tracking-[0.3em]" style="color: #6F4E1D;">
                Koleksi {{ (product as any).category?.name || 'Optik' }}
              </p>
              <div
                v-if="product.is_best_seller"
                class="flex items-center gap-1.5 px-3 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white"
                style="background: rgba(26,18,9,0.8); backdrop-filter: blur(4px); border: 1px solid rgba(184,138,68,0.3);"
              >
                <span class="material-symbols-outlined text-[10px]" style="color: var(--gold);">trending_up</span>
                Terlaris
              </div>
            </div>

            <div class="flex flex-col gap-3">
              <!-- Promo Badge (Buy X Get Y) -->
              <div
                v-if="getProductPromos(product).buyPromos.length > 0"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white shadow-card"
                style="background: var(--gold); border: 1px solid rgba(255,255,255,0.2);"
              >
                <span class="material-symbols-outlined text-[10px]">redeem</span>
                {{ 
                  getProductPromos(product).buyPromos[0]
                    ? `Beli ${getProductPromos(product).buyPromos[0].buy_quantity} Gratis ${getProductPromos(product).buyPromos[0].get_quantity}` 
                    : 'Promo Spesial' 
                }}
                <div v-if="getProductPromos(product).buyPromos[0]?.description" class="mt-1 normal-case font-medium opacity-90">
                  {{ formatPromoDescription(getProductPromos(product).buyPromos[0]?.description) }}
                </div>
              </div>

              <!-- Promo Badge (Product Discount) -->
              <div
                v-if="getProductPromos(product).discountPromos.length > 0"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white shadow-card"
                style="background: #ef4444; border: 1px solid rgba(255,255,255,0.2);"
              >
                <span class="material-symbols-outlined text-[10px]">percent</span>
                {{ 
                  getProductPromos(product).discountPromos[0] 
                    ? `Diskon ${getProductPromos(product).discountPromos[0].discount_type === 'percentage' ? Math.round(Number(getProductPromos(product).discountPromos[0].discount_value)) + '%' : 'Rp ' + Number(getProductPromos(product).discountPromos[0].discount_value).toLocaleString('id-ID')}` 
                    : 'Diskon Spesial' 
                }}
                <div v-if="getProductPromos(product).discountPromos[0]?.description" class="mt-1 normal-case font-medium opacity-90">
                  {{ formatPromoDescription(getProductPromos(product).discountPromos[0]?.description) }}
                </div>
              </div>
            </div>
          </div>

          <!-- Name + Price -->
          <div class="flex flex-col gap-1.5">
            <p class="text-xs font-black uppercase tracking-[0.2em]" style="color: var(--graphite);">
              {{ product.name }}
            </p>
            <h1 class="text-2xl font-bold leading-tight tracking-normal md:text-3xl" style="color: var(--ink); font-family: 'Cormorant Garamond', serif; letter-spacing: -0.02em;">
              {{ product.brand || 'Optik Medio' }}
            </h1>
            <div class="flex flex-wrap items-center gap-2 text-xs" style="color: var(--graphite);">
              <span class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base" style="color: var(--gold);">star</span>
                {{ Number(reviewSummary.avg_rating || product.avg_rating || 0).toFixed(1) }} dari {{ reviewSummary.total_reviews || product.review_count || 0 }} ulasan
              </span>
              <span class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base" style="color: var(--gold);">shopping_bag</span>
                {{ Number(product.purchase_count || 0) }} terjual
              </span>
            </div>
            <div class="flex items-center justify-between">
              <p v-if="!product.is_not_for_sale" class="text-xl font-black" style="color: #6F4E1D;">
                Rp {{ product.price.toLocaleString('id-ID') }}
              </p>
              <p v-else class="text-base font-bold uppercase tracking-widest" style="color: #6F4E1D;">
                Katalog Informasi
              </p>
              <div v-if="!product.is_not_for_sale" class="flex items-center gap-2">
                <span
                  class="w-2.5 h-2.5 rounded-lg"
                  :style="product.stock > 0 ? 'background: #16a34a; box-shadow: 0 0 8px rgba(22,163,74,0.5);' : 'background: #dc2626;'"
                ></span>
                <p class="text-xs font-bold" :style="product.stock > 0 ? 'color: #15803d;' : 'color: #dc2626;'">
                  {{ product.stock > 0 ? `Stok: ${product.stock}` : 'Stok Habis' }}
                </p>
              </div>
            </div>
          </div>

          <button
            @click="toggleWishlist"
            class="btn-outline w-full py-2 text-xs uppercase tracking-[0.1em]"
            :style="isWishlisted
              ? 'background: rgba(184,138,68,0.12); color: #6F4E1D; border-color: rgba(184,138,68,0.3);'
              : 'background: white; color: var(--graphite); border-color: rgba(184,138,68,0.18);'"
          >
            <span class="material-symbols-outlined text-lg">{{ isWishlisted ? 'favorite' : 'favorite_border' }}</span>
            {{ isWishlisted ? 'Tersimpan di Wishlist' : 'Tambah ke Wishlist' }}
          </button>

          <button
            @click="toggleCompare"
            class="w-full rounded-lg border px-4 py-2.5 flex items-center justify-center gap-2 text-sm font-black uppercase tracking-[0.14em] transition-all"
            :style="isCompared
              ? 'background: rgba(63,111,143,0.12); color: var(--optical-blue); border-color: rgba(63,111,143,0.28);'
              : 'background: white; color: var(--graphite); border-color: rgba(184,138,68,0.18);'"
          >
            <span class="material-symbols-outlined text-lg">compare_arrows</span>
            {{ isCompared ? 'Ada di Compare' : 'Bandingkan' }}
          </button>

          <!-- Divider -->
          <div class="h-px" style="background: linear-gradient(90deg, rgba(184,138,68,0.3), transparent);"></div>

          <!-- Description -->
          <p v-if="product.description" class="text-xs leading-6" style="color: var(--graphite);">
            {{ product.description }}
          </p>

          <!-- Frame Size Guide -->
          <div
            v-if="hasFrameGuide"
            class="border rounded-lg overflow-hidden"
            style="background: rgba(255,255,255,0.78); border-color: rgba(184,138,68,0.22);"
          >
            <div class="px-3 py-2.5 border-b flex items-center justify-between gap-2" style="border-color: rgba(184,138,68,0.14);">
              <div>
                <p class="text-xs font-black uppercase tracking-[0.16em]" style="color: #6F4E1D;">Panduan Ukuran Frame</p>
                <p class="mt-0.5 text-[11px]" style="color: var(--graphite);">Gunakan data ini untuk membandingkan kenyamanan fit.</p>
              </div>
              <span class="material-symbols-outlined text-lg" style="color: var(--gold);">straighten</span>
            </div>

            <div v-if="frameSizeRows.length > 0" class="grid grid-cols-2 sm:grid-cols-4 border-b" style="border-color: rgba(184,138,68,0.14);">
              <div
                v-for="row in frameSizeRows"
                :key="row.label"
                class="px-3 py-2 border-r last:border-r-0"
                style="border-color: rgba(184,138,68,0.14);"
              >
                <p class="text-[10px] font-black uppercase tracking-widest" style="color: var(--graphite);">{{ row.label }}</p>
                <p class="mt-0.5 text-lg font-black" style="color: var(--ink);">{{ row.value }} <span class="text-xs font-bold text-graphite/70">mm</span></p>
              </div>
            </div>

            <div v-if="frameProfileRows.length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-px" style="background: rgba(184,138,68,0.12);">
              <div
                v-for="row in frameProfileRows"
                :key="row.label"
                class="px-3 py-2"
                style="background: white;"
              >
                <p class="text-[10px] font-black uppercase tracking-widest" style="color: var(--graphite);">{{ row.label }}</p>
                <p class="mt-0.5 text-sm font-bold" style="color: var(--ink);">{{ formatProductLabel(row.value) }}</p>
              </div>
            </div>
          </div>

          <!-- Prescription Notice -->
          <div
            v-if="product.is_prescription_required && !product.is_not_for_sale"
            class="flex items-start gap-2 rounded-lg border p-2.5"
            style="background: rgba(255,255,255,0.72); border-color: rgba(184,138,68,0.28);"
          >
            <span class="material-symbols-outlined mt-0.5" style="color: var(--gold);">info</span>
            <div>
              <p class="text-xs font-bold" style="color: #6F4E1D;">Membutuhkan Resep Optik</p>
              <p class="mt-0.5 text-xs leading-5" style="color: var(--graphite);">Produk ini memerlukan resep optik yang valid untuk diproses.</p>
            </div>
          </div>

          <!-- Info Only Notice -->
          <div
            v-if="product.is_not_for_sale"
            class="flex flex-col gap-3 rounded-lg border p-4"
            style="background: rgba(26,18,9,0.03); border-color: rgba(184,138,68,0.2); border-left: 4px solid var(--gold);"
          >
            <div class="flex items-center gap-2 text-ink">
              <span class="material-symbols-outlined text-lg" style="color: var(--gold);">menu_book</span>
              <p class="text-sm font-bold">Katalog Brand Lensa</p>
            </div>
            <p class="text-xs leading-6 text-graphite">
              Informasi produk ini merupakan bagian dari katalog brand lensa yang kami gunakan di Optik Medio. 
              Produk ini tidak dijual secara terpisah. Untuk konsultasi lebih lanjut mengenai lensa terbaik untuk kebutuhan mata Anda, silakan hubungi tim ahli kami.
            </p>
            <button class="w-fit bg-[var(--ink)] px-4 py-2 text-[11px] font-bold uppercase tracking-widest text-white transition-colors hover:bg-graphite">
              Hubungi CS Optik Medio
            </button>
          </div>

          <!-- Color Selector -->
          <div v-if="product.variants?.colors?.length && !product.is_not_for_sale" class="flex flex-col gap-3">
            <p class="text-xs font-bold uppercase tracking-wider" style="color: var(--graphite);">
              Warna: <span class="font-medium" style="color: var(--ink);">{{ formState.color?.name }}</span>
            </p>
            <div class="flex gap-3 flex-wrap">
              <button
                v-for="color in product.variants.colors"
                :key="color.name"
                @click="formState.color = color"
                :style="{ backgroundColor: color.hex }"
                :class="['w-10 h-10 rounded-lg border-4 focus:outline-none transition-all', formState.color?.name === color.name ? 'scale-110' : 'border-transparent hover:scale-105']"
                :style-extra="formState.color?.name === color.name ? 'border-color: var(--gold); box-shadow: 0 0 0 2px rgba(184,138,68,0.4);' : ''"
              ></button>
            </div>
          </div>

          <!-- Size Selector -->
          <div v-if="product.variants?.sizes?.length && !product.is_not_for_sale" class="flex flex-col gap-3">
            <p class="text-xs font-bold uppercase tracking-wider" style="color: var(--graphite);">Ukuran</p>
            <div class="flex gap-2 flex-wrap">
              <button
                v-for="size in product.variants.sizes"
                :key="size"
                @click="formState.size = size"
                class="rounded-lg border px-3 py-2 text-xs font-bold transition-all"
                :style="formState.size === size
                  ? 'background: var(--ink); color: white; border-color: var(--ink); box-shadow: 0 4px 12px rgba(26,18,9,0.2);'
                  : 'background: transparent; color: var(--graphite); border-color: rgba(184,138,68,0.25);'"
              >
                {{ size }}
              </button>
            </div>
          </div>

          <!-- Prescription Form -->
          <div v-if="product.is_prescription_required && !product.is_not_for_sale" class="flex flex-col gap-3 border-t pt-3" style="border-color: rgba(184,138,68,0.15);">
            <div class="flex items-center justify-between">
              <h2 class="text-base font-bold" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Resep Kacamata Anda</h2>
            </div>

            <!-- Gunakan Resep Tersimpan -->
            <div v-if="authStore.user && prescriptions.length > 0" class="flex flex-col gap-2">
              <p class="text-[10px] font-black uppercase tracking-[0.2em]" style="color: var(--graphite);">Resep Tersimpan</p>
              <div class="flex flex-col gap-2">
                <button
                  v-for="profile in prescriptions"
                  :key="profile.id"
                  @click="applyPrescriptionProfile(profile)"
                  class="flex items-center justify-between border p-2.5 text-left transition-all hover:shadow-sm"
                  :style="selectedPrescriptionProfileId === profile.id
                    ? 'border-color: var(--gold); background: rgba(184,138,68,0.06); box-shadow: 0 0 0 2px rgba(184,138,68,0.25);'
                    : 'border-color: rgba(184,138,68,0.2); background: white;'"
                >
                  <div>
                    <p class="text-xs font-bold" style="color: var(--ink);">{{ profile.label }}</p>
                    <p class="text-[10px] mt-0.5" style="color: var(--graphite);">
                      OD: {{ profile.right_sphere ?? '—' }} / {{ profile.right_cylinder ?? '—' }} / {{ profile.right_axis ?? '—' }}
                      &nbsp;|&nbsp;
                      OS: {{ profile.left_sphere ?? '—' }} / {{ profile.left_cylinder ?? '—' }} / {{ profile.left_axis ?? '—' }}
                    </p>
                  </div>
                  <div class="flex items-center gap-2">
                    <span v-if="profile.verification_status === 'approved'" class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5" style="background: rgba(22,163,74,0.1); color: #16a34a;">Terverifikasi</span>
                    <span v-else-if="profile.verification_status === 'pending'" class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5" style="background: rgba(234,179,8,0.1); color: #ca8a04;">Menunggu</span>
                    <span class="material-symbols-outlined text-base" :style="selectedPrescriptionProfileId === profile.id ? 'color: var(--gold);' : 'color: rgba(184,138,68,0.3);'">
                      {{ selectedPrescriptionProfileId === profile.id ? 'check_circle' : 'radio_button_unchecked' }}
                    </span>
                  </div>
                </button>
              </div>
              <div class="flex items-center gap-3 mt-1">
                <div class="flex-1 h-px" style="background: rgba(184,138,68,0.15);"></div>
                <span class="text-[10px] font-black uppercase tracking-wider" style="color: var(--graphite);">atau isi manual</span>
                <div class="flex-1 h-px" style="background: rgba(184,138,68,0.15);"></div>
              </div>
            </div>

            <div class="rounded-lg border p-3" style="background: rgba(255,255,255,0.82); border-color: rgba(184,138,68,0.24);">
              <div class="mb-3 grid gap-2" :class="supportsAddInConfigurator ? 'grid-cols-5' : 'grid-cols-4'">
                <div class="col-span-1"></div>
                <div class="text-center text-[10px] font-black uppercase tracking-widest" style="color: var(--graphite);">SPH</div>
                <div class="text-center text-[10px] font-black uppercase tracking-widest" style="color: var(--graphite);">CYL</div>
                <div class="text-center text-[10px] font-black uppercase tracking-widest" style="color: var(--graphite);">Axis</div>
                <div v-if="supportsAddInConfigurator" class="text-center text-[10px] font-black uppercase tracking-widest" style="color: var(--graphite);">ADD</div>

                <div class="flex items-center justify-end pr-2 text-xs font-black" style="color: var(--ink);">OD</div>
                <div><select v-model="formState.prescription.od.sph" class="input-field rounded-lg p-1.5 text-xs" style="background: white; border: 1px solid rgba(184,138,68,0.24); color: var(--ink);"><option v-for="opt in sphOptions" :value="opt">{{opt}}</option></select></div>
                <div><select v-model="formState.prescription.od.cyl" class="input-field rounded-lg p-1.5 text-xs" style="background: white; border: 1px solid rgba(184,138,68,0.24); color: var(--ink);"><option v-for="opt in sphOptions" :value="opt">{{opt}}</option></select></div>
                <div><input v-model="formState.prescription.od.axis" :disabled="!usesOdAxis" type="number" min="1" max="180" class="input-field rounded-lg p-1.5 text-center text-xs disabled:bg-mist disabled:text-graphite/70 disabled:cursor-not-allowed" style="background: white; border: 1px solid rgba(184,138,68,0.24); color: var(--ink);"/></div>
                <div v-if="supportsAddInConfigurator"><select v-model="formState.prescription.od.add" class="input-field rounded-lg p-1.5 text-xs" style="background: white; border: 1px solid rgba(184,138,68,0.24); color: var(--ink);"><option v-for="opt in sphOptions.filter((opt) => !String(opt).startsWith('-'))" :value="opt">{{opt}}</option></select></div>

                <div class="flex items-center justify-end pr-2 text-xs font-black mt-2" style="color: var(--ink);">OS</div>
                <div class="mt-2"><select v-model="formState.prescription.os.sph" class="input-field rounded-lg p-1.5 text-xs" style="background: white; border: 1px solid rgba(184,138,68,0.24); color: var(--ink);"><option v-for="opt in sphOptions" :value="opt">{{opt}}</option></select></div>
                <div class="mt-2"><select v-model="formState.prescription.os.cyl" class="input-field rounded-lg p-1.5 text-xs" style="background: white; border: 1px solid rgba(184,138,68,0.24); color: var(--ink);"><option v-for="opt in sphOptions" :value="opt">{{opt}}</option></select></div>
                <div class="mt-2"><input v-model="formState.prescription.os.axis" :disabled="!usesOsAxis" type="number" min="1" max="180" class="input-field rounded-lg p-1.5 text-center text-xs disabled:bg-mist disabled:text-graphite/70 disabled:cursor-not-allowed" style="background: white; border: 1px solid rgba(184,138,68,0.24); color: var(--ink);"/></div>
                <div v-if="supportsAddInConfigurator" class="mt-2"><select v-model="formState.prescription.os.add" class="input-field rounded-lg p-1.5 text-xs" style="background: white; border: 1px solid rgba(184,138,68,0.24); color: var(--ink);"><option v-for="opt in sphOptions.filter((opt) => !String(opt).startsWith('-'))" :value="opt">{{opt}}</option></select></div>
              </div>

              <div class="border-t pt-3" style="border-color: rgba(184,138,68,0.15);">
                <div class="mb-3 flex items-center gap-4">
                  <label class="flex items-center gap-2 cursor-pointer text-xs font-bold" style="color: var(--graphite);">
                    <input type="radio" v-model="formState.pdType" value="single" class="accent-gold"/>
                    PD Tunggal
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer text-xs font-bold" style="color: var(--graphite);">
                    <input type="radio" v-model="formState.pdType" value="dual" class="accent-gold"/>
                    PD Ganda
                  </label>
                </div>
                <div v-if="formState.pdType === 'dual'" class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-[10px] font-bold mb-1.5" style="color: var(--graphite);">PD Kanan</label>
                    <input v-model="formState.prescription.pdRight" type="number" min="25" max="38" class="w-full rounded-lg p-2 text-sm" style="background: white; border: 1px solid rgba(184,138,68,0.24); color: var(--ink);"/>
                  </div>
                  <div>
                    <label class="block text-[10px] font-bold mb-1.5" style="color: var(--graphite);">PD Kiri</label>
                    <input v-model="formState.prescription.pdLeft" type="number" min="25" max="38" class="w-full rounded-lg p-2 text-sm" style="background: white; border: 1px solid rgba(184,138,68,0.24); color: var(--ink);"/>
                  </div>
                </div>
                <div v-else>
                  <label class="block text-[10px] font-bold mb-1.5" style="color: var(--graphite);">PD</label>
                  <input v-model="formState.prescription.pdSingle" type="number" min="50" max="75" class="w-full rounded-lg p-2 text-sm" style="background: white; border: 1px solid rgba(184,138,68,0.24); color: var(--ink);"/>
                </div>
              </div>
            </div>
          </div>

          <!-- Lens Configuration Summary (muncul setelah user memilih dari modal) -->
          <div
            v-if="isFrameProduct && (product as any).compatible_lens_options?.length > 0 && (selectedLensOption || selectedCoating)"
            class="border p-3"
            style="background: rgba(255,255,255,0.78); border-color: rgba(184,138,68,0.28);"
          >
            <div class="flex items-center justify-between mb-2">
              <p class="text-[10px] font-black uppercase tracking-[0.2em]" style="color: var(--graphite);">Konfigurasi Lensa</p>
              <button @click="openLensConfigurator" class="text-[10px] font-black uppercase tracking-wider underline" style="color: #6F4E1D;">Ubah</button>
            </div>
            <div class="flex flex-col gap-1">
              <div v-if="selectedLensOption" class="flex items-center justify-between text-xs">
                <span style="color: var(--graphite);">{{ selectedLensOption.name }}</span>
                <span class="font-bold" style="color: var(--ink);">+Rp {{ (selectedLensOption.base_price || 0).toLocaleString('id-ID') }}</span>
              </div>
              <div v-if="selectedCoating" class="flex items-center justify-between text-xs">
                <span style="color: var(--graphite);">{{ selectedCoating.name }}</span>
                <span class="font-bold" style="color: var(--ink);">+Rp {{ (selectedCoating.price || 0).toLocaleString('id-ID') }}</span>
              </div>
            </div>
          </div>

          <!-- Hint untuk frame yang belum dikonfigurasi -->
          <div
            v-else-if="isFrameProduct && (product as any).compatible_lens_options?.length > 0 && !selectedLensOption"
            class="p-3 border text-xs"
            style="background: rgba(255,255,255,0.78); border-color: rgba(184,138,68,0.24); color: var(--graphite);"
          >
            <span class="material-symbols-outlined text-sm align-middle mr-1" style="color: var(--gold);">info</span>
            Klik tombol di bawah untuk memilih jenis lensa dan coating yang sesuai.
          </div>

          <!-- Add to Cart Button -->
          <button
            v-if="!product.is_not_for_sale || isAppointmentProduct"
            @click="handleAddToCartClick"
            :disabled="!isAppointmentProduct && product.stock <= 0"
            class="flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-black uppercase tracking-[0.14em] shadow-card transition-all"
            :style="(isAppointmentProduct || product.stock > 0)
              ? (addedToCart
                ? 'background: linear-gradient(135deg, #15803d, #16a34a); color: white; box-shadow: 0 8px 25px rgba(22,163,74,0.3);'
                : 'background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%); color: white; box-shadow: 0 8px 25px rgba(26,18,9,0.25);')
              : 'background: rgba(245,242,238,0.8); color: #a09080; cursor: not-allowed;'"
          >
            <span class="material-symbols-outlined text-lg">{{ addedToCart ? 'check_circle' : (isAppointmentProduct ? 'calendar_today' : (product.stock > 0 ? 'shopping_bag' : 'block')) }}</span>
            {{ addedToCart ? 'Ditambahkan!' : (isAppointmentProduct ? 'Booking Jadwal Konsultasi' : (product.stock > 0 ? (isStandaloneLensProduct ? 'Lanjutkan Pembelian Lensa' : (isFrameProduct && (product as any).compatible_lens_options?.length > 0 ? (selectedLensOption ? 'Tambah ke Keranjang' : 'Pilih Lensa & Coating') : 'Tambah ke Keranjang')) : 'Stok Habis')) }}
          </button>

          <!-- Trust Badges -->
          <div class="grid grid-cols-3 gap-2 pt-1">
            <div class="flex flex-col items-center gap-1.5 text-center">
              <span class="material-symbols-outlined text-lg" style="color: var(--gold);">verified</span>
              <span class="text-[9px] font-bold uppercase tracking-wide" style="color: var(--graphite);">Produk Asli</span>
            </div>
            <div class="flex flex-col items-center gap-1.5 text-center">
              <span class="material-symbols-outlined text-lg" style="color: var(--gold);">local_shipping</span>
              <span class="text-[9px] font-bold uppercase tracking-wide" style="color: var(--graphite);">Pengiriman Cepat</span>
            </div>
            <div class="flex flex-col items-center gap-1.5 text-center">
              <span class="material-symbols-outlined text-lg" style="color: var(--gold);">support_agent</span>
              <span class="text-[9px] font-bold uppercase tracking-wide" style="color: var(--graphite);">Garansi Resmi</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <section v-if="hasRecommendationSection" class="container-commerce pb-10">
      <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <p class="text-xs font-black uppercase tracking-[0.25em] mb-2" style="color: #6F4E1D;">Rekomendasi Optik</p>
          <h2 class="text-xl font-black" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Pilihan yang Cocok</h2>
        </div>
        <router-link to="/products" class="text-xs font-black uppercase tracking-widest transition-all flex items-center gap-2 group" style="color: #6F4E1D;">
          Lihat Koleksi
          <span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">arrow_forward</span>
        </router-link>
      </div>

      <div v-if="primaryRecommendations.length > 0" class="mb-7">
        <h3 class="mb-3 text-xs font-black uppercase tracking-[0.18em]" style="color: #6F4E1D;">{{ primaryRecommendationTitle }}</h3>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-5">
          <article
            v-for="item in primaryRecommendations.slice(0, 4)"
            :key="item.id"
            @click="router.push(`/products/${item.slug}`)"
            class="cursor-pointer border bg-porcelain transition-all hover:shadow-card"
            style="border-color: rgba(184,138,68,0.14);"
          >
            <div class="flex aspect-square items-center justify-center p-3" style="background: linear-gradient(145deg, var(--ivory), var(--mist));">
              <img :src="resolveImageUrl(item)" :alt="item.name" class="w-full h-full object-contain mix-blend-multiply" loading="lazy" decoding="async" />
            </div>
            <div class="p-3">
              <p class="text-[10px] font-black uppercase tracking-widest mb-1" style="color: var(--graphite);">{{ item.name }}</p>
              <h4 class="line-clamp-2 text-xs font-bold" style="color: var(--ink);">{{ item.brand || 'Optik Medio' }}</h4>
              <p class="mt-1.5 text-xs font-black" style="color: #6F4E1D;">Rp {{ item.price.toLocaleString('id-ID') }}</p>
            </div>
          </article>
        </div>
      </div>

      <div v-if="showCompatibleLenses">
        <h3 class="mb-3 text-xs font-black uppercase tracking-[0.18em]" style="color: #6F4E1D;">Lensa Kompatibel</h3>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-5">
          <article
            v-for="item in compatibleLenses.slice(0, 4)"
            :key="item.id"
            @click="router.push(`/products/${item.slug}`)"
            class="cursor-pointer border bg-porcelain transition-all hover:shadow-card"
            style="border-color: rgba(184,138,68,0.14);"
          >
            <div class="flex aspect-square items-center justify-center p-3" style="background: linear-gradient(145deg, var(--ivory), var(--mist));">
              <img :src="resolveImageUrl(item)" :alt="item.name" class="w-full h-full object-contain mix-blend-multiply" loading="lazy" decoding="async" />
            </div>
            <div class="p-3">
              <p class="text-[10px] font-black uppercase tracking-widest mb-1" style="color: var(--graphite);">{{ item.brand || 'Lensa' }}</p>
              <h4 class="line-clamp-2 text-xs font-bold" style="color: var(--ink);">{{ item.name }}</h4>
              <p class="mt-1.5 text-xs font-black" style="color: #6F4E1D;">Rp {{ item.price.toLocaleString('id-ID') }}</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="container-commerce pb-10">
      <div class="rounded-lg border p-5 md:p-6" style="background: white; border-color: rgba(184,138,68,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <p class="text-xs font-black uppercase tracking-[0.25em] mb-2" style="color: #6F4E1D;">Customer Reviews</p>
            <h2 class="text-xl font-black" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Ulasan Produk</h2>
          </div>
          <div class="text-sm" style="color: var(--graphite);">
            Rating rata-rata <span class="font-black" style="color: var(--ink);">{{ Number(reviewSummary.avg_rating || product.avg_rating || 0).toFixed(1) }}</span>
            dari {{ reviewSummary.total_reviews || product.review_count || 0 }} ulasan
          </div>
        </div>

        <div v-if="productReviews.length === 0" class="text-sm" style="color: var(--graphite);">
          Belum ada ulasan untuk produk ini.
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <article
            v-for="review in productReviews"
            :key="review.id"
            class="rounded-lg border p-4"
            style="background: rgba(255,255,255,0.86); border-color: rgba(184,138,68,0.18);"
          >
            <div class="flex items-center justify-between gap-4 mb-3">
              <p class="font-black" style="color: var(--ink);">{{ review.user_name }}</p>
              <span class="text-xs" style="color: var(--graphite);">{{ review.created_at }}</span>
            </div>
            <div class="flex items-center gap-1 mb-3">
              <span
                v-for="star in 5"
                :key="star"
                class="material-symbols-outlined text-base"
                :style="star <= review.rating ? 'color: var(--gold);' : 'color: rgba(184,138,68,0.25);'"
              >
                star
              </span>
            </div>
            <p class="text-xs leading-6" style="color: var(--graphite);">
              {{ review.comment || 'Customer tidak menambahkan komentar tertulis.' }}
            </p>
          </article>
        </div>
      </div>
    </section>

    <!-- ╔══════════════════════════════════════╗ -->
    <!-- ║          LENS SELECTOR MODAL         ║ -->
    <!-- ╚══════════════════════════════════════╝ -->
    <Teleport to="body">
      <div v-if="isLensChoiceModalOpen" role="dialog" aria-modal="true" aria-labelledby="lens-choice-modal-title" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(10,8,5,0.75); backdrop-filter: blur(20px);">
        <div class="w-full max-w-md rounded-lg border p-5 md:p-6" style="background: #faf8f5; border-color: rgba(184,138,68,0.2); box-shadow: 0 30px 80px rgba(0,0,0,0.3);">
          <div class="mb-4 flex items-center justify-between">
            <h2 id="lens-choice-modal-title" class="text-xl font-black" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Lanjutkan Pembelian Lensa</h2>
            <button @click="isLensChoiceModalOpen = false" aria-label="Tutup dialog" class="flex h-9 w-9 items-center justify-center rounded-lg transition-all" style="background: rgba(184,138,68,0.1); color: #6F4E1D;">
              <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
          </div>

          <p class="text-sm leading-relaxed mb-6" style="color: var(--graphite);">
            Resep sudah siap. Anda bisa lanjut beli lensa ini saja, atau pilih frame terlebih dulu bila ingin dipasangkan dalam satu pesanan.
          </p>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <button
              @click="executeAddToCart()"
              class="w-full p-5 border text-left transition-all hover:-translate-y-1 hover:shadow-card"
              style="border-color: rgba(184,138,68,0.2); background: white;"
            >
              <span class="material-symbols-outlined text-2xl mb-3 block" style="color: var(--gold);">shopping_bag</span>
              <p class="text-sm font-black uppercase tracking-widest mb-1" style="color: var(--graphite);">Tanpa Frame</p>
              <h3 class="font-bold text-base" style="color: var(--ink);">Beli Lensa Saja</h3>
              <p class="text-sm mt-2" style="color: var(--graphite);">Tambahkan lensa ini ke keranjang dengan resep yang sudah Anda isi.</p>
            </button>

            <button
              @click="chooseFrameBeforeCheckout"
              class="w-full p-5 border text-left transition-all hover:-translate-y-1 hover:shadow-card"
              style="border-color: rgba(184,138,68,0.2); background: white;"
            >
              <span class="material-symbols-outlined text-2xl mb-3 block" style="color: var(--gold);">visibility</span>
              <p class="text-sm font-black uppercase tracking-widest mb-1" style="color: var(--graphite);">Dengan Frame</p>
              <h3 class="font-bold text-base" style="color: var(--ink);">Pilih Frame Dulu</h3>
              <p class="text-sm mt-2" style="color: var(--graphite);">Lanjut ke katalog untuk memilih frame sebelum checkout.</p>
            </button>
          </div>
        </div>
      </div>

      <!-- ╔══════════════════════════════════════════════════╗ -->
      <!-- ║     LENS OPTION + COATING CONFIGURATOR MODAL    ║ -->
      <!-- ╚══════════════════════════════════════════════════╝ -->
      <div v-if="isLensModalOpen" role="dialog" aria-modal="true" aria-labelledby="lens-modal-title" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(10,8,5,0.75); backdrop-filter: blur(20px);">
        <div class="w-full max-w-xl rounded-lg border" style="background: #faf8f5; border-color: rgba(184,138,68,0.2); box-shadow: 0 30px 80px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">

          <!-- Header -->
          <div class="flex items-center justify-between p-5 pb-0 md:p-6 md:pb-0">
            <div>
              <p class="text-[10px] font-black uppercase tracking-[0.24em] mb-1" style="color: #6F4E1D;">
                {{ configuratorStep === 'lens' ? 'Langkah 1 dari 2' : 'Langkah 2 dari 2' }}
              </p>
              <h2 id="lens-modal-title" class="text-xl font-black" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">
                {{ configuratorStep === 'lens' ? 'Pilih Jenis Lensa' : 'Pilih Coating Lensa' }}
              </h2>
              <p class="text-xs mt-1" style="color: var(--graphite);">
                {{ configuratorStep === 'lens'
                  ? 'Pilih jenis lensa yang sesuai dengan kebutuhan penglihatan Anda.'
                  : 'Tambahkan lapisan pelindung untuk kenyamanan dan ketahanan lensa.' }}
              </p>
            </div>
            <button @click="isLensModalOpen = false" class="w-10 h-10 rounded-lg flex items-center justify-center transition-all flex-shrink-0" style="background: rgba(184,138,68,0.1); color: #6F4E1D;">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <!-- Step indicator -->
          <div class="flex gap-2 px-5 pt-4 md:px-6">
            <div class="h-1 flex-1 rounded-lg transition-all" :style="configuratorStep === 'lens' ? 'background: var(--gold);' : 'background: var(--gold);'"></div>
            <div class="h-1 flex-1 rounded-lg transition-all" :style="configuratorStep === 'coating' ? 'background: var(--gold);' : 'background: rgba(184,138,68,0.2);'"></div>
          </div>

          <!-- Harga sementara -->
          <div class="mx-8 mt-4 p-4 border" style="background: rgba(184,138,68,0.05); border-color: rgba(184,138,68,0.2);">
            <div class="flex items-center justify-between text-xs">
              <span style="color: var(--graphite);">Frame</span>
              <span class="font-bold" style="color: var(--ink);">Rp {{ (product?.price || 0).toLocaleString('id-ID') }}</span>
            </div>
            <div v-if="selectedLensOption" class="flex items-center justify-between text-xs mt-1">
              <span style="color: var(--graphite);">{{ selectedLensOption.name }}</span>
              <span class="font-bold" style="color: var(--ink);">+Rp {{ (selectedLensOption.base_price || 0).toLocaleString('id-ID') }}</span>
            </div>
            <div v-if="selectedCoating" class="flex items-center justify-between text-xs mt-1">
              <span style="color: var(--graphite);">{{ selectedCoating.name }}</span>
              <span class="font-bold" style="color: var(--ink);">+Rp {{ (selectedCoating.price || 0).toLocaleString('id-ID') }}</span>
            </div>
            <div class="flex items-center justify-between mt-2 pt-2 border-t" style="border-color: rgba(184,138,68,0.2);">
              <span class="text-xs font-black uppercase tracking-wider" style="color: var(--graphite);">Total</span>
              <span class="font-black text-base" style="color: #6F4E1D;">Rp {{ configuratorTotalPrice.toLocaleString('id-ID') }}</span>
            </div>
          </div>

          <!-- ── STEP 1: Pilih Lens Option ── -->
          <div v-if="configuratorStep === 'lens'" class="p-5 pt-4 md:p-6 md:pt-4">
            <div v-if="isLensesLoading" class="flex justify-center py-12">
              <div class="w-10 h-10 rounded-lg border-4 border-t-transparent animate-spin" style="border-color: rgba(184,138,68,0.25); border-top-color: var(--gold);"></div>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <button
                v-for="opt in (product as any)?.compatible_lens_options || []"
                :key="opt.id"
                @click="selectLensOption(opt)"
                class="rounded-lg border p-4 text-left transition-all hover:shadow-card active:scale-95"
                style="border-color: rgba(184,138,68,0.2); background: white;"
              >
                <div class="flex items-start justify-between gap-2 mb-2">
                  <h3 class="font-bold text-sm leading-tight" style="color: var(--ink);">{{ opt.name }}</h3>
                  <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 flex-shrink-0" style="background: rgba(184,138,68,0.12); color: var(--graphite);">{{ opt.type?.replace('_', ' ') }}</span>
                </div>
                <p class="font-black text-base" style="color: #6F4E1D;">+Rp {{ (opt.base_price || 0).toLocaleString('id-ID') }}</p>
              </button>

              <div v-if="!(product as any)?.compatible_lens_options?.length" class="col-span-2 rounded-lg py-6 text-center" style="background: rgba(184,138,68,0.05); border: 1px solid rgba(184,138,68,0.2);">
                <span class="material-symbols-outlined mb-2 block text-2xl" style="color: var(--gold);">info</span>
                <p class="text-sm font-bold mb-1" style="color: var(--ink);">Lensa belum dikonfigurasi</p>
                <p class="text-xs mb-4" style="color: var(--graphite);">Admin belum mengatur pilihan lensa untuk frame ini. Anda tetap bisa melanjutkan — tim kami akan menghubungi untuk konfirmasi lensa.</p>
                <button
                  @click="skipCoating"
                  class="px-6 py-2.5 text-sm font-black uppercase tracking-wider"
                  style="background: var(--ink); color: white;"
                >
                  Lanjutkan Tanpa Pilih Lensa
                </button>
              </div>
            </div>

            <button @click="isLensModalOpen = false" class="mt-4 w-full rounded-lg py-2.5 text-xs font-bold transition-all" style="color: var(--graphite); border: 1px solid rgba(184,138,68,0.28); background: rgba(255,255,255,0.72);">
              Batal
            </button>
          </div>

          <!-- ── STEP 2: Pilih Coating ── -->
          <div v-if="configuratorStep === 'coating'" class="p-5 pt-4 md:p-6 md:pt-4">
            <div v-if="isCoatingsLoading" class="flex justify-center py-12">
              <div class="w-10 h-10 rounded-lg border-4 border-t-transparent animate-spin" style="border-color: rgba(184,138,68,0.25); border-top-color: var(--gold);"></div>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <button
                v-for="coating in allCoatings"
                :key="coating.id"
                @click="selectedCoating = coating; confirmLensConfiguration()"
                class="rounded-lg border p-4 text-left transition-all hover:shadow-card active:scale-95"
                :style="selectedCoating?.id === coating.id
                  ? 'border-color: var(--gold); background: rgba(184,138,68,0.06); box-shadow: 0 0 0 2px rgba(184,138,68,0.3);'
                  : 'border-color: rgba(184,138,68,0.2); background: white;'"
              >
                <h3 class="font-bold text-sm mb-1" style="color: var(--ink);">{{ coating.name }}</h3>
                <p v-if="coating.description" class="text-xs leading-relaxed mb-3" style="color: var(--graphite);">{{ coating.description }}</p>
                <p class="font-black text-base" style="color: #6F4E1D;">+Rp {{ (coating.price || 0).toLocaleString('id-ID') }}</p>
              </button>
            </div>

            <div class="flex flex-col gap-3 mt-6">
              <button
                @click="skipCoating"
                class="w-full rounded-lg py-2.5 text-xs font-bold transition-all"
                style="background: var(--ink); color: white;"
              >
                Lanjutkan Tanpa Coating
              </button>
              <button
                @click="configuratorStep = 'lens'"
                class="w-full rounded-lg py-2.5 text-xs font-bold transition-all"
                style="color: var(--graphite); border: 1px solid rgba(184,138,68,0.28); background: rgba(255,255,255,0.72);"
              >
                ← Kembali Pilih Lensa
              </button>
            </div>
          </div>

        </div>
      </div>
    </Teleport>
  </main>
</template>
