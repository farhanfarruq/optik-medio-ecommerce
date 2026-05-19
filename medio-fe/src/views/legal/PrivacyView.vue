<template>
  <div class="bg-[var(--ivory)] min-h-screen">
    <PageHero
      title="Kebijakan Privasi"
      :breadcrumbs="[{ label: 'Kebijakan Privasi' }]"
    />

    <!-- Main Content -->
    <main class="container-readable relative z-20 pt-24 pb-20 sm:pt-28">
      <div class="bg-porcelain p-8 md:p-12 border border-outline-variant/15 shadow-card">
        <div class="mb-12">
          <p class="text-xs font-black uppercase tracking-[0.2em] mb-3 text-primary">Informasi Legal</p>
          <p class="text-base leading-relaxed max-w-2xl text-on-surface-variant">
            Optik Medio berkomitmen untuk melindungi privasi Anda. Kami hanya menggunakan data Anda untuk memproses pesanan dan meningkatkan layanan.
          </p>
        </div>

        <div class="space-y-12">
          <section>
            <h2 class="text-xl font-black mb-4 text-on-surface" style="font-family: 'Cormorant Garamond', serif;">1. Data yang Kami Kumpulkan</h2>
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
            <h2 class="text-xl font-black mb-4 text-on-surface" style="font-family: 'Cormorant Garamond', serif;">2. Penggunaan Informasi</h2>
            <p class="text-on-surface-variant leading-relaxed">
              Informasi yang kami kumpulkan digunakan secara eksklusif untuk memproses pesanan Anda, memverifikasi pembayaran, mengatur pengiriman melalui ekspedisi mitra, serta memberikan bantuan teknis atau layanan purna jual yang Anda butuhkan.
            </p>
          </section>

          <section>
            <h2 class="text-xl font-black mb-4 text-on-surface" style="font-family: 'Cormorant Garamond', serif;">3. Keamanan Data</h2>
            <p class="text-on-surface-variant leading-relaxed">
              Keamanan data Anda adalah prioritas kami. Kami menggunakan enkripsi standar industri untuk melindungi transaksi finansial dan membatasi akses ke data pribadi Anda hanya kepada karyawan yang memerlukannya untuk memproses pesanan.
            </p>
          </section>
          
          <section>
            <h2 class="text-xl font-black mb-4 text-on-surface" style="font-family: 'Cormorant Garamond', serif;">4. Pembaruan Kebijakan</h2>
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

<script setup lang="ts">
import PageHero from '../../components/layout/PageHero.vue';
</script>
