# Requirements Document

## Introduction

Dokumen ini mendefinisikan standar audit untuk platform e-commerce Optik Medio — toko optik online berbasis Laravel 13 (backend) dan Vue 3 (frontend). Audit mencakup seluruh domain fungsional: autentikasi, produk, keranjang, checkout, pembayaran, pesanan, loyalitas, afiliasi, komplain, retur, pengiriman, dan panel admin. Setiap requirement menentukan **apa yang harus dicek**, **standar e-commerce yang harus dipenuhi**, dan **kriteria lulus** yang terukur.

---

## Glossary

- **Auditor**: Pihak yang melakukan pemeriksaan terhadap sistem.
- **System**: Platform e-commerce Optik Medio secara keseluruhan (backend Laravel + frontend Vue 3).
- **API**: Backend REST API yang diekspos oleh Laravel.
- **Admin Panel**: Antarmuka Filament yang digunakan oleh operator toko.
- **OTP**: One-Time Password 6 digit yang dikirim via email untuk verifikasi identitas.
- **OtpCode**: Record OTP yang tersimpan di database dengan masa berlaku 10 menit.
- **Order**: Entitas pesanan yang memiliki siklus status: `unpaid → paid → processing → shipped → delivered → completed`.
- **Payment**: Entitas pembayaran yang terhubung ke Order; provider dapat berupa `xendit` (online) atau manual (transfer bank).
- **LoyaltyPointLog**: Catatan perolehan dan pemakaian poin loyalitas pelanggan.
- **UserAffiliator**: Entitas afiliator dengan kode unik, status, dan persentase komisi.
- **Commission**: Entitas komisi afiliator yang terhubung ke pesanan.
- **Complain**: Entitas pengaduan pelanggan terkait pesanan.
- **ReturnRequest**: Entitas permintaan retur barang dari pelanggan.
- **LevelMember**: Tingkatan keanggotaan (Bronze, Silver, Gold, dst.) dengan persentase diskon.
- **Promo**: Entitas promosi dengan tipe `buy_x_get_y`, `transaction_discount`, atau `product_discount`.
- **Discount**: Kode diskon sekali pakai per pengguna.
- **StoreClose**: Entitas yang menandai toko sedang tutup sehingga checkout diblokir.
- **RajaOngkir**: Layanan pihak ketiga untuk kalkulasi ongkos kirim.
- **Xendit**: Payment gateway untuk pembayaran online.
- **Filament**: Framework admin panel yang digunakan di backend.
- **SoftDelete**: Mekanisme penghapusan lunak (data tidak benar-benar dihapus dari database).

---

## Requirements

### Requirement 1 — Audit Domain Autentikasi

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa seluruh alur autentikasi aman dan berfungsi sesuai standar, sehingga akun pelanggan terlindungi dari akses tidak sah.

#### Acceptance Criteria

1. THE System SHALL mengimplementasikan alur registrasi yang memvalidasi field `name`, `email` (unik), `password` (min 8 karakter, confirmed), dan `phone` (opsional) sebelum membuat akun.
2. WHEN pengguna mendaftar, THE System SHALL mengirimkan OTP 6 digit ke email pengguna dengan masa berlaku 10 menit.
3. WHEN pengguna melakukan login, THE System SHALL selalu mengirimkan OTP baru ke email dan mengembalikan respons `requires_otp: true` sebelum sesi aktif dibuat.
4. WHEN pengguna mencoba verifikasi OTP lebih dari 5 kali dalam satu sesi, THE System SHALL mengembalikan HTTP 429 dan menyertakan field `retry_after` dalam respons.
5. WHEN pengguna meminta kirim ulang OTP lebih dari 3 kali dalam 10 menit, THE System SHALL mengembalikan HTTP 429 tanpa mengirim OTP baru.
6. THE System SHALL menandai field `email_verified_at` pada tabel `users` hanya setelah OTP berhasil diverifikasi.
7. WHEN pengguna logout, THE System SHALL menginvalidasi sesi dan meregenerasi CSRF token.
8. THE System SHALL mengembalikan HTTP 401 atau 403 untuk semua endpoint yang memerlukan autentikasi ketika request tidak menyertakan sesi yang valid.
9. IF OTP yang dikirimkan sudah kadaluarsa atau sudah diverifikasi sebelumnya, THEN THE System SHALL mengembalikan HTTP 422 dengan pesan yang jelas.
10. THE System SHALL menyimpan OTP dalam tabel `otp_codes` dengan kolom `expires_at`, `verified_at`, dan `type`, bukan dalam plaintext di session atau cookie.

**Kriteria Lulus:**
- Semua 10 kriteria di atas terpenuhi tanpa pengecualian.
- Tidak ada endpoint autentikasi yang dapat diakses tanpa validasi OTP.
- Rate limiting OTP aktif dan terverifikasi melalui pengujian manual.

---

### Requirement 2 — Audit Domain Produk & Katalog

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa manajemen produk memenuhi standar e-commerce optik, sehingga data produk akurat dan konsisten di seluruh sistem.

#### Acceptance Criteria

1. THE System SHALL menyimpan setiap produk dengan atribut minimal: `name`, `price`, `stock`, `weight`, `category_id`, dan `is_prescription_required`.
2. WHEN stok produk bernilai 0, THE System SHALL mencegah penambahan produk tersebut ke pesanan baru dan mengembalikan pesan error yang informatif.
3. THE System SHALL mendukung produk dengan varian (`ProductVariant`) dan gambar ganda (`ProductImage`) dengan minimal satu gambar utama per produk.
4. WHEN produk memiliki `is_prescription_required = true`, THE System SHALL memvalidasi keberadaan data resep (`prescription`) pada item pesanan sebelum pesanan dibuat.
5. THE System SHALL mengimplementasikan SoftDelete pada model `Product` sehingga produk yang dihapus tidak muncul di katalog publik tetapi masih dapat direferensikan oleh pesanan lama.
6. THE System SHALL menyediakan endpoint pencarian dan filter produk berdasarkan kategori, merek, dan rentang harga.
7. WHEN stok produk dikurangi saat pesanan dibuat, THE System SHALL melakukan operasi decrement secara atomik untuk mencegah race condition pada stok.
8. THE System SHALL menyimpan ulasan produk (`ProductReview`) yang terhubung ke pesanan yang sudah selesai, bukan ke sembarang pengguna.

**Kriteria Lulus:**
- Validasi stok aktif dan tidak dapat di-bypass melalui API.
- Produk dengan resep wajib tidak dapat dipesan tanpa data resep.
- SoftDelete berfungsi: produk terhapus tidak muncul di endpoint publik.

---

### Requirement 3 — Audit Domain Keranjang Belanja

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa keranjang belanja mengelola item dengan benar termasuk skenario frame + lensa, sehingga pelanggan tidak mengalami inkonsistensi data saat checkout.

#### Acceptance Criteria

1. THE System SHALL mempertahankan state keranjang di sisi klien (Pinia store) dan menyinkronkannya dengan data produk terkini dari API sebelum proses checkout dimulai.
2. WHEN pelanggan menambahkan lensa kontak yang terhubung ke frame (`linked_item_index`), THE System SHALL memperlakukan pasangan frame-lensa sebagai satu unit logis dalam kalkulasi berat dan harga.
3. THE System SHALL menghitung total keranjang (`cartTotal`) secara konsisten antara kalkulasi di frontend dan kalkulasi di endpoint `POST /orders/calculate` di backend.
4. WHEN keranjang kosong, THE System SHALL mencegah navigasi ke halaman checkout dan menampilkan pesan yang sesuai.
5. THE System SHALL membersihkan state keranjang (`clearCart`) setelah pesanan berhasil dibuat.
6. IF produk dalam keranjang tidak lagi tersedia (stok habis atau dihapus) saat checkout, THEN THE System SHALL menginformasikan pelanggan dan mencegah pembuatan pesanan.

**Kriteria Lulus:**
- Total harga di frontend identik dengan total yang dikembalikan oleh endpoint kalkulasi backend (selisih 0).
- Skenario frame + lensa menghasilkan satu pesanan dengan dua item yang benar.
- Keranjang kosong setelah pesanan berhasil.

---

### Requirement 4 — Audit Domain Checkout & Kalkulasi Harga

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa kalkulasi harga di checkout akurat dan tidak dapat dimanipulasi, sehingga total yang dibayar pelanggan sesuai dengan yang tertera.

#### Acceptance Criteria

1. THE System SHALL menghitung `total_price` dengan formula: `subtotal + shipping_cost - discount_amount - promo_discount_amount - level_discount_amount - loyalty_discount_amount`, dengan nilai minimum 0.
2. THE System SHALL memvalidasi bahwa `discount_id` dan `promo_id` tidak dapat digunakan bersamaan dalam satu pesanan; jika keduanya dikirim, THE System SHALL mengembalikan HTTP 422.
3. WHEN kode diskon digunakan, THE System SHALL memvalidasi bahwa kode tersebut belum pernah digunakan oleh pengguna yang sama sebelumnya.
4. WHEN promo `buy_x_get_y` berlaku, THE System SHALL menambahkan item gratis ke pesanan dengan `product_price = 0` dan tidak mengurangi stok item gratis tersebut.
5. THE System SHALL membatasi penggunaan poin loyalitas maksimal 5% dari subtotal per transaksi, dengan konversi 1 poin = Rp 1.000.
6. WHEN toko dalam status tutup (`StoreClose`), THE System SHALL memblokir pembuatan pesanan baru dan menampilkan banner peringatan di halaman checkout.
7. THE System SHALL memvalidasi `shipping_address_id` milik pengguna yang sedang login sebelum pesanan dibuat.
8. THE System SHALL menyimpan semua komponen harga secara terpisah di tabel `orders` (`subtotal`, `shipping_cost`, `discount_amount`, `promo_discount_amount`, `level_discount_amount`, `loyalty_discount_amount`) untuk keperluan audit finansial.
9. WHEN pelanggan memilih layanan pengiriman, THE System SHALL menggunakan data ongkir dari RajaOngkir berdasarkan kecamatan tujuan dan total berat item (dalam gram).
10. THE System SHALL memvalidasi bahwa `shipping_address_id` yang dikirim dalam payload pesanan benar-benar milik pengguna yang sedang login.

**Kriteria Lulus:**
- Formula kalkulasi harga diverifikasi dengan minimal 5 skenario kombinasi diskon berbeda.
- Tidak ada celah untuk menggunakan diskon dan promo secara bersamaan.
- Poin loyalitas tidak dapat melebihi batas 5% subtotal.
- Checkout diblokir saat toko tutup.

---

### Requirement 5 — Audit Domain Pembayaran

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa alur pembayaran — baik online (Xendit) maupun manual (transfer bank) — aman, konsisten, dan tidak dapat dimanipulasi, sehingga status pesanan selalu mencerminkan kondisi pembayaran yang sebenarnya.

#### Acceptance Criteria

1. WHEN pembayaran Xendit berhasil, THE System SHALL memperbarui status `Payment` menjadi `success` dan status `Order` menjadi `paid` secara atomik melalui webhook atau sync.
2. THE System SHALL memvalidasi signature/token webhook Xendit sebelum memproses perubahan status pembayaran.
3. WHEN pelanggan mengunggah bukti pembayaran manual, THE System SHALL memvalidasi format file (jpg, jpeg, png, webp, pdf) dan ukuran maksimal 4 MB.
4. THE System SHALL mencegah pengunggahan bukti pembayaran untuk pesanan dengan status `cancelled`, `refunded`, atau `delivered`.
5. THE System SHALL mencegah pengunggahan bukti pembayaran untuk pesanan yang sudah diverifikasi (`is_payment_verified = true`).
6. WHEN admin memverifikasi pembayaran manual, THE System SHALL secara otomatis mengubah status `Order` menjadi `paid` dan mencatat `payment_verified_at` serta `verified_by`.
7. THE System SHALL menyimpan respons mentah dari Xendit (`raw_response`) di tabel `payments` untuk keperluan rekonsiliasi.
8. WHEN status pembayaran berubah menjadi `failed`, `cancelled`, atau `expired`, THE System SHALL secara otomatis mengubah status `Order` menjadi `cancelled`.
9. THE System SHALL mencegah pelanggan mengakses endpoint upload bukti bayar untuk pesanan milik pengguna lain (validasi kepemilikan).
10. THE System SHALL menyediakan endpoint `syncPayment` yang hanya berlaku untuk pesanan dengan provider `xendit`, dan mengembalikan HTTP 422 untuk metode pembayaran lain.

**Kriteria Lulus:**
- Webhook Xendit diverifikasi dengan signature yang valid.
- Status pesanan dan pembayaran selalu konsisten (tidak ada pesanan `paid` dengan payment `pending`).
- Upload bukti bayar hanya berhasil untuk pesanan yang valid dan belum diverifikasi.

---

### Requirement 6 — Audit Domain Pesanan & Siklus Status

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa siklus hidup pesanan terdokumentasi dengan baik dan transisi status berjalan sesuai aturan bisnis, sehingga tidak ada pesanan yang berada dalam status yang tidak konsisten.

#### Acceptance Criteria

1. THE System SHALL mencatat setiap perubahan status pesanan ke tabel `order_logs` dengan field `event_type`, `previous_status`, `current_status`, `acted_by`, dan `created_at`.
2. THE System SHALL mencatat event `order_created`, `status_changed`, `tracking_updated`, `payment_proof_uploaded`, dan `payment_verified` ke `order_logs`.
3. WHEN pelanggan mengkonfirmasi penerimaan pesanan (`confirmDelivery`), THE System SHALL memvalidasi bahwa status pesanan adalah `shipped` sebelum mengubahnya menjadi `delivered`.
4. WHEN pesanan dikonfirmasi diterima, THE System SHALL menghitung dan mengkredit poin loyalitas dengan formula: `max(1, floor(total_price / 10000))` poin.
5. THE System SHALL mengimplementasikan SoftDelete pada model `Order` sehingga pesanan yang dihapus tidak hilang dari database.
6. THE System SHALL memastikan bahwa pelanggan hanya dapat mengakses detail pesanan miliknya sendiri; akses ke pesanan pengguna lain harus mengembalikan HTTP 403.
7. WHEN nomor resi diperbarui oleh admin, THE System SHALL mencatat event `tracking_updated` di `order_logs`.
8. THE System SHALL menyediakan endpoint tracking yang mengembalikan seluruh riwayat `order_logs` dalam urutan kronologis.
9. THE System SHALL memvalidasi bahwa `order_number` bersifat unik di seluruh tabel `orders`.

**Kriteria Lulus:**
- Setiap perubahan status pesanan memiliki entri di `order_logs`.
- Konfirmasi penerimaan hanya berhasil dari status `shipped`.
- Poin loyalitas dikreditkan dengan nilai yang benar setelah konfirmasi.
- Pelanggan tidak dapat mengakses pesanan pengguna lain.

---

### Requirement 7 — Audit Domain Loyalitas & Keanggotaan

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa sistem poin loyalitas dan level keanggotaan berfungsi akurat dan tidak dapat dieksploitasi, sehingga pelanggan mendapatkan manfaat yang sesuai.

#### Acceptance Criteria

1. THE System SHALL menyimpan setiap transaksi poin (perolehan dan pemakaian) di tabel `loyalty_point_logs` dengan field `user_id`, `order_id`, `points`, `type`, dan `description`.
2. THE System SHALL memastikan saldo poin loyalitas pengguna (`loyalty_points` di tabel `users`) selalu konsisten dengan total dari `loyalty_point_logs`.
3. WHEN poin loyalitas digunakan dalam pesanan, THE System SHALL mengurangi saldo poin pengguna secara atomik dalam satu database transaction.
4. THE System SHALL mencegah penggunaan poin loyalitas melebihi saldo yang dimiliki pengguna.
5. THE System SHALL menerapkan diskon level keanggotaan (`level_discount_amount`) berdasarkan `discount_percentage` dari `LevelMember` yang aktif (`effective_until IS NULL`).
6. WHEN level keanggotaan pengguna berubah, THE System SHALL mencatat perubahan tersebut dan memperbarui diskon yang berlaku pada kalkulasi pesanan berikutnya.
7. THE System SHALL memastikan bahwa hanya satu level keanggotaan yang aktif per pengguna pada satu waktu.

**Kriteria Lulus:**
- Saldo poin di `users.loyalty_points` konsisten dengan sum dari `loyalty_point_logs`.
- Poin tidak dapat digunakan melebihi saldo.
- Diskon level keanggotaan diterapkan dengan benar pada kalkulasi checkout.

---

### Requirement 8 — Audit Domain Afiliasi & Komisi

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa sistem afiliasi mengelola kode referral, persetujuan afiliator, dan kalkulasi komisi dengan benar, sehingga tidak ada komisi yang salah hitung atau dibayarkan kepada afiliator yang tidak aktif.

#### Acceptance Criteria

1. THE System SHALL memastikan setiap `affiliate_code` di tabel `user_affiliators` bersifat unik.
2. WHEN pengguna mendaftar sebagai afiliator, THE System SHALL membuat entri `UserAffiliator` dengan status `pending` dan `commission_rate_percentage` default 5%.
3. THE System SHALL mencegah afiliator dengan status selain `approved` dari mendapatkan komisi baru.
4. WHEN admin menyetujui afiliator, THE System SHALL mencatat `approved_by` dan `approved_at`.
5. WHEN admin menolak afiliator, THE System SHALL mencatat `rejected_at` dan `rejection_reason`.
6. THE System SHALL memastikan bahwa komisi hanya dihitung untuk pesanan yang sudah berstatus `completed` atau `delivered`, bukan untuk pesanan yang masih `unpaid` atau `cancelled`.
7. THE System SHALL menyimpan detail komisi di tabel `commission_details` yang terhubung ke `commissions` dan `orders`.

**Kriteria Lulus:**
- Kode afiliasi unik dan tidak dapat duplikat.
- Afiliator dengan status `pending` atau `rejected` tidak mendapatkan komisi.
- Komisi hanya dihitung untuk pesanan yang sudah selesai.

---

### Requirement 9 — Audit Domain Komplain & Retur

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa alur penanganan komplain dan retur memiliki visibilitas penuh dan SLA yang terukur, sehingga tidak ada komplain yang terabaikan.

#### Acceptance Criteria

1. THE System SHALL memastikan setiap `Complain` terhubung ke `user_id` dan opsional ke `order_id`.
2. THE System SHALL mendukung transisi status komplain: `open → in_progress → resolved` atau `open → rejected`.
3. WHEN admin menyelesaikan komplain, THE System SHALL mencatat `resolved_at` dan `handled_by`.
4. THE System SHALL mengirimkan notifikasi email kepada pelanggan ketika admin memberikan respons terhadap komplain (`ComplainResponseMail`).
5. THE System SHALL memastikan bahwa `ReturnRequest` hanya dapat dibuat untuk pesanan dengan status `delivered` atau `completed`.
6. THE System SHALL menyimpan lampiran komplain (`attachment_path`) dan memvalidasi bahwa file yang diunggah adalah format gambar atau dokumen yang diizinkan.
7. THE System SHALL menyediakan filter dan pencarian komplain di Admin Panel berdasarkan status, pelanggan, dan nomor pesanan.

**Kriteria Lulus:**
- Semua komplain memiliki status yang valid dan terdokumentasi.
- Email notifikasi terkirim saat admin merespons.
- Retur hanya dapat dibuat untuk pesanan yang sudah diterima.

---

### Requirement 10 — Audit Domain Pengiriman

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa kalkulasi ongkos kirim akurat dan integrasi RajaOngkir berfungsi dengan benar, sehingga pelanggan tidak dikenakan biaya pengiriman yang salah.

#### Acceptance Criteria

1. THE System SHALL menghitung ongkos kirim berdasarkan kecamatan tujuan (`district_id`) dan total berat item dalam gram menggunakan API RajaOngkir.
2. THE System SHALL mengembalikan daftar layanan pengiriman yang tersedia beserta estimasi waktu (`etd`) dan biaya untuk setiap layanan.
3. WHEN berat total item di keranjang bernilai 0 atau tidak valid, THE System SHALL menampilkan pesan error yang informatif dan tidak memanggil API RajaOngkir.
4. THE System SHALL menyimpan `courier`, `courier_service`, dan `shipping_cost` yang dipilih pelanggan ke tabel `orders`.
5. THE System SHALL memvalidasi bahwa `shipping_cost` yang dikirim dalam payload pesanan konsisten dengan kalkulasi yang dilakukan oleh backend, bukan hanya mempercayai nilai dari frontend.
6. THE System SHALL mendukung konfigurasi `ShippingRate` statis sebagai fallback jika API RajaOngkir tidak tersedia.

**Kriteria Lulus:**
- Ongkos kirim yang tersimpan di pesanan konsisten dengan yang ditampilkan di checkout.
- Berat 0 tidak menyebabkan error tak tertangani.
- Data kurir dan layanan tersimpan dengan benar di tabel `orders`.

---

### Requirement 11 — Audit Domain Admin Panel (Filament)

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa Admin Panel memiliki kontrol akses yang tepat dan semua operasi kritis tercatat, sehingga tidak ada tindakan admin yang tidak terotorisasi.

#### Acceptance Criteria

1. THE System SHALL membatasi akses Admin Panel hanya untuk pengguna dengan role admin yang terautentikasi.
2. THE System SHALL menyediakan resource Filament untuk semua entitas utama: Order, Product, Category, User, Complain, Commission, Discount, Promo, Banner, Expedition, LevelMember, ReturnRequest, dan AppSetting.
3. WHEN admin memverifikasi pembayaran manual, THE System SHALL mencatat `verified_by` (ID admin) dan `payment_verified_at` di tabel `orders`.
4. THE System SHALL menyediakan fitur pencarian dan filter pada semua tabel di Admin Panel untuk memudahkan operasional.
5. THE System SHALL memastikan bahwa field sensitif (seperti password dan data resep) tidak ditampilkan dalam bentuk plaintext di Admin Panel.
6. WHEN admin mengubah status pesanan, THE System SHALL memicu pencatatan `order_logs` secara otomatis melalui model observer atau event.
7. THE System SHALL menyediakan halaman `AppSetting` untuk konfigurasi toko (nama toko, kontak, dll.) tanpa memerlukan deployment ulang.
8. THE System SHALL mendukung manajemen `StoreClose` untuk menutup toko sementara dan memblokir checkout secara otomatis.

**Kriteria Lulus:**
- Admin Panel tidak dapat diakses tanpa autentikasi.
- Semua resource utama tersedia dan dapat dioperasikan.
- Perubahan status pesanan oleh admin selalu menghasilkan entri `order_logs`.

---

### Requirement 12 — Audit Domain Keamanan & Standar API

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa API backend memenuhi standar keamanan e-commerce, sehingga data pelanggan dan transaksi terlindungi dari ancaman umum.

#### Acceptance Criteria

1. THE System SHALL menerapkan validasi input pada semua endpoint API menggunakan Laravel Form Request atau inline `validate()`, dan mengembalikan HTTP 422 dengan detail error untuk input yang tidak valid.
2. THE System SHALL menerapkan autentikasi berbasis sesi (Laravel Sanctum atau session guard) pada semua endpoint yang memerlukan login, dan mengembalikan HTTP 401 untuk request tanpa autentikasi.
3. THE System SHALL menerapkan rate limiting pada endpoint sensitif: verifikasi OTP (5 percobaan per 10 menit), kirim ulang OTP (3 permintaan per 10 menit), dan login.
4. THE System SHALL memastikan bahwa semua query database menggunakan Eloquent ORM atau query builder dengan parameter binding untuk mencegah SQL injection.
5. THE System SHALL memastikan bahwa file yang diunggah (bukti bayar, lampiran komplain) disimpan di direktori yang tidak dapat diakses langsung melalui URL publik tanpa otorisasi.
6. THE System SHALL mengembalikan HTTP 403 ketika pengguna mencoba mengakses resource milik pengguna lain (pesanan, alamat, dll.).
7. THE System SHALL memastikan bahwa respons API tidak menyertakan data sensitif yang tidak diperlukan (password hash, token internal, dll.) dalam payload JSON.
8. THE System SHALL mengimplementasikan CORS yang membatasi origin yang diizinkan untuk mengakses API.
9. WHEN terjadi error server (HTTP 500), THE System SHALL mencatat detail error ke log tanpa mengekspos stack trace ke respons API di environment production.
10. THE System SHALL memastikan bahwa semua operasi finansial (pembuatan pesanan, penggunaan poin, pencatatan komisi) dieksekusi dalam database transaction untuk menjaga konsistensi data.

**Kriteria Lulus:**
- Tidak ada endpoint yang dapat diakses tanpa autentikasi yang seharusnya memerlukan login.
- SQL injection tidak dimungkinkan melalui input pengguna.
- File upload tidak dapat diakses langsung tanpa otorisasi.
- Semua operasi finansial menggunakan database transaction.

---

### Requirement 13 — Audit Domain Konten & Pengaturan Toko

**User Story:** Sebagai Auditor, saya ingin memverifikasi bahwa konten toko (banner, artikel, FAQ) dan pengaturan aplikasi dikelola dengan benar, sehingga informasi yang ditampilkan kepada pelanggan selalu akurat.

#### Acceptance Criteria

1. THE System SHALL menyediakan endpoint publik untuk mengambil data `Banner`, `Article`, `Faq`, dan `AppSetting` tanpa memerlukan autentikasi.
2. THE System SHALL memastikan bahwa banner yang ditampilkan di frontend hanya yang berstatus aktif.
3. THE System SHALL menyediakan sitemap (`SitemapController`) yang mencakup URL produk, kategori, dan artikel untuk keperluan SEO.
4. THE System SHALL memastikan bahwa `AppSetting` dapat diperbarui melalui Admin Panel dan perubahan langsung tercermin di respons API tanpa cache yang kedaluwarsa.
5. THE System SHALL mendukung manajemen `Broadcast` (notifikasi/pengumuman) yang dapat ditargetkan ke semua pengguna atau segmen tertentu.

**Kriteria Lulus:**
- Endpoint konten publik dapat diakses tanpa login.
- Hanya banner aktif yang dikembalikan oleh API.
- Sitemap dapat diakses dan berisi URL yang valid.
