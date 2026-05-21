<script setup lang="ts">
import { computed } from 'vue';
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

const isAccountActive = computed(() =>
  route.path.startsWith('/profile')
  || route.path.startsWith('/orders')
  || route.path.startsWith('/wishlist')
  || route.path.startsWith('/addresses')
  || route.path.startsWith('/prescriptions')
  || route.path.startsWith('/affiliate')
  || route.path.startsWith('/warranty')
);
const isCartActive = computed(() => route.path === '/cart');
const cartCount = computed(() => cartStore.items.length);
</script>

<template>
  <nav class="bottom-tab-bar md:hidden" aria-label="Navigasi bawah">
    <div class="bottom-tab-bar__inner">
      <router-link
        v-for="tab in tabs"
        :key="tab.to"
        :to="tab.to"
        class="bottom-tab-bar__item"
        :class="{ 'bottom-tab-bar__item--active': isActive(tab) }"
        :aria-current="isActive(tab) ? 'page' : undefined"
      >
        <span
          class="material-symbols-outlined bottom-tab-bar__icon"
          :style="isActive(tab) ? { fontVariationSettings: '\'FILL\' 1' } : undefined"
        >{{ tab.icon }}</span>
        <span class="bottom-tab-bar__label">{{ tab.label }}</span>
        <span v-if="isActive(tab)" class="bottom-tab-bar__indicator" aria-hidden="true"></span>
      </router-link>

      <button
        type="button"
        @click="handleProfile"
        class="bottom-tab-bar__item"
        :class="{ 'bottom-tab-bar__item--active': isAccountActive }"
        :aria-current="isAccountActive ? 'page' : undefined"
        aria-label="Akun"
      >
        <span
          class="material-symbols-outlined bottom-tab-bar__icon"
          :style="isAccountActive ? { fontVariationSettings: '\'FILL\' 1' } : undefined"
        >person</span>
        <span class="bottom-tab-bar__label">Akun</span>
        <span v-if="isAccountActive" class="bottom-tab-bar__indicator" aria-hidden="true"></span>
      </button>

      <router-link
        to="/cart"
        class="bottom-tab-bar__item"
        :class="{ 'bottom-tab-bar__item--active': isCartActive }"
        :aria-current="isCartActive ? 'page' : undefined"
        aria-label="Keranjang"
      >
        <span class="bottom-tab-bar__icon-wrap">
          <span
            class="material-symbols-outlined bottom-tab-bar__icon"
            :style="isCartActive ? { fontVariationSettings: '\'FILL\' 1' } : undefined"
          >shopping_cart</span>
          <span
            v-if="cartCount"
            class="bottom-tab-bar__badge"
            aria-label="`${cartCount} item di keranjang`"
          >{{ cartCount > 99 ? '99+' : cartCount }}</span>
        </span>
        <span class="bottom-tab-bar__label">Keranjang</span>
        <span v-if="isCartActive" class="bottom-tab-bar__indicator" aria-hidden="true"></span>
      </router-link>
    </div>
  </nav>
</template>

<style scoped>
.bottom-tab-bar {
  position: fixed;
  inset: auto 0 0 0;
  z-index: 50;
  background: rgba(252, 250, 246, 0.96);
  border-top: 1px solid var(--mist);
  backdrop-filter: blur(14px);
  box-shadow: 0 -10px 30px rgba(21, 18, 14, 0.08);
  padding-bottom: env(safe-area-inset-bottom, 0px);
}

.bottom-tab-bar__inner {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  align-items: stretch;
  height: 64px;
}

.bottom-tab-bar__item {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  padding: 6px 4px 8px;
  min-width: 0;
  min-height: var(--tap-target);
  color: rgba(43, 41, 38, 0.58);
  transition: color var(--motion-base) var(--easing-standard);
}

.bottom-tab-bar__item:hover { color: var(--ink); }
.bottom-tab-bar__item--active { color: var(--ink); }

.bottom-tab-bar__icon-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.bottom-tab-bar__icon {
  font-size: 24px;
  color: rgba(43, 41, 38, 0.48);
  transition: color var(--motion-base) var(--easing-standard);
}

.bottom-tab-bar__item--active .bottom-tab-bar__icon { color: var(--gold); }

.bottom-tab-bar__label {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.01em;
}

.bottom-tab-bar__indicator {
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 28px;
  height: 2px;
  border-radius: 0 0 999px 999px;
  background: var(--gold);
}

.bottom-tab-bar__badge {
  position: absolute;
  top: -6px;
  right: -10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 999px;
  background: var(--gold);
  color: var(--ink);
  font-size: 9px;
  font-weight: 700;
  line-height: 1;
  border: 1.5px solid var(--porcelain);
}
</style>
