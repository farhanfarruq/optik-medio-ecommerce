<script setup lang="ts">
import { useCartStore } from '../../stores/cartStore';
import { useAuthStore } from '../../stores/authStore';
import { useRouter } from 'vue-router';
import { ref, onMounted, onUnmounted } from 'vue';

const cartStore = useCartStore();
const authStore = useAuthStore();
const router = useRouter();

const isScrolled = ref(false);
const isSearchOpen = ref(false);
const searchQuery = ref('');
const windowWidth = ref(window.innerWidth);

const handleScroll = () => {
  isScrolled.value = window.scrollY > 50;
};

const updateWidth = () => {
  windowWidth.value = window.innerWidth;
};

const toggleSearch = () => {
  isSearchOpen.value = !isSearchOpen.value;
  if (isSearchOpen.value) {
    setTimeout(() => {
      document.getElementById('search-input')?.focus();
    }, 100);
  }
};

const executeSearch = () => {
  if (searchQuery.value.trim()) {
    router.push({ path: '/products', query: { search: searchQuery.value } });
    isSearchOpen.value = false;
    searchQuery.value = '';
  }
};

onMounted(() => {
  window.addEventListener('scroll', handleScroll);
  window.addEventListener('resize', updateWidth);
  handleScroll();
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
  window.removeEventListener('resize', updateWidth);
});

const goToCart = () => router.push('/cart');
const handleUserClick = () => {
  if (authStore.isAuthenticated) router.push('/profile');
  else router.push('/login');
};
</script>

<template>
  <nav
    :class="[
      'fixed top-0 w-full z-50 transition-all duration-500',
      isScrolled
        ? 'bg-white/95 backdrop-blur-xl shadow-lg h-20'
        : 'bg-transparent h-24'
    ]"
  >
    <div class="flex justify-between items-center max-w-[1440px] mx-auto px-8 h-full">

      <!-- Logo -->
      <router-link to="/" class="flex items-center gap-3 group">
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

      <!-- Actions -->
      <div
        class="flex items-center gap-3 md:gap-6 transition-all duration-300 h-full"
        :class="isScrolled ? 'text-stone-800' : 'text-white drop-shadow-[0_1px_4px_rgba(0,0,0,0.5)]'"
      >
        <!-- Integrated Search Bar -->
        <div 
          class="relative flex items-center h-12 transition-all duration-500 ease-out overflow-hidden"
          :class="isSearchOpen ? 'w-[200px] md:w-[350px] px-4 bg-white/10 backdrop-blur-md border-b border-amber-500/50' : 'w-10'"
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
            @blur="() => { if(!searchQuery) isSearchOpen = false }"
          />

          <button 
            v-if="isSearchOpen" 
            @click="isSearchOpen = false" 
            class="shrink-0 text-stone-400 hover:text-stone-600 p-1"
          >
            <span class="material-symbols-outlined text-sm">close</span>
          </button>
        </div>

        <!-- User & Cart (Hidden when search is wide on mobile) -->
        <div v-if="!isSearchOpen || windowWidth > 768" class="flex items-center gap-3 md:gap-6">
          <button
            @click="handleUserClick"
            class="w-10 h-10 rounded-none flex items-center justify-center transition-all hover:scale-110 active:scale-95"
            :class="isScrolled ? 'hover:bg-stone-100' : 'hover:bg-white/15'"
          >
            <span class="material-symbols-outlined text-2xl">person</span>
          </button>

          <button
            @click="goToCart"
            class="relative w-10 h-10 rounded-none flex items-center justify-center transition-all hover:scale-110 active:scale-95"
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