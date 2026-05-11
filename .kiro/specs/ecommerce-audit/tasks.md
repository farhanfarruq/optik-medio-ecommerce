# Implementation Tasks — Audit E-Commerce Optik Medio

## Task Dependency Graph

```
T1 (Setup) → T2 (Auth) → T3 (Produk) → T4 (Keranjang) → T5 (Checkout)
                                                              ↓
T10 (Konten) ← T9 (Komplain/Retur) ← T8 (Afiliasi) ← T7 (Loyalitas)
     ↓                                                        ↑
T11 (Admin) → T12 (Keamanan) → T13 (Laporan Final)    T6 (Pesanan)
                                                              ↑
                                                        T5 (Checkout) → T6
```

---

## Task 1: Setup Lingkungan Audit & Instalasi Tools

**Requirement refs:** Design doc — Testing Strategy

- [ ] 1.1 Install library property-based testing `eris/eris` di backend
  ```bash
  cd medio-be && composer require --dev eris/eris
  ```
- [ ] 1.2 Buat direktori `tests/Audit/` di `medio-be/tests/` untuk semua test audit
- [ ] 1.3 Buat file `tests/Audit/AuditTestCase.php` sebagai base class dengan helper methods:
  - `assertFileExists(string $path)` — verifikasi file ada
  - `assertClassUsesTrait(string $class, string $trait)` — verifikasi trait digunakan
  - `assertMigrationHasColumn(string $table, string $column)` — verifikasi kolom ada di migration
  - `assertRouteHasMiddleware(string $route, string $middleware)` — verifikasi middleware di route
- [ ] 1.4 Buat file `AUDIT_REPORT.md` di root project sebagai template laporan kosong dengan struktur dari design doc
- [ ] 1.5 Verifikasi semua file yang akan diaudit dapat diakses (jalankan `php artisan route:list` untuk konfirmasi routes)

---

## Task 2: Audit Domain Autentikasi

**Requirement refs:** Req 1.1 – 1.10

- [ ] 2.1 Periksa `AuthController.php` — validasi registrasi: field `name`, `email` (unique), `password` (min:8, confirmed), `phone` (nullable)
  - Catat temuan: AUTH-001
- [ ] 2.2 Periksa `AuthController.php` — alur login: apakah selalu kirim OTP dan return `requires_otp: true` sebelum sesi dibuat
  - Catat temuan: AUTH-002
- [ ] 2.3 Periksa `AuthController.php` — rate limiting OTP verifikasi: `RateLimiter::tooManyAttempts($key, 5)` dengan window 600 detik, respons menyertakan `retry_after`
  - Catat temuan: AUTH-003
- [ ] 2.4 Periksa `routes/api.php` — throttle middleware pada `/login` (5,1), `/verify-otp` (5,10), `/resend-otp` (3,10)
  - Catat temuan: AUTH-004
- [ ] 2.5 Periksa migration `otp_codes` — kolom `expires_at`, `verified_at`, `type` ada; OTP tidak disimpan di session/cookie
  - Catat temuan: AUTH-005
- [ ] 2.6 Periksa `bootstrap/app.php` — session config: `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE`
  - Catat temuan: AUTH-006
- [ ] 2.7 Periksa `AuthController@logout` — invalidasi sesi dan regenerasi CSRF token
  - Catat temuan: AUTH-007
- [ ] 2.8 Tulis property test: OTP expired/sudah diverifikasi selalu return HTTP 422
  - File: `tests/Audit/AuthAuditTest.php`
- [ ] 2.9 Update `AUDIT_REPORT.md` — section Domain 1 Autentikasi dengan semua temuan AUTH-001 s/d AUTH-007

---

## Task 3: Audit Domain Produk & Katalog

**Requirement refs:** Req 2.1 – 2.8

- [ ] 3.1 Periksa migration `products` — kolom wajib: `name`, `price`, `stock`, `weight`, `category_id`, `is_prescription_required`
  - Catat temuan: PROD-001
- [ ] 3.2 Periksa `Product.php` model — `SoftDeletes` trait digunakan; relasi `variants()`, `images()`, `reviews()` ada
  - Catat temuan: PROD-002
- [ ] 3.3 Periksa `OrderController@store` — validasi stok: `$product->stock < $item['quantity']` sebelum decrement
  - Catat temuan: PROD-003
- [ ] 3.4 Periksa `OrderController@store` — validasi resep: `is_prescription_required` + `empty($item['prescription'])` menghasilkan HTTP 422
  - Catat temuan: PROD-004
- [ ] 3.5 Periksa `ProductController.php` — endpoint filter/pencarian berdasarkan kategori, merek, rentang harga
  - Catat temuan: PROD-005
- [ ] 3.6 Periksa `OrderController@store` — decrement stok menggunakan `Product::where('id', ...)->decrement('stock', ...)` (atomik)
  - Catat temuan: PROD-006
- [ ] 3.7 Periksa `ProductReview` — apakah ulasan terhubung ke pesanan yang sudah selesai (bukan sembarang user)
  - Catat temuan: PROD-007
- [ ] 3.8 Tulis property test: stok 0 selalu mencegah pembuatan pesanan (Property 9)
  - File: `tests/Audit/ProductAuditTest.php`
- [ ] 3.9 Update `AUDIT_REPORT.md` — section Domain 2 Produk

---

## Task 4: Audit Domain Keranjang Belanja

**Requirement refs:** Req 3.1 – 3.6

- [ ] 4.1 Periksa `cartStore.ts` — computed `cartTotal` konsisten dengan kalkulasi backend; `clearCart()` dipanggil setelah order berhasil
  - Catat temuan: CART-001
- [ ] 4.2 Periksa `CheckoutView.vue` — guard navigasi: redirect atau blokir jika `cartStore.items.length === 0`
  - Catat temuan: CART-002
- [ ] 4.3 Periksa `CheckoutView.vue` — handling `linked_item_index` untuk pasangan frame-lensa dalam `itemsPayload`
  - Catat temuan: CART-003
- [ ] 4.4 Periksa `OrderController@calculate` — apakah kalkulasi backend menggunakan harga dari database (bukan dari frontend payload)
  - Catat temuan: CART-004
- [ ] 4.5 Periksa `CheckoutView.vue` — `cartStore.clearCart()` dipanggil setelah `orderRepository.createOrder()` berhasil
  - Catat temuan: CART-005
- [ ] 4.6 Update `AUDIT_REPORT.md` — section Domain 3 Keranjang

---

## Task 5: Audit Domain Checkout & Kalkulasi Harga

**Requirement refs:** Req 4.1 – 4.10

- [ ] 5.1 Periksa `OrderController@calculate` dan `@store` — formula `total_price = max(0, subtotal + shipping - disc - promo - level - loyalty)`
  - Catat temuan: CHKOUT-001
- [ ] 5.2 Periksa `OrderController@store` — mutual exclusion: `if ($request->discount_id && $request->promo_id)` return HTTP 422
  - Catat temuan: CHKOUT-002
- [ ] 5.3 Periksa `OrderController@store` — validasi `DiscountUsage`: kode diskon tidak bisa dipakai dua kali oleh user yang sama
  - Catat temuan: CHKOUT-003
- [ ] 5.4 Periksa `OrderController@store` — promo `buy_x_get_y`: item gratis ditambahkan dengan `product_price = 0`, stok item gratis tidak dikurangi
  - Catat temuan: CHKOUT-004
- [ ] 5.5 Periksa `OrderController@calculate` dan `@store` — loyalty points: maks 5% subtotal, 1 poin = Rp 1.000
  - Catat temuan: CHKOUT-005
- [ ] 5.6 Periksa `EnsureStoreIsOpen` middleware atau `StoreClose` check di `OrderController@store`
  - Catat temuan: CHKOUT-006
- [ ] 5.7 Periksa `OrderController@store` — validasi `shipping_address_id` milik user yang login (bukan user lain)
  - Catat temuan: CHKOUT-007
- [ ] 5.8 Periksa migration `orders` — kolom `level_discount_amount` dan `loyalty_discount_amount` ada
  - Catat temuan: CHKOUT-008
- [ ] 5.9 Tulis property test: total harga tidak pernah negatif (Property 1)
  - File: `tests/Audit/CheckoutAuditTest.php`
- [ ] 5.10 Tulis property test: loyalty discount tidak melebihi 5% subtotal (Property 2)
  - File: `tests/Audit/CheckoutAuditTest.php`
- [ ] 5.11 Tulis property test: mutual exclusion diskon + promo (Property 8)
  - File: `tests/Audit/CheckoutAuditTest.php`
- [ ] 5.12 Update `AUDIT_REPORT.md` — section Domain 4 Checkout

---

## Task 6: Audit Domain Pesanan & Siklus Status

**Requirement refs:** Req 6.1 – 6.9

- [ ] 6.1 Periksa `Order.php` `booted()` hook — event `order_created` dicatat saat `created`
  - Catat temuan: ORDER-001
- [ ] 6.2 Periksa `Order.php` `booted()` hook — event `status_changed` dicatat saat `status` berubah, dengan `previous_status` dan `current_status` yang benar
  - Catat temuan: ORDER-002
- [ ] 6.3 Periksa `Order.php` `booted()` hook — event `tracking_updated`, `payment_proof_uploaded`, `payment_verified` dicatat
  - Catat temuan: ORDER-003
- [ ] 6.4 Periksa `OrderController@confirmDelivery` — validasi `strtolower($order->status) !== 'shipped'` return HTTP 422
  - Catat temuan: ORDER-004
- [ ] 6.5 Periksa `OrderController@confirmDelivery` — formula poin: `max(1, floor($order->total_price / 10000))`
  - Catat temuan: ORDER-005
- [ ] 6.6 Periksa `Order.php` — `SoftDeletes` trait digunakan
  - Catat temuan: ORDER-006
- [ ] 6.7 Periksa `OrderController@show` — validasi `$order->user_id !== $request->user()->id` return HTTP 403
  - Catat temuan: ORDER-007
- [ ] 6.8 Periksa migration `orders` — `order_number` memiliki `unique()` constraint
  - Catat temuan: ORDER-008
- [ ] 6.9 Periksa migration `order_logs` — kolom `event_type`, `previous_status`, `current_status`, `acted_by` ada
  - Catat temuan: ORDER-009
- [ ] 6.10 Tulis property test: konfirmasi penerimaan hanya valid dari status 'shipped' (Property 6)
  - File: `tests/Audit/OrderAuditTest.php`
- [ ] 6.11 Tulis property test: formula poin dari konfirmasi penerimaan (Property 7)
  - File: `tests/Audit/OrderAuditTest.php`
- [ ] 6.12 Tulis property test: setiap perubahan status order tercatat di order_logs (Property 5)
  - File: `tests/Audit/OrderAuditTest.php`
- [ ] 6.13 Update `AUDIT_REPORT.md` — section Domain 6 Pesanan

---

## Task 7: Audit Domain Loyalitas & Keanggotaan

**Requirement refs:** Req 7.1 – 7.7

- [ ] 7.1 Periksa migration `loyalty_point_logs` — kolom `user_id`, `order_id`, `points`, `type`, `description` ada
  - Catat temuan: LOYAL-001
- [ ] 7.2 Periksa `User@addLoyaltyPoints` — `increment('loyalty_points')` + `LoyaltyPointLog::create()` dalam satu operasi
  - Catat temuan: LOYAL-002
- [ ] 7.3 Periksa `User@redeemLoyaltyPoints` — guard `$this->loyalty_points < $points` return `false` tanpa mengubah saldo
  - Catat temuan: LOYAL-003
- [ ] 7.4 Periksa `OrderController@store` — redeem poin dalam `DB::transaction` bersama pembuatan order
  - Catat temuan: LOYAL-004
- [ ] 7.5 Periksa `User@updateMembershipLevel` — hanya satu level aktif (`effective_until IS NULL`) per user
  - Catat temuan: LOYAL-005
- [ ] 7.6 Periksa `OrderController@calculate` — `level_discount_amount` dihitung dari `LevelMember` aktif user
  - Catat temuan: LOYAL-006
- [ ] 7.7 Tulis property test: poin loyalitas tidak pernah negatif (Property 4)
  - File: `tests/Audit/LoyaltyAuditTest.php`
- [ ] 7.8 Tulis property test: konsistensi saldo poin vs loyalty_point_logs (Property 3)
  - File: `tests/Audit/LoyaltyAuditTest.php`
- [ ] 7.9 Update `AUDIT_REPORT.md` — section Domain 7 Loyalitas

---

## Task 8: Audit Domain Pembayaran

**Requirement refs:** Req 5.1 – 5.10

- [ ] 8.1 Periksa `WebhookController@xendit` — validasi token/signature Xendit sebelum memproses payload
  - Catat temuan: PAY-001
- [ ] 8.2 Periksa `WebhookController@xendit` — update status Payment dan Order dalam `DB::transaction`
  - Catat temuan: PAY-002
- [ ] 8.3 Periksa `OrderController@uploadPaymentProof` — validasi format file: `mimes:jpg,jpeg,png,webp,pdf` dan `max:4096`
  - Catat temuan: PAY-003
- [ ] 8.4 Periksa `OrderController@uploadPaymentProof` — guard status: `cancelled`, `refunded`, `delivered` return HTTP 422
  - Catat temuan: PAY-004
- [ ] 8.5 Periksa `OrderController@uploadPaymentProof` — guard `is_payment_verified = true` return HTTP 422
  - Catat temuan: PAY-005
- [ ] 8.6 Periksa `OrderController@uploadPaymentProof` — ownership check: `$order->user_id !== $request->user()->id`
  - Catat temuan: PAY-006
- [ ] 8.7 Periksa migration `payments` — kolom `raw_response`, `provider`, `paid_at` ada
  - Catat temuan: PAY-007
- [ ] 8.8 Periksa `OrderController@syncPayment` — guard provider bukan xendit return HTTP 422
  - Catat temuan: PAY-008
- [ ] 8.9 Periksa `Order.php` `booted()` — saat `status` berubah ke `cancelled`, payment status diupdate ke `cancelled`
  - Catat temuan: PAY-009
- [ ] 8.10 Tulis property test: webhook Xendit tanpa signature valid ditolak (Property 11)
  - File: `tests/Audit/PaymentAuditTest.php`
- [ ] 8.11 Update `AUDIT_REPORT.md` — section Domain 5 Pembayaran

---

## Task 9: Audit Domain Komplain & Retur

**Requirement refs:** Req 9.1 – 9.7

- [ ] 9.1 Periksa `ComplainController@store` — validasi `order_id` opsional, `subject` dan `message` wajib
  - Catat temuan: COMP-001
- [ ] 9.2 Periksa `ComplainObserver@updated` — email dikirim saat status berubah ke `in_progress`, `resolved`, atau `rejected`
  - Catat temuan: COMP-002
- [ ] 9.3 Periksa `ComplainResource.php` (Filament) — field `resolved_at` dan `handled_by` ada di form
  - Catat temuan: COMP-003
- [ ] 9.4 Periksa `ComplainController@store` — validasi attachment: `mimes:jpg,jpeg,png,pdf` dan `max:4096`
  - Catat temuan: COMP-004
- [ ] 9.5 Periksa `ReturnController@store` — guard status: `strtolower($order->status) !== 'delivered'` return HTTP 422
  - Catat temuan: COMP-005
- [ ] 9.6 Periksa `ReturnObserver@updated` — email dikirim saat status berubah ke `approved` atau `rejected`
  - Catat temuan: COMP-006
- [ ] 9.7 Periksa `ComplainResource.php` (Filament) — filter status dan pencarian berdasarkan pelanggan/nomor pesanan
  - Catat temuan: COMP-007
- [ ] 9.8 Tulis property test: ReturnRequest hanya untuk pesanan delivered/completed (Property 10)
  - File: `tests/Audit/ComplainAuditTest.php`
- [ ] 9.9 Update `AUDIT_REPORT.md` — section Domain 9 Komplain & Retur

---

## Task 10: Audit Domain Afiliasi & Komisi

**Requirement refs:** Req 8.1 – 8.7

- [ ] 10.1 Periksa migration `user_affiliators` — `affiliate_code` memiliki `unique()` constraint
  - Catat temuan: AFIL-001
- [ ] 10.2 Periksa `AffiliateController@apply` — status default `pending`, `commission_rate_percentage` default 5%
  - Catat temuan: AFIL-002
- [ ] 10.3 Periksa logika komisi — afiliator dengan status bukan `approved` tidak mendapatkan komisi baru
  - Catat temuan: AFIL-003
- [ ] 10.4 Periksa `UserAffiliatorResource.php` (Filament) — aksi approve mencatat `approved_by` dan `approved_at`
  - Catat temuan: AFIL-004
- [ ] 10.5 Periksa `UserAffiliatorResource.php` (Filament) — aksi reject mencatat `rejected_at` dan `rejection_reason`
  - Catat temuan: AFIL-005
- [ ] 10.6 Periksa logika komisi — komisi hanya dihitung untuk pesanan `completed` atau `delivered`
  - Catat temuan: AFIL-006
- [ ] 10.7 Periksa migration `commission_details` — relasi ke `commissions` dan `orders` ada
  - Catat temuan: AFIL-007
- [ ] 10.8 Update `AUDIT_REPORT.md` — section Domain 8 Afiliasi

---

## Task 11: Audit Domain Pengiriman

**Requirement refs:** Req 10.1 – 10.6

- [ ] 11.1 Periksa `ShippingController@cost` — kalkulasi menggunakan `district_id` dan berat dalam gram via RajaOngkir
  - Catat temuan: SHIP-001
- [ ] 11.2 Periksa `CheckoutView.vue` — guard berat 0: `shippingError.value` ditampilkan, API tidak dipanggil
  - Catat temuan: SHIP-002
- [ ] 11.3 Periksa respons `ShippingController@cost` — menyertakan `etd` dan `cost` per layanan
  - Catat temuan: SHIP-003
- [ ] 11.4 Periksa migration `orders` — kolom `courier`, `courier_service`, `shipping_cost` ada
  - Catat temuan: SHIP-004
- [ ] 11.5 Periksa `OrderController@store` — `shipping_cost` diambil dari `$shippingSelection` (backend), bukan langsung dari request frontend
  - Catat temuan: SHIP-005
- [ ] 11.6 Periksa `ShippingRate.php` model — ada sebagai fallback statis
  - Catat temuan: SHIP-006
- [ ] 11.7 Update `AUDIT_REPORT.md` — section Domain 10 Pengiriman

---

## Task 12: Audit Domain Admin Panel (Filament)

**Requirement refs:** Req 11.1 – 11.8

- [ ] 12.1 Periksa `AdminPanelProvider.php` — `authMiddleware([Authenticate::class])` aktif
  - Catat temuan: ADMIN-001
- [ ] 12.2 Periksa direktori `app/Filament/Resources/` — resource ada untuk: Order, Product, Category, User, Complain, Commission, Discount, Promo, Banner, Expedition, LevelMember, ReturnRequest, AppSetting
  - Catat temuan: ADMIN-002
- [ ] 12.3 Periksa `OrderResource.php` — aksi verifikasi pembayaran mencatat `verified_by` dan `payment_verified_at`
  - Catat temuan: ADMIN-003
- [ ] 12.4 Periksa semua Filament resource — filter dan pencarian tersedia di tabel
  - Catat temuan: ADMIN-004
- [ ] 12.5 Periksa `UserResource.php` — field `password` menggunakan `->password()` dan tidak ditampilkan plaintext
  - Catat temuan: ADMIN-005
- [ ] 12.6 Periksa `AppSettingResource.php` — form edit tersedia tanpa deployment ulang
  - Catat temuan: ADMIN-006
- [ ] 12.7 Periksa `StoreCloseResource.php` — resource ada dan dapat mengaktifkan/menonaktifkan status toko
  - Catat temuan: ADMIN-007
- [ ] 12.8 Update `AUDIT_REPORT.md` — section Domain 11 Admin Panel

---

## Task 13: Audit Domain Keamanan & Standar API

**Requirement refs:** Req 12.1 – 12.10

- [ ] 13.1 Periksa semua `app/Http/Controllers/API/*.php` — semua endpoint menggunakan `validate()` atau Form Request
  - Catat temuan: SEC-001
- [ ] 13.2 Periksa `routes/api.php` — semua protected routes memiliki `auth:sanctum` middleware
  - Catat temuan: SEC-002
- [ ] 13.3 Periksa `config/cors.php` — `allowed_origins` tidak `['*']` (wildcard) di production
  - Catat temuan: SEC-003
- [ ] 13.4 Periksa `SecurityHeaders.php` middleware — header `X-Frame-Options`, `X-Content-Type-Options` ada; CSP hanya di production
  - Catat temuan: SEC-004
- [ ] 13.5 Jalankan grep untuk raw SQL: `grep -rn "DB::statement\|whereRaw\|selectRaw" app/Http/Controllers/` — verifikasi semua menggunakan parameter binding
  - Catat temuan: SEC-005
- [ ] 13.6 Periksa `OrderController@store` — `DB::transaction` digunakan untuk operasi finansial (pembuatan order + redeem poin)
  - Catat temuan: SEC-006
- [ ] 13.7 Periksa storage path untuk file upload — `payment-proofs` dan `complaints` disimpan di `storage/app/public/`, bukan di `public/` langsung
  - Catat temuan: SEC-007
- [ ] 13.8 Periksa respons API — tidak ada field `password`, `remember_token`, atau data sensitif lain dalam JSON response
  - Catat temuan: SEC-008
- [ ] 13.9 Periksa `bootstrap/app.php` — error handling: stack trace tidak diekspos di production (`APP_DEBUG=false`)
  - Catat temuan: SEC-009
- [ ] 13.10 Update `AUDIT_REPORT.md` — section Domain 12 Keamanan

---

## Task 14: Audit Domain Konten & Pengaturan Toko

**Requirement refs:** Req 13.1 – 13.5

- [ ] 14.1 Periksa `routes/api.php` — endpoint `/banners`, `/articles`, `/faqs`, `/settings` tidak memerlukan `auth:sanctum`
  - Catat temuan: CONT-001
- [ ] 14.2 Periksa `BannerController@index` — filter `is_active = true` diterapkan
  - Catat temuan: CONT-002
- [ ] 14.3 Periksa apakah `SitemapController` ada dan mencakup URL produk, kategori, artikel
  - Catat temuan: CONT-003
- [ ] 14.4 Periksa `AppSetting` model — apakah ada cache yang perlu di-invalidate saat update
  - Catat temuan: CONT-004
- [ ] 14.5 Update `AUDIT_REPORT.md` — section Domain 13 Konten

---

## Task 15: Kompilasi Laporan Final & Prioritas Perbaikan

**Requirement refs:** Semua

- [ ] 15.1 Kompilasi semua temuan dari `AUDIT_REPORT.md` — hitung total CRITICAL, HIGH, MEDIUM, LOW per domain
- [ ] 15.2 Buat tabel ringkasan eksekutif di `AUDIT_REPORT.md`:
  ```
  | Domain | Status | CRITICAL | HIGH | MEDIUM | LOW |
  ```
- [ ] 15.3 Buat section "Prioritas Perbaikan" di `AUDIT_REPORT.md` — urutkan semua temuan CRITICAL dan HIGH berdasarkan dampak
- [ ] 15.4 Buat section "Quick Wins" — temuan LOW/MEDIUM yang mudah diperbaiki dalam < 1 jam
- [ ] 15.5 Jalankan semua property-based tests dan catat hasilnya:
  ```bash
  cd medio-be && php artisan test tests/Audit/ --verbose
  ```
- [ ] 15.6 Tambahkan hasil test ke `AUDIT_REPORT.md` — section "Hasil Property-Based Testing"
- [ ] 15.7 Review final: pastikan setiap temuan FAIL memiliki rekomendasi perbaikan yang konkret dan actionable
