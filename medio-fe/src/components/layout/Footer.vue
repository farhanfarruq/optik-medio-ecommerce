<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { settingRepository, type AppSettings } from '../../repositories/SettingRepository';

const settings = ref<AppSettings | null>(null);

onMounted(async () => {
  try {
    settings.value = await settingRepository.getSettings();
  } catch (error) {
    console.error('Failed to load settings', error);
  }
});
</script>

<template>
  <footer class="w-full border-t border-white/10 bg-graphite/80 text-ivory backdrop-blur-2xl">
    <div class="container-premium py-14 md:py-16">
      <div class="grid grid-cols-1 gap-10 md:grid-cols-2 lg:grid-cols-[1.2fr_1fr_0.8fr_1fr]">
        <section class="space-y-5">
          <div>
            <h2 class="font-headline text-3xl font-semibold text-ivory">Optik <span class="text-gold">Medio</span></h2>
            <div class="mt-3 h-px w-16 bg-gold/70"></div>
          </div>
          <p class="max-w-sm text-sm leading-7 text-ivory/68">
            Destinasi optical commerce di Lampung Tengah untuk frame premium, lensa, konsultasi resep, dan pickup store yang jelas.
          </p>
          <div class="flex items-center gap-3">
            <a href="#" class="flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/5 text-gold transition-colors hover:border-gold/50 hover:bg-gold/10" aria-label="Facebook"><span class="material-symbols-outlined text-base">public</span></a>
            <a href="#" class="flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/5 text-[10px] font-bold text-gold transition-colors hover:border-gold/50 hover:bg-gold/10" aria-label="Instagram">IG</a>
            <a href="#" class="flex h-9 w-9 items-center justify-center rounded-full border border-white/10 bg-white/5 text-[10px] font-bold text-gold transition-colors hover:border-gold/50 hover:bg-gold/10" aria-label="WhatsApp">WA</a>
          </div>
        </section>

        <section class="space-y-5">
          <h3 class="text-xs font-semibold uppercase tracking-[0.18em] text-ivory/78">Hubungi Kami</h3>
          <ul class="space-y-4 text-sm text-ivory/68">
            <li class="flex items-start gap-3"><span class="material-symbols-outlined mt-0.5 text-lg text-gold">location_on</span><span>{{ settings?.store_address || 'Pasar, Bandarsari, Lampung Tengah' }}</span></li>
            <li class="flex items-center gap-3"><span class="material-symbols-outlined text-lg text-gold">call</span><span>{{ settings?.store_phone || '0813-1196-9585' }}</span></li>
            <li class="flex items-center gap-3"><span class="material-symbols-outlined text-lg text-gold">schedule</span><span>{{ settings?.store_opening_hours || 'Tutup pukul 20.30' }}</span></li>
          </ul>
        </section>

        <section class="space-y-5">
          <h3 class="text-xs font-semibold uppercase tracking-[0.18em] text-ivory/78">Navigasi</h3>
          <ul class="space-y-3 text-sm text-ivory/64">
            <li><a href="/" class="transition-colors hover:text-ivory">Beranda</a></li>
            <li><a href="/products" class="transition-colors hover:text-ivory">Koleksi Kacamata</a></li>
            <li><a href="/faq" class="transition-colors hover:text-ivory">FAQ & Bantuan</a></li>
            <li><a :href="settings?.store_location_url" target="_blank" class="transition-colors hover:text-ivory">Cek Lokasi</a></li>
          </ul>
        </section>

        <section class="space-y-5">
          <h3 class="text-xs font-semibold uppercase tracking-[0.18em] text-ivory/78">Temukan Kami</h3>
          <div class="rounded-lg border border-white/10 bg-white/[0.03] p-4">
            <p class="text-sm leading-6 text-ivory/68">Pickup store, konsultasi resep, dan fitting frame tersedia di toko.</p>
            <a :href="settings?.store_location_url" target="_blank" class="mt-5 inline-flex items-center gap-2 rounded-lg border border-gold/35 px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.12em] text-gold transition-colors hover:bg-gold/10"><span class="material-symbols-outlined text-lg">directions</span>Buka Maps</a>
          </div>
        </section>
      </div>

      <div class="mt-12 flex flex-col gap-4 border-t border-white/10 pt-6 text-[11px] font-semibold text-ivory/45 md:flex-row md:items-center md:justify-between">
        <p>© 2026 Optik Medio. Seluruh hak cipta dilindungi.</p>
        <div class="flex items-center gap-5">
          <router-link to="/terms" class="transition-colors hover:text-ivory/80">Syarat & Ketentuan</router-link>
          <router-link to="/privacy" class="transition-colors hover:text-ivory/80">Kebijakan Privasi</router-link>
        </div>
      </div>
    </div>
  </footer>
</template>
