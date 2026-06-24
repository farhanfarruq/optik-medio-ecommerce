# Audit Repository Laporan Magang Panemu dan Studi Kasus Optik Medio

Tanggal audit: 22 Juni 2026

## Ringkasan

Tempat magang resmi adalah PT. Panemu Solusi Industri, Kulon Progo, Yogyakarta. Repository ini dipakai sebagai proyek studi kasus Optik Medio untuk menerapkan pengalaman magang pada pengembangan dan pemeliharaan sistem e-commerce. Optik Medio hanya diposisikan sebagai studi kasus akademik dan tidak ditulis sebagai sistem resmi milik PT. Panemu Solusi Industri kecuali ada pernyataan resmi.

Repository berisi dua boundary aplikasi utama:

1. `medio-fe`: frontend Vue 3 berbasis Vite dan TypeScript.
2. `medio-be`: backend Laravel 13 berbasis PHP 8.3.

Struktur laporan yang digunakan adalah Laporan Magang ITDA, bukan Laporan Kerja Praktik. Karena itu laporan final memakai empat bab utama: Pendahuluan, Pelaksanaan, Hasil dan Pembahasan, serta Penutup.

Laporan tidak boleh membuka detail internal sistem e-commerce perusahaan atau klien, termasuk source code internal, database internal, endpoint rahasia, credential, tiket issue internal, data pelanggan, data transaksi, konfigurasi server, screenshot dashboard internal, dan informasi production.

## Frontend

Manifest: `medio-fe/package.json`

Teknologi aktual yang ditemukan:

1. Vue 3.
2. Vite.
3. TypeScript.
4. Tailwind CSS.
5. Pinia.
6. Pinia persisted state.
7. Vue Router.
8. Axios.
9. DOMPurify.
10. VueUse.

File dan modul yang relevan untuk laporan:

1. `src/router/index.ts`.
2. `src/stores/authStore.ts`.
3. `src/stores/cartStore.ts`.
4. `src/stores/compareStore.ts`.
5. `src/stores/recentlyViewedStore.ts`.
6. `src/stores/wishlistStore.ts`.
7. `src/repositories/ProductRepository.ts`.
8. `src/repositories/OrderRepository.ts`.
9. `src/repositories/AuthRepository.ts`.
10. `src/repositories/ReviewRepository.ts`.
11. `src/views/Product.vue`.
12. `src/views/ProductDetail.vue`.
13. `src/views/CartView.vue`.
14. `src/views/checkout/CheckoutView.vue`.
15. `src/views/checkout/WaitingPayment.vue`.
16. `src/views/OrderDetail.vue`.
17. `src/views/Profile.vue`.
18. `src/views/ProductCompare.vue`.
19. `src/views/VirtualTryOn.vue`.
20. `src/views/Tracking.vue`.

Catatan: prompt awal menyebut React JS, Tailwind CSS, dan checkout WhatsApp. Audit repository menunjukkan frontend aktual memakai Vue 3, Vite, TypeScript, Tailwind CSS, Pinia, Vue Router, dan Axios. Laporan final mengikuti stack aktual agar tidak mengarang.

## Backend

Manifest: `medio-be/composer.json`

Teknologi aktual yang ditemukan:

1. PHP 8.3.
2. Laravel 13.
3. Laravel Sanctum.
4. Filament 5.5.
5. Xendit PHP SDK.
6. Resend Laravel.
7. Symfony HTML Sanitizer.
8. PHPUnit 12.
9. Laravel Pint.
10. Modul shipping atau ongkos kirim pada backend dan admin.

File dan modul yang relevan untuk laporan:

1. `routes/api.php`.
2. `app/Http/Controllers/API/ProductController.php`.
3. `app/Http/Controllers/API/OrderController.php`.
4. `app/Http/Controllers/API/CartController.php`.
5. `app/Http/Controllers/API/AuthController.php`.
6. `app/Http/Controllers/API/PaymentController.php` jika tersedia pada checkout saat validasi lanjutan.
7. `app/Http/Controllers/API/ShippingController.php`.
8. `app/Http/Controllers/API/ReviewController.php`.
9. `app/Http/Controllers/API/ComplaintController.php` atau `ComplainController.php` sesuai nama aktual.
10. Resource Filament untuk produk, kategori, pesanan, pengguna, pengaturan, dan data pendukung.

## Cakupan fitur studi kasus yang dapat dijelaskan

1. Katalog produk optik.
2. Detail produk.
3. Keranjang belanja.
4. Checkout.
5. Riwayat dan detail pesanan.
6. Autentikasi pelanggan.
7. Profil pelanggan.
8. Wishlist.
9. Perbandingan produk.
10. Pelacakan pesanan.
11. Pengaduan.
12. Ulasan.
13. Panel admin berbasis Filament.
14. Integrasi pembayaran berbasis Xendit.
15. Integrasi ekspedisi atau perhitungan ongkos kirim, termasuk pola RajaOngkir jika tersedia.

## Data yang belum boleh diisi tanpa validasi

1. NIM.
2. Alamat resmi PT. Panemu Solusi Industri.
3. Tanggal mulai dan selesai magang.
4. Divisi atau penempatan.
5. Nama dosen pembimbing.
6. Nama pembimbing lapangan.
7. NIP atau identitas pembimbing.
8. Sejarah, visi, misi, dan struktur organisasi resmi PT. Panemu Solusi Industri.
9. Logo resmi dan foto tempat magang.
10. Nomor kontak legal atau administratif perusahaan.

## Batasan kerahasiaan

1. Jangan memasukkan screenshot internal sistem perusahaan atau klien.
2. Jangan memasukkan source code, endpoint, nama database, credential, payload transaksi, data pelanggan, data order, atau konfigurasi server internal.
3. Website e-commerce perusahaan atau klien hanya boleh disebut sebagai konteks pengalaman kerja jika dibutuhkan, bukan sebagai objek pembahasan teknis utama.
4. Semua screenshot dan diagram teknis laporan menggunakan proyek studi kasus Optik Medio.

## Dampak ke laporan

Laporan final menggunakan placeholder untuk data yang belum tersedia. Placeholder dikumpulkan di `checklist-final.md`. Struktur bab tidak mengikuti laporan KP lama di `docs/kp/` karena laporan Magang hanya memakai BAB I sampai BAB IV.
