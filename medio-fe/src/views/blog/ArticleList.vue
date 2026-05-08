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
              <span class="text-white">Blog & Artikel</span>
            </nav>
            <router-link to="/" class="flex items-center gap-2 text-sm font-bold group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
              <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
              Kembali ke Beranda
            </router-link>
          </div>
          <!-- Page Title -->
          <h1 class="text-4xl font-black tracking-tight text-white" style="font-family: 'Outfit', sans-serif;">Blog & Artikel</h1>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-[1000px] mx-auto w-full px-6 pb-20 relative z-20">
      <div class="bg-white p-8 md:p-12 border border-outline-variant/15 shadow-sm">
        <div class="mb-12">
          <p class="text-xs font-black uppercase tracking-[0.2em] mb-3 text-primary">Informasi & Edukasi</p>
          <p class="text-base leading-relaxed max-w-2xl text-on-surface-variant">
            Temukan tips kesehatan mata, panduan memilih frame kacamata, hingga update terbaru seputar layanan Optik Medio.
          </p>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div v-for="i in 4" :key="i" class="h-80 bg-surface-container-low animate-pulse"></div>
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
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-12">
          <article v-for="article in articles" :key="article.id" class="group">
            <router-link :to="`/blog/${article.slug}`" class="block overflow-hidden mb-5 aspect-[16/10] bg-surface-container">
              <img 
                v-if="article.featured_image" 
                :src="resolveImageUrl(article.featured_image)" 
                :alt="article.title" 
                class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
              >
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
                <h3 class="text-xl font-bold text-on-surface group-hover:text-primary transition-colors leading-snug">
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
                : 'bg-white text-on-surface-variant border border-outline-variant/30 hover:border-primary'"
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
import { ref, onMounted } from 'vue';
import { apiClient } from '../../core/api/axiosclient';
import { resolveImageUrl } from '../../core/utils/image';

const articles = ref<any[]>([]);
const loading = ref(true);
const error = ref('');
const currentPage = ref(1);
const totalPages = ref(1);

onMounted(() => {
  document.title = 'Blog & Artikel | Optik Medio';
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
    year: 'numeric'
  }).format(date);
};
</script>
