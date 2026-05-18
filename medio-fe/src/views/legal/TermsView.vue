<template>
  <div class="bg-[var(--ivory)] min-h-screen">
    <PageHero
      title="Syarat & Ketentuan"
      :breadcrumbs="[{ label: 'Syarat & Ketentuan' }]"
    />

    <!-- Main Content -->
    <main class="max-w-[1000px] mx-auto w-full px-6 pb-20 relative z-20">
      <div class="bg-porcelain p-8 md:p-12 border border-outline-variant/15 shadow-card">
        <div class="mb-12">
          <p class="text-xs font-black uppercase tracking-[0.2em] mb-3 text-primary">Aturan Penggunaan</p>
          <p class="text-base leading-relaxed max-w-2xl text-on-surface-variant">
            Harap baca syarat dan ketentuan ini dengan saksama sebelum menggunakan layanan Optik Medio. Dengan bertransaksi, Anda setuju untuk terikat oleh aturan yang berlaku.
          </p>
        </div>

        <div class="space-y-12">
          <section>
            <h2 class="text-xl font-black mb-4 text-on-surface" style="font-family: 'Cormorant Garamond', serif;">1. Ketentuan Pemesanan</h2>
            <p class="text-on-surface-variant leading-relaxed">
              Semua pesanan yang dilakukan melalui situs Optik Medio tunduk pada ketersediaan stok. Kami berhak membatalkan pesanan jika data resep kacamata tidak lengkap atau terjadi kesalahan harga yang signifikan pada sistem.
            </p>
          </section>

          <section>
            <h2 class="text-xl font-black mb-4 text-on-surface" style="font-family: 'Cormorant Garamond', serif;">2. Pembayaran</h2>
            <p class="text-on-surface-variant leading-relaxed">
              Pembayaran harus dilakukan secara penuh melalui metode yang tersedia. Pesanan akan mulai diproses (termasuk pemasangan lensa resep) segera setelah verifikasi pembayaran berhasil diterima oleh sistem kami.
            </p>
          </section>

          <section>
            <h2 class="text-xl font-black mb-4 text-on-surface" style="font-family: 'Cormorant Garamond', serif;">3. Pengiriman & Pengembalian</h2>
            <p class="text-on-surface-variant leading-relaxed mb-4">
              Risiko kerusakan selama pengiriman menjadi tanggung jawab mitra ekspedisi, namun kami akan membantu proses klaim asuransi jika diperlukan. 
            </p>
            <p class="text-on-surface-variant leading-relaxed">
              Pengembalian atau penukaran barang hanya diperbolehkan jika terdapat cacat produksi atau kesalahan pengiriman dari pihak kami, yang dilaporkan maksimal 7 hari setelah barang diterima.
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
