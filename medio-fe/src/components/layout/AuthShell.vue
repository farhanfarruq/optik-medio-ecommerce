<script setup lang="ts">
defineProps<{
  eyebrow: string;
  title: string;
  description: string;
  panelTitle: string;
  panelSubtitle: string;
}>();
</script>

<template>
  <main class="auth-shell">
    <div class="auth-shell__grain" aria-hidden="true"></div>
    <div class="auth-shell__rule" aria-hidden="true"></div>

    <section class="container-commerce auth-shell__grid">
      <!-- Brand panel — desktop only. Editorial visual, not generic blob. -->
      <aside class="auth-shell__panel">
        <img
          src="/gambar/halaman-lain.jpeg"
          alt="Koleksi frame dan layanan Optik Medio"
          class="auth-shell__panel-image"
          loading="eager"
          decoding="async"
        />
        <div class="auth-shell__panel-scrim" aria-hidden="true"></div>

        <div class="auth-shell__panel-content">
          <div>
            <p class="eyebrow text-gold">Optik Medio Account</p>
            <h2 class="auth-shell__panel-title editorial-display">{{ panelTitle }}</h2>
            <p class="auth-shell__panel-sub">{{ panelSubtitle }}</p>
          </div>

          <ul class="auth-shell__benefits">
            <li class="auth-shell__benefit">
              <span class="material-symbols-outlined auth-shell__benefit-icon">local_shipping</span>
              <div>
                <p class="auth-shell__benefit-title">Tracking pesanan</p>
                <p class="auth-shell__benefit-meta">Pantau status real-time</p>
              </div>
            </li>
            <li class="auth-shell__benefit">
              <span class="material-symbols-outlined auth-shell__benefit-icon">visibility</span>
              <div>
                <p class="auth-shell__benefit-title">Resep optik tersimpan</p>
                <p class="auth-shell__benefit-meta">Pesan ulang tanpa input ulang</p>
              </div>
            </li>
            <li class="auth-shell__benefit">
              <span class="material-symbols-outlined auth-shell__benefit-icon">workspace_premium</span>
              <div>
                <p class="auth-shell__benefit-title">Garansi & after-sales</p>
                <p class="auth-shell__benefit-meta">Klaim cepat dari satu akun</p>
              </div>
            </li>
          </ul>
        </div>
      </aside>

      <!-- Form panel -->
      <div class="auth-shell__form-wrap">
        <div class="auth-shell__intro">
          <img
            src="/gambar/medio.jpeg"
            alt="Optik Medio"
            class="auth-shell__logo"
            loading="lazy"
            decoding="async"
          />
          <p class="eyebrow">{{ eyebrow }}</p>
          <h1 class="auth-shell__title editorial-display">{{ title }}</h1>
          <p class="auth-shell__desc">{{ description }}</p>
        </div>

        <slot />
      </div>
    </section>
  </main>
</template>

<style scoped>
.auth-shell {
  position: relative;
  min-height: calc(100vh - var(--header-height));
  padding-top: var(--header-height);
  background: var(--ivory);
  color: var(--ink);
  overflow: hidden;
}

.auth-shell__grain {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background:
    radial-gradient(120% 60% at 8% 0%, rgba(184, 138, 68, 0.08), transparent 60%),
    radial-gradient(80% 50% at 100% 100%, rgba(86, 96, 75, 0.06), transparent 70%);
}

.auth-shell__rule {
  position: absolute;
  left: 0;
  right: 0;
  top: var(--header-height);
  height: 1px;
  background: rgba(184, 138, 68, 0.20);
  pointer-events: none;
}

.auth-shell__grid {
  position: relative;
  display: grid;
  align-items: center;
  gap: 32px;
  padding-top: clamp(24px, 4vw, 56px);
  padding-bottom: clamp(28px, 4vw, 56px);
  min-height: calc(100vh - var(--header-height));
}

@media (min-width: 1024px) {
  .auth-shell__grid {
    grid-template-columns: 1.05fr 0.95fr;
    gap: 48px;
  }
}

.auth-shell__panel {
  position: relative;
  display: none;
  min-height: 600px;
  overflow: hidden;
  border-radius: 8px;
  border: 1px solid rgba(21, 18, 14, 0.10);
  background: var(--ink);
  color: var(--ivory);
  box-shadow: var(--shadow-soft);
}

@media (min-width: 1024px) {
  .auth-shell__panel { display: block; }
}

.auth-shell__panel-image {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0.66;
}

.auth-shell__panel-scrim {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(135deg, rgba(21, 18, 14, 0.86) 0%, rgba(21, 18, 14, 0.52) 60%, rgba(21, 18, 14, 0.32) 100%),
    linear-gradient(180deg, transparent 50%, rgba(21, 18, 14, 0.78));
}

.auth-shell__panel-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 100%;
  min-height: 600px;
  padding: clamp(24px, 3vw, 40px);
  gap: 28px;
}

.auth-shell__panel-title {
  margin-top: 14px;
  max-width: 22ch;
  font-size: clamp(2rem, 1.4rem + 1.8vw, 3.25rem);
  color: #fff;
  letter-spacing: 0;
  line-height: 1.06;
}

.auth-shell__panel-sub {
  margin-top: 16px;
  max-width: 42ch;
  font-size: 14px;
  line-height: 1.6;
  color: rgba(247, 243, 236, 0.84);
}

.auth-shell__benefits {
  display: grid;
  gap: 10px;
}

.auth-shell__benefit {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(21, 18, 14, 0.36);
  backdrop-filter: blur(6px);
}

.auth-shell__benefit-icon {
  color: var(--gold);
  flex-shrink: 0;
}

.auth-shell__benefit-title {
  font-size: 13px;
  font-weight: 600;
  color: #fff;
}

.auth-shell__benefit-meta {
  margin-top: 2px;
  font-size: 11px;
  font-weight: 500;
  color: rgba(247, 243, 236, 0.66);
}

.auth-shell__form-wrap {
  width: 100%;
  max-width: 520px;
  margin-inline: auto;
}

@media (min-width: 1024px) {
  .auth-shell__form-wrap { margin-inline: 0; }
}

.auth-shell__intro {
  margin-bottom: 24px;
  text-align: center;
}

@media (min-width: 1024px) {
  .auth-shell__intro { text-align: left; }
}

.auth-shell__logo {
  display: block;
  margin: 0 auto 18px;
  height: 52px;
  width: auto;
}

@media (min-width: 1024px) {
  .auth-shell__logo { margin-left: 0; }
}

.auth-shell__title {
  margin-top: 12px;
  font-size: clamp(1.875rem, 1.4rem + 1.4vw, 2.875rem);
  letter-spacing: 0;
  line-height: 1.06;
}

.auth-shell__desc {
  margin-top: 12px;
  font-size: 14px;
  line-height: 1.6;
  color: rgba(43, 41, 38, 0.74);
}
</style>
