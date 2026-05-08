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
              <router-link to="/blog" class="hover:text-white transition-colors">Blog</router-link>
              <span class="material-symbols-outlined text-sm">chevron_right</span>
              <span class="text-white truncate max-w-[200px]">{{ article?.title || 'Detail Artikel' }}</span>
            </nav>
            <router-link to="/blog" class="flex items-center gap-2 text-sm font-bold group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
              <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
              Kembali ke Blog
            </router-link>
          </div>
          <!-- Page Title -->
          <h1 class="text-3xl md:text-4xl font-black tracking-tight text-white line-clamp-1" style="font-family: 'Outfit', sans-serif;">{{ article?.title || 'Baca Artikel' }}</h1>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-[1000px] mx-auto w-full px-6 pb-20 relative z-20">
      <!-- Loading State -->
      <div v-if="loading" class="bg-white p-12 border border-outline-variant/15 shadow-sm space-y-8">
        <div class="h-8 bg-surface-container-low w-3/4 animate-pulse"></div>
        <div class="h-64 bg-surface-container-low w-full animate-pulse"></div>
        <div class="space-y-4">
          <div v-for="i in 10" :key="i" class="h-4 bg-surface-container-low w-full animate-pulse"></div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-white p-20 text-center border border-outline-variant/15 shadow-sm">
        <span class="material-symbols-outlined text-5xl text-error mb-4">error</span>
        <h2 class="text-2xl font-black text-on-surface mb-2">Artikel Tidak Ditemukan</h2>
        <p class="text-on-surface-variant mb-8">{{ error }}</p>
        <router-link to="/blog" class="px-8 py-4 bg-primary text-white font-black uppercase tracking-widest text-xs">
          Kembali ke Blog
        </router-link>
      </div>

      <!-- Article Content -->
      <article v-else-if="article" class="bg-white border border-outline-variant/15 shadow-sm overflow-hidden">
        <!-- Featured Image -->
        <div v-if="article.featured_image" class="w-full aspect-[21/9] overflow-hidden bg-surface-container">
          <img :src="resolveImageUrl(article.featured_image)" :alt="article.title" class="w-full h-full object-cover">
        </div>

        <div class="p-8 md:p-16">
          <!-- Article Header -->
          <header class="mb-12 border-b border-outline-variant/10 pb-10">
            <div v-if="Array.isArray(article.tags) && article.tags.length > 0" class="flex flex-wrap gap-2 mb-6">
              <span v-for="tag in article.tags" :key="tag" class="text-[10px] font-black uppercase tracking-widest bg-primary/5 text-primary px-3 py-1.5">
                {{ tag }}
              </span>
            </div>
            
            <h2 class="text-3xl md:text-5xl font-black text-on-surface leading-tight mb-8" style="font-family: 'Outfit', sans-serif;">
              {{ article.title }}
            </h2>

            <div class="flex flex-wrap items-center gap-x-8 gap-y-4 text-xs font-bold text-on-surface-variant uppercase tracking-widest">
              <div v-if="article.author" class="flex items-center gap-2">
                <span class="material-symbols-outlined text-lg text-primary">person</span>
                {{ article.author.name }}
              </div>
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-lg text-primary">calendar_today</span>
                <time :datetime="article.published_at">{{ formatDate(article.published_at) }}</time>
              </div>
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-lg text-primary">visibility</span>
                {{ article.views }} Views
              </div>
            </div>
          </header>

          <!-- Article Body -->
          <div class="prose prose-lg prose-primary max-w-none mb-16" v-html="article.content"></div>

          <!-- Social Share (Static) -->
          <div class="pt-10 border-t border-outline-variant/10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <span class="text-xs font-black uppercase tracking-[0.2em] text-on-surface">Bagikan Artikel</span>
            <div class="flex gap-4">
              <button class="w-10 h-10 flex items-center justify-center border border-outline-variant/20 rounded-full hover:bg-primary hover:text-white transition-all">
                <i class="fa-brands fa-facebook-f"></i>
              </button>
              <button class="w-10 h-10 flex items-center justify-center border border-outline-variant/20 rounded-full hover:bg-primary hover:text-white transition-all">
                <i class="fa-brands fa-x-twitter"></i>
              </button>
              <button class="w-10 h-10 flex items-center justify-center border border-outline-variant/20 rounded-full hover:bg-primary hover:text-white transition-all">
                <i class="fa-brands fa-whatsapp"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Related Articles -->
        <div v-if="relatedArticles.length > 0" class="bg-surface-container-lowest p-8 md:p-16 border-t border-outline-variant/10">
          <h3 class="text-2xl font-black text-on-surface mb-10" style="font-family: 'Outfit', sans-serif;">Artikel Terkait</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <router-link v-for="related in relatedArticles" :key="related.id" :to="`/blog/${related.slug}`" class="group">
              <div class="aspect-[16/10] overflow-hidden bg-surface-container mb-4">
                <img 
                  v-if="related.featured_image" 
                  :src="resolveImageUrl(related.featured_image)" 
                  :alt="related.title" 
                  class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                >
              </div>
              <h4 class="text-base font-bold text-on-surface group-hover:text-primary transition-colors line-clamp-2 mb-2 leading-snug">
                {{ related.title }}
              </h4>
              <time class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant">{{ formatDate(related.published_at) }}</time>
            </router-link>
          </div>
        </div>
      </article>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { apiClient } from '../../core/api/axiosclient';
import { resolveImageUrl } from '../../core/utils/image';

const route = useRoute();
const article = ref<any>(null);
const relatedArticles = ref<any[]>([]);
const loading = ref(true);
const error = ref('');

const fetchArticle = async (slug: string) => {
  loading.value = true;
  error.value = '';
  
  try {
    const response = await apiClient.get(`/articles/${slug}`);
    article.value = response.data.article;
    relatedArticles.value = response.data.related;
    
    if (article.value) {
      document.title = `${article.value.meta_title || article.value.title} | Optik Medio`;
    }
  } catch (err: any) {
    console.error('Error fetching article:', err);
    error.value = err.response?.data?.message || 'Gagal memuat artikel.';
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

watch(() => route.params.slug, (newSlug) => {
  if (newSlug) {
    fetchArticle(newSlug as string);
    window.scrollTo(0, 0);
  }
});

onMounted(() => {
  if (route.params.slug) {
    fetchArticle(route.params.slug as string);
  }
});
</script>

<style>
/* Styling for Rich Editor content */
.prose h1, .prose h2, .prose h3 {
  @apply text-on-surface font-black mt-12 mb-6 leading-tight;
  font-family: 'Outfit', sans-serif;
}
.prose p {
  @apply mb-8 text-on-surface-variant leading-relaxed text-lg;
}
.prose img {
  @apply rounded-none border border-outline-variant/10 my-12 shadow-sm;
}
.prose ul, .prose ol {
  @apply mb-8 space-y-3 pl-6;
}
.prose li {
  @apply text-on-surface-variant text-lg;
}
.prose blockquote {
  @apply border-l-4 border-primary pl-8 italic text-on-surface my-12 bg-surface-container-lowest py-8 pr-8;
}
</style>
