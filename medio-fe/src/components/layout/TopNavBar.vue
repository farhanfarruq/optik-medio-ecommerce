<script setup lang="ts">
import { useCartStore } from '../../stores/cartStore';
import { useAuthStore } from '../../stores/authStore';
import { useRouter, useRoute } from 'vue-router';
import { ref, onMounted, onUnmounted } from 'vue';

const cartStore = useCartStore();
const authStore = useAuthStore();
const router = useRouter();
const route = useRoute();

const isScrolled = ref(false);

const handleScroll = () => {
  isScrolled.value = window.scrollY > 50;
};

onMounted(() => {
  window.addEventListener('scroll', handleScroll);
  // Reset scroll state on route change (page might start at top)
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
          class="relative overflow-hidden rounded-xl group-hover:scale-110 transition-transform duration-300 p-1"
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
          class="w-10 h-10 rounded-full flex items-center justify-center transition-all hover:scale-110 active:scale-95"
          :class="isScrolled ? 'hover:bg-stone-100' : 'hover:bg-white/15'"
        >
          <span class="material-symbols-outlined text-2xl">search</span>
        </button>

        <button
          @click="handleUserClick"
          class="w-10 h-10 rounded-full flex items-center justify-center transition-all hover:scale-110 active:scale-95"
          :class="isScrolled ? 'hover:bg-stone-100' : 'hover:bg-white/15'"
        >
          <span class="material-symbols-outlined text-2xl">person</span>
        </button>

        <button
          @click="goToCart"
          class="relative w-10 h-10 rounded-full flex items-center justify-center transition-all hover:scale-110 active:scale-95"
          :class="isScrolled ? 'hover:bg-stone-100' : 'hover:bg-white/15'"
        >
          <span class="material-symbols-outlined text-2xl">shopping_cart</span>
          <span
            v-if="cartStore.items.length"
            class="absolute -top-1 -right-1 text-white text-[9px] w-5 h-5 flex items-center justify-center rounded-full border-2 border-white font-black shadow-lg"
            style="background: #c19a51;"
          >
            {{ cartStore.items.length }}
          </span>
        </button>
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