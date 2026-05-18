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
  { label: 'Produk', icon: 'storefront', to: '/products' },
  { label: 'Booking', icon: 'calendar_today', to: '/appointment' },
];

const isActive = (tab: { to: string; exact?: boolean }) => {
  if (tab.exact) return route.path === tab.to;
  return route.path === tab.to || route.path.startsWith(tab.to + '/');
};

const isAccountActive = () => route.path.startsWith('/profile') || route.path.startsWith('/orders') || route.path.startsWith('/wishlist');
</script>

<template>
  <nav
    class="md:hidden fixed bottom-0 left-0 right-0 z-50 border-t border-mist bg-porcelain/95 shadow-soft backdrop-blur-xl"
    style="padding-bottom: env(safe-area-inset-bottom, 0px);"
    aria-label="Navigasi bawah"
  >
    <div class="grid h-16 grid-cols-5 items-stretch">
      <router-link
        v-for="tab in tabs"
        :key="tab.to"
        :to="tab.to"
        class="flex min-w-0 flex-col items-center justify-center gap-0.5 px-1 transition-colors"
        :class="isActive(tab) ? 'text-ink' : 'text-graphite/55'"
      >
        <span
          class="material-symbols-outlined text-2xl transition-colors"
          :class="isActive(tab) ? 'text-gold' : 'text-graphite/45'"
          :style="isActive(tab) ? { fontVariationSettings: '\'FILL\' 1' } : undefined"
        >{{ tab.icon }}</span>
        <span class="max-w-full truncate text-[10px] font-semibold">{{ tab.label }}</span>
      </router-link>

      <button
        @click="handleProfile"
        class="flex min-w-0 flex-col items-center justify-center gap-0.5 px-1 transition-colors"
        :class="isAccountActive() ? 'text-ink' : 'text-graphite/55'"
      >
        <span
          class="material-symbols-outlined text-2xl transition-colors"
          :class="isAccountActive() ? 'text-gold' : 'text-graphite/45'"
          :style="isAccountActive() ? { fontVariationSettings: '\'FILL\' 1' } : undefined"
        >person</span>
        <span class="max-w-full truncate text-[10px] font-semibold">Profil</span>
      </button>

      <router-link
        to="/cart"
        class="relative flex min-w-0 flex-col items-center justify-center gap-0.5 px-1 transition-colors"
        :class="route.path === '/cart' ? 'text-ink' : 'text-graphite/55'"
      >
        <span class="relative inline-flex">
          <span
            class="material-symbols-outlined text-2xl transition-colors"
            :class="route.path === '/cart' ? 'text-gold' : 'text-graphite/45'"
            :style="route.path === '/cart' ? { fontVariationSettings: '\'FILL\' 1' } : undefined"
          >shopping_cart</span>
          <span
            v-if="cartStore.items.length"
            class="absolute -right-2 -top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-gold px-1 text-[9px] font-bold leading-none text-ink"
          >{{ cartStore.items.length }}</span>
        </span>
        <span class="max-w-full truncate text-[10px] font-semibold">Keranjang</span>
      </router-link>
    </div>
  </nav>
</template>
