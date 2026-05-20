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
  <main class="relative min-h-[calc(100vh-var(--header-height))] overflow-hidden bg-ivory pt-[var(--header-height)] text-ink">
    <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(135deg,rgba(21,18,14,0.05),transparent_38%,rgba(184,138,68,0.11))]"></div>
    <div class="pointer-events-none absolute inset-x-0 top-[var(--header-height)] h-px bg-gold/20"></div>
    <section class="container-commerce relative grid min-h-[calc(100vh-var(--header-height))] items-center gap-8 py-8 sm:py-10 lg:grid-cols-[1.02fr_0.98fr] lg:gap-12 lg:py-14">
      <aside class="relative hidden min-h-[620px] overflow-hidden rounded-lg border border-ink/10 bg-ink text-ivory shadow-card lg:block">
        <img
          src="/gambar/halaman-lain.jpeg"
          alt="Koleksi frame dan layanan Optik Medio"
          class="absolute inset-0 h-full w-full object-cover opacity-[0.72]"
          loading="eager"
          decoding="async"
        />
        <div class="absolute inset-0 bg-gradient-to-br from-ink/82 via-ink/62 to-ink/36"></div>
        <div class="absolute inset-0 bg-[linear-gradient(180deg,transparent_48%,rgba(21,18,14,0.78))]"></div>
        <div class="relative z-10 flex min-h-[620px] flex-col justify-between p-10">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gold">Optik Medio Account</p>
            <h2 class="mt-4 max-w-xl font-headline text-5xl font-semibold leading-tight text-white">{{ panelTitle }}</h2>
            <p class="mt-5 max-w-md text-sm leading-7 text-ivory/88">{{ panelSubtitle }}</p>
          </div>

          <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-white/22 bg-ink/34 p-4 backdrop-blur">
              <span class="material-symbols-outlined text-gold">local_shipping</span>
              <p class="mt-2 text-sm font-semibold">Tracking order</p>
            </div>
            <div class="rounded-lg border border-white/22 bg-ink/34 p-4 backdrop-blur">
              <span class="material-symbols-outlined text-gold">visibility</span>
              <p class="mt-2 text-sm font-semibold">Resep optik</p>
            </div>
            <div class="rounded-lg border border-white/22 bg-ink/34 p-4 backdrop-blur">
              <span class="material-symbols-outlined text-gold">workspace_premium</span>
              <p class="mt-2 text-sm font-semibold">Warranty</p>
            </div>
          </div>
        </div>
      </aside>

      <div class="mx-auto w-full max-w-[520px]">
        <div class="mb-7 text-center lg:text-left">
          <img src="/gambar/medio.jpeg" alt="Optik Medio" class="mx-auto mb-5 h-14 w-auto lg:mx-0" loading="lazy" decoding="async" />
          <p class="text-xs font-semibold uppercase tracking-[0.22em] text-gold">{{ eyebrow }}</p>
          <h1 class="mt-3 font-headline text-4xl font-semibold leading-tight text-ink sm:text-5xl">{{ title }}</h1>
          <p class="mt-3 text-sm leading-6 text-graphite/72">{{ description }}</p>
        </div>

        <slot />
      </div>
    </section>
  </main>
</template>
