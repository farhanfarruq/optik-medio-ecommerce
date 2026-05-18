<script setup lang="ts">
import { useCartStore } from '../../stores/cartStore';
import { useAuthStore } from '../../stores/authStore';
import { useRouter, useRoute } from 'vue-router';
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import { productRepository, type ProductSearchSuggestions } from '../../repositories/ProductRepository';
import { resolveImageUrl } from '../../core/utils/image';
import { useAnalytics } from '../../composables/useAnalytics';

const cartStore = useCartStore();
const authStore = useAuthStore();
const router = useRouter();
const route = useRoute();

const isScrolled = ref(false);
const isSearchOpen = ref(false);
const isMobileMenuOpen = ref(false);
const searchQuery = ref('');
const windowWidth = ref(window.innerWidth);
const searchSuggestions = ref<ProductSearchSuggestions>({ products: [], categories: [] });
const recentSearches = ref<string[]>([]);
const isSuggestionLoading = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const showSuggestionPanel = computed(() =>
  isSearchOpen.value &&
  (searchQuery.value.trim().length >= 2 || recentSearches.value.length > 0)
);

const handleScroll = () => {
  isScrolled.value = window.scrollY > 50;
};

const updateWidth = () => {
  windowWidth.value = window.innerWidth;
};

const toggleSearch = () => {
  isSearchOpen.value = !isSearchOpen.value;
  if (isSearchOpen.value) {
    isMobileMenuOpen.value = false;
    setTimeout(() => {
      document.getElementById('search-input')?.focus();
    }, 100);
  }
};

const toggleMobileMenu = () => {
  isMobileMenuOpen.value = !isMobileMenuOpen.value;
  if (isMobileMenuOpen.value) isSearchOpen.value = false;
};

const closeMobileMenu = () => {
  isMobileMenuOpen.value = false;
};

const executeSearch = () => {
  const query = searchQuery.value.trim();
  if (query) {
    saveRecentSearch(query);
    router.push({ path: '/products', query: { search: query } });
    isSearchOpen.value = false;
    searchQuery.value = '';
    searchSuggestions.value = { products: [], categories: [] };
  }
};

const handleSearchBlur = () => {
  window.setTimeout(() => {
    if (!searchQuery.value) isSearchOpen.value = false;
  }, 150);
};

const saveRecentSearch = (query: string) => {
  recentSearches.value = [query, ...recentSearches.value.filter(item => item.toLowerCase() !== query.toLowerCase())].slice(0, 5);
  window.localStorage.setItem('medio_recent_searches', JSON.stringify(recentSearches.value));
};

const loadRecentSearches = () => {
  try {
    const raw = window.localStorage.getItem('medio_recent_searches');
    const parsed = raw ? JSON.parse(raw) : [];
    recentSearches.value = Array.isArray(parsed) ? parsed.slice(0, 5) : [];
  } catch {
    recentSearches.value = [];
  }
};

const selectProduct = (slug: string) => {
  isSearchOpen.value = false;
  searchQuery.value = '';
  router.push(`/products/${slug}`);
};

const selectCategory = (slug: string) => {
  isSearchOpen.value = false;
  searchQuery.value = '';
  router.push(`/products/category/${slug}`);
};

const selectRecentSearch = (query: string) => {
  searchQuery.value = query;
  executeSearch();
};

// Close mobile menu on route change
watch(() => route.fullPath, () => {
  isMobileMenuOpen.value = false;
});

onMounted(() => {
  window.addEventListener('scroll', handleScroll);
  window.addEventListener('resize', updateWidth);
  loadRecentSearches();
  handleScroll();
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
  window.removeEventListener('resize', updateWidth);
  if (searchTimer) clearTimeout(searchTimer);
});

watch(searchQuery, (query) => {
  if (searchTimer) clearTimeout(searchTimer);
  const term = query.trim();
  if (term.length < 2) {
    searchSuggestions.value = { products: [], categories: [] };
    return;
  }

  searchTimer = setTimeout(async () => {
    try {
      isSuggestionLoading.value = true;
      searchSuggestions.value = await productRepository.getSearchSuggestions(term);

      if (
        searchSuggestions.value.products.length === 0 &&
        searchSuggestions.value.categories.length === 0
      ) {
        const { trackSearchNoResult } = useAnalytics();
        trackSearchNoResult(term);
      }
    } catch (error) {
      console.warn('Failed to fetch search suggestions', error);
      searchSuggestions.value = { products: [], categories: [] };
    } finally {
      isSuggestionLoading.value = false;
    }
  }, 250);
});

const goToCart = () => router.push('/cart');
const handleUserClick = () => {
  if (authStore.isAuthenticated) router.push('/profile');
  else router.push('/login');
};

// Computed top offset for drawer — matches navbar height + optional promo banner
const drawerTop = computed(() => {
  const bannerH = cartStore.isPromoBannerVisible ? 40 : 0;
  const navH = isScrolled.value ? 80 : 96;
  return `${bannerH + navH}px`;
});

const mobileNavItems = [
  { to: '/products', label: 'Produk', icon: 'storefront' },
  { to: '/face-shape-quiz', label: 'Quiz Bentuk Wajah', icon: 'quiz' },
  { to: '/virtual-try-on', label: 'Virtual Try-On', icon: 'face_retouching_natural' },
  { to: '/compare', label: 'Bandingkan Produk', icon: 'compare' },
  { to: '/appointment', label: 'Booking Appointment', icon: 'calendar_today' },
  { to: '/blog', label: 'Blog & Artikel', icon: 'menu_book' },
];
</script>

<template>
  <!-- Mobile drawer backdrop — starts below navbar -->
  <Transition name="fade">
    <div
      v-if="isMobileMenuOpen"
      class="md:hidden fixed left-0 right-0 bottom-0 z-40 bg-black/40 backdrop-blur-sm"
      :style="{ top: drawerTop }"
      @click="closeMobileMenu"
    />
  </Transition>

  <!-- Mobile slide-in drawer — from right, below navbar -->
  <Transition name="slide-left">
    <div
      v-if="isMobileMenuOpen"
      class="md:hidden fixed right-0 bottom-0 z-50 w-[260px] bg-white shadow-2xl flex flex-col"
      :style="{ top: drawerTop }"
    >
      <!-- Nav items -->
      <nav class="flex-1 overflow-y-auto py-2">
        <router-link
          v-for="item in mobileNavItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-4 px-5 py-3.5 text-sm font-bold transition-colors active:bg-stone-100"
          :class="route.path === item.to || route.path.startsWith(item.to + '/')
            ? 'text-amber-700 bg-amber-50'
            : 'text-stone-700 hover:bg-stone-50 hover:text-stone-950'"
        >
          <span
            class="material-symbols-outlined text-xl shrink-0"
            :class="route.path === item.to || route.path.startsWith(item.to + '/') ? 'text-amber-600' : 'text-stone-400'"
          >{{ item.icon }}</span>
          {{ item.label }}
          <span
            v-if="route.path === item.to || route.path.startsWith(item.to + '/')"
            class="ml-auto w-1.5 h-1.5 rounded-full bg-amber-500"
          />
        </router-link>
      </nav>
    </div>
  </Transition>

  <nav
    :style="{
      top: cartStore.isPromoBannerVisible ? '40px' : '0',
      transition: 'top 0.4s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.5s ease, height 0.5s ease, box-shadow 0.5s ease'
    }"
    :class="[
      'fixed w-full z-50',
      isScrolled
        ? 'bg-white/95 backdrop-blur-xl shadow-lg h-20'
        : 'bg-transparent h-24'
    ]"
  >
    <div class="flex justify-between items-center max-w-[1440px] mx-auto px-4 md:px-8 h-full">

      <!-- Logo -->
      <router-link to="/" class="flex items-center gap-2.5 md:gap-3 group">
        <div
          class="relative overflow-hidden rounded-none group-hover:scale-110 transition-transform duration-300 p-1"
          :class="isScrolled ? 'bg-white shadow-md' : 'bg-white/10 backdrop-blur-sm shadow-xl'"
        >
          <img src="/gambar/medio.jpeg" alt="Optik Medio" class="h-9 w-auto object-contain" />
        </div>
        <span
          class="text-xl font-black tracking-tight transition-all duration-300"
          :class="isScrolled
            ? 'text-stone-900'
            : 'text-white drop-shadow-[0_2px_8px_rgba(0,0,0,0.6)]'"
          style="font-family: 'Outfit', sans-serif;"
        >
          Optik Medio
        </span>
      </router-link>
      
      <!-- Center Links (desktop only) -->
      <div class="hidden md:flex items-center gap-6 ml-10 flex-grow">
        <router-link
          to="/products"
          class="text-xs font-black uppercase tracking-widest transition-colors"
          :class="isScrolled ? 'text-stone-700 hover:text-stone-950' : 'text-white/85 hover:text-white'"
        >
          Produk
        </router-link>
        <router-link
          to="/face-shape-quiz"
          class="text-xs font-black uppercase tracking-widest transition-colors"
          :class="isScrolled ? 'text-stone-700 hover:text-stone-950' : 'text-white/85 hover:text-white'"
        >
          Quiz
        </router-link>
        <router-link
          to="/virtual-try-on"
          class="text-xs font-black uppercase tracking-widest transition-colors"
          :class="isScrolled ? 'text-stone-700 hover:text-stone-950' : 'text-white/85 hover:text-white'"
        >
          Try-On
        </router-link>
        <router-link
          to="/compare"
          class="text-xs font-black uppercase tracking-widest transition-colors"
          :class="isScrolled ? 'text-stone-700 hover:text-stone-950' : 'text-white/85 hover:text-white'"
        >
          Compare
        </router-link>
      </div>

      <!-- Actions -->
      <div
        class="flex items-center gap-2 md:gap-6 transition-all duration-300 h-full"
        :class="isScrolled ? 'text-stone-800' : 'text-white drop-shadow-[0_1px_4px_rgba(0,0,0,0.5)]'"
      >
        <!-- Integrated Search Bar -->
        <div 
          class="relative flex items-center h-12 transition-all duration-500 ease-out"
          :class="isSearchOpen ? 'w-[160px] md:w-[350px] px-4 bg-white/10 backdrop-blur-md border-b border-amber-500/50' : 'w-10'"
          :style="isSearchOpen && isScrolled ? 'background: rgba(0,0,0,0.03);' : ''"
        >
          <button
            @click="isSearchOpen ? executeSearch() : toggleSearch()"
            class="shrink-0 w-10 h-10 flex items-center justify-center transition-all hover:scale-110 active:scale-95"
            :class="{ 'text-amber-500': isSearchOpen }"
          >
            <span class="material-symbols-outlined text-2xl">search</span>
          </button>
          
          <input
            v-if="isSearchOpen"
            id="search-input"
            v-model="searchQuery"
            type="text"
            placeholder="Cari..."
            class="w-full bg-transparent border-none text-sm font-bold focus:ring-0 outline-none placeholder:text-stone-500 px-2"
            :class="isScrolled ? 'text-stone-900' : 'text-white'"
            @keyup.enter="executeSearch"
            @blur="handleSearchBlur"
          />

          <button 
            v-if="isSearchOpen" 
            @click="isSearchOpen = false" 
            class="shrink-0 text-stone-400 hover:text-stone-600 p-1"
          >
            <span class="material-symbols-outlined text-sm">close</span>
          </button>

          <div
            v-if="showSuggestionPanel"
            class="absolute left-0 right-0 top-[calc(100%+10px)] bg-white text-stone-900 border border-stone-100 shadow-2xl p-3 max-h-[420px] overflow-y-auto"
          >
            <div v-if="isSuggestionLoading" class="py-4 text-center text-xs font-bold text-stone-500">
              Mencari...
            </div>

            <div v-else-if="searchQuery.trim().length >= 2" class="space-y-3">
              <div v-if="searchSuggestions.products.length > 0">
                <p class="text-[10px] font-black uppercase tracking-widest text-stone-400 mb-2">Produk</p>
                <button
                  v-for="product in searchSuggestions.products"
                  :key="product.id"
                  @mousedown.prevent="selectProduct(product.slug)"
                  class="w-full flex items-center gap-3 p-2 text-left hover:bg-stone-50 transition-colors"
                >
                  <img :src="resolveImageUrl(product)" :alt="product.name" class="w-10 h-10 object-contain bg-stone-50 border border-stone-100 shrink-0" />
                  <span class="min-w-0">
                    <span class="block text-xs font-black truncate">{{ product.name }}</span>
                    <span class="block text-[11px] text-stone-500 truncate">{{ product.brand || 'Optik Medio' }} · Rp {{ product.price.toLocaleString('id-ID') }}</span>
                  </span>
                </button>
              </div>

              <div v-if="searchSuggestions.categories.length > 0">
                <p class="text-[10px] font-black uppercase tracking-widest text-stone-400 mb-2">Kategori</p>
                <button
                  v-for="category in searchSuggestions.categories"
                  :key="category.id"
                  @mousedown.prevent="selectCategory(category.slug)"
                  class="w-full flex items-center gap-2 p-2 text-left text-xs font-bold hover:bg-stone-50 transition-colors"
                >
                  <span class="material-symbols-outlined text-base" style="color: #c19a51;">category</span>
                  {{ category.name }}
                </button>
              </div>

              <div v-if="searchSuggestions.products.length === 0 && searchSuggestions.categories.length === 0" class="py-4 text-center text-xs font-bold text-stone-500">
                Tidak ada saran ditemukan.
              </div>
            </div>

            <div v-else-if="recentSearches.length > 0">
              <p class="text-[10px] font-black uppercase tracking-widest text-stone-400 mb-2">Terakhir Dicari</p>
              <button
                v-for="query in recentSearches"
                :key="query"
                @mousedown.prevent="selectRecentSearch(query)"
                class="w-full flex items-center gap-2 p-2 text-left text-xs font-bold hover:bg-stone-50 transition-colors"
              >
                <span class="material-symbols-outlined text-base" style="color: #c19a51;">history</span>
                {{ query }}
              </button>
            </div>
          </div>
        </div>

        <!-- Desktop only: Appointment, Blog, User, Cart -->
        <div v-if="!isSearchOpen || windowWidth > 768" class="flex items-center gap-2 md:gap-4">
          <router-link
            to="/appointment"
            class="hidden md:flex w-10 h-10 rounded-none items-center justify-center transition-all hover:scale-110 active:scale-95"
            :class="isScrolled ? 'hover:bg-stone-100 text-stone-800' : 'hover:bg-white/15 text-white'"
            title="Booking Appointment"
          >
            <span class="material-symbols-outlined text-2xl">calendar_today</span>
          </router-link>
          <router-link
            to="/blog"
            class="hidden md:flex w-10 h-10 rounded-none items-center justify-center transition-all hover:scale-110 active:scale-95"
            :class="isScrolled ? 'hover:bg-stone-100 text-stone-800' : 'hover:bg-white/15 text-white'"
            title="Blog & Artikel"
          >
            <span class="material-symbols-outlined text-2xl">menu_book</span>
          </router-link>
          <button
            @click="handleUserClick"
            class="hidden md:flex w-10 h-10 rounded-none items-center justify-center transition-all hover:scale-110 active:scale-95"
            :class="isScrolled ? 'hover:bg-stone-100' : 'hover:bg-white/15'"
          >
            <span class="material-symbols-outlined text-2xl">person</span>
          </button>
          <button
            @click="goToCart"
            class="hidden md:flex relative w-10 h-10 rounded-none items-center justify-center transition-all hover:scale-110 active:scale-95"
            :class="isScrolled ? 'hover:bg-stone-100' : 'hover:bg-white/15'"
          >
            <span class="material-symbols-outlined text-2xl">shopping_cart</span>
            <span
              v-if="cartStore.items.length"
              class="absolute -top-1 -right-1 text-white text-[9px] w-5 h-5 flex items-center justify-center rounded-none border-2 border-white font-black shadow-lg"
              style="background: #c19a51;"
            >
              {{ cartStore.items.length }}
            </span>
          </button>

          <!-- Hamburger — mobile only, di kanan search -->
          <button
            @click="toggleMobileMenu"
            class="md:hidden w-10 h-10 flex items-center justify-center transition-all hover:scale-110 active:scale-95 shrink-0"
            :class="isScrolled ? 'text-stone-800' : 'text-white drop-shadow-[0_1px_4px_rgba(0,0,0,0.5)]'"
            aria-label="Buka menu"
          >
            <span class="material-symbols-outlined text-2xl">menu</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Bottom border that appears when solid -->
    <div
      class="absolute bottom-0 left-0 right-0 transition-all duration-500"
      :style="isScrolled
        ? 'height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.3), transparent); opacity: 1;'
        : 'height: 0; opacity: 0;'"
    ></div>
  </nav>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Drawer slides in from the RIGHT */
.slide-left-enter-active,
.slide-left-leave-active {
  transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.slide-left-enter-from,
.slide-left-leave-to {
  transform: translateX(100%);
}
</style>
