<script setup lang="ts">
import { useRouter, useRoute } from 'vue-router';
import { useCartStore } from '../../stores/cartStore';
import { useAuthStore } from '../../stores/authStore';

const router = useRouter();
const route = useRoute();
const cartStore = useCartStore();
const authStore = useAuthStore();

const handleProfile = () => {
  if (authStore.isAuthenticated) router.push('/profile');
  else router.push('/login');
};

const tabs = [
  { label: 'Beranda', icon: 'home', to: '/', exact: true },
  { label: 'Booking', icon: 'calendar_today', to: '/appointment' },
  { label: 'Blog', icon: 'menu_book', to: '/blog' },
];

const isActive = (tab: { to: string; exact?: boolean }) => {
  if (tab.exact) return route.path === tab.to;
  return route.path === tab.to || route.path.startsWith(tab.to + '/');
};
</script>

<template>
  <!-- Bottom Tab Bar — mobile only, hidden on lg+ -->
  <nav
    class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-xl border-t border-stone-100"
    style="padding-bottom: env(safe-area-inset-bottom, 0px);"
  >
    <div class="flex items-stretch h-16">

      <!-- Static tabs: Beranda, Booking, Blog -->
      <router-link
        v-for="tab in tabs"
        :key="tab.to"
        :to="tab.to"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors"
        :class="isActive(tab) ? 'text-amber-700' : 'text-stone-400'"
      >
        <span
          class="material-symbols-outlined text-2xl transition-all duration-200"
          :class="isActive(tab) ? 'text-amber-700' : 'text-stone-400'"
          :style="isActive(tab) ? 'font-variation-settings: \'FILL\' 1' : ''"
        >{{ tab.icon }}</span>
        <span class="text-[10px] font-bold tracking-tight">{{ tab.label }}</span>
      </router-link>

      <!-- Profile -->
      <button
        @click="handleProfile"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors"
        :class="route.path.startsWith('/profile') || route.path.startsWith('/orders') || route.path.startsWith('/wishlist') ? 'text-amber-700' : 'text-stone-400'"
      >
        <span
          class="material-symbols-outlined text-2xl"
          :style="route.path.startsWith('/profile') ? 'font-variation-settings: \'FILL\' 1; color: #b45309;' : ''"
        >person</span>
        <span class="text-[10px] font-bold tracking-tight">Profil</span>
      </button>

      <!-- Cart with badge -->
      <router-link
        to="/cart"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors relative"
        :class="route.path === '/cart' ? 'text-amber-700' : 'text-stone-400'"
      >
        <span class="relative inline-flex">
          <span
            class="material-symbols-outlined text-2xl"
            :style="route.path === '/cart' ? 'font-variation-settings: \'FILL\' 1; color: #b45309;' : ''"
          >shopping_cart</span>
          <span
            v-if="cartStore.items.length"
            class="absolute -top-1.5 -right-1.5 text-white text-[9px] min-w-[16px] h-4 px-1 flex items-center justify-center rounded-full font-black"
            style="background: #c19a51; line-height: 1;"
          >{{ cartStore.items.length }}</span>
        </span>
        <span class="text-[10px] font-bold tracking-tight">Keranjang</span>
      </router-link>

    </div>
  </nav>
</template>
