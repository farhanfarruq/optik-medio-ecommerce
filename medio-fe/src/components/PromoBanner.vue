<script setup lang="ts">
import { onBeforeUnmount, onMounted, computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCartStore } from '../stores/cartStore';

const cartStore = useCartStore();
const router = useRouter();
const route = useRoute();
const isScrolled = ref(false);
const isAuthPage = computed(() => ['Login', 'Register'].includes(route.name as string));
const isLightBanner = computed(() => isScrolled.value || isAuthPage.value);

const updateScrollState = () => {
  isScrolled.value = window.scrollY > 50;
};

onMounted(async () => {
  await cartStore.fetchPromos();
  updateScrollState();
  window.addEventListener('scroll', updateScrollState, { passive: true });
});

onBeforeUnmount(() => {
  window.removeEventListener('scroll', updateScrollState);
});

const activePromo = computed(() => {
  if (!cartStore.isPromoBannerVisible || !cartStore.activePromos) return null;
  return cartStore.activePromos.find((p: any) => p.is_banner_active) || null;
});

const promoLink = computed(() => {
  if (!activePromo.value) return '/products';
  return '/products?has_promo=true';
});

const handleAmbil = () => {
  if (activePromo.value) {
    cartStore.setPromo(activePromo.value.id);
    router.push(promoLink.value);
  }
};
</script>

<template>
  <Transition name="slide-down">
    <div
      v-if="activePromo"
      class="promo-banner"
      :class="{ 'promo-banner--light': isLightBanner }"
      role="region"
      aria-label="Promo aktif"
    >
      <div class="container-premium promo-banner__inner">
        <div class="promo-banner__content">
          <span class="material-symbols-outlined promo-banner__icon" aria-hidden="true">sell</span>
          <p class="promo-banner__text">
            <span class="promo-banner__name">{{ activePromo.name }}</span>
            <span class="promo-banner__sep" aria-hidden="true">·</span>
            <span class="promo-banner__desc">{{ activePromo.description }}</span>
          </p>
          <button
            type="button"
            class="promo-banner__cta"
            @click="handleAmbil"
          >
            Ambil Promo
          </button>
        </div>
        <button
          type="button"
          class="promo-banner__close"
          aria-label="Tutup banner promo"
          @click="cartStore.dismissPromoBanner()"
        >
          <span class="material-symbols-outlined" aria-hidden="true">close</span>
        </button>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.promo-banner {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  height: 36px;
  padding: 0 12px;
  border-bottom: 1px solid rgba(184, 138, 68, 0.25);
  background: rgba(21, 18, 14, 0.65);
  color: #fff;
  backdrop-filter: blur(14px);
  box-shadow: var(--shadow-card);
  transition: background-color var(--motion-slow) var(--easing-standard),
              border-color var(--motion-slow) var(--easing-standard),
              color var(--motion-slow) var(--easing-standard);
}

.promo-banner--light {
  background: rgba(252, 250, 246, 0.78);
  border-bottom-color: rgba(231, 225, 216, 0.8);
  color: var(--ink);
}

.promo-banner__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 0;
}

.promo-banner__content {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1 1 auto;
  justify-content: center;
}

.promo-banner__icon {
  font-size: 16px;
  flex-shrink: 0;
  color: inherit;
}

.promo-banner__text {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.2;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.promo-banner__name {
  flex-shrink: 0;
}

.promo-banner__sep,
.promo-banner__desc {
  display: none;
}

@media (min-width: 640px) {
  .promo-banner__sep,
  .promo-banner__desc {
    display: inline;
  }
  .promo-banner__sep {
    opacity: 0.5;
    font-weight: 400;
  }
  .promo-banner__desc {
    text-transform: none;
    letter-spacing: 0;
    font-weight: 500;
    opacity: 0.84;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

.promo-banner__cta {
  flex-shrink: 0;
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  background: var(--gold);
  color: var(--ink);
  transition: filter var(--motion-base);
}

.promo-banner__cta:hover { filter: brightness(0.95); }
.promo-banner__cta:active { transform: scale(0.96); }

.promo-banner--light .promo-banner__cta {
  background: var(--ink);
  color: var(--ivory);
}

.promo-banner__close {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  color: inherit;
  transition: background-color var(--motion-base);
}

.promo-banner__close:hover { background: rgba(255, 255, 255, 0.10); }
.promo-banner--light .promo-banner__close:hover { background: var(--ivory); }

.promo-banner__close .material-symbols-outlined {
  font-size: 16px;
}

.slide-down-enter-active,
.slide-down-leave-active {
  transition: transform var(--motion-slow) var(--easing-standard),
              opacity var(--motion-slow) var(--easing-standard);
}

.slide-down-enter-from,
.slide-down-leave-to {
  transform: translateY(-100%);
  opacity: 0;
}
</style>
