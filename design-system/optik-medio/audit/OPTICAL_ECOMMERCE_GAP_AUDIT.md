# Audit Gap Website Ecommerce Optik Medio

Tanggal: 2026-05-21
Basis audit: route frontend, repository API, controller/model backend, Filament admin resources, dan pembanding UX ecommerce/eyewear.

## Kesimpulan Singkat

Website sudah di atas ecommerce dasar. Fondasi yang sudah ada: katalog produk, kategori/brand, pencarian, compare, wishlist, cart, checkout, payment, tracking, review, resep optik, konfigurasi lensa, virtual try-on, face shape quiz, appointment, warranty/servis, komplain, loyalty, referral, affiliate, blog, FAQ, legal, dan admin panel.

Gap terbesar bukan jumlah fitur, tetapi trust dan conversion layer: halaman shipping/return terpisah, guest checkout, panduan ukuran/PD yang lebih kuat, store locator, dan recovery/CRM untuk customer yang ragu.

## Yang Sudah Kuat

- Katalog dan discovery: /products, kategori, brand landing, filter, search suggestions, recommendation, compare.
- Optik-specific: dimensi frame, face size fit, prescription profile, PD single/binocular, lampiran resep, validasi resep, lensa/coating, product compatibility, face shape quiz, virtual try-on.
- Transaksi: cart, checkout, Xendit/manual payment, payment proof, payment status polling, pickup toko, shipping cost, discount/promo.
- Account dan after-sales: order history, tracking, warranty, service claim, return request, complaint, prescription management.
- Growth: review foto, wishlist/share wishlist, loyalty, referral, affiliate approval, abandoned cart admin/job.
- Admin: Product, Order, Payment, Discount, Promo, Review, Prescription, Appointment, Store Branch, Warranty, Return, Complaint, Affiliate, Commission, Inventory.

## Gap Prioritas P0

### 1. Halaman Shipping Info dan Return Policy belum berdiri sendiri

Saat ini footer punya Terms, Privacy, FAQ, Komplain, Warranty, tetapi belum ada link eksplisit /shipping dan /returns. Untuk ecommerce, banyak user mencari ongkir, estimasi sampai, syarat retur, biaya retur, dan deadline retur di footer serta product page.

Rekomendasi:
- Tambah /shipping berisi kurir, estimasi, pickup toko, same-day/local delivery jika ada, tracking, area layanan.
- Tambah /returns berisi syarat retur kacamata, pengecualian lensa resep/custom, barang rusak, salah ukuran, biaya kirim balik, timeline refund.
- Tautkan di footer, PDP, cart, checkout.

Referensi: Baymard menyebut shipping dan return info perlu direct link, terutama footer; 15% user pernah abandon karena return policy tidak memuaskan.

### 2. Checkout wajib login; guest checkout belum nyata

Route Checkout masuk AUTH_REQUIRED_ROUTES, jadi user harus login sebelum bayar. Untuk ecommerce umum, forced account creation adalah friction besar.

Rekomendasi:
- Buat guest checkout untuk frame non-resep dan order sederhana.
- Untuk produk resep, tetap bisa guest dengan email/phone + upload resep, lalu account dibuat opsional setelah order.
- Setelah order sukses, tawarkan set password untuk menyimpan resep/order.

Referensi: Baymard mencatat checkout abandonment rata-rata 70.22%; 19% user abandon karena tidak mau membuat akun.

### 3. PD measurement guide/tool belum ada

Data PD sudah ada dan divalidasi, tetapi belum ada alat/halaman edukasi khusus untuk mengukur PD. Ini krusial untuk online prescription glasses.

Rekomendasi:
- Tambah /pd-guide: apa itu PD, kapan single/binocular dipakai, cara ukur manual, batas aman, disclaimer untuk resep tinggi/progressive.
- Tambah printable ruler atau camera/card-based PD helper pada fase berikutnya.
- Di checkout, jika PD kosong tampilkan CTA ke /pd-guide atau booking fitting.

Referensi: Warby Parker dan Zenni menempatkan PD sebagai data penting untuk pemesanan kacamata online; beberapa pemain eyewear menggabungkan virtual try-on dengan PD measurement.

### 4. Store locator/cabang belum menjadi halaman publik

Backend dan appointment punya store_branches, maps_url, latitude/longitude, availability. Frontend baru memakai cabang di appointment, belum ada halaman /stores.

Rekomendasi:
- Tambah /stores atau /cabang: daftar cabang, maps, jam buka, kontak WA, layanan tiap cabang, slot appointment, pickup availability.
- Hubungkan dari footer, product detail, checkout pickup, appointment.

## Gap Prioritas P1

### 5. Panduan ukuran frame perlu halaman mandiri

PDP punya ukuran frame, bridge, temple, frame width, dan face shape quiz. Namun user pemula perlu panduan: cara baca 52-18-145, ukuran wajah, hidung rendah/tinggi, frame sempit/lebar, progressive-friendly frame height.

Rekomendasi:
- Tambah /size-guide.
- Filter produk berdasarkan face width/PD/lens width secara lebih eksplisit.
- Tambah warning jika ukuran frame tidak cocok dengan PD atau resep tinggi.

### 6. Virtual try-on belum sekelas AR/measurement

VirtualTryOn sudah ada dengan upload/photo overlay dan saved previews. Gap berikutnya: real-size calibration, side-by-side compare, share preview, dan integrasi PD/face measurement.

Rekomendasi:
- Tambah calibration pakai kartu/ukuran referensi.
- Simpan beberapa frame dalam dressing room.
- Share preview ke WA atau link.
- Tandai kualitas hasil: preview style only, bukan pengukuran optik final.

### 7. Review sudah ada foto, tetapi belum ada admin reply publik

ProductReview punya image upload dan moderation. Belum tampak field balasan admin/staff pada review.

Rekomendasi:
- Tambah merchant_reply, replied_by, replied_at.
- Tampilkan balasan staff berbeda visual dari review customer.
- Prioritaskan reply untuk rating 1-3.

Referensi: Baymard menekankan respons ke negative review meningkatkan persepsi customer care.

### 8. Payment alternatif lokal belum lengkap

Ada Xendit/manual/COD signal, tetapi tidak tampak paylater/installment. Untuk frame+lensa premium, cicilan bisa menaikkan conversion.

Rekomendasi:
- Tambah QRIS/VA/e-wallet eksplisit jika Xendit sudah mendukung.
- Tambah cicilan/paylater jika target market cocok.
- Tampilkan fee dan redirect behavior sebelum user submit payment.

### 9. Halaman kontak/bantuan belum menjadi support center penuh

WA link ada di footer/FAQ, Fonnte service ada di backend. Namun belum ada /contact atau /help-center terpadu.

Rekomendasi:
- Tambah /contact atau /help: WA, telepon, email, jam CS, alamat, tracking, retur, appointment, klaim garansi.
- Tambah quick actions: cek order, ajukan komplain, booking fitting.

### 10. Product schema/SEO structured data perlu dipastikan

Ada SEO/meta utilities dan product CSV meta fields. Belum terbukti ada JSON-LD Product/Review/Breadcrumb di PDP.

Rekomendasi:
- Tambah JSON-LD Product, AggregateRating, Review, BreadcrumbList, LocalBusiness.
- Generate sitemap dari route + produk + artikel + kategori + brand.

## Gap Prioritas P2

### 11. Back-in-stock notification belum jelas sebagai fitur customer

Ada low stock/back-in-stock signal dan inventory admin, tetapi belum tampak flow customer minta notifikasi stok.

Rekomendasi:
- Saat stok habis: tombol “Ingatkan saya”, email/WA opt-in, admin list demand.

### 12. Home try-on atau reserve-in-store belum ada

Untuk optik omnichannel, user sering ingin coba beberapa frame di toko.

Rekomendasi:
- “Reserve frame di toko” dari PDP.
- Pilih cabang + slot fitting + maksimal 3 frame.
- Status reservasi di admin appointment/inventory.

### 13. Lens education bisa diperdalam

Optical controller memberi rekomendasi high index/progressive/photochromic. Perlu halaman edukasi non-teknis.

Rekomendasi:
- /lens-guide: single vision, progressive, blue light, photochromic, high index, coating, kapan dipilih.
- PDP dan checkout arahkan ke guide, bukan hanya label lensa.

### 14. Contact lens/softlens belum jelas sebagai kategori utama

Ada beberapa sinyal kata “lensa kontak/softlens”, tetapi route dan flow utama masih frame/kacamata.

Rekomendasi jika bisnis menjual softlens:
- Kategori softlens, power selector, base curve, diameter, masa pakai, cairan softlens, safety guide.
- Reorder flow untuk produk berulang.

### 15. Post-purchase CRM masih dasar

Abandoned cart email ada untuk user terdaftar. Bisa diperluas.

Rekomendasi:
- Reminder follow-up setelah order selesai: minta review, cara rawat kacamata, klaim garansi.
- Reminder service/cleaning 3-6 bulan.
- Reorder lensa kontak/cairan jika kategori ditambahkan.

## Backlog Implementasi Disarankan

1. Tambah /shipping dan /returns + footer links + PDP/checkout links.
2. Tambah /pd-guide dan /size-guide.
3. Tambah /stores pakai API /branches yang sudah ada.
4. Implement guest checkout minimal untuk non-resep.
5. Tambah merchant reply pada ProductReview.
6. Tambah JSON-LD Product/Breadcrumb/LocalBusiness + sitemap dinamis.
7. Tambah reserve-in-store dari PDP.
8. Tambah back-in-stock notification.
9. Upgrade virtual try-on: calibration, dressing room, share.
10. Tambah support center /contact.

## Catatan Risiko

- Website sudah kaya fitur; jangan tambah semua sekaligus. Mulai dari trust pages dan checkout friction karena paling dekat dengan conversion.
- Untuk resep tinggi/progressive, jangan over-promise online fitting. Arahkan ke appointment/fitting cabang bila risiko tinggi.
- Pastikan copy retur menjelaskan pengecualian lensa resep/custom agar tidak merugikan operasional.

## Sumber Pembanding

- Baymard Checkout UX Guide: https://baymard.com/learn/checkout-flow-ux-optimization
- Baymard Shipping/Return Footer Links: https://baymard.com/blog/footer-needs-return-shipping-links
- Baymard Product Page UX: https://baymard.com/blog/current-state-ecommerce-product-page-ux
- Warby Parker PD Guide: https://www.warbyparker.com/learn/pupillary-distance
- Zenni PD Guide: https://www.zennioptical.com/blog/getting-the-right-fit-how-to-measure-pupillary-distance-for-glasses/
