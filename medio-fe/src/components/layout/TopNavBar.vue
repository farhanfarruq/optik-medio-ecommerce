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

const handleScroll = () => {
  isScrolled.value = window.scrollY > 50;
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
  handleScroll();
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
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
        class="flex items-center gap-6 transition-all duration-300"
        :class="isScrolled ? 'text-stone-800' : 'text-white drop-shadow-[0_1px_4px_rgba(0,0,0,0.5)]'"
      >
        <button
          @click="toggleSearch"
          class="w-10 h-10 rounded-none flex items-center justify-center transition-all hover:scale-110 active:scale-95"
          :class="isScrolled ? 'hover:bg-stone-100' : 'hover:bg-white/15'"
        >
          <span class="material-symbols-outlined text-2xl">search</span>
        </button>

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

    <!-- Search Overlay -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-[-20px]"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-[-20px]"
      >
        <div v-if="isSearchOpen" class="fixed inset-0 z-[100] flex items-start justify-center pt-32 px-6">
          <!-- Backdrop -->
          <div class="absolute inset-0 bg-[#0a0805]/90 backdrop-blur-md" @click="toggleSearch"></div>
          
          <!-- Content -->
          <div class="relative w-full max-w-3xl">
            <div class="flex items-center gap-4 border-b-2 border-[#c19a51] pb-4">
              <span class="material-symbols-outlined text-4xl text-[#c19a51]">search</span>
              <input
                id="search-input"
                v-model="searchQuery"
                type="text"
                placeholder="Cari produk atau merk..."
                class="w-full bg-transparent border-none text-white text-3xl font-bold focus:ring-0 outline-none placeholder:text-stone-600"
                @keyup.enter="executeSearch"
              />
              <button @click="toggleSearch" class="text-stone-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-3xl">close</span>
              </button>
            </div>
            <p class="text-stone-500 text-sm mt-4 tracking-widest font-bold uppercase">Tekan Enter untuk mencari</p>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Bottom border that appears when solid -->
    <div
      class="absolute bottom-0 left-0 right-0 transition-all duration-500"
      :style="isScrolled
        ? 'height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.3), transparent); opacity: 1;'
        : 'height: 0; opacity: 0;'"
    ></div>
  </nav>
</template>