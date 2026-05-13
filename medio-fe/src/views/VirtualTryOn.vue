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

const savePreview = () => {
  if (!photoDataUrl.value || !selectedProduct.value) return;

  savedPreviews.value = [
    {
      id: Date.now(),
      photo: photoDataUrl.value,
      product: selectedProduct.value,
      scale: scale.value,
      offsetX: offsetX.value,
      offsetY: offsetY.value,
      rotation: rotation.value,
    },
    ...savedPreviews.value,
  ].slice(0, 4);

  window.localStorage.setItem('medio_virtual_try_on_previews', JSON.stringify(savedPreviews.value));
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

  <main class="min-h-screen bg-[#f5f2ee] pb-24 pt-24">
    <section class="max-w-[1440px] mx-auto px-6 md:px-12">
      <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-8">
        <section class="bg-white border border-stone-100 p-4 md:p-6">
          <div class="relative mx-auto max-w-3xl aspect-[4/5] bg-stone-100 overflow-hidden flex items-center justify-center">
            <img v-if="photoDataUrl" :src="photoDataUrl" alt="Foto wajah" class="absolute inset-0 w-full h-full object-contain" />
            <div v-else class="text-center p-8">
              <span class="material-symbols-outlined text-6xl mb-4 block" style="color: #c19a51;">add_a_photo</span>
              <p class="text-sm font-bold text-stone-500">Upload foto wajah dari depan untuk mulai mencoba frame.</p>
            </div>

            <img
              v-if="photoDataUrl && selectedProduct"
              :src="resolveImageUrl(selectedProduct)"
              :alt="selectedProduct.name"
              class="absolute object-contain pointer-events-none mix-blend-multiply"
              :style="frameStyle"
            />
          </div>
        </section>

        <aside class="bg-white border border-stone-100 p-6 h-fit">
          <div class="space-y-5">
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
                <button
                  v-for="product in suggestions.products"
                  :key="product.id"
                  @click="selectProduct(product)"
                  class="w-full flex items-center gap-3 p-2 text-left hover:bg-stone-50"
                >
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
              <p class="text-[11px] text-stone-500 mt-1">{{ selectedProduct.brand || 'Optik Medio' }}</p>
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
              <button @click="resetTransform" class="py-3 text-xs font-black uppercase tracking-widest border border-stone-300 text-stone-700">
                Reset
              </button>
              <button
                @click="savePreview"
                :disabled="!photoDataUrl || !selectedProduct"
                class="py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-50"
                style="background: #1a1209;"
              >
                Simpan
              </button>
            </div>
          </div>
        </aside>
      </div>

      <section v-if="savedPreviews.length > 0" class="mt-10">
        <div class="flex items-end justify-between gap-4 mb-5">
          <h2 class="text-2xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Preview Tersimpan</h2>
          <button @click="clearSavedPreviews" class="text-xs font-bold uppercase tracking-widest" style="color: #8a7a60;">Bersihkan</button>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <article v-for="preview in savedPreviews" :key="preview.id" class="bg-white border border-stone-100 p-3">
            <div class="relative aspect-[4/5] bg-stone-100 overflow-hidden">
              <img :src="preview.photo" alt="Preview tersimpan" class="absolute inset-0 w-full h-full object-contain" />
              <img :src="resolveImageUrl(preview.product)" :alt="preview.product.name" class="absolute object-contain mix-blend-multiply pointer-events-none" :style="{ width: `${preview.scale}%`, left: `${50 + preview.offsetX}%`, top: `${50 + preview.offsetY}%`, transform: `translate(-50%, -50%) rotate(${preview.rotation}deg)` }" />
            </div>
            <p class="mt-3 text-xs font-black line-clamp-2" style="color: #1a1209;">{{ preview.product.name }}</p>
          </article>
        </div>
      </section>
    </section>
  </main>
</template>
