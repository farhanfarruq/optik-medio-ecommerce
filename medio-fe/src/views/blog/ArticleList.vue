<template>
  <div class="min-h-screen bg-gray-50 pt-24 pb-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="text-center mb-12">
        <h1 class="text-4xl font-extrabold text-gray-900 sm:text-5xl">
          Blog & Artikel
        </h1>
        <p class="mt-4 text-xl text-gray-500 max-w-2xl mx-auto">
          Temukan tips kesehatan mata, panduan memilih kacamata, dan update terbaru dari Optik Medio.
        </p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-amber-600"></div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="text-center py-20">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 mb-4">
          <svg class="w-8 h-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900">Gagal Memuat Artikel</h3>
        <p class="mt-2 text-gray-500">{{ error }}</p>
        <button @click="fetchArticles()" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700">
          Coba Lagi
        </button>
      </div>

      <!-- Empty State -->
      <div v-else-if="!articles || articles.length === 0" class="text-center py-20">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
          <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
          </svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900">Belum Ada Artikel</h3>
        <p class="mt-2 text-gray-500">Kami sedang menyiapkan konten terbaik untuk Anda.</p>
      </div>

      <!-- Articles Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <article v-for="article in articles" :key="article.id" class="flex flex-col bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-300">
          <router-link :to="`/blog/${article.slug}`" class="flex-shrink-0">
            <img v-if="article.featured_image" :src="resolveImageUrl(article.featured_image)" :alt="article.title" class="h-56 w-full object-cover">
            <div v-else class="h-56 w-full bg-gray-200 flex items-center justify-center">
              <svg class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
          </router-link>
          
          <div class="flex-1 p-6 flex flex-col justify-between">
            <div class="flex-1">
              <div class="flex items-center justify-between mb-3 text-sm text-gray-500">
                <time :datetime="article.published_at">{{ formatDate(article.published_at) }}</time>
                <div v-if="Array.isArray(article.tags) && article.tags.length > 0" class="flex gap-2">
                  <span v-for="tag in article.tags.slice(0, 2)" :key="tag" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700">
                    {{ tag }}
                  </span>
                </div>
              </div>
              <router-link :to="`/blog/${article.slug}`" class="block mt-2">
                <h3 class="text-xl font-bold text-gray-900 hover:text-amber-600 transition-colors line-clamp-2">
                  {{ article.title }}
                </h3>
                <p class="mt-3 text-base text-gray-500 line-clamp-3">
                  {{ article.excerpt }}
                </p>
              </router-link>
            </div>
            <div class="mt-6 flex items-center">
              <router-link :to="`/blog/${article.slug}`" class="text-amber-600 hover:text-amber-700 font-medium text-sm inline-flex items-center">
                Baca selengkapnya
                <svg class="ml-1 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </router-link>
            </div>
          </div>
        </article>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="mt-12 flex justify-center">
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
          <button @click="fetchArticles(currentPage - 1)" :disabled="currentPage === 1" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50">
            <span class="sr-only">Previous</span>
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
          </button>
          
          <button v-for="page in totalPages" :key="page" @click="fetchArticles(page)" :class="[page === currentPage ? 'z-10 bg-amber-50 border-amber-500 text-amber-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50', 'relative inline-flex items-center px-4 py-2 border text-sm font-medium']">
            {{ page }}
          </button>
          
          <button @click="fetchArticles(currentPage + 1)" :disabled="currentPage === totalPages" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50">
            <span class="sr-only">Next</span>
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
          </button>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { apiClient } from '../../core/api/axiosclient';
import { resolveImageUrl } from '../../core/utils/image';

const articles = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const currentPage = ref(1);
const totalPages = ref(1);

onMounted(() => {
  document.title = 'Blog & Artikel Kesehatan Mata | Optik Medio';
  fetchArticles();
});

const fetchArticles = async (page = 1) => {
  loading.value = true;
  error.value = '';
  
  try {
    const response = await apiClient.get(`/articles?page=${page}`);
    articles.value = response.data.data || [];
    currentPage.value = response.data.current_page || 1;
    totalPages.value = response.data.last_page || 1;
  } catch (err: any) {
    console.error('Error fetching articles:', err);
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
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
};

</script>
