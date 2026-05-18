<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { productRepository } from '../repositories/ProductRepository';
import { useCompareStore } from '../stores/compareStore';
import { resolveImageUrl } from '../core/utils/image';
import type { Product } from '../types';

const router = useRouter();
const compareStore = useCompareStore();
const products = ref<Product[]>([]);
const isLoading = ref(false);
const errorMessage = ref('');

const goBack = () => {
  if (window.history.length > 1) {
    router.back();
    return;
  }

  router.push('/products');
};

const attributeLabels: Record<string, string> = {
  brand: 'Brand',
  price: 'Harga',
  stock: 'Stok',
  gender: 'Gender',
  frame_shape: 'Bentuk Frame',
  frame_material: 'Material',
  frame_color: 'Warna',
  face_size_fit: 'Fit Wajah',
  lens_width: 'Lebar Lensa',
  bridge_width: 'Bridge',
  temple_length: 'Temple',
  frame_width: 'Lebar Frame',
  is_prescription_required: 'Dukungan Resep',
  avg_rating: 'Rating',
  review_count: 'Ulasan',
};

const attributes = computed(() => Object.keys(attributeLabels));

const formatValue = (product: Product, attribute: string) => {
  const value = (product as any)[attribute];
  if (attribute === 'price') return `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
  if (attribute === 'is_prescription_required') return value ? 'Bisa resep' : 'Non resep';
  if (attribute === 'avg_rating') return Number(value || 0).toFixed(1);
  if (value === null || value === undefined || value === '') return '-';
  return String(value).replace(/[-_]/g, ' ');
};

const loadCompare = async () => {
  errorMessage.value = '';
  if (compareStore.items.length < 2) {
    products.value = compareStore.items;
    return;
  }

  try {
    isLoading.value = true;
    const response = await productRepository.compareProducts(compareStore.items.map(item => item.id));
    products.value = response.products;
  } catch (error: any) {
    errorMessage.value = error.response?.data?.message || 'Gagal memuat perbandingan produk.';
  } finally {
    isLoading.value = false;
  }
};

onMounted(loadCompare);
watch(() => compareStore.items.map(item => item.id).join(','), loadCompare);
</script>

<template>
  <div class="relative w-full bg-[var(--ivory)]" style="margin-bottom: -60px;">
    <div class="relative overflow-hidden" style="height: 280px;">
      <img src="/gambar/hero-bg.jpeg" alt="" class="absolute inset-0 w-full h-full object-cover object-center" style="transform: scale(1.08); object-position: center 40%;" />
      <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(10,8,5,0.68) 0%, rgba(30,20,10,0.48) 100%);"></div>
      <div class="absolute bottom-0 left-0 right-0" style="height: 100px; background: linear-gradient(to bottom, transparent 0%, var(--ivory) 100%);"></div>
      <div class="absolute" style="bottom: 100px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(184,138,68,0.6), transparent);"></div>
      <div class="relative z-10 h-full container-premium flex flex-col justify-between" :style="{ paddingTop: 'calc(var(--header-height, 96px) + 16px)', paddingBottom: '56px' }">
        <div>
          <nav class="flex items-center gap-2 text-xs font-medium mb-2" style="color: rgba(255,255,255,0.55);">
            <router-link to="/" class="hover:text-white transition-colors">Beranda</router-link>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-white">Compare</span>
          </nav>
          <button @click="goBack" class="flex items-center gap-2 text-sm font-bold group w-fit transition-all" style="color: rgba(184,138,68,0.95);">
            <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
            Kembali
          </button>
        </div>
        <div>
          <p class="text-[10px] font-black uppercase tracking-[0.3em] mb-3" style="color: var(--gold);">Compare</p>
          <h1 class="text-4xl md:text-5xl font-black tracking-normal text-white" style="font-family: 'Cormorant Garamond', serif;">Bandingkan Produk</h1>
        </div>
      </div>
    </div>
  </div>

  <main class="min-h-screen bg-ivory pb-24 pt-24">
    <section class="container-premium">
      <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-5 mb-8">
        <div>
          <p class="text-sm font-bold text-graphite/65">Pilih 2 sampai 4 produk untuk melihat perbedaan fitur, harga, dan rekomendasi.</p>
        </div>
        <div class="flex gap-3">
          <button @click="compareStore.clear()" class="btn-outline px-4 py-2 text-xs uppercase tracking-[0.12em]">
            Bersihkan
          </button>
          <button @click="router.push('/products')" class="btn-primary px-4 py-2 text-xs uppercase tracking-[0.12em]">
            Tambah Produk
          </button>
        </div>
      </div>

      <div v-if="errorMessage" class="mb-6 border p-4 text-sm font-bold" style="border-color: rgba(220,38,38,0.25); color: #dc2626; background: rgba(220,38,38,0.05);">
        {{ errorMessage }}
      </div>

      <div v-if="compareStore.items.length < 2" class="border border-dashed p-10 text-center bg-porcelain">
        <span class="material-symbols-outlined text-5xl mb-4 block" style="color: var(--gold);">compare_arrows</span>
        <h2 class="text-xl font-black mb-2" style="color: var(--ink);">Pilih minimal 2 produk</h2>
        <p class="text-sm text-graphite/65 mb-6">Maksimal 4 produk bisa dibandingkan sekaligus.</p>
        <button @click="router.push('/products')" class="px-6 py-3 text-xs font-black uppercase tracking-widest text-white" style="background: var(--ink);">
          Lihat Produk
        </button>
      </div>

      <div v-else class="premium-card overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr>
              <th class="w-48 p-4 text-left text-xs font-black uppercase tracking-widest text-graphite/65 bg-ivory">Atribut</th>
              <th v-for="product in products" :key="product.id" class="min-w-56 p-4 text-left align-top border-l border-mist">
                <button @click="router.push(`/products/${product.slug}`)" class="text-left group">
                  <img :src="resolveImageUrl(product)" :alt="product.name" class="h-24 w-24 rounded-lg border border-mist bg-ivory object-contain p-2 mb-3" />
                  <p class="text-[10px] font-black uppercase tracking-widest text-graphite/65">{{ product.brand || 'Optik Medio' }}</p>
                  <h2 class="font-black text-ink group-hover:text-gold">{{ product.name }}</h2>
                </button>
                <button @click="compareStore.remove(product.id)" class="mt-3 inline-flex text-xs font-semibold text-[#A65F55] underline">
                  Hapus
                </button>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="isLoading">
              <td :colspan="products.length + 1" class="p-8 text-center text-graphite/65">Memuat perbandingan...</td>
            </tr>
            <tr v-for="attribute in attributes" :key="attribute" class="border-t border-mist">
              <td class="p-4 font-black text-graphite bg-ivory">{{ attributeLabels[attribute] }}</td>
              <td v-for="product in products" :key="product.id + attribute" class="p-4 border-l border-mist text-graphite capitalize">
                {{ formatValue(product, attribute) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</template>
