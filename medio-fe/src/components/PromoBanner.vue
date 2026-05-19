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
const bannerTextStyle = computed(() => ({ color: isLightBanner.value ? 'var(--ink)' : '#fff' }));

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
      class="fixed top-0 z-[100] flex h-9 w-full items-center border-b px-3 shadow-card backdrop-blur-2xl transition-colors duration-300"
      :class="isLightBanner ? 'border-mist/80 bg-porcelain/70' : 'border-gold/25 bg-ink/50'"
      :style="bannerTextStyle"
    >
      <div class="container-premium flex items-center justify-between gap-3 px-0">
        <div class="flex min-w-0 flex-1 items-center justify-center gap-2 overflow-hidden md:gap-3">
          <span class="material-symbols-outlined shrink-0 text-[15px]" :style="bannerTextStyle">sell</span>
          <p class="truncate text-[10px] font-semibold uppercase tracking-[0.14em] md:text-xs">
            <span :style="bannerTextStyle">{{ activePromo.name }}</span>
            <span class="mx-2 hidden sm:inline" :style="{ color: isLightBanner ? 'rgba(26,18,9,0.38)' : 'rgba(255,255,255,0.5)' }">/</span>
            <span class="hidden normal-case tracking-normal sm:inline" :style="{ color: isLightBanner ? 'rgba(26,18,9,0.72)' : 'rgba(255,255,255,0.82)' }">{{ activePromo.description }}</span>
          </p>
          <button
            @click="handleAmbil"
            class="shrink-0 rounded-full px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] transition-colors active:scale-95"
            :class="isLightBanner ? 'bg-gold text-ink hover:bg-ivory' : 'bg-white/10 text-white ring-1 ring-white/25 hover:bg-white/15'"
          >
            Ambil
          </button>
        </div>
        <button @click="cartStore.dismissPromoBanner()" :class="[
            'flex h-7 w-7 shrink-0 items-center justify-center rounded-full transition-colors',
            isLightBanner ? 'hover:bg-ivory' : 'hover:bg-white/10'
          ]" :style="bannerTextStyle" aria-label="Tutup promo">
          <span class="material-symbols-outlined text-base">close</span>
        </button>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active { transition: transform 0.28s ease, opacity 0.28s ease; }
.slide-down-enter-from,
.slide-down-leave-to { transform: translateY(-100%); opacity: 0; }
</style>
