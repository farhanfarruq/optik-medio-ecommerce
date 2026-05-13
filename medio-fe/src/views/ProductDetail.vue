<script setup lang="ts">
import { computed, ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCartStore } from '../stores/cartStore';
import { useWishlistStore } from '../stores/wishlistStore';
import { productRepository } from '../repositories/ProductRepository';
import { reviewRepository, type Review } from '../repositories/ReviewRepository';
import type { Product } from '../types';

import { resolveImageUrl } from '../core/utils/image';
import { useToast } from '../composables/useToast';
import { useSeoMeta } from '../composables/useSeoMeta';
import { useAnalytics } from '../composables/useAnalytics';

const { showToast } = useToast();

const route = useRoute();
const router = useRouter();
const cartStore = useCartStore();
const wishlistStore = useWishlistStore();
const { setSeo, setJsonLd, buildProductJsonLd } = useSeoMeta();
const { trackProductViewed } = useAnalytics();

const product = ref<Product | null>(null);
const isLoading = ref(true);
const lenses = ref<Product[]>([]);
const isLensesLoading = ref(false);
const similarFrames = ref<Product[]>([]);
const compatibleLenses = ref<Product[]>([]);
const productReviews = ref<Review[]>([]);
const reviewSummary = ref({ avg_rating: 0, total_reviews: 0 });

const formState = reactive({
  color: null as any,
  size: '',
  pdType: 'dual',
  prescription: {
    od: { sph: '0.00', cyl: '0.00', axis: '0', add: '0.00' },
    os: { sph: '0.00', cyl: '0.00', axis: '0', add: '0.00' },
    pdRight: '',
    pdLeft: '',
    pdSingle: ''
  }
});

const isLensModalOpen = ref(false);
const activeImage = ref(0);
const addedToCart = ref(false);

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
      console.warn('Failed to fetch reviews', reviewError);
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
    }
    fetchRecommendations(slug);
  } catch (error) {
    console.error('Failed to fetch product', error);
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
    console.error('Failed to fetch lenses', error);
  } finally {
    isLensesLoading.value = false;
  }
};

const fetchRecommendations = async (slug: string) => {
  try {
    const recommendations = await productRepository.getRecommendations(slug);
    similarFrames.value = recommendations.similar_frames || [];
    compatibleLenses.value = recommendations.compatible_lenses || [];
  } catch (error) {
    console.warn('Failed to fetch product recommendations', error);
  }
};

const handleAddToCartClick = () => {
  if (!product.value) return;
  if (product.value.is_prescription_required) {
    isLensModalOpen.value = true;
  } else {
    executeAddToCart();
  }
};

const executeAddToCart = (selectedLens: any = null) => {
  if (!product.value) return;

  const cartItem = {
    ...product.value,
    variant: {
      color: formState.color?.name,
      size: formState.size
    }
  };

  cartStore.addToCart(
    cartItem as any,
    product.value.is_prescription_required ? formState.prescription : undefined,
    selectedLens
  );

  isLensModalOpen.value = false;
  addedToCart.value = true;
  showToast('Produk berhasil ditambahkan ke keranjang!', 'success');
  setTimeout(() => { addedToCart.value = false; }, 2500);
};

const isWishlisted = computed(() => product.value ? wishlistStore.isWishlisted(product.value.id) : false);

const toggleWishlist = async () => {
  if (!product.value) return;

  const added = await wishlistStore.toggleWishlist(product.value);
  showToast(
    added ? 'Produk ditambahkan ke wishlist.' : 'Produk dihapus dari wishlist.',
    'success',
  );
};

const sphOptions = ['-2.00', '-1.75', '-1.50', '-1.25', '-1.00', '-0.75', '-0.50', '-0.25', '0.00', '+0.25', '+0.50', '+0.75', '+1.00', '+1.25', '+1.50', '+1.75', '+2.00'];

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
  <main v-if="isLoading" class="flex-grow flex items-center justify-center py-32">
    <div class="flex flex-col items-center gap-4">
      <div class="w-14 h-14 rounded-none border-4 border-t-transparent animate-spin" style="border-color: rgba(193,154,81,0.25); border-top-color: #c19a51;"></div>
      <p class="text-sm font-medium text-stone-500">Memuat produk...</p>
    </div>
  </main>

  <main v-else-if="product" class="flex-grow w-full">
    <!-- ╔══════════════════════════╗ -->
    <!-- ║   MINI HERO BREADCRUMB   ║ -->
    <!-- ╚══════════════════════════╝ -->
    <div class="relative w-full" style="margin-bottom: -60px;">
      <div class="relative overflow-hidden" style="height: 300px;">
        <img
          src="/gambar/hero-bg.jpeg"
          alt=""
          class="absolute inset-0 w-full h-full object-cover object-center"
          style="transform: scale(1.08); object-position: center 40%;"
        />
        <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(10,8,5,0.82) 0%, rgba(30,20,10,0.65) 100%);"></div>
        <!-- Gradient bleed -->
        <div class="absolute bottom-0 left-0 right-0" style="height: 100px; background: linear-gradient(to bottom, transparent 0%, #F5F2EE 100%);"></div>
        <div class="absolute" style="bottom: 100px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.6), transparent);"></div>

        <div class="relative z-10 h-full max-w-[1440px] mx-auto px-6 md:px-12 flex flex-col justify-end pb-24 pt-24">
          <!-- Breadcrumb -->
          <nav class="flex items-center gap-2 text-xs font-medium mb-3" style="color: rgba(255,255,255,0.55);">
            <router-link to="/" class="hover:text-white transition-colors">Beranda</router-link>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <router-link to="/products" class="hover:text-white transition-colors">Koleksi</router-link>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-white">{{ product.brand || 'Optik Medio' }}</span>
          </nav>
          <button @click="router.back()" class="flex items-center gap-2 text-sm font-bold transition-all group w-fit" style="color: rgba(193,154,81,0.9);">
            <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
            Kembali ke Koleksi
          </button>
        </div>
      </div>
    </div>


    <div class="max-w-[1440px] mx-auto px-6 md:px-12 py-12 md:py-16" style="padding-top: 140px;">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-20">

        <!-- ── Left: Image Gallery ── -->
        <div class="lg:col-span-7 flex flex-col gap-5">
          <!-- Main Image -->
          <div
            class="relative aspect-[4/3] rounded-none overflow-hidden flex items-center justify-center group border"
            style="background: linear-gradient(145deg, #f5f2ee, #ede7dc); border-color: rgba(193,154,81,0.15);"
          >
            <img
              :src="resolveImageUrl(product.images?.[activeImage])"
              class="w-full h-full object-contain p-10 transition-transform duration-700 ease-in-out group-hover:scale-105 mix-blend-multiply"
              alt="Product"
            />
            <!-- Image Nav Arrows (if multiple) -->
            <button
              v-if="product.images?.length > 1 && activeImage > 0"
              @click="activeImage--"
              class="absolute left-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-none flex items-center justify-center transition-all opacity-0 group-hover:opacity-100"
              style="background: rgba(255,255,255,0.9); box-shadow: 0 2px 12px rgba(0,0,0,0.1);"
            >
              <span class="material-symbols-outlined text-lg" style="color: #1a1209;">chevron_left</span>
            </button>
            <button
              v-if="product.images?.length > 1 && activeImage < product.images.length - 1"
              @click="activeImage++"
              class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-none flex items-center justify-center transition-all opacity-0 group-hover:opacity-100"
              style="background: rgba(255,255,255,0.9); box-shadow: 0 2px 12px rgba(0,0,0,0.1);"
            >
              <span class="material-symbols-outlined text-lg" style="color: #1a1209;">chevron_right</span>
            </button>
          </div>

          <!-- Thumbnails -->
          <div v-if="product.images?.length > 1" class="grid grid-cols-5 gap-3">
            <button
              v-for="(img, index) in product.images"
              :key="index"
              @click="activeImage = index"
              class="aspect-square rounded-none overflow-hidden border-2 transition-all p-2"
              :style="activeImage === index
                ? 'border-color: #c19a51; opacity: 1; background: linear-gradient(145deg, #f5f2ee, #ede7dc);'
                : 'border-color: transparent; opacity: 0.6; background: linear-gradient(145deg, #f5f2ee, #ede7dc);'"
              :class="{ 'hover:opacity-100': activeImage !== index }"
            >
              <img :src="resolveImageUrl(img)" class="w-full h-full object-contain mix-blend-multiply" />
            </button>
          </div>
        </div>

        <!-- ── Right: Product Info ── -->
        <div class="lg:col-span-5 flex flex-col gap-7">

          <!-- Category + Badges -->
          <div class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
              <p class="text-[10px] font-black uppercase tracking-[0.3em]" style="color: #c19a51;">
                Koleksi {{ (product as any).category?.name || 'Optik' }}
              </p>
              <div
                v-if="product.is_best_seller"
                class="flex items-center gap-1.5 px-3 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white"
                style="background: rgba(26,18,9,0.8); backdrop-filter: blur(4px); border: 1px solid rgba(193,154,81,0.3);"
              >
                <span class="material-symbols-outlined text-[10px]" style="color: #c19a51;">trending_up</span>
                Terlaris
              </div>
            </div>

            <div class="flex flex-col gap-4">
              <!-- Promo Badge (Buy X Get Y) -->
              <div
                v-if="getProductPromos(product).buyPromos.length > 0"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white shadow-md"
                style="background: #c19a51; border: 1px solid rgba(255,255,255,0.2);"
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
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[9px] font-bold uppercase tracking-[0.1em] text-white shadow-md"
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
          <div class="flex flex-col gap-2">
            <p class="text-xs font-black uppercase tracking-[0.2em]" style="color: #8a7a60;">
              {{ product.name }}
            </p>
            <h1 class="font-bold text-4xl md:text-5xl leading-tight tracking-tight" style="color: #1a1209; font-family: 'Outfit', sans-serif; letter-spacing: -0.02em;">
              {{ product.brand || 'Optik Medio' }}
            </h1>
            <div class="flex flex-wrap items-center gap-4 text-sm" style="color: #8a7a60;">
              <span class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base" style="color: #c19a51;">star</span>
                {{ Number(reviewSummary.avg_rating || product.avg_rating || 0).toFixed(1) }} dari {{ reviewSummary.total_reviews || product.review_count || 0 }} ulasan
              </span>
              <span class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base" style="color: #c19a51;">shopping_bag</span>
                {{ Number(product.purchase_count || 0) }} terjual
              </span>
            </div>
            <div class="flex items-center justify-between">
              <p v-if="!product.is_not_for_sale" class="text-2xl font-black" style="color: #7a6230;">
                Rp {{ product.price.toLocaleString('id-ID') }}
              </p>
              <p v-else class="text-xl font-bold uppercase tracking-widest" style="color: #c19a51;">
                Katalog Informasi
              </p>
              <div v-if="!product.is_not_for_sale" class="flex items-center gap-2">
                <span
                  class="w-2.5 h-2.5 rounded-none"
                  :style="product.stock > 0 ? 'background: #16a34a; box-shadow: 0 0 8px rgba(22,163,74,0.5);' : 'background: #dc2626;'"
                ></span>
                <p class="text-sm font-bold" :style="product.stock > 0 ? 'color: #15803d;' : 'color: #dc2626;'">
                  {{ product.stock > 0 ? `Stok: ${product.stock}` : 'Stok Habis' }}
                </p>
              </div>
            </div>
          </div>

          <button
            @click="toggleWishlist"
            class="w-full py-3 px-5 rounded-none border flex items-center justify-center gap-3 text-sm font-black uppercase tracking-[0.16em] transition-all"
            :style="isWishlisted
              ? 'background: rgba(193,154,81,0.12); color: #7a6230; border-color: rgba(193,154,81,0.3);'
              : 'background: white; color: #5a5248; border-color: rgba(193,154,81,0.18);'"
          >
            <span class="material-symbols-outlined text-lg">{{ isWishlisted ? 'favorite' : 'favorite_border' }}</span>
            {{ isWishlisted ? 'Tersimpan di Wishlist' : 'Tambah ke Wishlist' }}
          </button>

          <!-- Divider -->
          <div class="h-px" style="background: linear-gradient(90deg, rgba(193,154,81,0.3), transparent);"></div>

          <!-- Description -->
          <p v-if="product.description" class="text-sm leading-relaxed" style="color: #5a5248;">
            {{ product.description }}
          </p>

          <!-- Frame Size Guide -->
          <div
            v-if="hasFrameGuide"
            class="border rounded-none overflow-hidden"
            style="background: rgba(245,242,238,0.65); border-color: rgba(193,154,81,0.18);"
          >
            <div class="px-4 py-3 border-b flex items-center justify-between gap-3" style="border-color: rgba(193,154,81,0.14);">
              <div>
                <p class="text-xs font-black uppercase tracking-[0.18em]" style="color: #7a6230;">Panduan Ukuran Frame</p>
                <p class="text-[11px] mt-1" style="color: #8a7a60;">Gunakan data ini untuk membandingkan kenyamanan fit.</p>
              </div>
              <span class="material-symbols-outlined text-xl" style="color: #c19a51;">straighten</span>
            </div>

            <div v-if="frameSizeRows.length > 0" class="grid grid-cols-2 sm:grid-cols-4 border-b" style="border-color: rgba(193,154,81,0.14);">
              <div
                v-for="row in frameSizeRows"
                :key="row.label"
                class="px-4 py-3 border-r last:border-r-0"
                style="border-color: rgba(193,154,81,0.14);"
              >
                <p class="text-[10px] font-black uppercase tracking-widest" style="color: #8a7a60;">{{ row.label }}</p>
                <p class="text-lg font-black mt-1" style="color: #1a1209;">{{ row.value }} <span class="text-xs font-bold text-stone-400">mm</span></p>
              </div>
            </div>

            <div v-if="frameProfileRows.length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-px" style="background: rgba(193,154,81,0.12);">
              <div
                v-for="row in frameProfileRows"
                :key="row.label"
                class="px-4 py-3"
                style="background: white;"
              >
                <p class="text-[10px] font-black uppercase tracking-widest" style="color: #8a7a60;">{{ row.label }}</p>
                <p class="text-sm font-bold mt-1" style="color: #1a1209;">{{ formatProductLabel(row.value) }}</p>
              </div>
            </div>
          </div>

          <!-- Prescription Notice -->
          <div
            v-if="product.is_prescription_required && !product.is_not_for_sale"
            class="p-4 rounded-none flex items-start gap-3 border"
            style="background: rgba(193,154,81,0.07); border-color: rgba(193,154,81,0.25);"
          >
            <span class="material-symbols-outlined mt-0.5" style="color: #c19a51;">info</span>
            <div>
              <p class="text-sm font-bold" style="color: #7a6230;">Membutuhkan Resep Optik</p>
              <p class="text-xs leading-relaxed mt-1" style="color: #8a7a60;">Produk ini memerlukan resep optik yang valid untuk diproses.</p>
            </div>
          </div>

          <!-- Info Only Notice -->
          <div
            v-if="product.is_not_for_sale"
            class="p-6 rounded-none flex flex-col gap-4 border"
            style="background: rgba(26,18,9,0.03); border-color: rgba(193,154,81,0.2); border-left: 4px solid #c19a51;"
          >
            <div class="flex items-center gap-2 text-stone-800">
              <span class="material-symbols-outlined text-xl" style="color: #c19a51;">menu_book</span>
              <p class="text-base font-bold">Katalog Brand Lensa</p>
            </div>
            <p class="text-sm leading-relaxed text-stone-600">
              Informasi produk ini merupakan bagian dari katalog brand lensa yang kami gunakan di Optik Medio. 
              Produk ini tidak dijual secara terpisah. Untuk konsultasi lebih lanjut mengenai lensa terbaik untuk kebutuhan mata Anda, silakan hubungi tim ahli kami.
            </p>
            <button class="w-fit px-6 py-2 bg-[#1a1209] text-white text-xs font-bold uppercase tracking-widest hover:bg-stone-800 transition-colors">
              Hubungi CS Optik Medio
            </button>
          </div>

          <!-- Color Selector -->
          <div v-if="product.variants?.colors?.length && !product.is_not_for_sale" class="flex flex-col gap-3">
            <p class="text-xs font-bold uppercase tracking-wider" style="color: #5a5248;">
              Warna: <span class="font-medium" style="color: #1a1209;">{{ formState.color?.name }}</span>
            </p>
            <div class="flex gap-3 flex-wrap">
              <button
                v-for="color in product.variants.colors"
                :key="color.name"
                @click="formState.color = color"
                :style="{ backgroundColor: color.hex }"
                :class="['w-10 h-10 rounded-none border-4 focus:outline-none transition-all', formState.color?.name === color.name ? 'scale-110' : 'border-transparent hover:scale-105']"
                :style-extra="formState.color?.name === color.name ? 'border-color: #c19a51; box-shadow: 0 0 0 2px rgba(193,154,81,0.4);' : ''"
              ></button>
            </div>
          </div>

          <!-- Size Selector -->
          <div v-if="product.variants?.sizes?.length && !product.is_not_for_sale" class="flex flex-col gap-3">
            <p class="text-xs font-bold uppercase tracking-wider" style="color: #5a5248;">Ukuran</p>
            <div class="flex gap-2 flex-wrap">
              <button
                v-for="size in product.variants.sizes"
                :key="size"
                @click="formState.size = size"
                class="px-4 py-2.5 rounded-none border text-sm font-bold transition-all"
                :style="formState.size === size
                  ? 'background: #1a1209; color: white; border-color: #1a1209; box-shadow: 0 4px 12px rgba(26,18,9,0.2);'
                  : 'background: transparent; color: #5a5248; border-color: rgba(193,154,81,0.25);'"
              >
                {{ size }}
              </button>
            </div>
          </div>

          <!-- Prescription Form -->
          <div v-if="product.is_prescription_required && !product.is_not_for_sale" class="flex flex-col gap-6 pt-6 border-t" style="border-color: rgba(193,154,81,0.15);">
            <h2 class="font-bold text-lg" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Resep Kacamata Anda</h2>

            <div class="p-5 rounded-none border" style="background: rgba(245,242,238,0.8); border-color: rgba(193,154,81,0.15);">
              <div class="grid grid-cols-5 gap-3 mb-4">
                <div class="col-span-1"></div>
                <div class="text-center text-[10px] font-black uppercase tracking-widest" style="color: #8a7a60;">SPH</div>
                <div class="text-center text-[10px] font-black uppercase tracking-widest" style="color: #8a7a60;">CYL</div>
                <div class="text-center text-[10px] font-black uppercase tracking-widest" style="color: #8a7a60;">Axis</div>
                <div class="text-center text-[10px] font-black uppercase tracking-widest" style="color: #8a7a60;">ADD</div>

                <div class="flex items-center justify-end pr-2 text-xs font-black" style="color: #1a1209;">OD</div>
                <div><select v-model="formState.prescription.od.sph" class="w-full rounded-none border-0 text-xs p-2 focus:ring-2" style="background: white; border: 1px solid rgba(193,154,81,0.2);"><option v-for="opt in sphOptions" :value="opt">{{opt}}</option></select></div>
                <div><select v-model="formState.prescription.od.cyl" class="w-full rounded-none border-0 text-xs p-2 focus:ring-2" style="background: white; border: 1px solid rgba(193,154,81,0.2);"><option v-for="opt in sphOptions" :value="opt">{{opt}}</option></select></div>
                <div><input v-model="formState.prescription.od.axis" type="number" class="w-full rounded-none text-xs p-2 text-center focus:ring-2" style="background: white; border: 1px solid rgba(193,154,81,0.2);"/></div>
                <div><select v-model="formState.prescription.od.add" class="w-full rounded-none border-0 text-xs p-2 focus:ring-2" style="background: white; border: 1px solid rgba(193,154,81,0.2);"><option v-for="opt in sphOptions" :value="opt">{{opt}}</option></select></div>

                <div class="flex items-center justify-end pr-2 text-xs font-black mt-2" style="color: #1a1209;">OS</div>
                <div class="mt-2"><select v-model="formState.prescription.os.sph" class="w-full rounded-none border-0 text-xs p-2 focus:ring-2" style="background: white; border: 1px solid rgba(193,154,81,0.2);"><option v-for="opt in sphOptions" :value="opt">{{opt}}</option></select></div>
                <div class="mt-2"><select v-model="formState.prescription.os.cyl" class="w-full rounded-none border-0 text-xs p-2 focus:ring-2" style="background: white; border: 1px solid rgba(193,154,81,0.2);"><option v-for="opt in sphOptions" :value="opt">{{opt}}</option></select></div>
                <div class="mt-2"><input v-model="formState.prescription.os.axis" type="number" class="w-full rounded-none text-xs p-2 text-center focus:ring-2" style="background: white; border: 1px solid rgba(193,154,81,0.2);"/></div>
                <div class="mt-2"><select v-model="formState.prescription.os.add" class="w-full rounded-none border-0 text-xs p-2 focus:ring-2" style="background: white; border: 1px solid rgba(193,154,81,0.2);"><option v-for="opt in sphOptions" :value="opt">{{opt}}</option></select></div>
              </div>

              <div class="pt-4 border-t" style="border-color: rgba(193,154,81,0.15);">
                <div class="flex items-center gap-6 mb-4">
                  <label class="flex items-center gap-2 cursor-pointer text-xs font-bold" style="color: #5a5248;">
                    <input type="radio" v-model="formState.pdType" value="single" class="accent-amber-700"/>
                    PD Tunggal
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer text-xs font-bold" style="color: #5a5248;">
                    <input type="radio" v-model="formState.pdType" value="dual" class="accent-amber-700"/>
                    PD Ganda
                  </label>
                </div>
                <div v-if="formState.pdType === 'dual'" class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-[10px] font-bold mb-1.5" style="color: #8a7a60;">PD Kanan</label>
                    <input v-model="formState.prescription.pdRight" type="number" class="w-full rounded-none p-2.5 text-sm" style="background: white; border: 1px solid rgba(193,154,81,0.2);"/>
                  </div>
                  <div>
                    <label class="block text-[10px] font-bold mb-1.5" style="color: #8a7a60;">PD Kiri</label>
                    <input v-model="formState.prescription.pdLeft" type="number" class="w-full rounded-none p-2.5 text-sm" style="background: white; border: 1px solid rgba(193,154,81,0.2);"/>
                  </div>
                </div>
                <div v-else>
                  <label class="block text-[10px] font-bold mb-1.5" style="color: #8a7a60;">PD</label>
                  <input v-model="formState.prescription.pdSingle" type="number" class="w-full rounded-none p-2.5 text-sm" style="background: white; border: 1px solid rgba(193,154,81,0.2);"/>
                </div>
              </div>
            </div>
          </div>

          <!-- Add to Cart Button -->
          <button
            v-if="!product.is_not_for_sale"
            @click="handleAddToCartClick"
            :disabled="product.stock <= 0"
            class="w-full py-4 px-6 font-black text-sm uppercase tracking-widest rounded-none transition-all flex items-center justify-center gap-3 shadow-lg"
            :style="product.stock > 0
              ? (addedToCart
                ? 'background: linear-gradient(135deg, #15803d, #16a34a); color: white; box-shadow: 0 8px 25px rgba(22,163,74,0.3);'
                : 'background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%); color: white; box-shadow: 0 8px 25px rgba(26,18,9,0.25);')
              : 'background: rgba(245,242,238,0.8); color: #a09080; cursor: not-allowed;'"
          >
            <span class="material-symbols-outlined text-lg">{{ addedToCart ? 'check_circle' : (product.stock > 0 ? 'shopping_bag' : 'block') }}</span>
            {{ addedToCart ? 'Ditambahkan!' : (product.stock > 0 ? (product.is_prescription_required ? 'Pilih Lensa & Tambah ke Keranjang' : 'Tambah ke Keranjang') : 'Stok Habis') }}
          </button>

          <!-- Trust Badges -->
          <div class="grid grid-cols-3 gap-3 pt-2">
            <div class="flex flex-col items-center gap-1.5 text-center">
              <span class="material-symbols-outlined text-2xl" style="color: #c19a51;">verified</span>
              <span class="text-[9px] font-bold uppercase tracking-wide" style="color: #8a7a60;">Produk Asli</span>
            </div>
            <div class="flex flex-col items-center gap-1.5 text-center">
              <span class="material-symbols-outlined text-2xl" style="color: #c19a51;">local_shipping</span>
              <span class="text-[9px] font-bold uppercase tracking-wide" style="color: #8a7a60;">Pengiriman Cepat</span>
            </div>
            <div class="flex flex-col items-center gap-1.5 text-center">
              <span class="material-symbols-outlined text-2xl" style="color: #c19a51;">support_agent</span>
              <span class="text-[9px] font-bold uppercase tracking-wide" style="color: #8a7a60;">Garansi Resmi</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <section v-if="similarFrames.length > 0 || compatibleLenses.length > 0" class="max-w-[1440px] mx-auto px-6 md:px-12 pb-16">
      <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">
        <div>
          <p class="text-xs font-black uppercase tracking-[0.25em] mb-2" style="color: #c19a51;">Rekomendasi Optik</p>
          <h2 class="text-3xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Pilihan yang Cocok</h2>
        </div>
        <router-link to="/products" class="text-xs font-black uppercase tracking-widest text-amber-700 hover:text-amber-800 transition-all flex items-center gap-2 group">
          Lihat Koleksi
          <span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">arrow_forward</span>
        </router-link>
      </div>

      <div v-if="similarFrames.length > 0" class="mb-10">
        <h3 class="text-sm font-black uppercase tracking-[0.18em] mb-4" style="color: #7a6230;">Frame Serupa</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <article
            v-for="item in similarFrames.slice(0, 4)"
            :key="item.id"
            @click="router.push(`/products/${item.slug}`)"
            class="cursor-pointer border bg-white transition-all hover:-translate-y-1 hover:shadow-lg"
            style="border-color: rgba(193,154,81,0.14);"
          >
            <div class="aspect-[4/5] p-4 flex items-center justify-center" style="background: linear-gradient(145deg, #f5f2ee, #ede7dc);">
              <img :src="resolveImageUrl(item)" :alt="item.name" class="w-full h-full object-contain mix-blend-multiply" />
            </div>
            <div class="p-4">
              <p class="text-[10px] font-black uppercase tracking-widest mb-1" style="color: #8a7a60;">{{ item.name }}</p>
              <h4 class="font-bold text-sm line-clamp-2" style="color: #1a1209;">{{ item.brand || 'Optik Medio' }}</h4>
              <p class="text-sm font-black mt-2" style="color: #7a6230;">Rp {{ item.price.toLocaleString('id-ID') }}</p>
            </div>
          </article>
        </div>
      </div>

      <div v-if="compatibleLenses.length > 0">
        <h3 class="text-sm font-black uppercase tracking-[0.18em] mb-4" style="color: #7a6230;">Lensa Kompatibel</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <article
            v-for="item in compatibleLenses.slice(0, 4)"
            :key="item.id"
            @click="router.push(`/products/${item.slug}`)"
            class="cursor-pointer border bg-white p-5 transition-all hover:-translate-y-1 hover:shadow-lg"
            style="border-color: rgba(193,154,81,0.14);"
          >
            <span class="material-symbols-outlined text-2xl mb-3 block" style="color: #c19a51;">visibility</span>
            <p class="text-[10px] font-black uppercase tracking-widest mb-1" style="color: #8a7a60;">{{ item.brand || 'Lensa' }}</p>
            <h4 class="font-bold text-sm line-clamp-2" style="color: #1a1209;">{{ item.name }}</h4>
            <p class="text-sm font-black mt-3" style="color: #7a6230;">Rp {{ item.price.toLocaleString('id-ID') }}</p>
          </article>
        </div>
      </div>
    </section>

    <section class="max-w-[1440px] mx-auto px-6 md:px-12 pb-16">
      <div class="border rounded-none p-8" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">
          <div>
            <p class="text-xs font-black uppercase tracking-[0.25em] mb-2" style="color: #c19a51;">Customer Reviews</p>
            <h2 class="text-3xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Ulasan Produk</h2>
          </div>
          <div class="text-sm" style="color: #8a7a60;">
            Rating rata-rata <span class="font-black" style="color: #1a1209;">{{ Number(reviewSummary.avg_rating || product.avg_rating || 0).toFixed(1) }}</span>
            dari {{ reviewSummary.total_reviews || product.review_count || 0 }} ulasan
          </div>
        </div>

        <div v-if="productReviews.length === 0" class="text-sm" style="color: #8a7a60;">
          Belum ada ulasan untuk produk ini.
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <article
            v-for="review in productReviews"
            :key="review.id"
            class="border rounded-none p-5"
            style="background: rgba(245,242,238,0.75); border-color: rgba(193,154,81,0.12);"
          >
            <div class="flex items-center justify-between gap-4 mb-3">
              <p class="font-black" style="color: #1a1209;">{{ review.user_name }}</p>
              <span class="text-xs" style="color: #8a7a60;">{{ review.created_at }}</span>
            </div>
            <div class="flex items-center gap-1 mb-3">
              <span
                v-for="star in 5"
                :key="star"
                class="material-symbols-outlined text-base"
                :style="star <= review.rating ? 'color: #c19a51;' : 'color: rgba(193,154,81,0.25);'"
              >
                star
              </span>
            </div>
            <p class="text-sm leading-relaxed" style="color: #5a5248;">
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
      <div v-if="isLensModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(10,8,5,0.75); backdrop-filter: blur(20px);">
        <div class="w-full max-w-2xl rounded-none p-8 border" style="background: #faf8f5; border-color: rgba(193,154,81,0.2); box-shadow: 0 30px 80px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
          <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Pilih Lensa Anda</h2>
            <button @click="isLensModalOpen = false" class="w-10 h-10 rounded-none flex items-center justify-center transition-all" style="background: rgba(193,154,81,0.1); color: #7a6230;">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div v-if="isLensesLoading" class="flex justify-center py-12">
            <div class="w-10 h-10 rounded-none border-4 border-t-transparent animate-spin" style="border-color: rgba(193,154,81,0.25); border-top-color: #c19a51;"></div>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <button
              v-for="lens in lenses.slice(0, 4)"
              :key="lens.id"
              @click="executeAddToCart(lens)"
              class="p-5 rounded-none border text-left transition-all hover:-translate-y-1 hover:shadow-lg active:scale-95"
              style="border-color: rgba(193,154,81,0.2); background: white;"
            >
              <h3 class="font-bold text-base mb-1" style="color: #1a1209;">{{ lens.name }}</h3>
              <p class="text-xs leading-relaxed mb-4" style="color: #8a7a60;">{{ lens.description }}</p>
              <p class="font-black text-base" style="color: #c19a51;">+Rp {{ lens.price.toLocaleString('id-ID') }}</p>
            </button>

            <div v-if="lenses.length === 0" class="col-span-2 text-center py-8 rounded-none" style="background: rgba(220,38,38,0.05); color: #dc2626;">
              Tidak ada lensa ditemukan. Hubungi tim kami.
            </div>
          </div>

          <button @click="isLensModalOpen = false" class="mt-8 w-full py-3 text-sm font-bold rounded-none transition-all" style="color: #8a7a60; border: 1px solid rgba(193,154,81,0.2);">
            Batal
          </button>
        </div>
      </div>
    </Teleport>
  </main>
</template>
