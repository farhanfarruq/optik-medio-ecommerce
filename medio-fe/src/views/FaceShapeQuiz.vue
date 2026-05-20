<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { productRepository } from '../repositories/ProductRepository';
import { resolveImageUrl } from '../core/utils/image';
import type { Product } from '../types';
import PageHero from '../components/layout/PageHero.vue';
import { useSeoMeta } from '../composables/useSeoMeta';

const router = useRouter();
const faceShape = ref('');
const stylePreference = ref('');
const faceSize = ref('');
const budget = ref('');
const results = ref<Product[]>([]);
const isLoading = ref(false);
const hasSubmitted = ref(false);


const faceShapeOptions = [
  { value: 'round', label: 'Round', frameShape: 'square', note: 'Frame tegas membantu memberi struktur wajah.' },
  { value: 'oval', label: 'Oval', frameShape: 'aviator', note: 'Proporsi fleksibel cocok untuk banyak bentuk frame.' },
  { value: 'square', label: 'Square', frameShape: 'round', note: 'Frame membulat melembutkan garis rahang.' },
  { value: 'heart', label: 'Heart', frameShape: 'cat_eye', note: 'Frame bagian bawah ringan menjaga keseimbangan wajah.' },
  { value: 'diamond', label: 'Diamond', frameShape: 'oval', note: 'Frame oval menyeimbangkan tulang pipi yang kuat.' },
];

const styleOptions = [
  { value: 'minimal', label: 'Minimal', material: 'metal' },
  { value: 'classic', label: 'Classic', material: 'acetate' },
  { value: 'bold', label: 'Bold', material: 'acetate' },
  { value: 'sporty', label: 'Sporty', material: 'tr90' },
];

const sizeOptions = [
  { value: 'small', label: 'Small' },
  { value: 'medium', label: 'Medium' },
  { value: 'large', label: 'Large' },
];

const budgetOptions = [
  { value: 'under_500', label: '< Rp 500 ribu', max: 500000 },
  { value: '500_1000', label: 'Rp 500 ribu - 1 juta', min: 500000, max: 1000000 },
  { value: 'above_1000', label: '> Rp 1 juta', min: 1000000 },
];

const selectedFace = computed(() => faceShapeOptions.find(item => item.value === faceShape.value));
const selectedStyle = computed(() => styleOptions.find(item => item.value === stylePreference.value));
const selectedBudget = computed(() => budgetOptions.find(item => item.value === budget.value));
const canSubmit = computed(() => faceShape.value && stylePreference.value && faceSize.value && budget.value);

const runQuiz = async () => {
  if (!canSubmit.value) return;

  try {
    isLoading.value = true;
    hasSubmitted.value = true;
    const params: Record<string, any> = {
      frame_shape: selectedFace.value?.frameShape,
      frame_material: selectedStyle.value?.material,
      face_size_fit: faceSize.value,
      in_stock_only: 'true',
      sort: 'popular',
      per_page: 8,
    };

    if (selectedBudget.value?.min) params.min_price = selectedBudget.value.min;
    if (selectedBudget.value?.max) params.max_price = selectedBudget.value.max;

    const response = await productRepository.getProducts(params);
    results.value = response.data || response;

    if (results.value.length === 0) {
      const fallback = await productRepository.getProducts({
        face_size_fit: faceSize.value,
        in_stock_only: 'true',
        sort: 'popular',
        per_page: 8,
      });
      results.value = fallback.data || fallback;
    }
  } finally {
    isLoading.value = false;
  }
};

const openFilteredProducts = () => {
  const query: Record<string, any> = {
    frame_shape: selectedFace.value?.frameShape,
    face_size_fit: faceSize.value,
    in_stock_only: 'true',
  };

  if (selectedBudget.value?.min) query.min_price = selectedBudget.value.min;
  if (selectedBudget.value?.max) query.max_price = selectedBudget.value.max;

  router.push({ path: '/products', query });
};

onMounted(() => {
  // SEO-2 (Phase 6)
  const { setSeo } = useSeoMeta();
  setSeo({
    title: 'Kuis Bentuk Wajah — Cari Frame yang Tepat',
    description:
      'Temukan frame kacamata yang paling cocok dengan bentuk wajah Anda. Kuis cepat untuk rekomendasi eyewear dari Optik Medio.',
    ogType: 'website',
  });
});
</script>

<template>
  <PageHero
    title="Cari Frame yang Cocok"
    subtitle="Jawab preferensi cepat untuk mendapat rekomendasi frame yang lebih relevan."
    :breadcrumbs="[{ label: 'Face Shape Quiz' }]"
    back-to="/products"
    back-label="Kembali ke Katalog"
  />

  <main class="min-h-screen bg-[var(--ivory)] pb-24 pt-40">
    <section class="container-premium">
      <div class="grid grid-cols-1 lg:grid-cols-[420px_1fr] gap-8">
        <aside class="premium-card p-6 h-fit">
          <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-3" style="color: var(--gold);">Quiz Cepat</p>
          <h2 class="text-3xl font-black tracking-normal mb-4" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Preferensi Frame</h2>
          <p class="text-sm leading-relaxed text-graphite/65 mb-6">Jawab empat pilihan cepat untuk mendapatkan rekomendasi frame yang lebih relevan.</p>

          <div class="space-y-5">
            <label class="block">
              <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Bentuk Wajah</span>
              <select v-model="faceShape" class="w-full premium-card px-3 py-3 text-sm focus:outline-none focus:border-gold">
                <option value="">Pilih</option>
                <option v-for="item in faceShapeOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
            </label>

            <label class="block">
              <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Style</span>
              <select v-model="stylePreference" class="w-full premium-card px-3 py-3 text-sm focus:outline-none focus:border-gold">
                <option value="">Pilih</option>
                <option v-for="item in styleOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
            </label>

            <label class="block">
              <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Ukuran Wajah</span>
              <select v-model="faceSize" class="w-full premium-card px-3 py-3 text-sm focus:outline-none focus:border-gold">
                <option value="">Pilih</option>
                <option v-for="item in sizeOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
            </label>

            <label class="block">
              <span class="block text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-2">Budget</span>
              <select v-model="budget" class="w-full premium-card px-3 py-3 text-sm focus:outline-none focus:border-gold">
                <option value="">Pilih</option>
                <option v-for="item in budgetOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
              </select>
            </label>

            <button
              @click="runQuiz"
              :disabled="!canSubmit || isLoading"
              class="w-full py-3 text-xs font-black uppercase tracking-widest text-white disabled:opacity-50"
              style="background: var(--ink);"
            >
              {{ isLoading ? 'Mencari...' : 'Lihat Rekomendasi' }}
            </button>
          </div>
        </aside>

        <section class="min-w-0">
          <div v-if="!hasSubmitted" class="premium-card p-10 min-h-[420px] flex flex-col justify-center">
            <span class="material-symbols-outlined text-6xl mb-5" style="color: var(--gold);">face</span>
            <h2 class="text-3xl font-black mb-3" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">Mulai dari bentuk wajah</h2>
            <p class="text-graphite/65 max-w-xl">Quiz ini memakai atribut produk optik yang sudah ada: bentuk frame, material, fit wajah, stok, dan rentang harga.</p>
          </div>

          <div v-else>
            <div class="premium-card p-6 mb-6">
              <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-2" style="color: var(--gold);">Hasil</p>
              <h2 class="text-2xl font-black mb-2" style="color: var(--ink); font-family: 'Cormorant Garamond', serif;">
                Rekomendasi {{ selectedFace?.label }}
              </h2>
              <p class="text-sm text-graphite/65">{{ selectedFace?.note }}</p>
              <button @click="openFilteredProducts" class="mt-4 text-xs font-black uppercase tracking-widest underline underline-offset-4" style="color: #7a6230;">
                Buka semua produk sesuai hasil
              </button>
            </div>

            <div v-if="isLoading" class="grid grid-cols-2 md:grid-cols-3 gap-5">
              <div v-for="item in 6" :key="item" class="animate-pulse premium-card">
                <div class="aspect-[4/5] bg-mist"></div>
                <div class="p-4 space-y-3">
                  <div class="h-3 bg-mist w-1/2"></div>
                  <div class="h-4 bg-mist w-4/5"></div>
                </div>
              </div>
            </div>

            <div v-else-if="results.length === 0" class="premium-card p-10 text-center">
              <h3 class="text-xl font-black mb-2" style="color: var(--ink);">Belum ada produk cocok</h3>
              <p class="text-sm text-graphite/65">Coba ubah budget atau ukuran wajah.</p>
            </div>

            <div v-else class="grid grid-cols-2 md:grid-cols-3 gap-5">
              <article
                v-for="product in results"
                :key="product.id"
                @click="router.push(`/products/${product.slug}`)"
                class="group premium-card cursor-pointer transition-all hover:-translate-y-1 hover:shadow-soft"
              >
                <div class="aspect-[4/5] bg-ivory flex items-center justify-center p-5">
                  <img :src="resolveImageUrl(product)" :alt="product.name" class="w-full h-full object-contain transition-transform group-hover:scale-105" loading="lazy" decoding="async" />
                </div>
                <div class="p-4">
                  <p class="text-[10px] font-black uppercase tracking-widest text-graphite/65 mb-1">{{ product.brand || 'Optik Medio' }}</p>
                  <h3 class="text-sm font-black line-clamp-2 min-h-10" style="color: var(--ink);">{{ product.name }}</h3>
                  <p class="mt-3 text-sm font-black" style="color: #7a6230;">Rp {{ product.price.toLocaleString('id-ID') }}</p>
                </div>
              </article>
            </div>
          </div>
        </section>
      </div>
    </section>
  </main>
</template>
