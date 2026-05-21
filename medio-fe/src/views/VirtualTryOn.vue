<script setup lang="ts">
import { logger } from '../core/utils/logger';
import { computed, ref, watch } from 'vue';
import { productRepository, type ProductSearchSuggestions } from '../repositories/ProductRepository';
import { resolveImageUrl } from '../core/utils/image';
import type { Product } from '../types';
import PageHero from '../components/layout/PageHero.vue';

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
    logger.warn('Could not load glasses image for composite:', e);
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
  <PageHero
    title="Coba Frame dari Foto"
    subtitle="Upload foto wajah dan sesuaikan frame secara visual sebelum memilih produk."
    :breadcrumbs="[{ label: 'Virtual Try-On' }]"
    back-to="/products"
    back-label="Kembali ke Katalog"
  />

  <!-- ═══ DESKTOP layout (lg+) ═══════════════════════════════════════════ -->
  <main class="hidden lg:block min-h-screen bg-[var(--ivory)] pb-24 pt-8">
    <section class="max-w-[1440px] mx-auto px-12">
      <div class="grid grid-cols-[1fr_360px] gap-8">
        <section class="premium-card p-6">
          <div class="relative mx-auto max-w-3xl aspect-[4/5] bg-mist overflow-hidden flex items-center justify-center">
            <img v-if="photoDataUrl" :src="photoDataUrl" alt="Foto wajah" class="absolute inset-0 w-full h-full object-contain" loading="lazy" decoding="async" />
            <div v-else class="text-center p-8">
              <span class="material-symbols-outlined text-6xl mb-4 block" style="color: var(--gold);">add_a_photo</span>
              <p class="text-sm font-bold text-graphite/65">Upload foto wajah dari depan untuk mulai mencoba frame.</p>
            </div>
            <img v-if="photoDataUrl && selectedProduct" :src="resolveImageUrl(selectedProduct)" :alt="selectedProduct.name" class="absolute object-contain pointer-events-none mix-blend-multiply" :style="frameStyle" loading="lazy" decoding="async" />
          </div>
        </section>
        <aside class="premium-card p-6 h-fit space-y-5">
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Foto</span>
            <input type="file" accept="image/*" @change="handlePhotoUpload" class="w-full border border-mist px-3 py-3 text-sm" />
          </label>
          <div class="relative">
            <label class="block">
              <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Cari Frame</span>
              <input v-model="searchQuery" type="text" placeholder="Nama frame..." class="w-full border border-mist px-3 py-3 text-sm focus:outline-none focus:border-gold" />
            </label>
            <div v-if="searchQuery.trim().length >= 2 && (suggestions.products.length > 0 || isSearching)" class="absolute z-20 left-0 right-0 mt-2 premium-card shadow-soft p-2 max-h-72 overflow-y-auto">
              <p v-if="isSearching" class="p-3 text-xs font-bold text-graphite/65">Mencari...</p>
              <button v-for="product in suggestions.products" :key="product.id" @click="selectProduct(product)" class="w-full flex items-center gap-3 p-2 text-left hover:bg-ivory">
                <img :src="resolveImageUrl(product)" :alt="product.name" class="w-10 h-10 object-contain bg-ivory border border-mist" loading="lazy" decoding="async" />
                <span class="min-w-0">
                  <span class="block text-xs font-black truncate">{{ product.name }}</span>
                  <span class="block text-[11px] text-graphite/65 truncate">{{ product.brand || 'Optik Medio' }}</span>
                </span>
              </button>
            </div>
          </div>
          <div v-if="selectedProduct" class="p-3 border border-mist bg-ivory">
            <p class="text-xs font-black text-ink">{{ selectedProduct.name }}</p>
            <p class="text-[11px] text-graphite/65 mt-1">{{ (selectedProduct as any).brand || 'Optik Medio' }}</p>
          </div>
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Scale</span>
            <input v-model.number="scale" type="range" min="25" max="90" class="w-full accent-gold" />
          </label>
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Horizontal</span>
            <input v-model.number="offsetX" type="range" min="-35" max="35" class="w-full accent-gold" />
          </label>
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Vertical</span>
            <input v-model.number="offsetY" type="range" min="-10" max="65" class="w-full accent-gold" />
          </label>
          <label class="block">
            <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Rotasi</span>
            <input v-model.number="rotation" type="range" min="-20" max="20" class="w-full accent-gold" />
          </label>
          <div class="grid grid-cols-2 gap-3">
            <button @click="resetTransform" class="py-3 text-xs font-black uppercase tracking-widest border border-mist text-graphite">Reset</button>
            <button @click="savePreview" :disabled="!photoDataUrl || !selectedProduct || isSaving" class="py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-50 flex items-center justify-center gap-2" style="background: var(--ink);">
              <span v-if="isSaving" class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
              <span>{{ isSaving ? 'Menyimpan...' : 'Simpan' }}</span>
            </button>
          </div>
        </aside>
      </div>
      <section v-if="savedPreviews.length > 0" class="mt-10">
        <div class="flex items-end justify-between gap-4 mb-5">
          <h2 class="text-2xl font-black" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Preview Tersimpan</h2>
          <button @click="clearSavedPreviews" class="text-xs font-bold uppercase tracking-widest" style="color: #5c4a3a;">Bersihkan</button>
        </div>
        <div class="grid grid-cols-4 gap-4">
          <article v-for="preview in savedPreviews" :key="preview.id" class="premium-card p-3">
            <div class="relative aspect-[4/5] bg-mist overflow-hidden">
              <img v-if="preview.compositeImage" :src="preview.compositeImage" :alt="preview.productName" class="absolute inset-0 w-full h-full object-contain" loading="lazy" decoding="async" />
              <img v-else :src="preview.photo" alt="Preview tersimpan" class="absolute inset-0 w-full h-full object-contain" loading="lazy" decoding="async" />
            </div>
            <p class="mt-3 text-xs font-black line-clamp-2" style="color: var(--ink);">{{ preview.productName || preview.product?.name }}</p>
            <p class="text-[11px] text-graphite/65 mt-0.5">{{ preview.productBrand || preview.product?.brand || 'Optik Medio' }}</p>
            <button @click="downloadPreview(preview)" class="mt-3 w-full flex items-center justify-center gap-1.5 py-2 text-[10px] font-black uppercase tracking-widest border border-mist text-graphite hover:bg-ivory transition-colors">
              <span class="material-symbols-outlined text-sm">download</span>
              Download
            </button>
          </article>
        </div>
      </section>
    </section>
  </main>

  <!-- ═══ MOBILE layout (< lg) ════════════════════════════════════════════ -->
  <div class="lg:hidden flex flex-col bg-[var(--ivory)]" style="padding-top: 96px; padding-bottom: 380px;">

    <!-- Upload & Search card -->
    <div class="mx-4 mt-4 bg-porcelain rounded-2xl shadow-card overflow-visible">
      <!-- Upload foto -->
      <label class="flex items-center gap-3 px-4 py-4 border-b border-mist cursor-pointer active:bg-ivory transition-colors rounded-t-2xl">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background: #fdf6ec;">
          <span class="material-symbols-outlined text-xl" style="color: var(--gold);">add_a_photo</span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-[10px] font-black uppercase tracking-widest text-graphite/45 mb-0.5">Foto Wajah</p>
          <p class="text-sm font-bold truncate" :class="photoDataUrl ? 'text-ink' : 'text-graphite/45'">
            {{ photoDataUrl ? 'Foto dipilih ✓' : 'Pilih dari galeri...' }}
          </p>
        </div>
        <span class="material-symbols-outlined text-graphite/40 shrink-0">chevron_right</span>
        <input type="file" accept="image/*" @change="handlePhotoUpload" class="hidden" />
      </label>

      <!-- Cari frame -->
      <div class="relative px-4 py-3">
        <p class="text-[10px] font-black uppercase tracking-widest text-graphite/45 mb-2">Cari Frame</p>
        <div class="flex items-center gap-2 bg-ivory rounded-xl px-3 py-2.5">
          <span class="material-symbols-outlined text-lg text-graphite/45 shrink-0">search</span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Nama frame kacamata..."
            class="flex-1 bg-transparent text-sm font-bold focus:outline-none placeholder:text-graphite/45 placeholder:font-normal"
          />
          <button v-if="searchQuery" @click="searchQuery = ''" class="text-graphite/45 shrink-0">
            <span class="material-symbols-outlined text-base">close</span>
          </button>
        </div>
        <div v-if="searchQuery.trim().length >= 2 && (suggestions.products.length > 0 || isSearching)" class="absolute z-20 left-4 right-4 mt-2 bg-porcelain rounded-2xl shadow-soft border border-mist overflow-hidden max-h-64 overflow-y-auto">
          <p v-if="isSearching" class="p-4 text-xs font-bold text-graphite/65 text-center">Mencari...</p>
          <button
            v-for="product in suggestions.products"
            :key="product.id"
            @click="selectProduct(product)"
            class="w-full flex items-center gap-3 px-4 py-3 text-left active:bg-ivory transition-colors border-b border-mist last:border-0"
          >
            <img :src="resolveImageUrl(product)" :alt="product.name" class="w-12 h-12 object-contain bg-ivory rounded-xl border border-mist shrink-0" loading="lazy" decoding="async" />
            <span class="min-w-0">
              <span class="block text-sm font-black truncate text-ink">{{ product.name }}</span>
              <span class="block text-xs text-graphite/65 truncate">{{ product.brand || 'Optik Medio' }}</span>
            </span>
          </button>
        </div>
      </div>

      <!-- Selected product chip -->
      <div v-if="selectedProduct" class="mx-4 mb-3 flex items-center gap-3 bg-gold/10 border border-gold/25 rounded-xl px-3 py-2.5">
        <img :src="resolveImageUrl(selectedProduct)" :alt="selectedProduct.name" class="w-10 h-10 object-contain rounded-lg premium-card shrink-0" loading="lazy" decoding="async" />
        <div class="min-w-0 flex-1">
          <p class="text-xs font-black text-ink truncate">{{ selectedProduct.name }}</p>
          <p class="text-[11px] text-graphite/65">{{ (selectedProduct as any).brand || 'Optik Medio' }}</p>
        </div>
        <button @click="selectedProduct = null; searchQuery = ''" class="text-graphite/45 shrink-0 p-1">
          <span class="material-symbols-outlined text-base">close</span>
        </button>
      </div>
    </div>

    <!-- Preview canvas -->
    <div class="mx-4 mt-4 bg-porcelain rounded-2xl overflow-hidden shadow-card">
      <div class="relative aspect-[4/5] bg-mist flex items-center justify-center">
        <img v-if="photoDataUrl" :src="photoDataUrl" alt="Foto wajah" class="absolute inset-0 w-full h-full object-contain" loading="lazy" decoding="async" />
        <div v-else class="text-center p-8">
          <span class="material-symbols-outlined text-5xl mb-3 block" style="color: var(--gold);">add_a_photo</span>
          <p class="text-sm font-bold text-graphite/45">Upload foto wajah dari depan</p>
        </div>
        <img v-if="photoDataUrl && selectedProduct" :src="resolveImageUrl(selectedProduct)" :alt="selectedProduct.name" class="absolute object-contain pointer-events-none mix-blend-multiply" :style="frameStyle" loading="lazy" decoding="async" />
      </div>
    </div>

    <!-- Mobile saved previews -->
    <section v-if="savedPreviews.length > 0" class="mx-4 mt-6">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-black" style="color: var(--ink);">Preview Tersimpan</h2>
        <button @click="clearSavedPreviews" class="text-xs font-bold" style="color: #5c4a3a;">Bersihkan</button>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <article v-for="preview in savedPreviews" :key="preview.id" class="bg-porcelain rounded-2xl p-3 shadow-card">
          <div class="relative aspect-[4/5] bg-mist rounded-xl overflow-hidden">
            <img v-if="preview.compositeImage" :src="preview.compositeImage" :alt="preview.productName" class="absolute inset-0 w-full h-full object-contain" loading="lazy" decoding="async" />
            <img v-else :src="preview.photo" alt="Preview" class="absolute inset-0 w-full h-full object-contain" loading="lazy" decoding="async" />
          </div>
          <p class="mt-2 text-xs font-black line-clamp-1" style="color: var(--ink);">{{ preview.productName || preview.product?.name }}</p>
          <button @click="downloadPreview(preview)" class="mt-2 w-full flex items-center justify-center gap-1 py-2 text-[10px] font-black uppercase tracking-widest bg-ivory border border-mist rounded-lg text-graphite active:bg-mist transition-colors">
            <span class="material-symbols-outlined text-sm">download</span>
            Download
          </button>
        </article>
      </div>
    </section>
  </div>

  <!-- ═══ iOS-style bottom sheet (mobile only) ════════════════════════════ -->
  <div class="lg:hidden fixed left-3 right-3 z-40" style="bottom: calc(88px + env(safe-area-inset-bottom));">
    <div
      class="bg-porcelain rounded-3xl border border-mist shadow-[0_18px_60px_rgba(0,0,0,0.24)] transition-all duration-300 ease-out overflow-hidden"
      :style="isSheetExpanded ? 'max-height: min(62vh, 520px); overflow-y: auto;' : 'max-height: 218px; overflow: hidden;'"
    >
      <!-- Drag handle + header -->
      <button
        @click="toggleSheet"
        class="w-full flex flex-col items-center pt-3 pb-1 active:bg-ivory transition-colors"
        aria-label="Toggle kontrol"
      >
        <div class="w-9 h-1 rounded-full bg-mist mb-3"></div>
        <div class="flex items-center justify-between w-full px-5 pb-2">
          <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base" style="color: var(--gold);">tune</span>
            <span class="text-sm font-black text-ink">Edit Posisi Frame</span>
          </div>
          <span
            class="material-symbols-outlined text-xl text-graphite/45 transition-transform duration-300"
            :style="isSheetExpanded ? 'transform: rotate(180deg)' : ''"
          >expand_less</span>
        </div>
      </button>

      <!-- Sliders -->
      <div class="px-5 space-y-5 pb-2">
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-black uppercase tracking-widest text-graphite/65">Scale</span>
            <span class="text-xs font-bold tabular-nums" style="color: var(--gold);">{{ scale }}%</span>
          </div>
          <input v-model.number="scale" type="range" min="25" max="90" class="ios-slider w-full" />
        </div>
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-black uppercase tracking-widest text-graphite/65">Horizontal</span>
            <span class="text-xs font-bold tabular-nums" style="color: var(--gold);">{{ offsetX > 0 ? '+' : '' }}{{ offsetX }}</span>
          </div>
          <input v-model.number="offsetX" type="range" min="-35" max="35" class="ios-slider w-full" />
        </div>
        <div v-show="isSheetExpanded">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-black uppercase tracking-widest text-graphite/65">Vertical</span>
            <span class="text-xs font-bold tabular-nums" style="color: var(--gold);">{{ offsetY > 0 ? '+' : '' }}{{ offsetY }}</span>
          </div>
          <input v-model.number="offsetY" type="range" min="-10" max="65" class="ios-slider w-full" />
        </div>
        <div v-show="isSheetExpanded">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-black uppercase tracking-widest text-graphite/65">Rotasi</span>
            <span class="text-xs font-bold tabular-nums" style="color: var(--gold);">{{ rotation }}°</span>
          </div>
          <input v-model.number="rotation" type="range" min="-20" max="20" class="ios-slider w-full" />
        </div>
      </div>

      <!-- Action buttons -->
      <div class="px-5 pt-3 pb-5 grid grid-cols-2 gap-3">
        <button
          @click="resetTransform"
          class="py-3.5 text-xs font-black uppercase tracking-widest border border-mist text-graphite rounded-2xl active:bg-mist transition-colors"
        >
          Reset
        </button>
        <button
          @click="savePreview"
          :disabled="!photoDataUrl || !selectedProduct || isSaving"
          class="py-3.5 text-xs font-black uppercase tracking-widest text-white rounded-2xl disabled:opacity-40 flex items-center justify-center gap-2 transition-opacity active:opacity-80"
          style="background: var(--ink);"
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
  border: 2px solid var(--gold);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
  cursor: pointer;
}
.ios-slider::-moz-range-thumb {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #fff;
  border: 2px solid var(--gold);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
  cursor: pointer;
}
</style>
