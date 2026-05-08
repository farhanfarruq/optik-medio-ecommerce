<script setup lang="ts">
import TopNavBar from './TopNavBar.vue';
import Footer from './Footer.vue';
import PromoBanner from '../PromoBanner.vue';
import ToastContainer from '../ui/ToastContainer.vue';
import { useRoute } from 'vue-router';
import { computed } from 'vue';
import { useCartStore } from '../../stores/cartStore';

const route = useRoute();
const cartStore = useCartStore();

// Halaman Auth (Login/Register) → tampil full-screen tanpa navbar/footer
const isAuthPage = computed(() => ['Login', 'Register'].includes(route.name as string));

// Full-bleed hero pages (no bg texture overlay needed)
const isFullHeroPage = computed(() =>
  ['Home', 'Products', 'ProductsByCategory', 'Checkout', 'Login'].includes(route.name as string)
);

// CSS variable untuk tinggi header total (navbar + banner promo jika aktif)
// Banner: 40px, Navbar: 80px (scrolled) / 96px (top) → pakai 96px sebagai basis
const headerHeight = computed(() => cartStore.isPromoBannerVisible ? '136px' : '96px');
</script>

<template>
  <div
    class="bg-[#F5F2EE] text-stone-900 font-body min-h-screen flex flex-col"
    :style="{ '--header-height': headerHeight }"
  >

    <!-- Very subtle bg texture — halaman-lain.jpeg with ultra-low opacity -->
    <div
      v-if="!isFullHeroPage"
      class="fixed inset-0 z-0 pointer-events-none"
      aria-hidden="true"
    >
      <img
        src="/gambar/halaman-lain.jpeg"
        alt=""
        class="w-full h-full object-cover"
        style="opacity: 0.5; filter: grayscale(0.6) blur(0px);"
      />
      <!-- Very light warm tint to blend seamlessly -->
      <div
        class="absolute inset-0"
        style="background: linear-gradient(135deg, rgba(245,242,238,0.96) 0%, rgba(240,234,222,0.94) 100%);"
      ></div>
    </div>

    <PromoBanner v-if="!isAuthPage" />
    <TopNavBar v-if="!isAuthPage" />
    <ToastContainer />

    <div class="flex-grow flex flex-col relative z-10">
      <router-view :key="route.fullPath" />
    </div>

    <Footer v-if="!isAuthPage" class="relative z-10" />
  </div>
</template>