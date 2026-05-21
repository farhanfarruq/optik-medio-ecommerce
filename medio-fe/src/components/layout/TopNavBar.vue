<script setup lang="ts">
import { logger } from '../../core/utils/logger';
import { useCartStore } from '../../stores/cartStore';
import { useAuthStore } from '../../stores/authStore';
import { useRouter, useRoute } from 'vue-router';
import { computed, ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
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

const isSearchOpen = ref(false);
const isMobileMenuOpen = ref(false);
const searchQuery = ref('');
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const isMobileViewport = computed(() => windowWidth.value < 768);

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
  // Auto-close mobile-only UI saat resize ke ≥1024px (drawer hanya muncul di <lg)
  if (windowWidth.value >= 1024 && isMobileMenuOpen.value) {
    isMobileMenuOpen.value = false;
    document.body.style.overflow = '';
  }
  // Tutup search overlay mobile saat resize ke ≥768px (overlay hanya untuk <md)
  if (windowWidth.value >= 768 && isSearchOpen.value) {
    document.body.style.overflow = '';
  }
};

const focusSearchInput = () => {
  nextTick(() => {
    setTimeout(() => {
      const id = isMobileViewport.value ? 'search-input-mobile' : 'search-input';
      document.getElementById(id)?.focus();
    }, 80);
  });
};

const openSearch = () => {
  isSearchOpen.value = true;
  isMobileMenuOpen.value = false;
  if (isMobileViewport.value) {
    document.body.style.overflow = 'hidden';
  }
  focusSearchInput();
};

const closeSearch = () => {
  isSearchOpen.value = false;
  document.body.style.overflow = '';
};

const toggleMobileMenu = () => {
  isMobileMenuOpen.value = !isMobileMenuOpen.value;
  if (isMobileMenuOpen.value) {
    closeSearch();
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
};

const closeMobileMenu = () => {
  isMobileMenuOpen.value = false;
  document.body.style.overflow = '';
};

const executeSearch = () => {
  const query = searchQuery.value.trim();
  if (query) {
    saveRecentSearch(query);
    router.push({ path: '/products', query: { search: query } });
    closeSearch();
    searchQuery.value = '';
    searchSuggestions.value = { products: [], categories: [] };
  }
};

const handleSearchBlur = () => {
  // Delay supaya klik suggestion sempat terdaftar (mousedown.prevent menjaga,
  // tapi keep delay untuk safety di Safari mobile)
  window.setTimeout(() => {
    if (!searchQuery.value && !isMobileViewport.value) closeSearch();
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

const clearRecentSearches = () => {
  recentSearches.value = [];
  window.localStorage.removeItem('medio_recent_searches');
};

const selectProduct = (slug: string) => {
  closeSearch();
  searchQuery.value = '';
  router.push(`/products/${slug}`);
};

const selectCategory = (slug: string) => {
  closeSearch();
  searchQuery.value = '';
  router.push(`/products/category/${slug}`);
};

const selectRecentSearch = (query: string) => {
  searchQuery.value = query;
  executeSearch();
};

// Close panels on route change
watch(() => route.fullPath, () => {
  closeMobileMenu();
  closeSearch();
});

// Esc keyboard listener (overlay/menu close)
const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    if (isSearchOpen.value) closeSearch();
    else if (isMobileMenuOpen.value) closeMobileMenu();
  }
};

onMounted(() => {
  window.addEventListener('scroll', handleScroll, { passive: true });
  window.addEventListener('resize', updateWidth);
  window.addEventListener('keydown', handleKeydown);
  loadRecentSearches();
  handleScroll();
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
  window.removeEventListener('resize', updateWidth);
  window.removeEventListener('keydown', handleKeydown);
  if (searchTimer) clearTimeout(searchTimer);
  document.body.style.overflow = '';
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
      logger.warn('Failed to fetch search suggestions', error);
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

// Top offset untuk drawer/overlay — sinkron dengan promo banner + tinggi nav (72px)
const drawerTop = computed(() => {
  const bannerH = cartStore.isPromoBannerVisible ? 36 : 0;
  return `${bannerH + 72}px`;
});

const desktopNavItems = [
  { to: '/', label: 'Beranda', exact: true },
  { to: '/products', label: 'Produk' },
  { to: '/face-shape-quiz', label: 'Quiz' },
  { to: '/virtual-try-on', label: 'Coba Virtual' },
  { to: '/compare', label: 'Bandingkan' },
];

const mobileNavItems = [
  { to: '/', label: 'Beranda', icon: 'home', exact: true },
  { to: '/products', label: 'Produk', icon: 'storefront' },
  { to: '/face-shape-quiz', label: 'Quiz Bentuk Wajah', icon: 'quiz' },
  { to: '/virtual-try-on', label: 'Coba Virtual', icon: 'face_retouching_natural' },
  { to: '/compare', label: 'Bandingkan Produk', icon: 'compare' },
  { to: '/appointment', label: 'Booking Konsultasi', icon: 'calendar_today' },
  { to: '/blog', label: 'Blog & Artikel', icon: 'menu_book' },
];

const isNavActive = (item: { to: string; exact?: boolean }) => {
  if (item.exact) return route.path === item.to;
  return route.path === item.to || route.path.startsWith(item.to + '/');
};

const cartCount = computed(() => cartStore.items.length);
const cartCountLabel = computed(() => cartCount.value > 99 ? '99+' : String(cartCount.value));
</script>

<template>
  <!-- Mobile/tablet drawer backdrop (<lg only — desktop has inline nav links) -->
  <Transition name="fade">
    <div
      v-if="isMobileMenuOpen"
      class="tn-tablet-down fixed left-0 right-0 bottom-0 z-40 bg-ink/55 backdrop-blur-sm"
      :style="{ top: drawerTop }"
      role="button"
      tabindex="-1"
      aria-label="Tutup menu navigasi"
      @click="closeMobileMenu"
      @keydown.enter="closeMobileMenu"
      @keydown.space.prevent="closeMobileMenu"
    />
  </Transition>

  <!-- Slide-in drawer (right) — mobile + tablet -->
  <Transition name="slide-left">
    <aside
      v-if="isMobileMenuOpen"
      class="tn-tablet-down fixed right-0 bottom-0 z-50 flex w-[320px] max-w-[88vw] flex-col border-l border-mist bg-porcelain shadow-soft"
      :style="{ top: drawerTop }"
      role="dialog"
      aria-label="Menu navigasi"
      aria-modal="true"
    >
      <header class="flex items-center justify-between border-b border-mist px-5 py-4">
        <p class="eyebrow-mute">Navigasi</p>
        <button
          type="button"
          class="btn-icon-ghost"
          aria-label="Tutup menu"
          @click="closeMobileMenu"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </header>

      <nav class="flex-1 overflow-y-auto px-3 py-3" aria-label="Menu utama">
        <router-link
          v-for="item in mobileNavItems"
          :key="item.to"
          :to="item.to"
          class="drawer-link"
          :class="{ 'drawer-link--active': isNavActive(item) }"
          :aria-current="isNavActive(item) ? 'page' : undefined"
        >
          <span
            class="material-symbols-outlined drawer-link__icon"
            :style="isNavActive(item) ? { fontVariationSettings: '\'FILL\' 1' } : undefined"
          >{{ item.icon }}</span>
          <span class="drawer-link__label">{{ item.label }}</span>
          <span v-if="isNavActive(item)" class="drawer-link__indicator" aria-hidden="true"></span>
        </router-link>

        <div class="my-3 divider-rule"></div>

        <router-link
          v-if="!authStore.isAuthenticated"
          to="/login"
          class="drawer-link"
        >
          <span class="material-symbols-outlined drawer-link__icon">login</span>
          <span class="drawer-link__label">Masuk / Daftar</span>
        </router-link>
        <router-link
          v-else
          to="/profile"
          class="drawer-link"
        >
          <span class="material-symbols-outlined drawer-link__icon">account_circle</span>
          <span class="drawer-link__label">Akun Saya</span>
        </router-link>
      </nav>
    </aside>
  </Transition>

  <!-- Mobile full-screen search overlay -->
  <Transition name="search-overlay">
    <section
      v-if="isSearchOpen && isMobileViewport"
      class="tn-search-overlay fixed inset-0 z-[60] flex flex-col bg-ivory"
      role="dialog"
      aria-label="Pencarian produk"
      aria-modal="true"
    >
      <div class="flex items-center gap-2 border-b border-mist bg-porcelain/96 px-3 py-3 backdrop-blur-xl"
           :style="{ paddingTop: 'calc(0.75rem + env(safe-area-inset-top, 0px))' }">
        <button
          type="button"
          class="btn-icon-ghost"
          aria-label="Tutup pencarian"
          @click="closeSearch"
        >
          <span class="material-symbols-outlined">arrow_back</span>
        </button>
        <div class="flex flex-1 items-center gap-2 rounded-full border border-mist bg-porcelain px-4 py-2 focus-within:border-gold/55 focus-within:shadow-card">
          <span class="material-symbols-outlined text-base text-graphite/60" aria-hidden="true">search</span>
          <input
            id="search-input-mobile"
            v-model="searchQuery"
            type="search"
            inputmode="search"
            enterkeyhint="search"
            placeholder="Cari frame, lensa, kategori"
            class="min-w-0 flex-1 bg-transparent text-sm font-medium text-ink placeholder:text-graphite/55 focus:outline-none"
            autocomplete="off"
            @keyup.enter="executeSearch"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="text-graphite/55 hover:text-ink"
            aria-label="Hapus teks"
            @click="searchQuery = ''"
          >
            <span class="material-symbols-outlined text-base">close</span>
          </button>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto px-3 pb-8 pt-3">
        <div v-if="isSuggestionLoading" class="py-10 text-center text-xs font-semibold text-graphite/55">
          Mencari...
        </div>

        <div v-else-if="searchQuery.trim().length >= 2" class="space-y-5">
          <div v-if="searchSuggestions.products.length > 0">
            <p class="suggestion-heading">Produk</p>
            <button
              v-for="product in searchSuggestions.products"
              :key="product.id"
              type="button"
              class="suggestion-product"
              @mousedown.prevent="selectProduct(product.slug)"
            >
              <img
                :src="resolveImageUrl(product)"
                :alt="product.name"
                class="suggestion-product__image"
                loading="lazy"
                decoding="async"
              />
              <span class="suggestion-product__meta">
                <span class="suggestion-product__name">{{ product.name }}</span>
                <span class="suggestion-product__sub">{{ product.brand || 'Optik Medio' }} · Rp {{ product.price.toLocaleString('id-ID') }}</span>
              </span>
            </button>
          </div>

          <div v-if="searchSuggestions.categories.length > 0">
            <p class="suggestion-heading">Kategori</p>
            <button
              v-for="category in searchSuggestions.categories"
              :key="category.id"
              type="button"
              class="suggestion-row"
              @mousedown.prevent="selectCategory(category.slug)"
            >
              <span class="material-symbols-outlined text-base text-gold" aria-hidden="true">category</span>
              {{ category.name }}
            </button>
          </div>

          <div
            v-if="searchSuggestions.products.length === 0 && searchSuggestions.categories.length === 0"
            class="empty-state"
          >
            <span class="material-symbols-outlined text-3xl text-graphite/40" aria-hidden="true">search_off</span>
            <p class="text-sm font-semibold text-ink">Tidak ada hasil</p>
            <p class="text-xs text-graphite/65">Coba kata kunci lain seperti "frame metal" atau "lensa progresif".</p>
          </div>
        </div>

        <div v-else>
          <div v-if="recentSearches.length > 0">
            <div class="flex items-center justify-between px-1 pb-2">
              <p class="suggestion-heading mb-0">Terakhir Dicari</p>
              <button
                type="button"
                class="text-[11px] font-semibold uppercase tracking-[0.14em] text-graphite/55 hover:text-ink"
                @click="clearRecentSearches"
              >
                Hapus
              </button>
            </div>
            <button
              v-for="query in recentSearches"
              :key="query"
              type="button"
              class="suggestion-row"
              @mousedown.prevent="selectRecentSearch(query)"
            >
              <span class="material-symbols-outlined text-base text-gold" aria-hidden="true">history</span>
              {{ query }}
            </button>
          </div>

          <div class="mt-6">
            <p class="suggestion-heading">Saran Cepat</p>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="suggestion in ['Frame Metal', 'Frame Acetate', 'Lensa Progresif', 'Sunglasses', 'Anak']"
                :key="suggestion"
                type="button"
                class="chip"
                @click="searchQuery = suggestion"
              >
                {{ suggestion }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>
  </Transition>

  <!-- Top navigation bar -->
  <nav
    class="top-nav"
    :class="{ 'top-nav--light': isLightNav, 'top-nav--has-banner': cartStore.isPromoBannerVisible }"
    role="navigation"
    aria-label="Navigasi utama"
  >
    <div class="container-premium top-nav__inner">
      <!-- Logo -->
      <router-link to="/" class="top-nav__logo group" aria-label="Optik Medio — Beranda">
        <span
          class="top-nav__logo-mark"
          :class="isLightNav ? 'top-nav__logo-mark--light' : 'top-nav__logo-mark--dark'"
        >
          <img
            src="/gambar/medio.jpeg"
            alt=""
            class="h-8 w-auto object-contain"
            loading="lazy"
            decoding="async"
          />
        </span>
        <span class="top-nav__logo-text" :class="isLightNav ? 'text-ink' : 'top-nav__logo-text--dark'">
          Optik Medio
        </span>
      </router-link>

      <!-- Center nav links (desktop only) -->
      <ul class="top-nav__links">
        <li v-for="item in desktopNavItems" :key="item.to">
          <router-link
            :to="item.to"
            class="top-nav__link"
            :class="[
              isLightNav ? 'top-nav__link--light' : 'top-nav__link--dark',
              { 'top-nav__link--active': isNavActive(item) }
            ]"
            :aria-current="isNavActive(item) ? 'page' : undefined"
          >
            {{ item.label }}
          </router-link>
        </li>
      </ul>

      <!-- Right actions -->
      <div class="top-nav__actions">
        <!-- Desktop/tablet expanding search bar (≥768px) -->
        <div
          class="top-nav__search tn-md-up"
          :class="{
            'top-nav__search--open': isSearchOpen,
            'top-nav__search--light': isLightNav
          }"
        >
          <button
            type="button"
            class="top-nav__icon-btn"
            :class="isLightNav ? 'top-nav__icon-btn--light' : 'top-nav__icon-btn--dark'"
            :aria-label="isSearchOpen ? 'Cari sekarang' : 'Buka pencarian'"
            @click="isSearchOpen ? executeSearch() : openSearch()"
          >
            <span class="material-symbols-outlined">search</span>
          </button>
          <input
            v-show="isSearchOpen"
            id="search-input"
            v-model="searchQuery"
            type="search"
            inputmode="search"
            enterkeyhint="search"
            placeholder="Cari frame, lensa, kategori"
            class="top-nav__search-input"
            autocomplete="off"
            @keyup.enter="executeSearch"
            @blur="handleSearchBlur"
          />
          <button
            v-if="isSearchOpen"
            type="button"
            class="top-nav__search-close"
            aria-label="Tutup pencarian"
            @click="closeSearch"
          >
            <span class="material-symbols-outlined text-base">close</span>
          </button>

          <!-- Desktop suggestion panel -->
          <div
            v-if="showSuggestionPanel && !isMobileViewport"
            class="top-nav__suggest"
          >
            <div v-if="isSuggestionLoading" class="py-4 text-center text-xs font-semibold text-graphite/60">
              Mencari...
            </div>

            <div v-else-if="searchQuery.trim().length >= 2" class="space-y-3">
              <div v-if="searchSuggestions.products.length > 0">
                <p class="suggestion-heading">Produk</p>
                <button
                  v-for="product in searchSuggestions.products"
                  :key="product.id"
                  type="button"
                  class="suggestion-product"
                  @mousedown.prevent="selectProduct(product.slug)"
                >
                  <img
                    :src="resolveImageUrl(product)"
                    :alt="product.name"
                    class="suggestion-product__image"
                    loading="lazy"
                    decoding="async"
                  />
                  <span class="suggestion-product__meta">
                    <span class="suggestion-product__name">{{ product.name }}</span>
                    <span class="suggestion-product__sub">{{ product.brand || 'Optik Medio' }} · Rp {{ product.price.toLocaleString('id-ID') }}</span>
                  </span>
                </button>
              </div>

              <div v-if="searchSuggestions.categories.length > 0">
                <p class="suggestion-heading">Kategori</p>
                <button
                  v-for="category in searchSuggestions.categories"
                  :key="category.id"
                  type="button"
                  class="suggestion-row"
                  @mousedown.prevent="selectCategory(category.slug)"
                >
                  <span class="material-symbols-outlined text-base text-gold" aria-hidden="true">category</span>
                  {{ category.name }}
                </button>
              </div>

              <div
                v-if="searchSuggestions.products.length === 0 && searchSuggestions.categories.length === 0"
                class="py-6 text-center text-xs font-semibold text-graphite/60"
              >
                Tidak ada saran ditemukan.
              </div>
            </div>

            <div v-else-if="recentSearches.length > 0">
              <p class="suggestion-heading">Terakhir Dicari</p>
              <button
                v-for="query in recentSearches"
                :key="query"
                type="button"
                class="suggestion-row"
                @mousedown.prevent="selectRecentSearch(query)"
              >
                <span class="material-symbols-outlined text-base text-gold" aria-hidden="true">history</span>
                {{ query }}
              </button>
            </div>
          </div>
        </div>

        <!-- Mobile-only search trigger (<768px) -->
        <button
          type="button"
          class="top-nav__icon-btn tn-mobile-only"
          :class="isLightNav ? 'top-nav__icon-btn--light' : 'top-nav__icon-btn--dark'"
          aria-label="Buka pencarian"
          @click="openSearch"
        >
          <span class="material-symbols-outlined">search</span>
        </button>

        <!-- Tablet+desktop quick icons (≥768px) -->
        <router-link
          to="/appointment"
          class="top-nav__icon-btn tn-md-up"
          :class="isLightNav ? 'top-nav__icon-btn--light' : 'top-nav__icon-btn--dark'"
          aria-label="Booking konsultasi"
          title="Booking Konsultasi"
        >
          <span class="material-symbols-outlined">calendar_today</span>
        </router-link>
        <router-link
          to="/blog"
          class="top-nav__icon-btn tn-md-up"
          :class="isLightNav ? 'top-nav__icon-btn--light' : 'top-nav__icon-btn--dark'"
          aria-label="Blog dan artikel"
          title="Blog & Artikel"
        >
          <span class="material-symbols-outlined">menu_book</span>
        </router-link>
        <button
          type="button"
          class="top-nav__icon-btn tn-md-up"
          :class="isLightNav ? 'top-nav__icon-btn--light' : 'top-nav__icon-btn--dark'"
          :aria-label="authStore.isAuthenticated ? 'Akun saya' : 'Masuk atau daftar'"
          @click="handleUserClick"
        >
          <span class="material-symbols-outlined">person</span>
        </button>

        <!-- Cart (≥768px only — mobile uses BottomTabBar) -->
        <button
          type="button"
          class="top-nav__icon-btn top-nav__cart tn-md-up"
          :class="isLightNav ? 'top-nav__icon-btn--light' : 'top-nav__icon-btn--dark'"
          aria-label="Keranjang belanja"
          @click="goToCart"
        >
          <span class="material-symbols-outlined">shopping_cart</span>
          <span
            v-if="cartCount"
            class="top-nav__cart-badge"
            :aria-label="`${cartCount} item di keranjang`"
          >{{ cartCountLabel }}</span>
        </button>

        <!-- Hamburger (<1024px — desktop has inline center nav) -->
        <button
          type="button"
          class="top-nav__icon-btn tn-tablet-down"
          :class="isLightNav ? 'top-nav__icon-btn--light' : 'top-nav__icon-btn--dark'"
          aria-label="Buka menu navigasi"
          :aria-expanded="isMobileMenuOpen"
          @click="toggleMobileMenu"
        >
          <span class="material-symbols-outlined">{{ isMobileMenuOpen ? 'close' : 'menu' }}</span>
        </button>
      </div>
    </div>

    <span class="top-nav__rule" :class="{ 'top-nav__rule--visible': isScrolled }" aria-hidden="true"></span>
  </nav>
</template>

<style scoped>
.top-nav {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 50;
  /* Tetap 72px supaya konsisten dengan DefaultLayout `--header-height` contract. */
  height: 72px;
  background: rgba(43, 41, 38, 0.50);
  backdrop-filter: blur(18px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.10);
  box-shadow: var(--shadow-card);
  transition:
    top var(--motion-slow) var(--easing-standard),
    background-color var(--motion-slow) var(--easing-standard),
    border-color var(--motion-slow) var(--easing-standard);
}

/* ─── Responsive visibility utilities (scoped, BEAT specificity Tailwind) ─── */
/* Default: hide. Each utility shows itself only at its breakpoint range. */
.tn-mobile-only,
.tn-md-up,
.tn-tablet-down,
.tn-desktop-only { display: none !important; }

/* Mobile: <768px → tampilkan mobile-only + tablet-down */
@media (max-width: 767.98px) {
  .tn-mobile-only { display: inline-flex !important; }
  .tn-tablet-down { display: flex !important; }
}

/* Tablet: 768–1023.98px → tampilkan md-up + tablet-down */
@media (min-width: 768px) and (max-width: 1023.98px) {
  .tn-md-up { display: inline-flex !important; }
  .tn-tablet-down { display: flex !important; }
}

/* Desktop: ≥1024px → tampilkan md-up + desktop-only */
@media (min-width: 1024px) {
  .tn-md-up { display: inline-flex !important; }
  .tn-desktop-only { display: inline-flex !important; }
}

/* Special: search bar uses flex (not inline-flex) when shown */
@media (min-width: 768px) {
  .top-nav__search.tn-md-up { display: flex !important; }
}

.top-nav--has-banner { top: 36px; }

.top-nav--light {
  background: rgba(252, 250, 246, 0.78);
  border-bottom-color: rgba(231, 225, 216, 0.85);
}

.top-nav__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 100%;
  gap: 12px;
}

/* Logo */
.top-nav__logo {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
  /* No transform on hover supaya tidak geser layout */
}

.top-nav__logo-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 40px;
  width: 40px;
  padding: 4px;
  border-radius: 8px;
  border: 1px solid;
  transition: border-color var(--motion-base);
}

.top-nav__logo-mark--light {
  background: #fff;
  border-color: var(--mist);
  box-shadow: var(--shadow-card);
}

.top-nav__logo-mark--dark {
  background: rgba(255, 255, 255, 0.10);
  border-color: rgba(255, 255, 255, 0.22);
  backdrop-filter: blur(8px);
}

.top-nav__logo-text {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-size: 20px;
  font-weight: 600;
  letter-spacing: 0;
  transition: color var(--motion-base);
}

.top-nav__logo-text--dark {
  color: #fff;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.55);
}

/* Center links — desktop only */
.top-nav__links {
  display: none;
  align-items: center;
  gap: 4px;
  flex: 1 1 auto;
  justify-content: center;
}

@media (min-width: 1024px) {
  .top-nav__links { display: flex; }
}

.top-nav__link {
  position: relative;
  display: inline-flex;
  align-items: center;
  padding: 8px 14px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  transition: background-color var(--motion-base), color var(--motion-base);
}

.top-nav__link--light { color: var(--ink); }
.top-nav__link--light:hover { background: var(--ivory); }

.top-nav__link--dark { color: #fff; }
.top-nav__link--dark:hover { background: rgba(255, 255, 255, 0.10); }

.top-nav__link--active::after {
  content: '';
  position: absolute;
  bottom: 2px;
  left: 50%;
  transform: translateX(-50%);
  width: 18px;
  height: 2px;
  border-radius: 999px;
  background: var(--gold);
}

/* Actions */
.top-nav__actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

@media (min-width: 768px) {
  .top-nav__actions { gap: 6px; }
}

.top-nav__icon-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 999px;
  flex-shrink: 0;
  transition: background-color var(--motion-base), color var(--motion-base), transform var(--motion-base);
}

.top-nav__icon-btn:active { transform: scale(0.94); }

.top-nav__icon-btn .material-symbols-outlined { font-size: 22px; }

.top-nav__icon-btn--light { color: var(--ink); }
.top-nav__icon-btn--light:hover { background: var(--ivory); }

.top-nav__icon-btn--dark { color: #fff; }
.top-nav__icon-btn--dark:hover { background: rgba(255, 255, 255, 0.10); }

.top-nav__cart-badge {
  position: absolute;
  top: -2px;
  right: -2px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--gold);
  color: var(--ink);
  font-size: 10px;
  font-weight: 700;
  line-height: 1;
  border: 2px solid var(--porcelain);
}

.top-nav--light .top-nav__cart-badge { border-color: var(--porcelain); }
.top-nav:not(.top-nav--light) .top-nav__cart-badge { border-color: var(--graphite); }

/* Desktop/tablet search bar (expanding) — default hidden, shown via .tn-md-up media queries */
.top-nav__search {
  position: relative;
  display: none;
  align-items: center;
  height: 40px;
  border-radius: 999px;
  border: 1px solid transparent;
  transition: width var(--motion-slow) var(--easing-standard),
              background-color var(--motion-base),
              border-color var(--motion-base);
  width: 40px;
  overflow: visible;
}

.top-nav__search--open {
  width: 320px;
  background: var(--porcelain);
  border-color: rgba(184, 138, 68, 0.45);
  box-shadow: var(--shadow-card);
  padding-right: 4px;
}

@media (min-width: 1280px) {
  .top-nav__search--open { width: 380px; }
}

.top-nav__search-input {
  flex: 1 1 auto;
  min-width: 0;
  padding: 0 8px;
  background: transparent;
  border: 0;
  font-size: 13px;
  font-weight: 500;
  color: var(--ink);
  outline: none;
}

.top-nav__search-input::placeholder { color: rgba(43, 41, 38, 0.48); }

.top-nav__search-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  border-radius: 999px;
  color: rgba(43, 41, 38, 0.62);
}

.top-nav__search-close:hover {
  background: var(--ivory);
  color: var(--ink);
}

/* Desktop suggestion panel */
.top-nav__suggest {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 12px);
  max-height: 480px;
  overflow-y: auto;
  padding: 12px;
  border-radius: 12px;
  background: var(--porcelain);
  border: 1px solid var(--mist);
  box-shadow: var(--shadow-soft);
}

/* Suggestion sub-elements (shared mobile + desktop) */
.suggestion-heading {
  margin: 0 4px 8px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  color: rgba(43, 41, 38, 0.50);
}

.suggestion-row {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  text-align: left;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
  transition: background-color var(--motion-base);
  min-height: var(--tap-target);
}

.suggestion-row:hover { background: var(--ivory); }

.suggestion-product {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 10px;
  border-radius: 8px;
  text-align: left;
  transition: background-color var(--motion-base);
  min-height: var(--tap-target);
}

.suggestion-product:hover { background: var(--ivory); }

.suggestion-product__image {
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  object-fit: contain;
  background: var(--ivory);
  border: 1px solid var(--mist);
  border-radius: 6px;
}

.suggestion-product__meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.suggestion-product__name {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.suggestion-product__sub {
  margin-top: 2px;
  font-size: 11px;
  color: rgba(43, 41, 38, 0.62);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Drawer link */
.drawer-link {
  position: relative;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  margin-bottom: 4px;
  font-size: 14px;
  font-weight: 600;
  color: var(--graphite);
  border: 1px solid transparent;
  min-height: 48px;
  transition: background-color var(--motion-base), color var(--motion-base), border-color var(--motion-base);
}

.drawer-link:hover {
  background: var(--ivory);
  color: var(--ink);
}

.drawer-link__icon {
  font-size: 22px;
  flex-shrink: 0;
  color: var(--taupe);
  transition: color var(--motion-base);
}

.drawer-link__label {
  flex: 1 1 auto;
  min-width: 0;
}

.drawer-link--active {
  background: rgba(184, 138, 68, 0.12);
  border-color: rgba(184, 138, 68, 0.28);
  color: var(--ink);
}

.drawer-link--active .drawer-link__icon { color: var(--gold); }

.drawer-link__indicator {
  width: 6px;
  height: 6px;
  border-radius: 999px;
  background: var(--gold);
  flex-shrink: 0;
}

/* Bottom rule */
.top-nav__rule {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: rgba(184, 138, 68, 0.28);
  opacity: 0;
  transition: opacity var(--motion-slow);
}

.top-nav__rule--visible { opacity: 1; }

/* Transitions */
.fade-enter-active,
.fade-leave-active { transition: opacity var(--motion-base) ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }

.slide-left-enter-active,
.slide-left-leave-active { transition: transform var(--motion-slow) var(--easing-standard); }
.slide-left-enter-from,
.slide-left-leave-to { transform: translateX(100%); }

.search-overlay-enter-active,
.search-overlay-leave-active { transition: opacity var(--motion-base) var(--easing-standard); }
.search-overlay-enter-from,
.search-overlay-leave-to { opacity: 0; }
</style>
