<template>
  <div class="bg-[var(--ivory)] min-h-screen">
    <PageHero
      title="Pertanyaan Umum (FAQ)"
      :breadcrumbs="[{ label: 'FAQ' }]"
    />

    <!-- Main Content -->
    <main class="container-readable relative z-20 pt-24 pb-20 sm:pt-28">
      <div class="bg-porcelain p-8 md:p-12 border border-outline-variant/15 shadow-card">
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
                : 'bg-porcelain text-on-surface-variant border-outline-variant/30 hover:border-primary/50'
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
                : 'bg-porcelain text-on-surface-variant border-outline-variant/30 hover:border-primary/50'
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
            v-for="faq in filteredFaqs" 
            :key="faq.id"
            class="border border-outline-variant/10 overflow-hidden bg-porcelain hover:border-primary/20 transition-all duration-300"
          >
            <button 
              @click="toggleFaq(faq.id)"
              class="w-full flex items-center justify-between p-6 text-left group transition-colors"
              :class="{ 'bg-surface-container-low': openFaqId === faq.id }"
            >
              <span class="text-base md:text-lg font-bold text-on-surface group-hover:text-primary transition-colors pr-8">
                {{ faq.question }}
              </span>
              <span 
                class="material-symbols-outlined transition-transform duration-300 text-primary"
                :class="{ 'rotate-180': openFaqId === faq.id }"
              >
                expand_more
              </span>
            </button>
            
            <div 
              class="overflow-hidden transition-all duration-300 ease-in-out"
              :style="{ maxHeight: openFaqId === faq.id ? '500px' : '0' }"
            >
              <div class="px-6 pb-8 text-on-surface-variant leading-relaxed border-t border-outline-variant/5 pt-6 text-sm md:text-base" v-html="sanitizeHtml(faq.answer)"></div>
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
            :href="whatsappHref" 
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 px-8 py-4 bg-primary text-white font-black uppercase tracking-widest hover:shadow-card hover:-translate-y-0.5 transition-all"
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
import { ref, onMounted, computed, watch } from 'vue';
import PageHero from '../../components/layout/PageHero.vue';
import { apiClient } from '../../core/api/axiosclient';
import { settingRepository, type AppSettings } from '../../repositories/SettingRepository';
import { sanitizeHtml } from '../../core/utils/sanitize';

interface Faq {
  id: number;
  question: string;
  answer: string;
  category: string;
  sort_order: number;
}

const faqs = ref<Faq[]>([]);
const settings = ref<AppSettings | null>(null);
const isLoading = ref(true);
const openFaqId = ref<number | null>(null);
const selectedCategory = ref('Semua');

const categories = computed(() => {
  const cats = faqs.value.map(f => f.category).filter(c => !!c);
  return [...new Set(cats)];
});

const filteredFaqs = computed(() => {
  if (selectedCategory.value === 'Semua') return faqs.value;
  return faqs.value.filter(f => f.category === selectedCategory.value);
});

const whatsappHref = computed(() => {
  const phone = settings.value?.store_phone?.replace(/\D/g, '') || '628972173420';
  return `https://wa.me/${phone}`;
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

const fetchSettings = async () => {
  try {
    settings.value = await settingRepository.getSettings();
  } catch (error) {
    console.error('Failed to load settings', error);
  }
};

const toggleFaq = (id: number) => {
  if (openFaqId.value === id) {
    openFaqId.value = null;
  } else {
    openFaqId.value = id;
  }
};

watch(selectedCategory, () => {
  openFaqId.value = null;
});

onMounted(() => {
  fetchFaqs();
  fetchSettings();
});
</script>
