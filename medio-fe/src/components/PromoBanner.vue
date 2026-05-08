<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useCartStore } from '../stores/cartStore';

const cartStore = useCartStore();
const router = useRouter();

onMounted(async () => {
  await cartStore.fetchPromos();
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
    <div v-if="activePromo" class="bg-[#1a1209] text-white py-2 px-4 text-center fixed top-0 w-full z-[100] border-b border-[#c19a51]/30 shadow-lg h-[40px] flex items-center">
      <div class="max-w-7xl mx-auto w-full flex items-center justify-between gap-4">
        <div class="flex-1 flex items-center justify-center gap-3 overflow-hidden">
          <div class="flex items-center gap-2 shrink-0">
            <span class="material-symbols-outlined text-[14px] text-[#c19a51] animate-pulse">sell</span>
            <p class="text-[10px] md:text-xs font-black uppercase tracking-[0.15em] whitespace-nowrap">
              <span class="text-[#fcd34d]">{{ activePromo.name }}</span>
            </p>
          </div>
          <div class="hidden md:block w-px h-3 bg-white/30 shrink-0"></div>
          <p class="text-[10px] md:text-xs text-white/90 font-bold truncate italic">
            "{{ activePromo.description }}"
          </p>
          <button @click="handleAmbil" class="ml-2 text-[9px] font-black uppercase tracking-widest bg-[#c19a51] text-[#1a1209] px-3 py-1 rounded-none hover:bg-white hover:scale-105 active:scale-95 transition-all shadow-sm shrink-0">
            Ambil
          </button>
        </div>
        <button @click="cartStore.dismissPromoBanner()" class="text-white/50 hover:text-white transition-colors p-1 flex items-center justify-center shrink-0">
          <span class="material-symbols-outlined text-sm">close</span>
        </button>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
  transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.4s ease;
}

.slide-down-enter-from,
.slide-down-leave-to {
  transform: translateY(-100%);
  opacity: 0;
}
</style>
