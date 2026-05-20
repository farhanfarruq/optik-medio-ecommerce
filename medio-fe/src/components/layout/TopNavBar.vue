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
const isAuthPage = computed(() => ['Login', 'Register'].includes(route.name as string));
const isLightNav = computed(() => isScrolled.value || isAuthPage.value);
const navTextStyle = computed(() => ({ color: isLightNav.value ? 'var(--ink)' : '#fff' }));
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
  const bannerH = cartStore.isPromoBannerVisible ? 36 : 0;
  const navH = 72;
  return `${bannerH + navH}px`;
});

const mobileNavItems = [
  { to: '/', label: 'Beranda', icon: 'home' },
  { to: '/products', label: 'Produk', icon: 'storefront' },
  { to: '/face-shape-quiz', label: 'Quiz Bentuk Wajah', icon: 'quiz' },
  { to: '/virtual-try-on', label: 'Coba Virtual', icon: 'face_retouching_natural' },
  { to: '/compare', label: 'Bandingkan Produk', icon: 'compare' },
  { to: '/appointment', label: 'Booking Konsultasi', icon: 'calendar_today' },
  { to: '/blog', label: 'Blog & Artikel', icon: 'menu_book' },
];
</script>

<template>
  <!-- Mobile drawer backdrop — starts below navbar -->
  <Transition name="fade">
    <div
      v-if="isMobileMenuOpen"
      class="md:hidden fixed left-0 right-0 bottom-0 z-40 bg-ink/45 backdrop-blur-sm"
      :style="{ top: drawerTop }"
      @click="closeMobileMenu"
    />
  </Transition>

  <!-- Mobile slide-in drawer — from right, below navbar -->
  <Transition name="slide-left">
    <div
      v-if="isMobileMenuOpen"
      class="md:hidden fixed right-0 bottom-0 z-50 w-[280px] bg-porcelain shadow-soft flex flex-col border-l border-mist"
      :style="{ top: drawerTop }"
    >
      <!-- Nav items -->
      <nav class="flex-1 overflow-y-auto py-2">
        <router-link
          v-for="item in mobileNavItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-semibold transition-colors active:bg-gold/10"
          :class="route.path === item.to || route.path.startsWith(item.to + '/')
            ? 'text-ink bg-gold/15 border border-gold/25'
            : 'text-graphite hover:bg-ivory hover:text-ink border border-transparent'"
        >
          <span
            class="material-symbols-outlined text-xl shrink-0"
            :class="route.path === item.to || route.path.startsWith(item.to + '/') ? 'text-gold' : 'text-taupe'"
          >{{ item.icon }}</span>
          {{ item.label }}
          <span
            v-if="route.path === item.to || route.path.startsWith(item.to + '/')"
            class="ml-auto w-1.5 h-1.5 rounded-full bg-gold"
          />
        </router-link>
      </nav>
    </div>
  </Transition>

  <nav
    :style="{
      top: cartStore.isPromoBannerVisible ? '36px' : '0',
      transition: 'top 0.4s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.5s ease, height 0.5s ease, box-shadow 0.5s ease'
    }"
    :class="[
      'fixed w-full z-50',
      isLightNav
        ? 'bg-porcelain/70 backdrop-blur-2xl shadow-card h-[72px] border-b border-mist/80'
        : 'bg-graphite/50 backdrop-blur-2xl shadow-card h-[72px] border-b border-white/10'
    ]"
  >
    <div class="container-premium flex justify-between items-center h-full gap-4">

      <!-- Logo -->
      <router-link to="/" class="flex items-center gap-2.5 md:gap-3 group">
        <div
          class="relative overflow-hidden rounded-lg group-hover:scale-[1.03] transition-transform duration-300 p-0.5 border"
          :class="isLightNav ? 'bg-white border-mist shadow-card' : 'bg-white/10 border-white/20 backdrop-blur-sm'"
        >
          <img src="/gambar/medio.jpeg" alt="Optik Medio" class="h-8 w-auto object-contain" />
        </div>
        <span
          class="font-headline text-xl font-semibold tracking-normal transition-all duration-300"
          :class="isLightNav
            ? 'text-ink'
            : 'text-white drop-shadow-[0_2px_8px_rgba(0,0,0,0.6)]'"
          
        >
          Optik Medio
        </span>
      </router-link>
      
      <!-- Center Links (desktop only) -->
      <div class="hidden md:flex items-center justify-center gap-1 flex-grow">
        <router-link
          to="/"
          class="rounded-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] transition-colors"
          :class="isLightNav ? 'hover:bg-ivory' : 'hover:bg-white/10'"
          :style="navTextStyle"
        >
          Beranda
        </router-link>
        <router-link
          to="/products"
          class="rounded-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] transition-colors"
          :class="isLightNav ? 'hover:bg-ivory' : 'hover:bg-white/10'"
          :style="navTextStyle"
        >
          Produk
        </router-link>
        <router-link
          to="/face-shape-quiz"
          class="rounded-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] transition-colors"
          :class="isLightNav ? 'hover:bg-ivory' : 'hover:bg-white/10'"
          :style="navTextStyle"
        >
          Quiz
        </router-link>
        <router-link
          to="/virtual-try-on"
          class="rounded-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] transition-colors"
          :class="isLightNav ? 'hover:bg-ivory' : 'hover:bg-white/10'"
          :style="navTextStyle"
        >
          Coba Virtual
        </router-link>
        <router-link
          to="/compare"
          class="rounded-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.12em] transition-colors"
          :class="isLightNav ? 'hover:bg-ivory' : 'hover:bg-white/10'"
          :style="navTextStyle"
        >
          Bandingkan
        </router-link>
      </div>

      <!-- Actions -->
      <div
        class="flex items-center gap-2 transition-all duration-300 h-full"
        :style="navTextStyle"
      >
        <!-- Integrated Search Bar -->
        <div 
          class="relative flex items-center h-10 rounded-full border transition-all duration-300 ease-out"
          :class="isSearchOpen ? 'w-[190px] sm:w-[260px] md:w-[360px] px-2 bg-porcelain border-gold/40 shadow-card text-ink' : 'w-11 border-transparent'"
          :style="isSearchOpen && isScrolled ? 'background: rgba(0,0,0,0.03);' : ''"
        >
          <button
            @click="isSearchOpen ? executeSearch() : toggleSearch()"
            class="shrink-0 w-9 h-9 flex items-center justify-center rounded-full transition-colors active:scale-95"
            :class="isSearchOpen ? 'text-gold hover:bg-gold/10' : isLightNav ? 'hover:bg-ivory' : 'hover:bg-white/10'"
            :style="!isSearchOpen ? navTextStyle : undefined"
          >
            <span class="material-symbols-outlined text-2xl">search</span>
          </button>
          
          <input
            v-if="isSearchOpen"
            id="search-input"
            v-model="searchQuery"
            type="text"
            placeholder="Cari frame, lensa, kategori"
            class="w-full min-w-0 bg-transparent border-none text-sm font-medium focus:ring-0 outline-none placeholder:text-graphite/55 px-2 text-ink"
            @keyup.enter="executeSearch"
            @blur="handleSearchBlur"
          />

          <button 
            v-if="isSearchOpen" 
            @click="isSearchOpen = false" 
            class="shrink-0 text-graphite/60 hover:text-ink hover:bg-ivory rounded-full p-1"
          >
            <span class="material-symbols-outlined text-sm">close</span>
          </button>

          <div
            v-if="showSuggestionPanel"
            class="absolute left-0 right-0 top-[calc(100%+12px)] bg-porcelain text-ink border border-mist shadow-soft p-3 max-h-[420px] overflow-y-auto rounded-lg"
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
                  class="w-full flex items-center gap-3 p-2 text-left hover:bg-ivory transition-colors rounded-lg"
                >
                  <img :src="resolveImageUrl(product)" :alt="product.name" class="w-11 h-11 object-contain bg-ivory border border-mist shrink-0 rounded-md" />
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
                  <span class="material-symbols-outlined text-base text-gold">category</span>
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
                <span class="material-symbols-outlined text-base text-gold">history</span>
                {{ query }}
              </button>
            </div>
          </div>
        </div>

        <!-- Desktop only: Appointment, Blog, User, Cart -->
        <div v-if="!isSearchOpen || windowWidth > 768" class="flex items-center gap-2 md:gap-4">
          <router-link
            to="/appointment"
            class="hidden md:flex w-9 h-9 rounded-full items-center justify-center transition-colors active:scale-95"
            :class="isLightNav ? 'hover:bg-ivory' : 'hover:bg-white/10'"
            :style="navTextStyle"
            title="Booking Konsultasi"
          >
            <span class="material-symbols-outlined text-2xl">calendar_today</span>
          </router-link>
          <router-link
            to="/blog"
            class="hidden md:flex w-9 h-9 rounded-full items-center justify-center transition-colors active:scale-95"
            :class="isLightNav ? 'hover:bg-ivory' : 'hover:bg-white/10'"
            :style="navTextStyle"
            title="Blog & Artikel"
          >
            <span class="material-symbols-outlined text-2xl">menu_book</span>
          </router-link>
          <button
            @click="handleUserClick"
            class="hidden md:flex w-9 h-9 rounded-full items-center justify-center transition-colors active:scale-95"
            :class="isLightNav ? 'hover:bg-ivory' : 'hover:bg-white/10'"
            :style="navTextStyle"
          >
            <span class="material-symbols-outlined text-2xl">person</span>
          </button>
          <button
            @click="goToCart"
            class="hidden md:flex relative w-9 h-9 rounded-full items-center justify-center transition-colors active:scale-95"
            :class="isLightNav ? 'hover:bg-ivory' : 'hover:bg-white/10'"
            :style="navTextStyle"
          >
            <span class="material-symbols-outlined text-2xl">shopping_cart</span>
            <span
              v-if="cartStore.items.length"
              class="absolute -top-1 -right-1 text-ink bg-gold text-[10px] min-w-5 h-5 px-1 flex items-center justify-center rounded-full border-2 border-porcelain font-bold"
            >
              {{ cartStore.items.length }}
            </span>
          </button>

          <!-- Hamburger — mobile only, di kanan search -->
          <button
            @click="toggleMobileMenu"
            class="md:hidden w-10 h-10 flex items-center justify-center rounded-full transition-colors active:scale-95 shrink-0"
            :style="navTextStyle"
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
        ? 'height: 1px; background: rgba(184,138,68,0.28); opacity: 1;'
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
