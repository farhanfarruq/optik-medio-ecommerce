<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { productRepository, type ProductSearchSuggestions } from '../repositories/ProductRepository';
import { resolveImageUrl } from '../core/utils/image';
import type { Product } from '../types';

const router = useRouter();
const photoDataUrl = ref('');
const selectedProduct = ref<Product | null>(null);
const searchQuery = ref('');
const suggestions = ref<ProductSearchSuggestions>({ products: [], categories: [] });
const isSearching = ref(false);
const isSaving = ref(false);
const scale = ref(54);
const offsetX = ref(0);
const offsetY = ref(34);
const rotation = ref(0);
const savedPreviews = ref<any[]>([]);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const frameStyle = computed(() => ({
  width: `${scale.value}%`,
  left: `${50 + offsetX.value}%`,
  top: `${50 + offsetY.value}%`,
  transform: `translate(-50%, -50%) rotate(${rotation.value}deg)`,
}));

const handlePhotoUpload = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = () => {
    photoDataUrl.value = String(reader.result || '');
  };
  reader.readAsDataURL(file);
};

const selectProduct = (product: Product) => {
  selectedProduct.value = product;
  searchQuery.value = product.name;
  suggestions.value = { products: [], categories: [] };
};

const resetTransform = () => {
  scale.value = 54;
  offsetX.value = 0;
  offsetY.value = 34;
  rotation.value = 0;
};

const clearSavedPreviews = () => {
  savedPreviews.value = [];
  window.localStorage.removeItem('medio_virtual_try_on_previews');
};

const goBack = () => {
  if (window.history.length > 1) {
    router.back();
    return;
  }

  router.push('/products');
};

/**
 * Load an image from a URL into an HTMLImageElement.
 * For canvas compositing, converts absolute backend URLs to relative paths
 * so the request goes through the Vite dev proxy (avoids CORS on canvas).
 */
const toProxiedUrl = (url: string): string => {
  // In dev, rewrite http://localhost:8000/storage/... → /storage/...
  // so Vite proxy handles it and canvas doesn't get tainted by CORS
  try {
    const parsed = new URL(url);
    const backendHost = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api')
      .replace('/api', '');
    const backendOrigin = new URL(backendHost).origin;
    if (parsed.origin === backendOrigin) {
      return parsed.pathname + parsed.search;
    }
  } catch {
    // not a full URL, return as-is
  }
  return url;
};

const loadImage = (src: string): Promise<HTMLImageElement> => {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => resolve(img);
    img.onerror = reject;
    img.src = src;
  });
};

/**
 * Composite the face photo + glasses overlay onto a canvas and return a data URL.
 * This is what gets stored in savedPreviews so the preview card always shows both layers.
 */
const compositeToDataUrl = async (
  faceDataUrl: string,
  glassesUrl: string,
  scaleVal: number,
  offsetXVal: number,
  offsetYVal: number,
  rotationVal: number,
): Promise<string> => {
  const CANVAS_W = 600;
  const CANVAS_H = 750; // 4:5 ratio

  const canvas = document.createElement('canvas');
  canvas.width = CANVAS_W;
  canvas.height = CANVAS_H;
  const ctx = canvas.getContext('2d')!;

  // Draw face photo (object-contain style)
  const faceImg = await loadImage(faceDataUrl);
  const faceRatio = faceImg.width / faceImg.height;
  const canvasRatio = CANVAS_W / CANVAS_H;
  let drawW: number, drawH: number, drawX: number, drawY: number;
  if (faceRatio > canvasRatio) {
    drawW = CANVAS_W;
    drawH = CANVAS_W / faceRatio;
    drawX = 0;
    drawY = (CANVAS_H - drawH) / 2;
  } else {
    drawH = CANVAS_H;
    drawW = CANVAS_H * faceRatio;
    drawX = (CANVAS_W - drawW) / 2;
    drawY = 0;
  }
  ctx.drawImage(faceImg, drawX, drawY, drawW, drawH);

  // Draw glasses overlay
  try {
    const glassesImg = await loadImage(toProxiedUrl(glassesUrl));
    const glassesW = (scaleVal / 100) * CANVAS_W;
    const glassesH = (glassesImg.height / glassesImg.width) * glassesW;
    const centerX = (50 + offsetXVal) / 100 * CANVAS_W;
    const centerY = (50 + offsetYVal) / 100 * CANVAS_H;

    ctx.save();
    ctx.translate(centerX, centerY);
    ctx.rotate((rotationVal * Math.PI) / 180);
    // Match the CSS mix-blend-multiply used in the live editor
    ctx.globalCompositeOperation = 'multiply';
    ctx.drawImage(glassesImg, -glassesW / 2, -glassesH / 2, glassesW, glassesH);
    ctx.restore();
    // Reset composite operation for any subsequent draws
    ctx.globalCompositeOperation = 'source-over';
  } catch (e) {
    // If glasses image fails to load, still save the face photo
    console.warn('Could not load glasses image for composite:', e);
  }

  try {
    return canvas.toDataURL('image/jpeg', 0.92);
  } catch {
    // Canvas tainted by cross-origin image — return face only
    return faceDataUrl;
  }
};

const savePreview = async () => {
  if (!photoDataUrl.value || !selectedProduct.value) return;

  isSaving.value = true;
  try {
    const glassesUrl = resolveImageUrl(selectedProduct.value);
    const compositeDataUrl = await compositeToDataUrl(
      photoDataUrl.value,
      glassesUrl,
      scale.value,
      offsetX.value,
      offsetY.value,
      rotation.value,
    );

    savedPreviews.value = [
      {
        id: Date.now(),
        // Store the composited image so preview cards always show both layers
        compositeImage: compositeDataUrl,
        // Keep originals for re-rendering if needed
        photo: photoDataUrl.value,
        productName: selectedProduct.value.name,
        productBrand: (selectedProduct.value as any).brand || 'Optik Medio',
        scale: scale.value,
        offsetX: offsetX.value,
        offsetY: offsetY.value,
        rotation: rotation.value,
      },
      ...savedPreviews.value,
    ].slice(0, 4);

    window.localStorage.setItem('medio_virtual_try_on_previews', JSON.stringify(savedPreviews.value));
  } finally {
    isSaving.value = false;
  }
};

const downloadPreview = (preview: any) => {
  const link = document.createElement('a');
  link.href = preview.compositeImage || preview.photo;
  link.download = `virtual-tryon-${preview.productName?.replace(/\s+/g, '-') ?? 'preview'}-${preview.id}.jpg`;
  link.click();
};

const loadSavedPreviews = () => {
  try {
    const raw = window.localStorage.getItem('medio_virtual_try_on_previews');
    const parsed = raw ? JSON.parse(raw) : [];
    savedPreviews.value = Array.isArray(parsed) ? parsed.slice(0, 4) : [];
  } catch {
    savedPreviews.value = [];
  }
};

loadSavedPreviews();

// Mobile bottom sheet state
const isSheetExpanded = ref(false);
const toggleSheet = () => { isSheetExpanded.value = !isSheetExpanded.value; };

watch(searchQuery, (query) => {
  if (searchTimer) clearTimeout(searchTimer);
  const term = query.trim();
  if (term.length < 2) {
    suggestions.value = { products: [], categories: [] };
    return;
  }

  searchTimer = setTimeout(async () => {
    try {
      isSearching.value = true;
      suggestions.value = await productRepository.getSearchSuggestions(term);
    } finally {
      isSearching.value = false;
    }
  }, 250);
});
</script>


<template>
  <!-- Hero banner -->
  <div class="relative w-full bg-[#f5f2ee]" style="margin-bottom: -60px;">
    <div class="relative overflow-hidden" style="height: 280px;">
      <img src="/gambar/hero-bg.jpeg" alt="" class="absolute inset-0 w-full h-full object-cover object-center" style="transform: scale(1.08); object-position: center 40%;" />
      <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(10,8,5,0.68) 0%, rgba(30,20,10,0.48) 100%);"></div>
      <div class="absolute bottom-0 left-0 right-0" style="height: 100px; background: linear-gradient(to bottom, transparent 0%, #F5F2EE 100%);"></div>
      <div class="absolute" style="bottom: 100px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.6), transparent);"></div>
      <div class="relative z-10 h-full max-w-[1440px] mx-auto px-6 md:px-12 flex flex-col justify-between" :style="{ paddingTop: 'calc(var(--header-height, 96px) + 16px)', paddingBottom: '56px' }">
        <div>
          <nav class="flex items-center gap-2 text-xs font-medium mb-2" style="color: rgba(255,255,255,0.55);">
            <router-link to="/" class="hover:text-white transition-colors">Beranda</router-link>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-white">Virtual Try-On</span>
          </nav>
          <button @click="goBack" class="flex items-center gap-2 text-sm font-bold group w-fit transition-all" style="color: rgba(193,154,81,0.95);">
            <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
            Kembali
          </button>
        </div>
        <div>
          <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-3" style="color: #c19a51;">Virtual Try-On</p>
          <h1 class="text-4xl md:text-5xl font-black tracking-tight text-white" style="font-family: 'Outfit', sans-serif;">Coba Frame dari Foto</h1>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ DESKTOP layout (lg+) ═══════════════════════════════════════════ -->
  <main class="hidden lg:block min-h-screen bg-[#f5f2ee] pb-24 pt-24">
    <section class="max-w-[1440px] mx-auto px-12">
      <div class="grid grid-cols-[1fr_360px] gap-8">
        <section class="bg-white border border-stone-100 p-6">
          <div class="relative mx-auto max-w-3xl aspect-[4/5] bg-stone-100 overflow-hidden flex items-center justify-center">
            <img v-if="photoDataUrl" :src="photoDataUrl" alt="Foto wajah" class="absolute inset-0 w-full h-full object-contain" />
            <div v-else class="text-center p-8">
              <span class="material-symbols-outlined text-6xl mb-4 block" style="color: #c19a51;">add_a_photo</span>
              <p class="text-sm font-bold text-stone-500">Upload foto wajah dari depan untuk mulai mencoba frame.</p>
            </div>
            <img v-if="photoDataUrl && selectedProduct" :src="resolveImageUrl(selectedProduct)" :alt="selectedProduct.name" class="absolute object-contain pointer-events-none mix-blend-multiply" :style="frameStyle" />
          </div>
        </section>
        <aside class="bg-white border border-stone-100 p-6 h-fit space-y-5">
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Foto</span>
            <input type="file" accept="image/*" @change="handlePhotoUpload" class="w-full border border-stone-300 px-3 py-3 text-sm" />
          </label>
          <div class="relative">
            <label class="block">
              <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Cari Frame</span>
              <input v-model="searchQuery" type="text" placeholder="Nama frame..." class="w-full border border-stone-300 px-3 py-3 text-sm focus:outline-none focus:border-amber-700" />
            </label>
            <div v-if="searchQuery.trim().length >= 2 && (suggestions.products.length > 0 || isSearching)" class="absolute z-20 left-0 right-0 mt-2 bg-white border border-stone-100 shadow-xl p-2 max-h-72 overflow-y-auto">
              <p v-if="isSearching" class="p-3 text-xs font-bold text-stone-500">Mencari...</p>
              <button v-for="product in suggestions.products" :key="product.id" @click="selectProduct(product)" class="w-full flex items-center gap-3 p-2 text-left hover:bg-stone-50">
                <img :src="resolveImageUrl(product)" :alt="product.name" class="w-10 h-10 object-contain bg-stone-50 border border-stone-100" />
                <span class="min-w-0">
                  <span class="block text-xs font-black truncate">{{ product.name }}</span>
                  <span class="block text-[11px] text-stone-500 truncate">{{ product.brand || 'Optik Medio' }}</span>
                </span>
              </button>
            </div>
          </div>
          <div v-if="selectedProduct" class="p-3 border border-stone-100 bg-stone-50">
            <p class="text-xs font-black text-stone-900">{{ selectedProduct.name }}</p>
            <p class="text-[11px] text-stone-500 mt-1">{{ (selectedProduct as any).brand || 'Optik Medio' }}</p>
          </div>
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Scale</span>
            <input v-model.number="scale" type="range" min="25" max="90" class="w-full accent-amber-700" />
          </label>
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Horizontal</span>
            <input v-model.number="offsetX" type="range" min="-35" max="35" class="w-full accent-amber-700" />
          </label>
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Vertical</span>
            <input v-model.number="offsetY" type="range" min="-10" max="65" class="w-full accent-amber-700" />
          </label>
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-stone-500 mb-2">Rotasi</span>
            <input v-model.number="rotation" type="range" min="-20" max="20" class="w-full accent-amber-700" />
          </label>
          <div class="grid grid-cols-2 gap-3">
            <button @click="resetTransform" class="py-3 text-xs font-black uppercase tracking-widest border border-stone-300 text-stone-700">Reset</button>
            <button @click="savePreview" :disabled="!photoDataUrl || !selectedProduct || isSaving" class="py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-50 flex items-center justify-center gap-2" style="background: #1a1209;">
              <span v-if="isSaving" class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
              <span>{{ isSaving ? 'Menyimpan...' : 'Simpan' }}</span>
            </button>
          </div>
        </aside>
      </div>
      <section v-if="savedPreviews.length > 0" class="mt-10">
        <div class="flex items-end justify-between gap-4 mb-5">
          <h2 class="text-2xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Preview Tersimpan</h2>
          <button @click="clearSavedPreviews" class="text-xs font-bold uppercase tracking-widest" style="color: #8a7a60;">Bersihkan</button>
        </div>
        <div class="grid grid-cols-4 gap-4">
          <article v-for="preview in savedPreviews" :key="preview.id" class="bg-white border border-stone-100 p-3">
            <div class="relative aspect-[4/5] bg-stone-100 overflow-hidden">
              <img v-if="preview.compositeImage" :src="preview.compositeImage" :alt="preview.productName" class="absolute inset-0 w-full h-full object-contain" />
              <img v-else :src="preview.photo" alt="Preview tersimpan" class="absolute inset-0 w-full h-full object-contain" />
            </div>
            <p class="mt-3 text-xs font-black line-clamp-2" style="color: #1a1209;">{{ preview.productName || preview.product?.name }}</p>
            <p class="text-[11px] text-stone-500 mt-0.5">{{ preview.productBrand || preview.product?.brand || 'Optik Medio' }}</p>
            <button @click="downloadPreview(preview)" class="mt-3 w-full flex items-center justify-center gap-1.5 py-2 text-[10px] font-black uppercase tracking-widest border border-stone-200 text-stone-700 hover:bg-stone-50 transition-colors">
              <span class="material-symbols-outlined text-sm">download</span>
              Download
            </button>
          </article>
        </div>
      </section>
    </section>
  </main>

  <!-- ═══ MOBILE layout (< lg) ════════════════════════════════════════════ -->
  <div class="lg:hidden flex flex-col bg-[#f5f2ee]" style="padding-top: 96px; padding-bottom: 300px;">

    <!-- Upload & Search card -->
    <div class="mx-4 mt-4 bg-white rounded-2xl shadow-sm overflow-visible">
      <!-- Upload foto -->
      <label class="flex items-center gap-3 px-4 py-4 border-b border-stone-100 cursor-pointer active:bg-stone-50 transition-colors rounded-t-2xl">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background: #fdf6ec;">
          <span class="material-symbols-outlined text-xl" style="color: #c19a51;">add_a_photo</span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-[10px] font-black uppercase tracking-widest text-stone-400 mb-0.5">Foto Wajah</p>
          <p class="text-sm font-bold truncate" :class="photoDataUrl ? 'text-stone-900' : 'text-stone-400'">
            {{ photoDataUrl ? 'Foto dipilih ✓' : 'Pilih dari galeri...' }}
          </p>
        </div>
        <span class="material-symbols-outlined text-stone-300 shrink-0">chevron_right</span>
        <input type="file" accept="image/*" @change="handlePhotoUpload" class="hidden" />
      </label>

      <!-- Cari frame -->
      <div class="relative px-4 py-3">
        <p class="text-[10px] font-black uppercase tracking-widest text-stone-400 mb-2">Cari Frame</p>
        <div class="flex items-center gap-2 bg-stone-50 rounded-xl px-3 py-2.5">
          <span class="material-symbols-outlined text-lg text-stone-400 shrink-0">search</span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Nama frame kacamata..."
            class="flex-1 bg-transparent text-sm font-bold focus:outline-none placeholder:text-stone-400 placeholder:font-normal"
          />
          <button v-if="searchQuery" @click="searchQuery = ''" class="text-stone-400 shrink-0">
            <span class="material-symbols-outlined text-base">close</span>
          </button>
        </div>
        <div v-if="searchQuery.trim().length >= 2 && (suggestions.products.length > 0 || isSearching)" class="absolute z-20 left-4 right-4 mt-2 bg-white rounded-2xl shadow-2xl border border-stone-100 overflow-hidden max-h-64 overflow-y-auto">
          <p v-if="isSearching" class="p-4 text-xs font-bold text-stone-500 text-center">Mencari...</p>
          <button
            v-for="product in suggestions.products"
            :key="product.id"
            @click="selectProduct(product)"
            class="w-full flex items-center gap-3 px-4 py-3 text-left active:bg-stone-50 transition-colors border-b border-stone-50 last:border-0"
          >
            <img :src="resolveImageUrl(product)" :alt="product.name" class="w-12 h-12 object-contain bg-stone-50 rounded-xl border border-stone-100 shrink-0" />
            <span class="min-w-0">
              <span class="block text-sm font-black truncate text-stone-900">{{ product.name }}</span>
              <span class="block text-xs text-stone-500 truncate">{{ product.brand || 'Optik Medio' }}</span>
            </span>
          </button>
        </div>
      </div>

      <!-- Selected product chip -->
      <div v-if="selectedProduct" class="mx-4 mb-3 flex items-center gap-3 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2.5">
        <img :src="resolveImageUrl(selectedProduct)" :alt="selectedProduct.name" class="w-10 h-10 object-contain rounded-lg bg-white border border-stone-100 shrink-0" />
        <div class="min-w-0 flex-1">
          <p class="text-xs font-black text-stone-900 truncate">{{ selectedProduct.name }}</p>
          <p class="text-[11px] text-stone-500">{{ (selectedProduct as any).brand || 'Optik Medio' }}</p>
        </div>
        <button @click="selectedProduct = null; searchQuery = ''" class="text-stone-400 shrink-0 p-1">
          <span class="material-symbols-outlined text-base">close</span>
        </button>
      </div>
    </div>

    <!-- Preview canvas -->
    <div class="mx-4 mt-4 bg-white rounded-2xl overflow-hidden shadow-sm">
      <div class="relative aspect-[4/5] bg-stone-100 flex items-center justify-center">
        <img v-if="photoDataUrl" :src="photoDataUrl" alt="Foto wajah" class="absolute inset-0 w-full h-full object-contain" />
        <div v-else class="text-center p-8">
          <span class="material-symbols-outlined text-5xl mb-3 block" style="color: #c19a51;">add_a_photo</span>
          <p class="text-sm font-bold text-stone-400">Upload foto wajah dari depan</p>
        </div>
        <img v-if="photoDataUrl && selectedProduct" :src="resolveImageUrl(selectedProduct)" :alt="selectedProduct.name" class="absolute object-contain pointer-events-none mix-blend-multiply" :style="frameStyle" />
      </div>
    </div>

    <!-- Mobile saved previews -->
    <section v-if="savedPreviews.length > 0" class="mx-4 mt-6">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-black" style="color: #1a1209;">Preview Tersimpan</h2>
        <button @click="clearSavedPreviews" class="text-xs font-bold" style="color: #8a7a60;">Bersihkan</button>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <article v-for="preview in savedPreviews" :key="preview.id" class="bg-white rounded-2xl p-3 shadow-sm">
          <div class="relative aspect-[4/5] bg-stone-100 rounded-xl overflow-hidden">
            <img v-if="preview.compositeImage" :src="preview.compositeImage" :alt="preview.productName" class="absolute inset-0 w-full h-full object-contain" />
            <img v-else :src="preview.photo" alt="Preview" class="absolute inset-0 w-full h-full object-contain" />
          </div>
          <p class="mt-2 text-xs font-black line-clamp-1" style="color: #1a1209;">{{ preview.productName || preview.product?.name }}</p>
          <button @click="downloadPreview(preview)" class="mt-2 w-full flex items-center justify-center gap-1 py-2 text-[10px] font-black uppercase tracking-widest bg-stone-50 border border-stone-200 rounded-lg text-stone-700 active:bg-stone-100 transition-colors">
            <span class="material-symbols-outlined text-sm">download</span>
            Download
          </button>
        </article>
      </div>
    </section>
  </div>

  <!-- ═══ iOS-style bottom sheet (mobile only) ════════════════════════════ -->
  <div class="lg:hidden fixed bottom-0 left-0 right-0 z-30">
    <div
      class="bg-white rounded-t-3xl shadow-[0_-4px_32px_rgba(0,0,0,0.12)] transition-all duration-300 ease-out"
      :style="isSheetExpanded ? 'max-height: 72vh; overflow-y: auto;' : 'max-height: 230px; overflow: hidden;'"
    >
      <!-- Drag handle + header -->
      <button
        @click="toggleSheet"
        class="w-full flex flex-col items-center pt-3 pb-1 active:bg-stone-50 transition-colors"
        aria-label="Toggle kontrol"
      >
        <div class="w-9 h-1 rounded-full bg-stone-200 mb-3"></div>
        <div class="flex items-center justify-between w-full px-5 pb-2">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base" style="color: #c19a51;">tune</span>
            <span class="text-sm font-black text-stone-900">Edit Posisi Frame</span>
          </div>
          <span
            class="material-symbols-outlined text-xl text-stone-400 transition-transform duration-300"
            :style="isSheetExpanded ? 'transform: rotate(180deg)' : ''"
          >expand_less</span>
        </div>
      </button>

      <!-- Sliders -->
      <div class="px-5 space-y-5 pb-2">
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-black uppercase tracking-widest text-stone-500">Scale</span>
            <span class="text-xs font-bold tabular-nums" style="color: #c19a51;">{{ scale }}%</span>
          </div>
          <input v-model.number="scale" type="range" min="25" max="90" class="ios-slider w-full" />
        </div>
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-black uppercase tracking-widest text-stone-500">Horizontal</span>
            <span class="text-xs font-bold tabular-nums" style="color: #c19a51;">{{ offsetX > 0 ? '+' : '' }}{{ offsetX }}</span>
          </div>
          <input v-model.number="offsetX" type="range" min="-35" max="35" class="ios-slider w-full" />
        </div>
        <div v-show="isSheetExpanded">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-black uppercase tracking-widest text-stone-500">Vertical</span>
            <span class="text-xs font-bold tabular-nums" style="color: #c19a51;">{{ offsetY > 0 ? '+' : '' }}{{ offsetY }}</span>
          </div>
          <input v-model.number="offsetY" type="range" min="-10" max="65" class="ios-slider w-full" />
        </div>
        <div v-show="isSheetExpanded">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-black uppercase tracking-widest text-stone-500">Rotasi</span>
            <span class="text-xs font-bold tabular-nums" style="color: #c19a51;">{{ rotation }}°</span>
          </div>
          <input v-model.number="rotation" type="range" min="-20" max="20" class="ios-slider w-full" />
        </div>
      </div>

      <!-- Action buttons -->
      <div class="px-5 pt-3 pb-8 grid grid-cols-2 gap-3">
        <button
          @click="resetTransform"
          class="py-3.5 text-xs font-black uppercase tracking-widest border border-stone-200 text-stone-700 rounded-2xl active:bg-stone-100 transition-colors"
        >
          Reset
        </button>
        <button
          @click="savePreview"
          :disabled="!photoDataUrl || !selectedProduct || isSaving"
          class="py-3.5 text-xs font-black uppercase tracking-widest text-white rounded-2xl disabled:opacity-40 flex items-center justify-center gap-2 transition-opacity active:opacity-80"
          style="background: #1a1209;"
        >
          <span v-if="isSaving" class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
          <span>{{ isSaving ? 'Menyimpan...' : 'Simpan Preview' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ios-slider {
  -webkit-appearance: none;
  appearance: none;
  height: 6px;
  border-radius: 3px;
  background: #e7e5e4;
  outline: none;
  cursor: pointer;
}
.ios-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid #c19a51;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
  cursor: pointer;
}
.ios-slider::-moz-range-thumb {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid #c19a51;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
  cursor: pointer;
}
</style>
