<template>
  <div class="min-h-screen bg-white pt-24 pb-16">
    <!-- Loading State -->
    <div v-if="loading" class="flex justify-center items-center py-32">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-amber-600"></div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="max-w-3xl mx-auto px-4 py-20 text-center">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 mb-4">
        <svg class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      </div>
      <h2 class="text-2xl font-bold text-gray-900 mb-2">Artikel Tidak Ditemukan</h2>
      <p class="text-gray-500 mb-8">{{ error }}</p>
      <router-link to="/blog" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700">
        Kembali ke Blog
      </router-link>
    </div>

    <!-- Article Content -->
    <article v-else-if="article" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Breadcrumb -->
      <nav class="flex text-sm text-gray-500 mb-8" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
          <li class="inline-flex items-center">
            <router-link to="/" class="hover:text-gray-900">Home</router-link>
          </li>
          <li>
            <div class="flex items-center">
              <svg class="w-4 h-4 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
              <router-link to="/blog" class="hover:text-gray-900 ml-1 md:ml-2">Blog</router-link>
            </div>
          </li>
          <li aria-current="page">
            <div class="flex items-center">
              <svg class="w-4 h-4 mx-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
              <span class="ml-1 text-gray-400 md:ml-2 truncate max-w-[200px]">{{ article.title }}</span>
            </div>
          </li>
        </ol>
      </nav>

      <!-- Article Header -->
      <header class="text-center mb-10">
        <div v-if="Array.isArray(article.tags) && article.tags.length > 0" class="flex flex-wrap justify-center gap-2 mb-4">
          <span v-for="tag in article.tags" :key="tag" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-amber-50 text-amber-700">
            {{ tag }}
          </span>
        </div>
        <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 leading-tight mb-4">
          {{ article.title }}
        </h1>
        <div class="flex items-center justify-center text-sm text-gray-500 space-x-4">
          <span v-if="article.author" class="flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            {{ article.author.name }}
          </span>
          <span class="flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <time :datetime="article.published_at">{{ formatDate(article.published_at) }}</time>
          </span>
          <span class="flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            {{ article.views }} views
          </span>
        </div>
      </header>

      <!-- Featured Image -->
      <div v-if="article.featured_image" class="mb-12 rounded-2xl overflow-hidden shadow-lg">
        <img :src="resolveImageUrl(article.featured_image)" :alt="article.title" class="w-full h-auto max-h-[500px] object-cover">
      </div>

      <!-- Article Body (HTML from RichEditor) -->
      <div class="prose prose-lg prose-primary max-w-none mb-16 prose-img:rounded-xl" v-html="article.content"></div>

      <!-- Social Share (Static UI) -->
      <div class="border-t border-b border-gray-200 py-6 mb-16 flex items-center justify-between">
        <span class="text-sm font-medium text-gray-900">Bagikan artikel ini:</span>
        <div class="flex space-x-4">
          <button class="text-gray-400 hover:text-amber-600 transition-colors">
            <span class="sr-only">Facebook</span>
            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" /></svg>
          </button>
          <button class="text-gray-400 hover:text-blue-400 transition-colors">
            <span class="sr-only">Twitter</span>
            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84" /></svg>
          </button>
          <button class="text-gray-400 hover:text-green-500 transition-colors">
            <span class="sr-only">WhatsApp</span>
            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.274.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.045.072.045.419-.1.824zm-3.423-14.416c-6.627 0-12 5.373-12 12s5.373 12 12 12 12-5.373 12-12-5.373-12-12-12zm.029 18.88c-1.161 0-2.305-.292-3.318-.844l-3.677.964.984-3.595c-.607-1.052-.927-2.246-.926-3.468.001-5.824 4.74-10.563 10.567-10.564 5.823 0 10.564 4.745 10.564 10.566s-4.739 10.565-10.564 10.565z" /></svg>
          </button>
        </div>
      </div>

      <!-- Related Articles -->
      <div v-if="relatedArticles.length > 0">
        <h3 class="text-2xl font-bold text-gray-900 mb-8">Artikel Terkait</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <router-link v-for="related in relatedArticles" :key="related.id" :to="`/blog/${related.slug}`" class="group">
            <div class="aspect-w-16 aspect-h-9 rounded-xl overflow-hidden bg-gray-100 mb-4">
              <img v-if="related.featured_image" :src="resolveImageUrl(related.featured_image)" :alt="related.title" class="object-cover group-hover:scale-105 transition-transform duration-300">
            </div>
            <h4 class="text-lg font-bold text-gray-900 group-hover:text-amber-600 line-clamp-2 mb-2">{{ related.title }}</h4>
            <time class="text-sm text-gray-500">{{ formatDate(related.published_at) }}</time>
          </router-link>
        </div>
      </div>
    </article>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { apiClient } from '../../core/api/axiosclient';
import { resolveImageUrl } from '../../core/utils/image';

const route = useRoute();
// const { resolveImageUrl } = useUrlResolver();

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
    
    // Update SEO dynamically based on loaded article
    if (article.value) {
      document.title = `${article.value.meta_title || article.value.title} | Optik Medio`;
    }
    
    // Note: for meta tags we could manipulate the DOM, but it's not strictly necessary for simple SPA without SSR
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
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
};

// Re-fetch when route parameter changes
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
/* Styling for Rich Editor content (Tailwind Typography) */
.prose {
  @apply text-gray-700 leading-relaxed;
}
.prose h2, .prose h3, .prose h4 {
  @apply text-gray-900 font-bold mt-8 mb-4;
}
.prose p {
  @apply mb-6;
}
.prose a {
  @apply text-amber-600 underline hover:text-amber-800;
}
.prose blockquote {
  @apply border-l-4 border-amber-500 pl-4 italic text-gray-600 my-6 bg-gray-50 py-2 pr-4 rounded-r-lg;
}
.prose ul {
  @apply list-disc list-inside mb-6 space-y-2;
}
.prose ol {
  @apply list-decimal list-inside mb-6 space-y-2;
}
.prose img {
  @apply w-full rounded-xl shadow-md my-8;
}
</style>
