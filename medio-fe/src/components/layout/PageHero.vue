<template>
  <div class="relative w-full" style="margin-bottom: -60px;">
    <div class="relative overflow-hidden" style="height: 280px;">
      <img
        src="/gambar/hero-bg.jpeg"
        alt=""
        class="absolute inset-0 h-full w-full object-cover object-center"
        style="transform: scale(1.08); object-position: center 40%;"
      />
      <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(10,8,5,0.65) 0%, rgba(30,20,10,0.45) 100%);"></div>
      <div class="absolute bottom-0 left-0 right-0" style="height: 100px; background: linear-gradient(to bottom, transparent 0%, var(--ivory) 100%);"></div>
      <div class="absolute" style="bottom: 100px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(184,138,68,0.6), transparent);"></div>

      <div
        class="relative z-10 mx-auto flex h-full max-w-[1000px] flex-col justify-between px-6"
        :style="{ paddingTop: 'calc(var(--header-height, 96px) + 16px)', paddingBottom: '56px' }"
      >
        <div>
          <nav class="mb-2 flex flex-wrap items-center gap-2 text-xs font-medium" style="color: rgba(255,255,255,0.55);" aria-label="Breadcrumb">
            <router-link to="/" class="transition-colors hover:text-white">Beranda</router-link>
            <template v-for="crumb in breadcrumbs" :key="crumb.label + '-' + (crumb.to || 'current')">
              <span class="material-symbols-outlined text-sm">chevron_right</span>
              <router-link v-if="crumb.to" :to="crumb.to" class="transition-colors hover:text-white">
                {{ crumb.label }}
              </router-link>
              <span v-else class="max-w-[220px] truncate text-white">{{ crumb.label }}</span>
            </template>
          </nav>
          <router-link :to="backTo" class="group flex w-fit items-center gap-2 text-sm font-bold transition-all" style="color: rgba(184,138,68,0.9);">
            <span class="material-symbols-outlined text-lg transition-transform group-hover:-translate-x-1">arrow_back</span>
            {{ backLabel }}
          </router-link>
        </div>

        <div class="max-w-3xl">
          <h1 :class="titleClass">{{ title }}</h1>
          <p v-if="subtitle" class="mt-2 max-w-2xl text-sm font-medium leading-6 text-white/72 md:text-base">{{ subtitle }}</p>
        </div>
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
  subtitle?: string;
}>(), {
  backTo: '/',
  backLabel: 'Kembali ke Beranda',
  titleClass: 'text-4xl font-black tracking-normal text-white',
  subtitle: '',
});
</script>
