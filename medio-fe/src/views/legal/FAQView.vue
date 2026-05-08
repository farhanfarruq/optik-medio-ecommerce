<template>
  <div class="bg-[#F5F2EE] min-h-screen">
    <!-- Mini Hero with gradient bleed -->
    <div class="relative w-full" style="margin-bottom: -20px;">
      <div class="relative overflow-hidden" style="height: 320px;">
        <img src="/gambar/hero-bg.jpeg" alt="" class="absolute inset-0 w-full h-full object-cover object-center" style="transform: scale(1.08); object-position: center 40%;" />
        <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(10,8,5,0.65) 0%, rgba(30,20,10,0.45) 100%);"></div>
        <div class="absolute bottom-0 left-0 right-0" style="height: 100px; background: linear-gradient(to bottom, transparent 0%, #F5F2EE 100%);"></div>
        <div class="absolute" style="bottom: 100px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.6), transparent);"></div>
        <div class="relative z-10 h-full max-w-[1000px] mx-auto px-6 flex flex-col justify-between" :style="{ paddingTop: 'calc(var(--header-height, 96px) + 24px)', paddingBottom: '48px' }">
          <!-- Breadcrumb + Back -->
          <div>
            <nav class="flex items-center gap-2 text-xs font-medium mb-2" style="color: rgba(255,255,255,0.55);">
              <router-link to="/" class="hover:text-white transition-colors">Beranda</router-link>
              <span class="material-symbols-outlined text-sm">chevron_right</span>
              <span class="text-white">FAQ</span>
            </nav>
            <router-link to="/" class="flex items-center gap-2 text-sm font-bold group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
              <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
              Kembali ke Beranda
            </router-link>
          </div>
          <!-- Page Title -->
          <h1 class="text-4xl font-black tracking-tight text-white" style="font-family: 'Outfit', sans-serif;">Pertanyaan Umum (FAQ)</h1>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-[1000px] mx-auto w-full px-6 pb-20 relative z-20">
      <div class="bg-white p-8 md:p-12 border border-outline-variant/15 shadow-sm">
        <div class="mb-10">
          <p class="text-xs font-black uppercase tracking-[0.2em] mb-3 text-primary">Pusat Bantuan</p>
          <p class="text-base leading-relaxed max-w-2xl text-on-surface-variant">
            Temukan jawaban untuk pertanyaan yang paling sering diajukan seputar produk, pemesanan, dan layanan kami.
          </p>
        </div>

        <!-- Category Tabs -->
        <div v-if="categories.length > 1" class="flex flex-wrap gap-2 mb-10">
          <button 
            @click="selectedCategory = 'Semua'"
            :class="[
              'px-6 py-2.5 text-xs font-black uppercase tracking-widest transition-all border',
              selectedCategory === 'Semua' 
                ? 'bg-primary text-white border-primary' 
                : 'bg-white text-on-surface-variant border-outline-variant/30 hover:border-primary/50'
            ]"
          >
            Semua
          </button>
          <button 
            v-for="cat in categories" 
            :key="cat"
            @click="selectedCategory = cat"
            :class="[
              'px-6 py-2.5 text-xs font-black uppercase tracking-widest transition-all border',
              selectedCategory === cat 
                ? 'bg-primary text-white border-primary' 
                : 'bg-white text-on-surface-variant border-outline-variant/30 hover:border-primary/50'
            ]"
          >
            {{ cat }}
          </button>
        </div>

        <!-- FAQ Accordion -->
        <div class="space-y-4">
          <div v-if="isLoading" class="space-y-4">
            <div v-for="i in 5" :key="i" class="h-16 bg-surface-container-low animate-pulse"></div>
          </div>
          
          <div 
            v-for="(faq, index) in filteredFaqs" 
            :key="faq.id"
            class="border border-outline-variant/10 overflow-hidden bg-white hover:border-primary/20 transition-all duration-300"
          >
            <button 
              @click="toggleFaq(index)"
              class="w-full flex items-center justify-between p-6 text-left group transition-colors"
              :class="{ 'bg-surface-container-lowest': openIndices.includes(index) }"
            >
              <span class="text-base md:text-lg font-bold text-on-surface group-hover:text-primary transition-colors pr-8">
                {{ faq.question }}
              </span>
              <span 
                class="material-symbols-outlined transition-transform duration-300 text-primary"
                :class="{ 'rotate-180': openIndices.includes(index) }"
              >
                expand_more
              </span>
            </button>
            
            <div 
              class="overflow-hidden transition-all duration-300 ease-in-out"
              :style="{ maxHeight: openIndices.includes(index) ? '500px' : '0' }"
            >
              <div class="px-6 pb-8 text-on-surface-variant leading-relaxed border-t border-outline-variant/5 pt-6 text-sm md:text-base">
                {{ faq.answer }}
                
                <div v-if="faq.category" class="mt-4">
                  <span class="text-[10px] font-black uppercase tracking-widest bg-primary/5 text-primary px-3 py-1.5 inline-block">
                    Kategori: {{ faq.category }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="!isLoading && filteredFaqs.length === 0" class="py-20 text-center border border-dashed border-outline-variant/50">
            <span class="material-symbols-outlined text-4xl text-outline-variant mb-4">search_off</span>
            <p class="text-on-surface-variant font-medium">Tidak ada pertanyaan yang ditemukan.</p>
          </div>
        </div>

        <!-- Contact CTA -->
        <div class="mt-16 p-8 md:p-10 bg-surface-container-low text-center border border-outline-variant/10">
          <h3 class="text-xl md:text-2xl font-black mb-3 text-on-surface">Masih punya pertanyaan lain?</h3>
          <p class="mb-8 text-on-surface-variant max-w-lg mx-auto text-sm md:text-base">Tim CS kami siap membantu Anda setiap hari pukul 09.00 - 20.00 WIB.</p>
          <a 
            href="https://wa.me/628972173420" 
            target="_blank"
            class="inline-flex items-center gap-2 px-8 py-4 bg-primary text-white font-black uppercase tracking-widest hover:shadow-lg hover:-translate-y-0.5 transition-all"
          >
            <span class="material-symbols-outlined text-xl">chat</span>
            Hubungi WhatsApp
          </a>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { apiClient } from '../../core/api/axiosclient';

interface Faq {
  id: number;
  question: string;
  answer: string;
  category: string;
  sort_order: number;
}

const faqs = ref<Faq[]>([]);
const isLoading = ref(true);
const openIndices = ref<number[]>([]);
const selectedCategory = ref('Semua');

const categories = computed(() => {
  const cats = faqs.value.map(f => f.category).filter(c => !!c);
  return [...new Set(cats)];
});

const filteredFaqs = computed(() => {
  if (selectedCategory.value === 'Semua') return faqs.value;
  return faqs.value.filter(f => f.category === selectedCategory.value);
});

const fetchFaqs = async () => {
  isLoading.value = true;
  try {
    const response = await apiClient.get('/faqs');
    faqs.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch FAQs', error);
  } finally {
    isLoading.value = false;
  }
};

const toggleFaq = (index: number) => {
  if (openIndices.value.includes(index)) {
    openIndices.value = openIndices.value.filter(i => i !== index);
  } else {
    openIndices.value = [index]; // Solo accordion
  }
};

onMounted(() => {
  fetchFaqs();
});
</script>
