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
  <footer class="w-full relative overflow-hidden" style="background: #120e09; color: #f5f2ee;">
    <!-- Decorative background elements -->
    <div class="absolute top-0 left-0 w-full h-[1px]" style="background: linear-gradient(90deg, transparent, rgba(193,154,81,0.3), transparent);"></div>
    <div class="absolute -bottom-24 -right-24 w-96 h-96 rounded-full" style="background: radial-gradient(circle, rgba(193,154,81,0.05) 0%, transparent 70%);"></div>

    <div class="max-w-[1440px] mx-auto px-6 md:px-12 py-16 relative z-10">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">
        
        <!-- Brand Info -->
        <div class="space-y-6">
          <div>
            <h2 class="text-2xl font-black tracking-tight" style="font-family: 'Outfit', sans-serif; color: #f5f2ee;">
              Optik <span style="color: #c19a51;">Medio</span>
            </h2>
            <div class="h-1 w-12 mt-2" style="background: #c19a51;"></div>
          </div>
          <p class="text-sm leading-relaxed text-stone-400 max-w-xs">
            Destinasi kacamata premium terpercaya di Lampung Tengah. Kami menghadirkan koleksi frame terkini dengan pelayanan jujur dan berpengalaman.
          </p>
          <div class="flex items-center gap-4">
            <a href="#" class="w-9 h-9 rounded-full flex items-center justify-center transition-all hover:scale-110" style="background: rgba(193,154,81,0.1); border: 1px solid rgba(193,154,81,0.2);">
              <i class="material-symbols-outlined text-sm" style="color: #c19a51;">facebook</i>
            </a>
            <a href="#" class="w-9 h-9 rounded-full flex items-center justify-center transition-all hover:scale-110" style="background: rgba(193,154,81,0.1); border: 1px solid rgba(193,154,81,0.2);">
              <span class="text-[10px] font-bold" style="color: #c19a51;">IG</span>
            </a>
            <a href="#" class="w-9 h-9 rounded-full flex items-center justify-center transition-all hover:scale-110" style="background: rgba(193,154,81,0.1); border: 1px solid rgba(193,154,81,0.2);">
              <span class="text-[10px] font-bold" style="color: #c19a51;">WA</span>
            </a>
          </div>
        </div>

        <!-- Contact Info -->
        <div class="space-y-6">
          <h3 class="text-xs font-black uppercase tracking-[0.2em] text-stone-300">Hubungi Kami</h3>
          <ul class="space-y-4">
            <li class="flex items-start gap-3">
              <span class="material-symbols-outlined text-lg" style="color: #c19a51;">location_on</span>
              <span class="text-sm text-stone-400">{{ settings?.store_address || 'Pasar, Bandarsari, Lampung Tengah' }}</span>
            </li>
            <li class="flex items-center gap-3">
              <span class="material-symbols-outlined text-lg" style="color: #c19a51;">call</span>
              <span class="text-sm text-stone-400">{{ settings?.store_phone || '0813-1196-9585' }}</span>
            </li>
            <li class="flex items-center gap-3">
              <span class="material-symbols-outlined text-lg" style="color: #c19a51;">schedule</span>
              <span class="text-sm text-stone-400">{{ settings?.store_opening_hours || 'Tutup pukul 20.30' }}</span>
            </li>
          </ul>
        </div>

        <!-- Links -->
        <div class="space-y-6">
          <h3 class="text-xs font-black uppercase tracking-[0.2em] text-stone-300">Navigasi</h3>
          <ul class="space-y-3">
            <li><a href="/" class="text-sm text-stone-400 hover:text-white transition-colors">Beranda</a></li>
            <li><a href="/products" class="text-sm text-stone-400 hover:text-white transition-colors">Koleksi Kacamata</a></li>
            <li><a href="#" class="text-sm text-stone-400 hover:text-white transition-colors">Layanan Lensa</a></li>
            <li><a :href="settings?.store_location_url" target="_blank" class="text-sm text-stone-400 hover:text-white transition-colors">Cek Lokasi (Maps)</a></li>
          </ul>
        </div>

        <!-- Maps Preview / Action -->
        <div class="space-y-6">
          <h3 class="text-xs font-black uppercase tracking-[0.2em] text-stone-300">Temukan Kami</h3>
          <div class="relative group cursor-pointer overflow-hidden rounded-sm aspect-video bg-stone-800 flex items-center justify-center">
            <img src="https://images.unsplash.com/photo-1526778548025-fa2f459cd5c1?auto=format&fit=crop&q=80&w=400" class="absolute inset-0 w-full h-full object-cover opacity-40 grayscale transition-all duration-700 group-hover:scale-110 group-hover:grayscale-0" />
            <a :href="settings?.store_location_url" target="_blank" class="relative z-10 flex flex-col items-center gap-2 px-6 py-3 bg-stone-900/80 backdrop-blur-md border border-stone-700 group-hover:border-amber-600 transition-all">
               <span class="material-symbols-outlined text-amber-500">directions</span>
               <span class="text-[10px] font-black uppercase tracking-widest text-white">Buka di Maps</span>
            </a>
          </div>
        </div>

      </div>

      <div class="mt-16 pt-8 border-t border-stone-800/50 flex flex-col md:flex-row justify-between items-center gap-4">
        <p class="text-[10px] font-bold uppercase tracking-widest text-stone-500">
          © 2026 Optik Medio. All Rights Reserved.
        </p>
        <div class="flex items-center gap-6">
          <a href="#" class="text-[10px] font-bold uppercase tracking-widest text-stone-500 hover:text-stone-300">Syarat & Ketentuan</a>
          <a href="#" class="text-[10px] font-bold uppercase tracking-widest text-stone-500 hover:text-stone-300">Kebijakan Privasi</a>
        </div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap');
</style>
