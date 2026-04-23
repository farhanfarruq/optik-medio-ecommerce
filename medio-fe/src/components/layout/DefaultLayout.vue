<script setup lang="ts">
import TopNavBar from './TopNavBar.vue';
import Footer from './Footer.vue';
import ToastContainer from '../ui/ToastContainer.vue';
import { useRoute } from 'vue-router';
import { computed } from 'vue';

const route = useRoute();

// Halaman Auth (Login/Register) → tampil full-screen tanpa navbar/footer
const isAuthPage = computed(() => ['Login', 'Register'].includes(route.name as string));

// Full-bleed hero pages (no bg texture overlay needed)
const isFullHeroPage = computed(() =>
  ['Home', 'Products', 'ProductsByCategory', 'Checkout', 'Login'].includes(route.name as string)
);
</script>

<template>
  <div class="bg-[#F5F2EE] text-stone-900 font-body min-h-screen flex flex-col">

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

    <TopNavBar v-if="!isAuthPage" />
    <ToastContainer />

    <div class="flex-grow flex flex-col relative z-10">
      <router-view />
    </div>

    <Footer v-if="!isAuthPage" class="relative z-10" />
  </div>
</template>