<script setup lang="ts">
import TopNavBar from './TopNavBar.vue';
import BottomTabBar from './BottomTabBar.vue';
import Footer from './Footer.vue';
import PromoBanner from '../PromoBanner.vue';
import ToastContainer from '../ui/ToastContainer.vue';
import { useRoute } from 'vue-router';
import { computed } from 'vue';
import { useCartStore } from '../../stores/cartStore';

const route = useRoute();
const cartStore = useCartStore();

const isAuthPage = computed(() => ['Login', 'Register'].includes(route.name as string));
const headerHeight = computed(() => cartStore.isPromoBannerVisible ? '108px' : '72px');
</script>

<template>
  <div
    class="min-h-screen bg-ivory text-ink font-body flex flex-col"
    :style="{ '--header-height': headerHeight }"
  >
    <PromoBanner />
    <TopNavBar />
    <ToastContainer />

    <main class="relative z-10 flex flex-1 flex-col">
      <router-view :key="route.fullPath" />
    </main>

    <Footer v-if="!isAuthPage" class="relative z-10 hidden md:block" />
    <BottomTabBar v-if="!isAuthPage" />
  </div>
</template>
