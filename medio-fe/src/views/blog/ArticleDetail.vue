<template>
  <div class="bg-[var(--ivory)] min-h-screen">
    <PageHero
      :title="article?.title || 'Baca Artikel'"
      :breadcrumbs="[
        { label: 'Blog', to: '/blog' },
        { label: article?.title || 'Detail Artikel' }
      ]"
      back-to="/blog"
      back-label="Kembali ke Blog"
      title-class="text-4xl font-black tracking-normal text-white line-clamp-2"
    />

    <!-- Main Content -->
    <main class="container-premium max-w-5xl py-12 pb-20">
      <!-- Loading State -->
      <div v-if="loading" class="premium-card p-12 space-y-8">
        <div class="h-8 bg-surface-container-low w-3/4 animate-pulse"></div>
        <div class="h-64 bg-surface-container-low w-full animate-pulse"></div>
        <div class="space-y-4">
          <div v-for="i in 10" :key="i" class="h-4 bg-surface-container-low w-full animate-pulse"></div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="premium-card p-20 text-center">
        <span class="material-symbols-outlined text-5xl text-error mb-4">error</span>
        <h2 class="text-2xl font-black text-on-surface mb-2">Artikel Tidak Ditemukan</h2>
        <p class="text-on-surface-variant mb-8">{{ error }}</p>
        <router-link to="/blog" class="px-8 py-4 bg-primary text-white font-black uppercase tracking-widest text-xs">
          Kembali ke Blog
        </router-link>
      </div>

      <!-- Article Content -->
      <article v-else-if="article" class="premium-card overflow-hidden">
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
            
            <h2 class="text-3xl md:text-5xl font-black text-on-surface leading-tight mb-8" style="font-family: 'Cormorant Garamond', serif;">
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
          <div class="prose prose-lg max-w-none mb-16" v-html="sanitizedContent"></div>

          <div class="pt-10 border-t border-outline-variant/10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <span class="text-xs font-black uppercase tracking-[0.2em] text-on-surface">Bagikan Artikel</span>
            <div class="flex gap-4">
              <a
                :href="facebookShareUrl"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Bagikan artikel ke Facebook"
                class="w-10 h-10 flex items-center justify-center border border-outline-variant/20 rounded-full hover:bg-primary hover:text-white transition-all"
              >
                <span class="material-symbols-outlined text-lg">share</span>
              </a>
              <a
                :href="xShareUrl"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Bagikan artikel ke X"
                class="w-10 h-10 flex items-center justify-center border border-outline-variant/20 rounded-full hover:bg-primary hover:text-white transition-all"
              >
                <span class="text-xs font-black">X</span>
              </a>
              <a
                :href="whatsappShareUrl"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Bagikan artikel ke WhatsApp"
                class="w-10 h-10 flex items-center justify-center border border-outline-variant/20 rounded-full hover:bg-primary hover:text-white transition-all"
              >
                <span class="material-symbols-outlined text-lg">chat</span>
              </a>
            </div>
          </div>
        </div>

        <!-- Related Articles -->
        <div v-if="relatedArticles.length > 0" class="bg-surface-container-low p-8 md:p-16 border-t border-outline-variant/10">
          <h3 class="text-2xl font-black text-on-surface mb-10" style="font-family: 'Cormorant Garamond', serif;">Artikel Terkait</h3>
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
import { computed, ref, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import PageHero from '../../components/layout/PageHero.vue';
import { apiClient } from '../../core/api/axiosclient';
import { resolveImageUrl } from '../../core/utils/image';
import { sanitizeHtml } from '../../core/utils/sanitize';

const route = useRoute();
const article = ref<any>(null);
const relatedArticles = ref<any[]>([]);
const loading = ref(true);
const error = ref('');

const sanitizedContent = computed(() => sanitizeHtml(article.value?.content));

const currentUrl = computed(() => {
  if (typeof window === 'undefined') return '';
  return window.location.href;
});

const shareText = computed(() => article.value?.title || 'Artikel Optik Medio');
const facebookShareUrl = computed(() => `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(currentUrl.value)}`);
const xShareUrl = computed(() => `https://twitter.com/intent/tweet?url=${encodeURIComponent(currentUrl.value)}&text=${encodeURIComponent(shareText.value)}`);
const whatsappShareUrl = computed(() => `https://api.whatsapp.com/send?text=${encodeURIComponent(`${shareText.value} ${currentUrl.value}`)}`);

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
    window.scrollTo(0, 0);
    fetchArticle(route.params.slug as string);
  }
});
</script>

<style>
/* Styling for Rich Editor content */
.prose h1, .prose h2, .prose h3 {
  @apply text-on-surface font-black mt-12 mb-6 leading-tight;
  font-family: 'Cormorant Garamond', serif;
}
.prose p {
  @apply mb-8 text-on-surface-variant leading-relaxed text-lg;
}
.prose img {
  @apply rounded-lg border border-outline-variant/10 my-12 shadow-card;
}
.prose ul, .prose ol {
  @apply mb-8 space-y-3 pl-6;
}
.prose li {
  @apply text-on-surface-variant text-lg;
}
.prose blockquote {
  @apply border-l-4 border-primary pl-8 italic text-on-surface my-12 bg-surface-container-low py-8 pr-8;
}
</style>
