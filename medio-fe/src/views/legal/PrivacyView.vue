<template>
  <div class="bg-[#F5F2EE] min-h-screen">
    <!-- Mini Hero with gradient bleed -->
    <div class="relative w-full" style="margin-bottom: -20px;">
      <div class="relative overflow-hidden" style="height: 320px;">
        <img src="/gambar/hero-bg.jpeg" alt="" class="absolute inset-0 w-full h-full object-cover object-center" style="transform: scale(1.08); object-position: center 40%;" />
        <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(10,8,5,0.65) 0%, rgba(30,20,10,0.45) 100%);"></div>
        <div class="absolute bottom-0 left-0 right-0" style="height: 100px; background: linear-gradient(to bottom, transparent 0%, #F5F2EE 100%);"></div>
        <div class="absolute" style="bottom: 100px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.6), transparent);"></div>
        <div class="relative z-10 h-full max-w-[1000px] mx-auto px-6 flex flex-col justify-between" :style="{ paddingTop: 'calc(var(--header-height, 96px) + 24px)', paddingBottom: '48px' }">
          <!-- Breadcrumb + Back -->
          <div>
            <nav class="flex items-center gap-2 text-xs font-medium mb-2" style="color: rgba(255,255,255,0.55);">
              <router-link to="/" class="hover:text-white transition-colors">Beranda</router-link>
              <span class="material-symbols-outlined text-sm">chevron_right</span>
              <span class="text-white">Kebijakan Privasi</span>
            </nav>
            <router-link to="/" class="flex items-center gap-2 text-sm font-bold group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
              <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
              Kembali ke Beranda
            </router-link>
          </div>
          <!-- Page Title -->
          <h1 class="text-4xl font-black tracking-tight text-white" style="font-family: 'Outfit', sans-serif;">Kebijakan Privasi</h1>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-[1000px] mx-auto w-full px-6 pb-20 relative z-20">
      <div class="bg-white p-8 md:p-12 border border-outline-variant/15 shadow-sm">
        <div class="mb-12">
          <p class="text-xs font-black uppercase tracking-[0.2em] mb-3 text-primary">Informasi Legal</p>
          <p class="text-base leading-relaxed max-w-2xl text-on-surface-variant">
            Optik Medio berkomitmen untuk melindungi privasi Anda. Kami hanya menggunakan data Anda untuk memproses pesanan dan meningkatkan layanan.
          </p>
        </div>

        <div class="space-y-12">
          <section>
            <h2 class="text-xl font-black mb-4 text-on-surface" style="font-family: 'Outfit', sans-serif;">1. Data yang Kami Kumpulkan</h2>
            <p class="text-on-surface-variant leading-relaxed mb-4">
              Kami mengumpulkan informasi pribadi yang Anda berikan saat mendaftar, melakukan pemesanan, atau menghubungi layanan pelanggan kami. Data ini meliputi:
            </p>
            <ul class="list-disc pl-6 text-on-surface-variant space-y-2">
              <li>Nama lengkap dan informasi kontak (email, nomor telepon).</li>
              <li>Alamat pengiriman dan penagihan.</li>
              <li>Detail resep kacamata atau lensa yang Anda unggah.</li>
              <li>Riwayat transaksi dan preferensi belanja.</li>
            </ul>
          </section>

          <section>
            <h2 class="text-xl font-black mb-4 text-on-surface" style="font-family: 'Outfit', sans-serif;">2. Penggunaan Informasi</h2>
            <p class="text-on-surface-variant leading-relaxed">
              Informasi yang kami kumpulkan digunakan secara eksklusif untuk memproses pesanan Anda, memverifikasi pembayaran, mengatur pengiriman melalui ekspedisi mitra, serta memberikan bantuan teknis atau layanan purna jual yang Anda butuhkan.
            </p>
          </section>

          <section>
            <h2 class="text-xl font-black mb-4 text-on-surface" style="font-family: 'Outfit', sans-serif;">3. Keamanan Data</h2>
            <p class="text-on-surface-variant leading-relaxed">
              Keamanan data Anda adalah prioritas kami. Kami menggunakan enkripsi standar industri untuk melindungi transaksi finansial dan membatasi akses ke data pribadi Anda hanya kepada karyawan yang memerlukannya untuk memproses pesanan.
            </p>
          </section>
          
          <section>
            <h2 class="text-xl font-black mb-4 text-on-surface" style="font-family: 'Outfit', sans-serif;">4. Pembaruan Kebijakan</h2>
            <p class="text-on-surface-variant leading-relaxed">
              Kami berhak memperbarui Kebijakan Privasi ini sewaktu-waktu. Perubahan akan segera berlaku setelah dipublikasikan di halaman ini. Kami menyarankan Anda untuk meninjau halaman ini secara berkala.
            </p>
          </section>
        </div>

        <div class="mt-16 pt-8 border-t border-outline-variant/10 text-center">
          <p class="text-xs text-on-surface-variant italic">Terakhir diperbarui: 8 Mei 2026</p>
        </div>
      </div>
    </main>
  </div>
</template>
