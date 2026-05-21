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
  titleClass: '',
  subtitle: '',
});
</script>

<template>
  <header class="page-hero">
    <div class="page-hero__media">
      <img
        src="/gambar/hero-bg.jpeg"
        alt=""
        class="page-hero__image"
        loading="lazy"
        decoding="async"
      />
      <div class="page-hero__scrim" aria-hidden="true"></div>
      <div class="page-hero__fade" aria-hidden="true"></div>
      <div class="page-hero__rule" aria-hidden="true"></div>
    </div>

    <div class="container-commerce page-hero__inner">
      <div class="page-hero__top">
        <nav class="page-hero__breadcrumbs" aria-label="Breadcrumb">
          <router-link to="/" class="page-hero__crumb-link">Beranda</router-link>
          <template v-for="crumb in breadcrumbs" :key="crumb.label + '-' + (crumb.to || 'current')">
            <span class="material-symbols-outlined page-hero__crumb-sep" aria-hidden="true">chevron_right</span>
            <router-link
              v-if="crumb.to"
              :to="crumb.to"
              class="page-hero__crumb-link"
            >{{ crumb.label }}</router-link>
            <span v-else class="page-hero__crumb-current">{{ crumb.label }}</span>
          </template>
        </nav>

        <router-link :to="backTo" class="page-hero__back group">
          <span class="material-symbols-outlined text-lg transition-transform group-hover:-translate-x-0.5">arrow_back</span>
          <span class="hidden sm:inline">{{ backLabel }}</span>
        </router-link>
      </div>

      <div class="page-hero__title-block">
        <h1 :class="['page-hero__title editorial-display', titleClass]">{{ title }}</h1>
        <p v-if="subtitle" class="page-hero__subtitle">{{ subtitle }}</p>
      </div>
    </div>
  </header>
</template>

<style scoped>
.page-hero {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  /* Hero pendek di mobile, grow di desktop. */
  min-height: clamp(220px, 36vw, 360px);
  /* Tidak pakai margin-bottom negatif — konten setelah hero cukup pakai padding normal.
     Fade ke ivory sudah di-handle oleh .page-hero__fade di dalam hero. */
  margin-bottom: 0;
  padding-top: calc(var(--header-height, 72px) + clamp(28px, 4vw, 52px));
  /* Padding bottom lebih besar supaya fade terlihat dan konten tidak ketutupan */
  padding-bottom: clamp(40px, 5vw, 64px);
  color: #fff;
}

.page-hero__media {
  position: absolute;
  inset: 0;
  z-index: -1;
}

.page-hero__image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center 40%;
  transform: scale(1.06);
}

.page-hero__scrim {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(135deg, rgba(10, 8, 5, 0.68) 0%, rgba(30, 20, 10, 0.42) 100%),
    linear-gradient(180deg, rgba(10, 8, 5, 0.10) 0%, rgba(10, 8, 5, 0.55) 100%);
}

.page-hero__fade {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: clamp(72px, 10vw, 120px);
  background: linear-gradient(to bottom, transparent 0%, var(--ivory) 100%);
}

.page-hero__rule {
  position: absolute;
  left: 0;
  right: 0;
  bottom: clamp(72px, 10vw, 120px);
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(184, 138, 68, 0.55), transparent);
}

.page-hero__inner {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: clamp(20px, 3vw, 40px);
  height: 100%;
}

.page-hero__top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.page-hero__breadcrumbs {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.62);
}

.page-hero__crumb-link {
  transition: color 200ms ease;
}
.page-hero__crumb-link:hover { color: #fff; }

.page-hero__crumb-sep {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.42);
}

.page-hero__crumb-current {
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #fff;
}

.page-hero__back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: rgba(184, 138, 68, 0.92);
  letter-spacing: 0.02em;
  transition: color 200ms ease;
}
.page-hero__back:hover { color: #fff; }

.page-hero__title-block {
  max-width: 64ch;
}

.page-hero__title {
  color: #fff;
  font-size: clamp(1.875rem, 1.2rem + 3.2vw, 3.75rem);
  letter-spacing: 0;
  line-height: 1.04;
}

.page-hero__subtitle {
  margin-top: 10px;
  max-width: 60ch;
  font-size: clamp(13px, 1.2vw, 16px);
  font-weight: 500;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.78);
}
</style>
