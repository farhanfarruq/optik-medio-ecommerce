<template>
  <div class="bg-[var(--ivory)] min-h-screen">
    <PageHero
      title="Blog & Artikel"
      subtitle="Tips kesehatan mata, panduan memilih frame, dan update layanan Optik Medio."
      :breadcrumbs="[{ label: 'Blog & Artikel' }]"
    />

    <!-- Main Content -->
    <main class="container-premium pt-8 pb-20">
      <div>
        <div class="mb-10">
          <label for="article-search" class="sr-only">Cari artikel</label>
          <div class="relative">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline-variant">search</span>
            <input
              id="article-search"
              v-model="searchQuery"
              type="search"
              placeholder="Cari artikel..."
              class="w-full border border-outline-variant/30 bg-porcelain py-4 pl-12 pr-4 text-sm text-on-surface outline-none transition-all focus:border-primary"
            />
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
          <div v-for="i in 6" :key="i" class="h-64 bg-surface-container-low animate-pulse"></div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="text-center py-20 border border-dashed border-outline-variant/30">
          <span class="material-symbols-outlined text-4xl text-error mb-4">error</span>
          <h3 class="text-lg font-bold text-on-surface">Gagal Memuat Artikel</h3>
          <p class="mt-2 text-on-surface-variant mb-6">{{ error }}</p>
          <button @click="fetchArticles()" class="px-8 py-3 bg-primary text-white font-black uppercase tracking-widest text-xs">
            Coba Lagi
          </button>
        </div>

        <!-- Empty State -->
        <div v-else-if="!articles || articles.length === 0" class="text-center py-20 border border-dashed border-outline-variant/30">
          <span class="material-symbols-outlined text-4xl text-outline-variant mb-4">article</span>
          <h3 class="text-lg font-bold text-on-surface">Belum Ada Artikel</h3>
          <p class="mt-2 text-on-surface-variant">Kami sedang menyiapkan konten terbaik untuk Anda.</p>
        </div>

        <!-- Articles Grid -->
        <div v-else class="grid grid-cols-1 gap-x-6 gap-y-10 md:grid-cols-2 lg:grid-cols-3">
          <article v-for="article in articles" :key="article.id" class="group">
            <router-link :to="`/blog/${article.slug}`" class="mb-4 block aspect-[4/3] overflow-hidden bg-surface-container">
              <img 
                v-if="article.featured_image" 
                :src="resolveImageUrl(article.featured_image)" 
                :alt="article.title" 
                class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy" decoding="async">
              <div v-else class="w-full h-full flex items-center justify-center">
                <span class="material-symbols-outlined text-4xl text-outline-variant">image</span>
              </div>
            </router-link>
            
            <div class="space-y-3">
              <div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-widest text-primary">
                <time :datetime="article.published_at">{{ formatDate(article.published_at) }}</time>
                <span v-if="Array.isArray(article.tags) && article.tags[0]" class="bg-primary/5 px-2 py-0.5">{{ article.tags[0] }}</span>
              </div>
              <router-link :to="`/blog/${article.slug}`" class="block group">
                <h3 class="text-lg font-bold leading-snug text-on-surface transition-colors group-hover:text-primary">
                  {{ article.title }}
                </h3>
                <p class="mt-3 text-sm text-on-surface-variant line-clamp-2 leading-relaxed">
                  {{ article.excerpt }}
                </p>
              </router-link>
              <div class="pt-4">
                <router-link :to="`/blog/${article.slug}`" class="text-[10px] font-black uppercase tracking-[0.2em] text-primary inline-flex items-center gap-1 group/btn">
                  Baca Selengkapnya
                  <span class="material-symbols-outlined text-sm group-hover/btn:translate-x-1 transition-transform">arrow_right_alt</span>
                </router-link>
              </div>
            </div>
          </article>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="mt-16 flex justify-center border-t border-outline-variant/10 pt-10">
          <nav class="flex items-center gap-2">
            <button 
              @click="fetchArticles(currentPage - 1)" 
              :disabled="currentPage === 1" 
              class="w-10 h-10 flex items-center justify-center border border-outline-variant/30 text-on-surface-variant hover:border-primary hover:text-primary disabled:opacity-30 disabled:hover:border-outline-variant/30 transition-all"
            >
              <span class="material-symbols-outlined text-lg">chevron_left</span>
            </button>
            
            <button 
              v-for="page in totalPages" 
              :key="page" 
              @click="fetchArticles(page)" 
              class="w-10 h-10 flex items-center justify-center text-xs font-black transition-all"
              :class="page === currentPage 
                ? 'bg-primary text-white border border-primary' 
                : 'bg-porcelain text-on-surface-variant border border-outline-variant/30 hover:border-primary'"
            >
              {{ page }}
            </button>
            
            <button 
              @click="fetchArticles(currentPage + 1)" 
              :disabled="currentPage === totalPages" 
              class="w-10 h-10 flex items-center justify-center border border-outline-variant/30 text-on-surface-variant hover:border-primary hover:text-primary disabled:opacity-30 disabled:hover:border-outline-variant/30 transition-all"
            >
              <span class="material-symbols-outlined text-lg">chevron_right</span>
            </button>
          </nav>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { logger } from '../../core/utils/logger';
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import PageHero from '../../components/layout/PageHero.vue';
import { apiClient } from '../../core/api/axiosclient';
import { resolveImageUrl } from '../../core/utils/image';
import { useSeoMeta } from '../../composables/useSeoMeta';

const articles = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const currentPage = ref(1);
const totalPages = ref(1);
const searchQuery = ref('');
let searchTimeout: ReturnType<typeof setTimeout> | undefined;

onMounted(() => {
  // SEO-2 (Phase 6): set meta tags untuk halaman daftar artikel.
  const { setSeo } = useSeoMeta();
  setSeo({
    title: 'Blog & Artikel',
    description: 'Tips kesehatan mata, panduan memilih frame, dan update layanan Optik Medio.',
    ogType: 'website',
  });

  fetchArticles();
});

onBeforeUnmount(() => {
  if (searchTimeout) clearTimeout(searchTimeout);
});

watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => fetchArticles(1), 400);
});

const fetchArticles = async (page = 1) => {
  loading.value = true;
  error.value = '';
  
  try {
    const params = new URLSearchParams({ page: String(page) });
    const search = searchQuery.value.trim();

    if (search) {
      params.set('search', search);
    }

    const response = await apiClient.get(`/articles?${params.toString()}`);
    articles.value = response.data.data || [];
    currentPage.value = response.data.current_page || 1;
    totalPages.value = response.data.last_page || 1;
  } catch (err: any) {
    logger.error('Error fetching articles:', err);
    error.value = err.response?.data?.message || 'Terjadi kesalahan saat memuat artikel.';
  } finally {
    loading.value = false;
  }
};

const formatDate = (dateString: string) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  }).format(date);
};
</script>
