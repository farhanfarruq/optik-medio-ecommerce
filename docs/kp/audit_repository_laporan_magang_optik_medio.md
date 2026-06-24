# Audit Repository untuk Penguatan Laporan Magang Optik Medio

Dokumen ini disusun sebagai bahan teknis untuk laporan magang berjudul **Implementasi Sistem E-Commerce Optik Medio Berbasis Web sebagai Studi Kasus Magang di PT Panemu Solusi Industri**. Audit dilakukan read-only terhadap repository `optik-medio-ecommerce` pada `medio-fe` dan `medio-be`.

Catatan kerahasiaan: credential, API key, token, webhook secret, endpoint production, data pelanggan asli, data transaksi asli, dan konfigurasi server sensitif tidak boleh dimasukkan ke laporan. File konfigurasi seperti `medio-be/.env.example` dan `medio-be/config/services.php` cukup dijelaskan dengan label `[DIREDAKSI]`.

## A. Ringkasan Eksekutif Project

| Aspek | Hasil Audit |
|---|---|
| Nama project | Optik Medio |
| Jenis sistem | Sistem e-commerce optik berbasis web |
| Tujuan sistem | Menyediakan katalog produk optik, proses keranjang, checkout, pembayaran, pengiriman, riwayat pesanan, komplain, dan panel admin |
| Aktor utama | Pelanggan, admin, Xendit, RajaOngkir |
| Frontend | Vue 3, Vite, TypeScript, Tailwind CSS, Pinia, Vue Router, Axios. Bukti: `medio-fe/package.json`, `medio-fe/src/router/index.ts`, `medio-fe/src/core/api/axiosclient.ts` |
| Backend | Laravel, PHP 8.3, Laravel Sanctum, Filament, Xendit SDK. Bukti: `medio-be/composer.json`, `medio-be/routes/api.php`, `medio-be/config/sanctum.php` |
| Database | MySQL dengan migration untuk users, products, carts, orders, payments, shipping, reviews, wishlists, complains, affiliate, dan admin data. Bukti: `medio-be/database/migrations/` |
| Integrasi pihak ketiga | Xendit untuk invoice/payment webhook dan RajaOngkir untuk wilayah serta ongkos kirim. Bukti: `medio-be/app/Services/XenditService.php`, `medio-be/app/Http/Controllers/API/WebhookController.php`, `medio-be/app/Services/RajaOngkirService.php`, `medio-be/app/Http/Controllers/API/ShippingController.php` |
| Batasan laporan | Jangan tampilkan `.env`, secret key, token webhook, IP internal, URL production, atau data transaksi/pelanggan asli |

Fitur utama yang benar-benar ditemukan: katalog produk, detail produk, keranjang, checkout, waiting payment, login/register/OTP, profil, alamat, wishlist, share wishlist, komparasi produk, ulasan produk, pengaduan, tracking pesanan, riwayat pesanan, pembayaran Xendit, ongkir RajaOngkir, panel admin Filament, dashboard widget, dan pengujian otomatis backend.

## B. Checklist Kesesuaian Laporan dengan Repository

| No | Bagian Laporan | Klaim/Fitur | Bukti di Repository | Status | Catatan Revisi |
|---:|---|---|---|---|---|
| 1 | Katalog produk | Pelanggan melihat daftar produk | `medio-fe/src/views/Product.vue`, `medio-fe/src/repositories/ProductRepository.ts`, `medio-be/app/Http/Controllers/API/ProductController.php`, `medio-be/routes/api.php` | Sesuai | Jelaskan filter, brand, search suggestion jika dibahas |
| 2 | Detail produk | Pelanggan melihat detail produk | `medio-fe/src/views/ProductDetail.vue`, `GET api/products/{slug}` di `medio-be/routes/api.php` | Sesuai | Tambahkan relasi gambar, varian, review dari `Product.php` |
| 3 | Keranjang | Pelanggan mengelola cart | `medio-fe/src/views/CartView.vue`, `medio-fe/src/stores/cartStore.ts`, `medio-be/app/Http/Controllers/API/CartController.php` | Sesuai | Jelaskan sync cart ke backend |
| 4 | Checkout | Pelanggan membuat order | `medio-fe/src/views/checkout/CheckoutView.vue`, `POST api/orders`, `POST api/orders/calculate`, `medio-be/app/Http/Controllers/API/OrderController.php` | Sesuai | Bahas validasi stok, alamat, payment method, ongkir |
| 5 | Waiting payment | Halaman menunggu pembayaran | `medio-fe/src/views/checkout/WaitingPayment.vue`, `medio-fe/src/repositories/OrderRepository.ts`, `GET api/orders/{id}/payment-status` | Sesuai | Jelaskan polling/sync status pembayaran |
| 6 | Login/register pelanggan | Autentikasi pelanggan | `medio-fe/src/views/Login.vue`, `medio-fe/src/views/Register.vue`, `medio-fe/src/repositories/AuthRepository.ts`, `medio-be/app/Http/Controllers/API/AuthController.php` | Sesuai | Sertakan OTP karena ada `verify-otp` dan `resend-otp` |
| 7 | Profil pelanggan | Profil, alamat, pesanan, wishlist | `medio-fe/src/views/Profile.vue`, `PATCH api/auth/profile`, `api/addresses`, `api/orders` | Sesuai | Profil menjadi container beberapa tab |
| 8 | Wishlist | Simpan dan bagikan wishlist | `medio-fe/src/stores/wishlistStore.ts`, `api/wishlist`, `api/wishlist/share`, `medio-be/app/Http/Controllers/API/WishlistController.php` | Sesuai | Tambahkan share link sebagai fitur pendukung |
| 9 | Komparasi produk | Membandingkan produk | `medio-fe/src/views/ProductCompare.vue`, `medio-fe/src/stores/compareStore.ts`, `POST api/products/compare` | Sesuai | Jelaskan minimal dua produk untuk compare |
| 10 | Ulasan | Review produk | `medio-fe/src/repositories/ReviewRepository.ts`, `api/products/{slug}/reviews`, `api/reviews`, `medio-be/app/Models/ProductReview.php` | Sesuai | Ada test `ReviewWithPhotoTest.php` |
| 11 | Pengaduan | Komplain pesanan | `medio-fe/src/views/Complaint.vue`, `medio-fe/src/views/ComplaintDetail.vue`, `medio-be/app/Http/Controllers/API/ComplainController.php`, `medio-be/app/Models/Complain.php` | Sesuai | Jelaskan status komplain secara ringkas |
| 12 | Tracking pesanan | Pelacakan status order | `medio-fe/src/views/Tracking.vue`, `GET api/orders/{id}/tracking`, `medio-fe/src/composables/useOrderStatus.ts` | Sesuai | Tracking internal status/order log, bukan bukti cek resi eksternal |
| 13 | Admin produk | CRUD produk admin | `medio-be/app/Filament/Resources/ProductResource.php` | Sesuai | Tambahkan gambar, varian, stok bila relevan |
| 14 | Admin kategori | CRUD kategori admin | `medio-be/app/Filament/Resources/CategoryResource.php` | Sesuai | Cocok untuk diagram admin |
| 15 | Admin pesanan | Kelola order | `medio-be/app/Filament/Resources/OrderResource.php` | Sesuai | Bahas perubahan status dan log order |
| 16 | Admin pembayaran | Kelola payment | `medio-be/app/Filament/Resources/PaymentResource.php`, `PaymentMethodResource.php`, `Banks/BankResource.php` | Sesuai | Pisahkan payment record, payment method, dan bank |
| 17 | Admin pengiriman | Ekspedisi dan ongkir | `medio-be/app/Filament/Resources/ExpeditionResource.php`, `ShippingRateResource.php` | Sesuai sebagian | Tidak ditemukan model/resource `Shipment`; sebut sebagai pengelolaan ekspedisi/ongkir, bukan modul shipment terpisah |
| 18 | Integrasi RajaOngkir | Ambil wilayah dan hitung ongkir | `RajaOngkirService.php`, `ShippingController.php`, `ShippingRepository.ts`, `api/shipping/*` | Sesuai | Jangan tampilkan API key |
| 19 | Integrasi Xendit | Invoice, webhook, sync status | `XenditService.php`, `WebhookController.php`, `XenditWebhookTest.php`, `api/webhook/xendit` | Sesuai | Jangan tampilkan secret key/token webhook |
| 20 | Pengujian black-box | Pengujian fitur dari sisi input-output | `medio-be/tests/Feature/*` dan rancangan manual di laporan | Perlu Revisi | Bedakan test otomatis repository dan skenario black-box manual laporan |

## C. Bagian yang Perlu Ditambahkan ke Laporan

| Bagian | Yang Perlu Ditambahkan | Alasan | Data Repository | Rekomendasi Isi | Prioritas |
|---|---|---|---|---|---|
| Halaman awal | Pernyataan studi kasus mandiri | Menghindari pembahasan sistem internal perusahaan/klien | `README.md`, struktur `medio-fe`, `medio-be` | Jelaskan Optik Medio sebagai studi kasus e-commerce optik | Wajib |
| BAB I | Rumusan masalah berbasis pemeliharaan e-commerce | Selaras dengan aktivitas magang: issue, debugging, testing | `routes/api.php`, controller, tests | Rumusan: bagaimana e-commerce dibangun, diuji, dan diintegrasikan | Wajib |
| BAB I | Batasan masalah dan kerahasiaan | Mencegah kebocoran data sensitif | `.env.example`, `config/services.php` | Secret dan data asli direpresentasikan sebagai `[DIREDAKSI]` | Wajib |
| BAB II | Uraian tools kerja | Menghubungkan magang dengan praktik development | `package.json`, `composer.json` | Git, VS Code, browser, Laravel, Vue, Filament | Disarankan |
| BAB II | Alur development sebelum production | Sesuai konteks magang | `tests/Feature`, route, service | Analisis issue, debugging, testing, validasi, review | Wajib |
| BAB III | Arsitektur sistem | Menjadi inti hasil teknis | `router/index.ts`, `routes/api.php`, Filament resources | Frontend Vue, API Laravel, DB MySQL, Filament, Xendit, RajaOngkir | Wajib |
| BAB III | Diagram DFD dan ERD | Membuktikan pemahaman sistem | migrations, models | DFD Level 0-2 dan ERD dari migration | Wajib |
| BAB III | Alur checkout dan payment | Proses bisnis utama | `OrderController.php`, `XenditService.php`, `WaitingPayment.vue` | Jelaskan order dibuat, payment record, invoice, webhook, status | Wajib |
| BAB III | Alur pengiriman dan ongkir | Integrasi utama selain payment | `ShippingController.php`, `RajaOngkirService.php`, `ShippingRepository.ts` | Wilayah, ekspedisi, service, biaya, estimasi | Wajib |
| BAB III | Tabel status order/payment | Agar status tidak ambigu | `Order.php`, `Payment.php`, `WebhookController.php` | unpaid, paid, processing, shipped, delivered, completed, cancelled; pending/success/failed/cancelled/expired | Wajib |
| BAB III | Pengujian black-box | Bukti validasi fitur | `tests/Feature/*` | Tabel input, langkah, expected, actual, status | Wajib |
| BAB IV | Kesimpulan teknis | Menutup ketercapaian magang | Seluruh audit | Kesimpulan pemahaman full-stack e-commerce dan integrasi | Wajib |
| Daftar Pustaka | Referensi framework dan integrasi | Mendukung akademik | Laravel, Vue, Filament, Xendit, RajaOngkir docs | Cantumkan dokumentasi resmi, bukan secret/internal docs | Disarankan |
| Lampiran | Screenshot dan bukti visual | Memperkuat BAB III | halaman frontend dan panel admin | Screenshot katalog, checkout, payment, tracking, admin resources | Wajib |

## D. Aktor dan Hak Akses Sistem

| Aktor | Deskripsi | Hak Akses/Fungsi | Bukti File |
|---|---|---|---|
| Pelanggan | Pengguna yang berbelanja produk optik | Register/login, melihat produk, cart, checkout, payment, wishlist, compare, review, komplain, tracking | `medio-fe/src/router/index.ts`, `AuthRepository.ts`, `CartController.php`, `OrderController.php`, `WishlistController.php` |
| Admin | Pengelola sistem melalui Filament | Mengelola produk, kategori, order, payment, ekspedisi, ongkir, user, review, komplain, dashboard | `medio-be/app/Filament/Resources/*Resource.php`, `medio-be/app/Filament/Widgets/*Widget.php` |
| Xendit | Sistem eksternal pembayaran | Membuat invoice, mengirim webhook status pembayaran, sinkronisasi status payment/order | `XenditService.php`, `WebhookController.php`, `XenditWebhookTest.php` |
| RajaOngkir | Sistem eksternal ongkos kirim/wilayah | Menyediakan provinsi, kota, distrik, dan perhitungan ongkir | `RajaOngkirService.php`, `ShippingController.php`, `ShippingRepository.ts` |

## E. Alur Utama Sistem

1. Alur pelanggan melihat produk sampai checkout: pelanggan membuka `Product.vue`, frontend memanggil `ProductRepository.ts`, backend `ProductController.php` mengirim katalog, pelanggan membuka `ProductDetail.vue`, menambahkan item ke `cartStore.ts`, membuka `CartView.vue`, lalu `CheckoutView.vue` mengirim order ke `OrderController.php`.
2. Alur pelanggan melakukan pembayaran Xendit: `OrderController.php` membuat order dan payment, `XenditService.php` membuat invoice dengan `external_id = order_number`, frontend menampilkan `WaitingPayment.vue`, backend menerima `WebhookController.php@xendit`, lalu status `payments` dan `orders` diperbarui.
3. Alur pelanggan memilih ekspedisi dan ongkos kirim RajaOngkir: `ShippingRepository.ts` memanggil `api/shipping/provinces`, `cities`, `districts`, dan `cost`; `ShippingController.php` meneruskan ke `RajaOngkirService.php`; hasil ongkir dipakai di checkout.
4. Alur riwayat/status pesanan: frontend `Profile.vue`, `OrderDetail.vue`, dan `Tracking.vue` memanggil `OrderRepository.ts`; backend menyediakan `GET api/orders`, `GET api/orders/{id}`, dan `GET api/orders/{id}/tracking`.
5. Alur admin mengelola produk: admin masuk Filament, menggunakan `ProductResource.php`, data disimpan ke tabel `products`, `product_variants`, `product_images`, dan terkait kategori.
6. Alur admin mengelola pesanan: `OrderResource.php` mengelola status order, `Order.php` mencatat perubahan status ke `order_logs`.
7. Alur perubahan status pesanan: `Order.php` menangani status update, `Payment.php` dapat mengubah order menjadi paid/cancelled, dan `WebhookController.php` mencatat update via Xendit.
8. Alur perubahan status pembayaran: payment awal pending/unpaid, webhook/sync Xendit mengubah status menjadi success/failed/expired/cancelled, lalu order ikut berubah.
9. Alur data antarkomponen: Vue 3 frontend -> Axios API client -> Laravel routes/api.php -> controller/service -> MySQL migration/model -> Filament admin; Xendit dan RajaOngkir terhubung melalui service backend.

## F. Rekomendasi Diagram yang Harus Dibuat

| Diagram | Tujuan | Data yang Dipakai | Rekomendasi Bentuk |
|---|---|---|---|
| Arsitektur sistem Optik Medio | Menjelaskan komponen besar | `medio-fe`, `medio-be`, MySQL, Xendit, RajaOngkir, Filament | Box diagram 5 komponen |
| Flowchart pelanggan | Menggambarkan belanja end-to-end | router, repository, order controller | Produk -> cart -> checkout -> payment -> tracking |
| Flowchart admin | Menggambarkan pengelolaan admin | Filament resources | Login admin -> kelola master data -> order/payment |
| DFD Level 0 | Konteks sistem | aktor dan endpoint utama | Pelanggan/Admin/Xendit/RajaOngkir ke Sistem Optik Medio |
| DFD Level 1 | Dekomposisi proses | auth, produk, cart, checkout, payment, shipping | 6-8 proses utama |
| DFD Level 2 checkout | Detail proses checkout | `OrderController.php`, `ShippingController.php`, `XenditService.php` | Validasi cart -> ongkir -> order -> invoice |
| ERD | Relasi database | `database/migrations`, models | users, products, carts, orders, payments, shipping, reviews |
| Alur RajaOngkir | Integrasi ongkir | `RajaOngkirService.php` | Request wilayah/ongkir -> response biaya |
| Alur Xendit | Integrasi payment | `XenditService.php`, `WebhookController.php` | Create invoice -> waiting payment -> webhook |
| Status order/payment | State transition | `Order.php`, `Payment.php` | Diagram state sederhana |

## G. Tabel Simbol Flowchart dan DFD

| Simbol Flowchart | Fungsi | Contoh di Laporan |
|---|---|---|
| Terminator | Awal/akhir proses | Mulai checkout, selesai pembayaran |
| Process | Aktivitas sistem | Hitung ongkir, buat order, buat invoice |
| Decision | Percabangan | Stok cukup? Payment success? |
| Input/Output | Masukan/keluaran data | Data alamat, response ongkir |
| Flowline | Arah alur | Dari cart ke checkout |
| Database | Penyimpanan | Tabel orders, payments |

| Simbol DFD | Fungsi | Contoh di Laporan |
|---|---|---|
| External Entity | Aktor luar sistem | Pelanggan, Admin, Xendit, RajaOngkir |
| Process | Proses pengolahan data | Proses Checkout |
| Data Store | Penyimpanan data | Data Produk, Data Order |
| Data Flow | Aliran data | Data pembayaran, data ongkir |

## H. Rancangan DFD

DFD Level 0:

| Entitas | Aliran Masuk | Aliran Keluar | Bukti |
|---|---|---|---|
| Pelanggan | Login, cart, checkout, review, komplain | Produk, invoice, status order | `router/index.ts`, `routes/api.php` |
| Admin | Data master, update order/payment | Laporan admin, status pengelolaan | `app/Filament/Resources` |
| Xendit | Webhook status pembayaran | Request invoice/sync payment | `XenditService.php`, `WebhookController.php` |
| RajaOngkir | Data wilayah dan ongkir | Request provinsi/kota/distrik/cost | `RajaOngkirService.php` |

DFD Level 1 yang disarankan:

| Proses | Input | Output | Data Store | Bukti |
|---|---|---|---|---|
| Autentikasi | email, password, OTP | token/session, profil user | users, otp_codes | `AuthController.php`, `OtpCode.php` |
| Manajemen Produk | filter, slug, product ids | katalog, detail, compare | products, categories, reviews | `ProductController.php`, `Product.php` |
| Keranjang | product id, qty | cart item | carts, cart_items | `CartController.php`, `Cart.php` |
| Checkout | cart, alamat, ongkir, payment method | order, payment | orders, order_items, payments | `OrderController.php` |
| Pengiriman | lokasi, courier, weight | ongkir, layanan | expeditions, shipping_rates | `ShippingController.php`, `RajaOngkirService.php` |
| Komplain/Review | order id, rating, complaint | review/complaint record | product_reviews, complains | `ReviewController.php`, `ComplainController.php` |

DFD Level 2 Checkout:

1. Validasi user dan cart.
2. Validasi alamat atau metode pickup.
3. Ambil ongkir dari `RajaOngkirService.php` bila dikirim.
4. Hitung subtotal, diskon, loyalty, shipping protection, dan total.
5. Simpan `orders` dan `order_items`.
6. Simpan `payments`.
7. Jika payment method Xendit, buat invoice melalui `XenditService.php`.
8. Frontend menampilkan `WaitingPayment.vue`.
9. Webhook Xendit memperbarui status pembayaran dan pesanan.

## I. Rancangan ERD dan Database

Tabel inti yang perlu masuk ERD:

| Kelompok | Tabel | Bukti Migration/Model |
|---|---|---|
| User/Auth | users, personal_access_tokens, otp_codes, shipping_addresses | `2024_01_01_000000_create_users_table.php`, `create_personal_access_tokens_table.php`, `create_otp_codes_table.php`, `create_shipping_addresses_table.php` |
| Produk | categories, products, product_variants, product_images, product_reviews, product_compatibilities | `create_categories_table.php`, `create_products_table.php`, `Product.php`, `ProductReview.php` |
| Cart | carts, cart_items | `2026_05_12_100001_create_carts_table.php`, `Cart.php`, `CartItem.php` |
| Order | orders, order_items, order_logs, return_requests | `create_orders_table.php`, `create_order_items_table.php`, `create_order_logs_table.php`, `Order.php` |
| Payment | payments, payment_methods, banks | `create_payments_table.php`, `create_payment_methods_table.php`, `create_banks_table.php`, `Payment.php` |
| Shipping | expeditions, shipping_rates | `create_expeditions_table.php`, `create_shipping_rates_table.php`, `Expedition.php`, `ShippingRateResource.php` |
| Promo/Loyalty | discounts, discount_usages, promos, promo_usages, loyalty_point_logs | migration diskon/promo/loyalty |
| Wishlist/Complaint | wishlists, complains | `create_wishlists_and_loyalty_tables.php`, `create_complains_table.php` |
| Affiliate | user_affiliators, commissions, commission_details, referral_codes, referral_uses | migration affiliate dan commission |

Relasi utama:

- `users` memiliki banyak `orders`, `shipping_addresses`, `wishlists`, `product_reviews`, dan `complains`.
- `categories` memiliki banyak `products`.
- `products` memiliki banyak `product_variants`, `product_images`, `product_reviews`, dan `order_items`.
- `orders` memiliki banyak `order_items`, satu `payment`, satu `shipping_address`, dan banyak `order_logs`.
- `payments` milik satu `order` dan dapat mengacu ke `payment_methods`.
- `complains` mengacu ke `users` dan `orders`.
- `commissions` memiliki banyak `commission_details`, dan detail dapat mengacu ke `orders`.

## J. Status Pesanan, Pembayaran, dan Pengiriman

| Jenis Status | Nilai/Perilaku | Bukti |
|---|---|---|
| Pesanan | `unpaid`, `paid`, `processing`, `shipped`, `delivered`, `completed`, `cancelled` sesuai opsi/status flow di model | `medio-be/app/Models/Order.php` |
| Pembayaran | `pending`, `success`, `failed`, `cancelled`, `expired`; success mengubah order unpaid menjadi paid | `medio-be/app/Models/Payment.php`, `WebhookController.php`, `XenditService.php` |
| Pengiriman | Data kurir, service, ongkir, tracking number, shipped/delivered timestamp tersimpan di order | `Order.php`, `OrderController.php`, `Tracking.vue` |
| Catatan status | Perubahan status dicatat ke `order_logs` | `Order.php`, `WebhookController.php`, `2026_05_07_000019_create_order_logs_table.php` |

Catatan revisi: jangan menulis ada model `Shipment` karena `medio-be/app/Models/Shipment.php` tidak ditemukan. Gunakan istilah pengiriman berbasis field order, ekspedisi, dan shipping rate.

## K. Rancangan Endpoint Umum

| Kelompok | Endpoint Umum | Fungsi | Bukti |
|---|---|---|---|
| Auth | `POST /api/auth/login`, `POST /api/auth/register`, `POST /api/auth/verify-otp`, `POST /api/auth/logout`, `GET /api/auth/me`, `PATCH /api/auth/profile` | Autentikasi dan profil | `routes/api.php`, `AuthController.php`, `AuthRepository.ts` |
| Produk | `GET /api/products`, `GET /api/products/{slug}`, `GET /api/products/filters`, `POST /api/products/compare` | Katalog, detail, filter, compare | `ProductController.php`, `ProductRepository.ts` |
| Cart | `GET /api/cart`, `POST /api/cart/items`, `PUT /api/cart/items/{itemId}`, `DELETE /api/cart/items/{itemId}`, `POST /api/cart/sync` | Kelola keranjang | `CartController.php` |
| Order | `POST /api/orders/calculate`, `POST /api/orders`, `GET /api/orders`, `GET /api/orders/{id}`, `GET /api/orders/{id}/tracking` | Checkout dan riwayat | `OrderController.php`, `OrderRepository.ts` |
| Payment | `GET /api/orders/{id}/payment-status`, `POST /api/orders/{id}/sync-payment`, `POST /api/webhook/xendit` | Waiting payment dan webhook | `OrderController.php`, `WebhookController.php` |
| Shipping | `GET /api/shipping/provinces`, `GET /api/shipping/cities`, `GET /api/shipping/districts`, `POST /api/shipping/cost` | Wilayah dan ongkir | `ShippingController.php`, `ShippingRepository.ts` |
| Wishlist | `GET /api/wishlist`, `POST /api/wishlist/toggle`, `POST /api/wishlist/share`, `GET /api/wishlist/shared/{token}` | Wishlist dan share | `WishlistController.php`, `wishlistStore.ts` |
| Review | `GET /api/products/{slug}/reviews`, `POST /api/reviews`, `DELETE /api/reviews/{id}` | Ulasan produk | `ReviewController.php`, `ReviewRepository.ts` |
| Complaint | `GET /api/complaints`, `POST /api/complaints`, `GET /api/complaints/{id}` | Pengaduan | `ComplainController.php`, `ComplaintRepository.ts` |

## L. Integrasi RajaOngkir

Alur integrasi:

1. Frontend checkout meminta daftar provinsi/kota/distrik melalui `ShippingRepository.ts`.
2. Backend menerima request di `ShippingController.php`.
3. Controller memanggil `RajaOngkirService.php`.
4. Service membaca konfigurasi `[DIREDAKSI]` dari `config/services.php`.
5. Service meminta data wilayah atau biaya ke API RajaOngkir/Komerce.
6. Backend mengembalikan daftar layanan, biaya, dan estimasi ke frontend.
7. Checkout memakai ongkir dalam perhitungan total order di `OrderController.php`.

| Input | Proses | Output | Bukti |
|---|---|---|---|
| province id | `getCities()` | daftar kota | `RajaOngkirService.php`, `ShippingController.php` |
| city id | `getDistricts()` | daftar distrik | `RajaOngkirService.php` |
| destination, weight, courier | `calculateAllCouriers()` | service, cost, etd | `RajaOngkirService.php`, `POST /api/shipping/cost` |

Caption gambar: **Gambar X. Alur integrasi RajaOngkir pada proses checkout Optik Medio, mulai dari pemilihan alamat pelanggan, permintaan ongkos kirim ke backend Laravel, pemanggilan service RajaOngkir, hingga pengembalian biaya pengiriman ke frontend Vue.**

## M. Integrasi Xendit

Alur integrasi:

1. Pelanggan menyelesaikan checkout.
2. `OrderController.php` membuat order dan payment.
3. `XenditService.php` membuat invoice dengan `external_id` berupa `order_number`, amount dari `total_price`, payer email, redirect success, dan redirect failure.
4. Frontend mengarahkan pengguna ke halaman pembayaran/waiting payment.
5. Xendit mengirim webhook ke `POST /api/webhook/xendit`.
6. `WebhookController.php` memvalidasi token webhook `[DIREDAKSI]`, mencatat event log, memperbarui `payments`, memperbarui `orders`, dan membuat `order_logs`.
7. `WaitingPayment.vue` atau `OrderRepository.ts` dapat mengecek `payment-status` atau `sync-payment`.

| Input | Proses | Output | Bukti |
|---|---|---|---|
| order_number, total_price, email | create invoice | invoice URL | `XenditService.php` |
| webhook payload external_id/status | validate dan update payment/order | payment success/failed/expired, order paid/cancelled | `WebhookController.php` |
| order id | sync payment/status polling | status pembayaran terbaru | `OrderController.php`, `OrderRepository.ts` |

Caption gambar: **Gambar X. Alur integrasi pembayaran Xendit pada Optik Medio, mulai dari pembuatan invoice setelah checkout, halaman menunggu pembayaran, callback webhook Xendit, hingga sinkronisasi status pembayaran dan pesanan.**

## N. Perancangan Antarmuka dan Screenshot yang Dibutuhkan

| Screenshot | File/Bukti | Catatan |
|---|---|---|
| Halaman katalog produk | `medio-fe/src/views/Product.vue` | Tampilkan filter/search bila ada |
| Halaman detail produk | `ProductDetail.vue` | Tampilkan gambar, varian, tombol cart |
| Halaman keranjang | `CartView.vue` | Tampilkan item, qty, subtotal |
| Halaman checkout | `views/checkout/CheckoutView.vue` | Tampilkan alamat, ongkir, payment method |
| Halaman waiting payment | `views/checkout/WaitingPayment.vue` | Tampilkan status pembayaran dan CTA |
| Login/register | `Login.vue`, `Register.vue` | Sertakan OTP bila masuk narasi |
| Profil pelanggan | `Profile.vue` | Tab profil/alamat/order/wishlist |
| Wishlist | `Profile.vue`, `wishlistStore.ts`, `SharedWishlist.vue` | Sertakan share wishlist |
| Komparasi | `ProductCompare.vue` | Tampilkan dua produk dibandingkan |
| Pengaduan | `Complaint.vue`, `ComplaintDetail.vue` | Tampilkan form dan detail |
| Riwayat pesanan | `Profile.vue`, `OrderRepository.ts` | Tampilkan daftar order |
| Tracking | `Tracking.vue` | Tampilkan status order dan tracking number |
| Admin produk | `ProductResource.php` | Screenshot Filament |
| Admin kategori | `CategoryResource.php` | Screenshot Filament |
| Admin pesanan | `OrderResource.php` | Screenshot Filament |
| Admin pembayaran | `PaymentResource.php` | Screenshot Filament |
| Admin pengiriman | `ExpeditionResource.php`, `ShippingRateResource.php` | Sebut sebagai ekspedisi/ongkir |
| Dashboard admin | `app/Filament/Widgets/*Widget.php` | Stats, recent orders, low stock |

## O. Pengujian Black-box

Test otomatis yang ditemukan: `AuthSessionTest.php`, `CartPersistenceTest.php`, `CheckoutControlTest.php`, `PaymentStatusTest.php`, `ProductDiscoveryTest.php`, `ProductFilterMetadataTest.php`, `ProductRecommendationsTest.php`, `ReviewWithPhotoTest.php`, `ShippingProtectionComplaintTest.php`, `WishlistShareTest.php`, `XenditWebhookTest.php`, `DeliveredOrderAutoCompletionTest.php`, `HealthEndpointTest.php`.

Rancangan black-box manual untuk laporan:

| No | Fitur | Skenario | Langkah Pengujian | Data Uji | Hasil Diharapkan | Bukti Repo |
|---:|---|---|---|---|---|---|
| 1 | Katalog | Produk tampil | Buka `/products` | filter kosong | Daftar produk muncul | `Product.vue`, `GET /api/products` |
| 2 | Detail produk | Detail tampil | Klik produk | slug valid | Nama, harga, gambar, varian tampil | `ProductDetail.vue`, `GET /api/products/{slug}` |
| 3 | Cart | Tambah item | Klik tambah ke keranjang | product id, qty 1 | Cart bertambah | `CartView.vue`, `CartController.php` |
| 4 | Checkout | Buat order | Isi alamat, ongkir, payment | cart valid | Order tersimpan | `CheckoutView.vue`, `OrderController.php` |
| 5 | Waiting payment | Status pending | Buka waiting payment | order unpaid | Status pembayaran tampil | `WaitingPayment.vue`, `payment-status` |
| 6 | Login | Login berhasil | Masukkan kredensial valid | email/password | User masuk aplikasi | `AuthRepository.ts`, `AuthController.php` |
| 7 | Wishlist | Toggle wishlist | Klik wishlist | product id | Produk masuk/keluar wishlist | `WishlistController.php` |
| 8 | Komparasi | Compare dua produk | Pilih dua produk | product ids | Tabel perbandingan tampil | `ProductCompare.vue`, `POST /api/products/compare` |
| 9 | Pengaduan | Buat komplain | Submit form komplain | order id, deskripsi | Komplain tersimpan | `ComplainController.php` |
| 10 | Admin produk | Tambah/edit produk | Admin buka Filament product | data produk | Produk tersimpan | `ProductResource.php` |
| 11 | Admin pesanan | Update status | Admin ubah status order | order id/status | Status dan log berubah | `OrderResource.php`, `Order.php` |
| 12 | Pembayaran Xendit | Webhook success | Kirim webhook valid | external_id/status PAID | Payment success, order paid | `WebhookController.php`, `XenditWebhookTest.php` |
| 13 | Ekspedisi RajaOngkir | Hitung ongkir | Pilih alamat/courier | district, weight | Biaya dan ETD tampil | `ShippingController.php`, `RajaOngkirService.php` |

Tambahkan kolom `Hasil Aktual`, `Status`, dan `Dokumentasi Screenshot` saat pengujian manual dilakukan.

## P. Kendala Teknis dan Solusi

| No | Kendala | Penyebab | Solusi | Status | Bukti File/Alur | Catatan Laporan |
|---:|---|---|---|---|---|---|
| 1 | Validasi alamat pengiriman | Checkout membutuhkan alamat/distrik valid | Validasi request dan endpoint shipping | Ada di repo | `ShippingController.php`, `OrderController.php` | Jelaskan sebagai kendala integrasi shipping |
| 2 | Respons RajaOngkir gagal/lambat | API eksternal bergantung jaringan/key | Error handling service dan fallback tampilan | Rekomendasi untuk laporan | `RajaOngkirService.php` | Jangan tampilkan API key |
| 3 | Status pembayaran belum sinkron | User dapat menutup halaman pembayaran | Webhook, polling status, dan sync payment | Ada di repo | `WebhookController.php`, `OrderRepository.ts` | Bahas waiting payment |
| 4 | Webhook Xendit perlu diamankan | Endpoint menerima callback eksternal | Token webhook dan allowed IP | Ada di repo | `config/services.php`, `WebhookController.php` | Secret wajib `[DIREDAKSI]` |
| 5 | Validasi stok produk | Checkout harus mencegah oversell | Action decrement/restore stock | Ada di repo | `DecrementProductStockAction.php`, `RestoreProductStockAction.php`, `OrderController.php` | Jelaskan sebagai kontrol transaksi |
| 6 | Pemisahan development/production | Konfigurasi eksternal berbeda | `.env.example` dan config service | Ada di repo | `.env.example`, `config/services.php` | Jangan tulis endpoint/key production |
| 7 | Tampilan mobile checkout | Checkout memuat banyak input | Uji responsive manual | Rekomendasi untuk laporan | `CheckoutView.vue` | Perlu screenshot mobile |

## Q. Evaluasi Hasil Magang

Melalui studi kasus Optik Medio, kegiatan magang dapat dievaluasi sebagai proses pembelajaran pemeliharaan dan pengembangan sistem e-commerce full-stack. Pada sisi frontend, mahasiswa memahami bagaimana Vue 3, Vue Router, Pinia, dan Axios digunakan untuk membangun katalog produk, keranjang, checkout, waiting payment, profil, wishlist, dan tracking. Pada sisi backend, mahasiswa memahami bagaimana Laravel menyediakan REST API, validasi request, model, migration, service integrasi, dan autentikasi Sanctum.

Pengalaman memahami issue/bug, debugging, pengujian, dan alur sebelum production tercermin dari adanya test otomatis pada `medio-be/tests/Feature`, route API, service Xendit/RajaOngkir, dan resource Filament. Studi kasus ini juga menunjukkan hubungan antara mata kuliah pemrograman web, basis data, rekayasa perangkat lunak, pengujian perangkat lunak, keamanan aplikasi, dan integrasi sistem. Data perusahaan/klien tetap tidak dibahas secara rinci; Optik Medio digunakan sebagai representasi mandiri untuk menjelaskan konsep teknis tanpa membuka informasi internal.

## R. Rekomendasi Revisi Laporan

| No | Lokasi Laporan | Masalah | Revisi yang Disarankan | Prioritas | Output |
|---:|---|---|---|---|---|
| 1 | BAB III | Arsitektur belum detail | Tambahkan arsitektur Vue-Laravel-MySQL-Filament-Xendit-RajaOngkir | Wajib | Diagram arsitektur |
| 2 | BAB III | DFD belum lengkap | Tambahkan DFD Level 0, Level 1, dan Level 2 checkout | Wajib | 3 diagram DFD |
| 3 | BAB III | ERD belum berbasis migration | Susun ERD dari migration dan model | Wajib | ERD |
| 4 | BAB III | Alur checkout belum teknis | Jelaskan cart, ongkir, order, payment, invoice | Wajib | Flowchart checkout |
| 5 | BAB III | Status order/payment belum dijelaskan | Tambahkan tabel status dan transisi | Wajib | Tabel status |
| 6 | BAB III | Integrasi Xendit perlu bukti | Tambahkan alur invoice, webhook, sync status | Wajib | Diagram Xendit |
| 7 | BAB III | Integrasi RajaOngkir perlu bukti | Tambahkan input-output wilayah/ongkir | Wajib | Diagram RajaOngkir |
| 8 | BAB III/Lampiran | Screenshot belum lengkap | Ambil screenshot frontend dan Filament | Wajib | Lampiran gambar |
| 9 | BAB III | Black-box masih umum | Isi tabel skenario berdasarkan fitur repo | Wajib | Tabel pengujian |
| 10 | BAB IV | Kesimpulan belum mengikat magang | Hubungkan debugging, testing, dev-production, dan studi kasus | Disarankan | Narasi evaluasi |

## S. Paket Output Siap Dikirim ke ChatGPT

1. Ringkasan project: Optik Medio adalah e-commerce optik berbasis Vue 3 frontend dan Laravel backend, dengan panel admin Filament, database MySQL, integrasi Xendit, dan integrasi RajaOngkir.
2. Fitur yang ditemukan: katalog produk, detail produk, cart, checkout, waiting payment, auth login/register/OTP, profil, alamat, wishlist/share, komparasi, review, komplain, tracking, riwayat order, admin produk/kategori/order/payment/ekspedisi, dashboard widgets.
3. Fitur yang tidak ditemukan: model/resource `Shipment` terpisah tidak ditemukan; pengiriman dikelola melalui field order, ekspedisi, dan shipping rate. Frontend tidak memanggil Xendit langsung; integrasi Xendit berada di backend.
4. Alur sistem: pelanggan berinteraksi dengan Vue, frontend memanggil API Laravel, backend memproses model/database, admin mengelola data via Filament, Xendit mengirim webhook pembayaran, RajaOngkir menyediakan ongkir.
5. Diagram yang harus dibuat: arsitektur sistem, flowchart pelanggan, flowchart admin, DFD Level 0, DFD Level 1, DFD Level 2 checkout, ERD, alur RajaOngkir, alur Xendit, status order/payment.
6. Database dan relasi: users, products, categories, carts, cart_items, orders, order_items, payments, shipping_addresses, expeditions, shipping_rates, product_reviews, wishlists, complains, order_logs, affiliate/commission tables.
7. Endpoint umum: auth, products, cart, orders, shipping, wishlist, review, complaints, webhook Xendit.
8. Integrasi RajaOngkir: `ShippingRepository.ts` -> `ShippingController.php` -> `RajaOngkirService.php` -> API eksternal -> biaya/ETD ke checkout.
9. Integrasi Xendit: `OrderController.php` -> `XenditService.php` membuat invoice -> `WaitingPayment.vue` menunggu -> `WebhookController.php` memperbarui payment/order.
10. Screenshot yang perlu diambil: katalog, detail, cart, checkout, waiting payment, login/register, profil, wishlist, compare, complaint, order history, tracking, admin product/category/order/payment/shipping/dashboard.
11. Tabel pengujian: gunakan skenario black-box pada bagian O, lalu isi hasil aktual dan screenshot setelah diuji manual.
12. Kendala dan solusi: validasi alamat, API RajaOngkir, sinkronisasi payment, keamanan webhook, validasi stok, pemisahan environment, responsive checkout.
13. Rekomendasi revisi laporan: perkuat BAB III dengan diagram, ERD, endpoint, integrasi, status, pengujian, dan lampiran visual; jaga semua secret tetap `[DIREDAKSI]`.
