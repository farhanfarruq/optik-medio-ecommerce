<template>
  <div class="relative w-full" style="margin-bottom: 0px;">
    <div class="relative overflow-hidden" style="height: 340px;">
      <img
        src="/gambar/hero-bg.jpeg"
        alt=""
        class="absolute inset-0 w-full h-full object-cover object-center"
        style="transform: scale(1.08); object-position: center 40%;"
      />
      <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(10,8,5,0.65) 0%, rgba(30,20,10,0.45) 100%);"></div>
      <div class="absolute bottom-0 left-0 right-0" style="height: 100px; background: linear-gradient(to bottom, transparent 0%, #F5F2EE 100%);"></div>
      <div class="absolute" style="bottom: 100px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.6), transparent);"></div>
      <div
        class="relative z-10 h-full max-w-[1000px] mx-auto px-6 flex flex-col justify-between"
        :style="{ paddingTop: 'calc(var(--header-height, 96px) + 24px)', paddingBottom: '60px' }"
      >
        <div>
          <nav class="flex items-center gap-2 text-xs font-medium mb-2" style="color: rgba(255,255,255,0.55);">
            <router-link to="/" class="hover:text-white transition-colors">Beranda</router-link>
            <template v-for="crumb in breadcrumbs" :key="`${crumb.label}-${crumb.to || 'current'}`">
              <span class="material-symbols-outlined text-sm">chevron_right</span>
              <router-link v-if="crumb.to" :to="crumb.to" class="hover:text-white transition-colors">
                {{ crumb.label }}
              </router-link>
              <span v-else class="text-white truncate max-w-[200px]">{{ crumb.label }}</span>
            </template>
          </nav>
          <router-link :to="backTo" class="flex items-center gap-2 text-sm font-bold group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
            <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
            {{ backLabel }}
          </router-link>
        </div>
        <h1 :class="titleClass" style="font-family: 'Outfit', sans-serif;">{{ title }}</h1>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
type Breadcrumb = {
  label: string;
  to?: string;
};

withDefaults(defineProps<{
  title: string;
  breadcrumbs: Breadcrumb[];
  backTo?: string;
  backLabel?: string;
  titleClass?: string;
}>(), {
  backTo: '/',
  backLabel: 'Kembali ke Beranda',
  titleClass: 'text-4xl font-black tracking-tight text-white',
});
</script>
