# Big E-Commerce Roadmap - Optik Medio

## Tujuan Dokumen

Dokumen ini adalah roadmap lanjutan setelah audit awal e-commerce Optik Medio. Fokusnya adalah mengubah website dari e-commerce optik fungsional menjadi platform optical commerce yang lebih lengkap, mudah digunakan pelanggan, mudah dioperasikan admin, dan siap tumbuh seperti big e-commerce.

Dokumen ini melengkapi:

- `requirements.md` sebagai standar audit saat ini.
- `design.md` sebagai metode audit dan correctness properties.
- `tasks.md` sebagai checklist audit dan implementasi awal.
- `AUDIT_REPORT.md` sebagai laporan temuan dan status awal.

## Ringkasan Strategi

Optik Medio sebaiknya diposisikan bukan hanya sebagai toko online yang menjual frame, lensa kontak, dan aksesoris, tetapi sebagai guided optical shopping platform.

Artinya, website harus membantu pelanggan:

- Memilih frame yang cocok.
- Memilih lensa yang sesuai kebutuhan.
- Mengisi atau mengunggah resep mata.
- Memahami total harga dengan jelas.
- Checkout tanpa kebingungan.
- Memantau pesanan, komplain, retur, dan loyalty dengan mudah.

Admin harus dibantu untuk:

- Melihat kondisi bisnis harian.
- Memproses order lebih cepat.
- Mengelola stok dan produk optik yang kompleks.
- Menangani pembayaran, pengiriman, retur, komplain, dan promo tanpa banyak kerja manual.
- Mendeteksi masalah operasional sebelum menjadi kerugian.

## Prinsip Implementasi

1. Backend tetap menjadi sumber kebenaran untuk harga, stok, promo, diskon, loyalty, dan status order.
2. Frontend boleh menghitung estimasi untuk UX, tetapi wajib selalu melakukan validasi ulang ke backend.
3. Semua endpoint yang menerima ID dari user wajib melakukan ownership check.
4. Semua operasi finansial dan inventory wajib memakai database transaction.
5. Semua fitur baru harus punya audit trail atau minimal event log untuk aksi penting.
6. Admin panel tidak boleh hanya CRUD; admin harus punya workflow yang mempercepat pekerjaan.
7. Fitur optik harus menjadi pembeda utama dari e-commerce umum.
8. Implementasi dilakukan per fase agar tidak merusak flow checkout yang sudah berjalan.

## Progress Checklist

Status ini diperbarui sesuai implementasi aktual di repo.

- [x] Phase 0 - Stabilization Baseline: fondasi teknis, admin operational summary, dan manual regression checklist selesai; sisa test khusus race/ownership perlu dilanjutkan.
- [x] Phase 1 - Optical Product Foundation: core optical schema, admin fields, API filters, backend tests, frontend filter panel, sorting, size guide, recommendations, lens data model, and admin validation completed.
- [x] Phase 2 - Prescription Builder: prescription profiles, CRUD API, ownership guard, optical validation, saved prescription reuse, attachment upload, profile management UI, admin verification workflow, and tests completed.
- [x] Phase 3 - Frame Plus Lens Configurator: backend pricing, compatibility check, frontend lens/coating configurator, checkout snapshot review, admin order snapshot display, and tests completed.
- [x] Phase 4 - Discovery, Recommendation, and Conversion: search autocomplete, compare, recently viewed, wishlist share, face shape quiz, virtual try-on v1, admin discovery controls, and QA tests completed.
- [x] Phase 5 - Checkout, Payment, and Retention.
- [x] Phase 6 - Admin Operations and Workflow.
- [x] Phase 7 - Marketing, SEO, and Marketplace Readiness.
- [x] Phase 8 - Observability, Analytics, and Performance.
- [x] Phase 9 - Advanced Optical Commerce.

### Completed Phase 0 Items

- [x] Backend test suite pass: `php artisan test`.
- [x] Audit test suite added in `medio-be/tests/Audit`.
- [x] Frontend build pass: `npm run build`.
- [x] Health check endpoint added: `GET /api/health`.
- [x] Request correlation ID middleware added with `X-Request-ID` response header.
- [x] Health endpoint feature test added.
- [x] Checkout/order ownership checks strengthened.
- [x] Checkout/order stock, prescription, discount, promo, loyalty, and transaction controls strengthened.
- [x] Payment webhook token and idempotency tests pass.
- [x] Admin operational summary covers unpaid orders, pending payment proof, low stock, open complaints, and pending returns.
- [x] Manual regression checklist added in `.kiro/specs/ecommerce-audit/manual-regression-checklist.md`.
- [x] Dedicated feature tests added for foreign `shipping_address_id` rejection.
- [x] Dedicated feature test added for loyalty points cap during checkout calculation.
- [x] Stock oversell regression test added for second checkout after stock was consumed.
- [x] CSS `@import` order warning fixed.

### Remaining Phase 0 Items

- [ ] Add true concurrent stock race simulation if test environment supports parallel database transactions.

### Completed Phase 1 Items

- [x] Product filter metadata endpoint added: `GET /api/products/filters`.
- [x] Product filter metadata test added for active categories, brands, price range, optical attributes, and filter flags.
- [x] Core optical product fields added for frame gender, shape, material, color, fit, dimensions, and Merchant Center attributes.
- [x] Product model fillable/casts updated for optical attributes.
- [x] Product listing API filters updated for gender, frame shape, material, color, fit, prescription support, and stock.
- [x] Backend feature test added for optical product listing filters.
- [x] Filament product form/table updated for core optical attributes.
- [x] Frontend product types and repository contract updated for product filter metadata.
- [x] Frontend product filter panel added for brand, gender, shape, material, color, fit, price, stock, and prescription support.
- [x] Active filter chips and reset filter action added to product listing.
- [x] Product detail frame size guide added for frame dimensions and optical profile attributes.
- [x] Product recommendations endpoint added: `GET /api/products/{slug}/recommendations`.
- [x] Product recommendations feature test added for similar frames, compatible lenses, compatible lens options, and out-of-stock exclusion.
- [x] Product detail recommendation section added for similar frames and compatible lenses.
- [x] Dedicated lens option, lens coating, and product compatibility tables/models added.
- [x] Filament resources added for lens options, lens coatings, and product compatibility management.
- [x] Product prescription rules added and exposed in backend/frontend product types.
- [x] Admin product validation added for SKU uniqueness, frame size completeness, prescription rules, and active product images.
- [x] Product listing sorting added for latest, price, best seller, rating, and popularity.
- [x] Backend tests added for lens configuration schema, model relationships, and price sorting.

### Remaining Phase 1 Items

- [x] Optional follow-up: add CSV import/export for products. ✅ Selesai — `ProductCsvImport` Filament page dengan export CSV dan import CSV (create/update by SKU).
- [x] Optional follow-up: add visual product comparison UI. ✅ Sudah ada sejak Phase 4 (ProductCompare.vue).
- [x] Optional follow-up: add delivery estimate block on product detail using shipping rules. ✅ Sudah ada di product detail (frame size guide + shipping info).

### Completed Phase 2 Items

- [x] `PrescriptionProfile` table/model added with optical prescription fields, attachment path, verification fields, and default marker.
- [x] User relation added for prescription profiles.
- [x] Protected prescription profile API added: `GET`, `POST`, `GET {id}`, `PUT {id}`, `DELETE {id}`, and `POST {id}/set-default`.
- [x] Ownership guard added for all prescription detail/update/delete/default actions.
- [x] Optical validation added for numeric sphere/cylinder, axis range, cylinder-axis dependency, ADD lens type rule, PD requirement, and attachment type/size.
- [x] Backend feature tests added for CRUD, default profile, ownership guard, and invalid optical rules.
- [x] Frontend prescription repository added.
- [x] Product detail can load saved prescriptions, apply default profile, apply selected profile, and save the current prescription for logged-in users.
- [x] Product detail prescription save supports attachment upload for jpg, jpeg, png, webp, and pdf.
- [x] Filament prescription profile resource added for admin review/editing.
- [x] Admin verify/unverify action added with `verified_by` and `verified_at` tracking.
- [x] Account profile route/menu added for prescription management.
- [x] Profile page prescription list, edit label/notes, set default, and delete actions added.

### Remaining Phase 2 Items

- [x] Optional follow-up: add full prescription create form directly in profile page. ✅ Selesai — form lengkap dengan tabel OD/OS, PD, lens type, attachment upload, dan set default.
- [x] Optional follow-up: add explicit admin notes/rejection reason for prescription verification. ✅ Selesai — migration `admin_notes` + `verification_status`, Filament action Approve/Tolak dengan form catatan.

### Completed Phase 3 Items

- [x] Order item optical configuration fields added: lens option, lens coating, prescription profile, lens price, coating price, and configuration snapshot.
- [x] `OrderItem` model fillable/casts/relations updated for optical configuration.
- [x] `OpticalPricingService` added for frame/lens/coating pricing, compatibility checks, prescription rule validation, and snapshot generation.
- [x] Protected `POST /api/optical/configure` endpoint added.
- [x] Protected `GET /api/optical/lens-coatings` endpoint added.
- [x] Checkout calculation and order creation now support backend-priced `lens_option_id`, `lens_coating_id`, and `prescription_profile_id`.
- [x] Order creation persists optical configuration snapshot to `order_items`.
- [x] Backend tests added for configure price breakdown and order item snapshot persistence.
- [x] Product detail now has step-by-step optical configurator for lens type, coating, backend quote, warnings, and add-to-cart flow.
- [x] Cart checkout payload sends optical configuration IDs and prescription profile ID.
- [x] Checkout page shows optical configuration snapshot before payment.
- [x] Admin order detail shows lens option, coating, prescription profile/manual input, and price breakdown.

### Remaining Phase 3 Items

- [x] Add frontend step-by-step frame + lens configurator UI.
- [x] Add coating selection UI.
- [x] Add checkout review block for optical configuration snapshot.
- [x] Add admin order detail display for optical configuration snapshot.

### Completed Phase 4 Items

- [x] `GET /api/products/search-suggestions` added for active buyable product and category suggestions.
- [x] `POST /api/products/compare` added with 2-4 active product guard.
- [x] Product discovery tests added for search leakage prevention and compare limits.
- [x] Frontend product repository supports search suggestions and compare.
- [x] Navbar search autocomplete added with product suggestions, category suggestions, and recent searches.
- [x] Product listing compare toggle added with sticky compare bar.
- [x] Product compare page added at `/compare`.
- [x] Recently viewed products added on product detail with local browser storage.
- [x] Wishlist share link added using encrypted product-ID token without exposing user data.
- [x] Shared wishlist public page added at `/wishlist/shared/:token`.
- [x] Wishlist share tests added for invalid token and private data leakage prevention.
- [x] Face shape quiz added at `/face-shape-quiz` with face shape, style, size, budget, and recommendation results.
- [x] Product listing can open with quiz-driven query filters for frame shape, face size, stock, and budget.
- [x] Navbar links added for Products, Quiz, and Compare.
- [x] Virtual try-on v1 added at `/virtual-try-on` with photo upload, frame search, manual scale/position/rotation, and saved previews.
- [x] Product admin discovery controls added: product tags, campaign tags, featured product, and recommendation priority.
- [x] Product repository supports featured and campaign tag filters plus priority-based sorting.
- [x] Search suggestions and product recommendations now include discovery priority controls.
- [x] Product discovery test added for featured campaign priority ordering.
- [x] Frequently bought together recommendation added from historical paid/processing/shipped/delivered order items.

### Remaining Phase 4 Items

- [x] Add recently viewed products.
- [x] Add wishlist share link without exposing private user data.
- [x] Add face shape quiz and recommendation result page.
- [x] Add virtual try-on v1.
- [x] Add admin product tags/recommendation priority/featured campaign controls.

### Completed Phase 5 Items

- [x] `GET /api/orders/{id}/payment-status` endpoint added for lightweight polling.
- [x] `Cart` and `CartItem` models added with server-side persistence.
- [x] Cart migration added: `carts` and `cart_items` tables.
- [x] `WebhookEventLog` model and migration added for audit trail and idempotency tracking.
- [x] `WebhookController` updated to record all Xendit events to `webhook_event_logs`.
- [x] `CartController` API added: `GET`, `POST /items`, `PUT /items/{id}`, `DELETE /items/{id}`, `DELETE`, `POST /sync`.
- [x] Cart routes added under `/api/cart` (auth required).
- [x] `SendAbandonedCartReminder` job added with retry policy and email notification.
- [x] `Console/Kernel.php` added with hourly schedule for abandoned cart job.
- [x] `AbandonedCartResource` Filament resource added for admin abandoned cart dashboard.
- [x] `OrderRepository.ts` updated with `getPaymentStatus()` and `syncPayment()` methods.
- [x] `WaitingPayment.vue` updated with Xendit polling (5s interval, max 5 min), expired state banner, manual sync button, and Xendit-specific instructions.
- [x] Backend feature tests added: `CartPersistenceTest` (10 tests) and `PaymentStatusTest` (8 tests).
- [x] `XenditWebhookTest` updated to include `webhook_event_logs` table in manual schema setup.
- [x] All 61 backend tests pass.
- [x] Frontend build pass.

### Remaining Phase 5 Items

- [ ] Optional follow-up: guest checkout (opsi A: guest order dengan email+phone, opsi B: quick account creation setelah order).
- [x] Optional follow-up: cart recovery UX banner di frontend (restore cart dari server setelah login). ✅ Selesai — `authStore` sync cart lokal ke server setelah login/OTP verified via `POST /api/cart/sync`.
- [x] Optional follow-up: payment dashboard admin (Xendit pending, manual proof pending, failed/expired, reconciliation export). ✅ Selesai — PaymentResource diupgrade dengan filter Xendit pending, manual proof pending, failed/expired, dan bulk action export CSV rekonsiliasi.
- [ ] Optional follow-up: E2E checkout test dengan Playwright.

### Completed Phase 6 Items

- [x] Migration `low_stock_threshold` ditambahkan ke tabel `products` (default 5).
- [x] Migration `stock_adjustments` table ditambahkan untuk audit trail penyesuaian stok.
- [x] Migration `expand_user_role_enum` — role diperluas dari `admin/user` ke 7 role: `owner`, `admin`, `finance`, `warehouse`, `customer_service`, `content_manager`, `user`.
- [x] `StockAdjustment` model ditambahkan dengan method `adjust()` (atomic stock update + log), `reason_label` attribute, dan relasi ke `Product` dan `User`.
- [x] `Product` model diupdate: tambah `low_stock_threshold` ke fillable/casts, relasi `stockAdjustments()`, dan helper `isLowStock()`.
- [x] `User` model diupdate: tambah role constants (`ROLE_OWNER`, `ROLE_ADMIN`, dll), `STAFF_ROLES` array, helper `isStaff()`, `hasRole()`, `isOwnerOrAdmin()`, dan `canAccessPanel()` untuk Filament.
- [x] `OrderKanban` Filament Page ditambahkan — board visual per status order (unpaid, paid, processing, shipped, delivered, cancelled) dengan count per kolom dan link ke detail.
- [x] `InventoryResource` Filament Resource ditambahkan — tabel produk aktif dengan filter low stock/out of stock, action "Sesuaikan Stok" (form inline), action "Riwayat Stok" (modal), dan bulk action "Set Batas Minimum".
- [x] `OrderResource` diupdate: status options diperluas dengan optical statuses (`waiting_prescription_review`, `prescription_verified`, `lens_processing`, `ready_to_ship`, `completed`), bulk actions ditambah (`mark_shipped`, `bulk_update_tracking`, `mark_cancelled`).
- [x] `AdminActivityWidget` Filament Widget ditambahkan — tabel 15 aktivitas admin terbaru dari `order_logs` yang dilakukan oleh staff.
- [x] `LowStockWidget` Filament Widget ditambahkan — tabel produk aktif yang stoknya di bawah threshold, dengan link ke inventory management.
- [x] `StatsOverview` widget diupdate — low stock count sekarang menggunakan `low_stock_threshold` dinamis per produk.
- [x] `UserResource` diupdate — role options diperluas ke 7 role dengan emoji, filter multi-select, dan badge color per role.
- [x] `AdminPanelProvider` diupdate — tambah `authGuard('web')`.
- [x] Backend feature tests: `AdminRolePermissionTest` (8 tests) dan `InventoryManagementTest` (9 tests).
- [x] Semua 78 backend tests pass.
- [x] Frontend build pass.

### Remaining Phase 6 Items

- [ ] Optional follow-up: print invoice dan print shipping label dari order detail.
- [x] Optional follow-up: customer notification trigger dari admin (email/WhatsApp saat status berubah). ✅ Selesai — action "Kirim Notifikasi" di OrderResource dengan pilihan: update status, pengingat pembayaran, atau pesan kustom.
- [x] Optional follow-up: stock opname page (bulk stock count input). ✅ Selesai — `StockOpname` Filament page dengan tabel input stok aktual per produk, highlight selisih, dan simpan penyesuaian.
- [x] Optional follow-up: import/export CSV untuk stok produk. ✅ Selesai — `ProductCsvImport` Filament page dengan export semua produk aktif dan import CSV (create/update by SKU).

### Completed Phase 7 Items

- [x] Migration `meta_title`, `meta_description`, `canonical_slug`, `og_image` ditambahkan ke tabel `products`.
- [x] Migration `meta_title`, `meta_description`, `og_image` ditambahkan ke tabel `categories`.
- [x] Migration `images` (JSON) ditambahkan ke tabel `product_reviews` untuk review dengan foto.
- [x] Migration `referral_codes` dan `referral_uses` tables ditambahkan.
- [x] `ProductReview` model diupdate: tambah `images` ke fillable/casts.
- [x] `Product` model diupdate: tambah SEO fields ke fillable.
- [x] `Category` model diupdate: tambah SEO fields ke fillable.
- [x] `ReferralCode` model ditambahkan dengan `getOrCreateForUser()`, `generateUniqueCode()`, `use()` (fraud guard: tidak bisa pakai kode sendiri, satu user satu kode seumur hidup).
- [x] `ReferralUse` model ditambahkan.
- [x] `ReviewController` diupdate: tambah `index()` (public, paginated), `store()` dengan foto upload (maks 3 foto, maks 2MB), `destroy()` dengan hapus file.
- [x] `MerchantFeedController` ditambahkan: `GET /api/merchant-feed` (TSV/JSON), `GET /api/merchant-feed/diagnostics` (laporan produk tidak eligible).
- [x] `ReferralController` ditambahkan: `GET /api/referral/my-code`, `POST /api/referral/use`, `GET /api/referral/validate/{code}`.
- [x] Routes ditambahkan: product reviews public, merchant feed (rate-limited), referral validate (public), referral protected.
- [x] `ProductResource` Filament diupdate: tambah SEO section (meta title, meta description, canonical slug, OG image).
- [x] `CategoryResource` Filament diupdate: tambah SEO section.
- [x] `useSeoMeta` composable ditambahkan: `setSeo()`, `setJsonLd()`, `buildProductJsonLd()`, `buildBreadcrumbJsonLd()`, `resetSeo()`.
- [x] `ProductDetail.vue` diupdate: inject dynamic meta tags + Product JSON-LD setelah produk loaded.
- [x] `ReferralPage.vue` ditambahkan: halaman referral dengan kode unik, share URL, riwayat penggunaan, form gunakan kode, dan CTA untuk guest.
- [x] Router diupdate: tambah route `/referral` dan `/referral/:code`.
- [x] Backend feature tests: `MerchantFeedTest` (6 tests), `ReviewWithPhotoTest` (6 tests), `ReferralSystemTest` (11 tests).
- [x] Semua 101 backend tests pass.
- [x] Frontend build pass.

### Remaining Phase 7 Items

- [x] Optional follow-up: category landing pages dengan SEO meta dan produk terkait. ✅ Selesai — `CategoryLanding.vue` di `/c/:slug` dengan SEO meta, JSON-LD BreadcrumbList, dan grid produk.
- [x] Optional follow-up: brand landing pages. ✅ Selesai — `BrandLanding.vue` di `/brand/:brand` dengan SEO meta dan grid produk.
- [ ] Optional follow-up: promo landing pages.
- [x] Optional follow-up: review request flow email setelah order delivered. ✅ Selesai — `ReviewRequestMail`, `SendReviewRequest` job (daily 10:00, 3 hari setelah delivered), template email.
- [x] Optional follow-up: loyalty landing page. ✅ Selesai — `LoyaltyPage.vue` di `/loyalty` dengan cara kerja, level membership, progress bar, riwayat poin.

### Completed Phase 8 Items

- [x] Migration `business_events` table ditambahkan dengan index pada `event_type`, `user_id`, dan `created_at`.
- [x] `BusinessEvent` model ditambahkan dengan 13 event type constants, method `record()` (fire-and-forget, tidak throw), dan relasi ke `User`.
- [x] `BusinessEventController` ditambahkan: `POST /api/events` (rate-limited 60/menit, whitelist event types dari frontend).
- [x] `OrderController` diupdate: inject `order_created` business event setelah order berhasil dibuat.
- [x] `WebhookController` diupdate: inject `payment_success` business event setelah Xendit webhook berhasil.
- [x] `QueueMonitorWidget` Filament Widget ditambahkan: failed jobs total, failed jobs 24 jam, pending jobs, webhook gagal, webhook pending.
- [x] `AnalyticsFunnelWidget` Filament Widget ditambahkan: bar chart funnel konversi 7 hari (product_viewed → payment_success).
- [x] `SearchNoResultWidget` Filament Widget ditambahkan: tabel kata pencarian tanpa hasil 7 hari, dikelompokkan dan diurutkan frekuensi.
- [x] `CheckoutFailedWidget` Filament Widget ditambahkan: tabel alasan checkout gagal 7 hari.
- [x] `useAnalytics` composable ditambahkan: `trackProductViewed`, `trackAddToCart`, `trackCheckoutStarted`, `trackShippingSelected`, `trackPaymentSelected`, `trackSearchNoResult`, `trackCheckoutFailed`, `trackFilterUsed`.
- [x] `useErrorBoundary` composable ditambahkan: `setupGlobalErrorHandlers()` (unhandledrejection + error), `useErrorBoundary()` (Vue onErrorCaptured), error log lokal.
- [x] `main.ts` diupdate: setup global error handlers, Vue `errorHandler` dan `warnHandler`.
- [x] `ProductDetail.vue` diupdate: inject `trackProductViewed` setelah produk loaded.
- [x] `CheckoutView.vue` diupdate: inject `trackCheckoutStarted` di onMounted, `trackCheckoutFailed` di error handler.
- [x] `TopNavBar.vue` diupdate: inject `trackSearchNoResult` saat suggestions kosong.
- [x] `Product.vue` diupdate: tambah `loading="lazy"` dan `decoding="async"` pada product card images.
- [x] `vite.config.ts` diupdate: manual chunk splitting (vendor-vue, vendor-utils), target ES2020, CSS minify — main bundle turun dari 470KB ke 308KB (−34%).
- [x] Backend feature tests: `BusinessEventTest` (7 tests).
- [x] Semua 108 backend tests pass.
- [x] Frontend build pass.

### Remaining Phase 8 Items

- [ ] Optional follow-up: integrasi Sentry atau error monitoring service eksternal.
- [x] Optional follow-up: Core Web Vitals measurement dengan web-vitals library. ✅ Selesai — `useWebVitals.ts` composable menggunakan PerformanceObserver API (LCP, CLS, INP, TTFB), tanpa library eksternal, dikirim ke backend sebagai business event.
- [ ] Optional follow-up: image WebP/AVIF conversion pipeline.
- [ ] Optional follow-up: Lighthouse CI baseline dalam GitHub Actions.

### Completed Phase 9 Items

- [x] Migration `store_branches` + `branch_schedules` tables ditambahkan (capacity, operating hours, override per tanggal).
- [x] Migration `appointments` table ditambahkan (appointment_number, branch, date, time, service_type, status, customer info).
- [x] Migration `warranties` + `service_claims` tables ditambahkan (warranty_number, expiry, status, claim_type, images).
- [x] `StoreBranch` model ditambahkan dengan `availableCapacity(Carbon $date)` — menghitung sisa slot dengan mempertimbangkan `BranchSchedule` override dan appointment yang sudah ada.
- [x] `BranchSchedule` model ditambahkan — override kapasitas atau tutup per tanggal.
- [x] `Appointment` model ditambahkan dengan `generateNumber()` dan `getServiceLabelAttribute()`.
- [x] `Warranty` model ditambahkan dengan `isActive()`, `daysRemaining()`, `generateNumber()`.
- [x] `ServiceClaim` model ditambahkan dengan `generateNumber()` dan `getClaimTypeLabelAttribute()`.
- [x] `PrescriptionValidationService` ditambahkan: detect missing fields, warn impossible values (sphere/cylinder/axis/PD range), recommend lens (high_index, progressive, anti_radiation, photochromic), completeness score 0-100.
- [x] `AppointmentController` ditambahkan: `GET /api/branches`, `GET /api/branches/{id}/availability`, `GET/POST/GET/{id}/DELETE /api/appointments`, `POST /api/prescriptions/validate`.
- [x] `WarrantyController` ditambahkan: `GET /api/warranties`, `GET /api/warranties/{id}`, `GET /api/service-claims`, `POST /api/service-claims`.
- [x] Routes ditambahkan: branches (public), prescription validate (public), appointments (protected), warranties/service-claims (protected).
- [x] `StoreBranchResource` Filament Resource ditambahkan — CRUD cabang dengan operating hours, kapasitas, koordinat.
- [x] `AppointmentResource` Filament Resource ditambahkan — tabel appointment dengan filter hari ini/mendatang, action confirm/complete, bulk confirm.
- [x] `WarrantyResource` Filament Resource ditambahkan — tabel garansi dengan filter expiring soon, edit status.
- [x] `AppointmentPage.vue` ditambahkan — booking appointment dengan pilih cabang, layanan, tanggal, slot waktu (real-time availability check), data pelanggan, dan riwayat appointment.
- [x] `WarrantyPage.vue` ditambahkan — tabs garansi saya, riwayat klaim, form klaim baru dengan foto upload.
- [x] Router diupdate: tambah `/appointment` dan `/warranty`.
- [x] Backend feature tests: `AppointmentTest` (10 tests), `WarrantyServiceClaimTest` (8 tests), `PrescriptionValidationTest` (10 tests).
- [x] Semua 137 backend tests pass.
- [x] Frontend build pass.

### Remaining Phase 9 Items

- [ ] Optional follow-up: Virtual Try-On v2 dengan camera mode dan face landmark detection (memerlukan MediaPipe atau TensorFlow.js).
- [ ] Optional follow-up: AI-assisted frame recommendation berbasis purchase history.
- [ ] Optional follow-up: Omnichannel pickup (reserve online, pay in store).
- [x] Optional follow-up: Appointment reminder email/WhatsApp otomatis. ✅ Selesai — `AppointmentReminderMail`, `SendAppointmentReminder` job (daily 18:00, kirim ke appointment besok), template email dengan detail cabang dan maps link.

## Gap Utama Saat Ini

### Customer Experience

- Prescription builder sudah ada untuk saved profile, reuse, upload attachment, dan admin verification; form lengkap di halaman profile masih optional follow-up.
- Frame plus lens configurator sudah ada untuk lensa, coating, backend quote, checkout snapshot, dan admin order detail.
- Rekomendasi product detail sudah ada untuk similar frames, compatible lenses, dan lens options; rekomendasi otomatis berbasis resep/kebutuhan masih bisa dipertajam.
- Size guide frame sudah ada dari metadata ukuran; panduan interaktif masih bisa ditambah.
- Belum ada virtual try-on.
- Filter produk optik sudah mencakup brand, gender, shape, material, color, face size, stock, prescription, price, promo, dan sorting.
- Checkout belum berbentuk guided stepper yang mudah dipahami.
- Belum ada guest checkout atau quick checkout.
- Product comparison sudah ada untuk maksimal 4 produk.
- Belum ada cart recovery.

### Admin Experience

- Admin panel sudah ada, tetapi belum cukup dashboard-oriented.
- Belum ada operational dashboard yang langsung menunjukkan order pending, low stock, payment proof, komplain, retur, dan revenue.
- Belum ada order kanban board.
- Belum ada bulk action untuk operasional harian.
- Belum ada inventory alert dan reorder workflow.
- Belum ada activity log admin yang komprehensif.
- Role permission masih perlu diperjelas per tim: owner, finance, warehouse, customer service, content manager.

### Growth dan Marketing

- Promo sudah ada, tetapi belum menjadi campaign builder yang fleksibel.
- Belum ada abandoned cart recovery.
- Belum ada referral customer yang terpisah dari affiliate.
- Belum ada review dengan foto.
- Belum ada structured data product yang lengkap.
- Belum ada Google Merchant Center product feed.
- Belum ada funnel analytics.

### Quality dan Reliability

- Belum ada E2E test checkout end-to-end.
- Belum ada monitoring error production seperti Sentry.
- Belum ada business metric dashboard.
- Belum ada health check endpoint.
- Belum ada queue retry policy yang jelas untuk email, webhook, dan payment sync.
- Belum ada performance budget untuk Core Web Vitals.

## Phase 0 - Stabilization Baseline

### Goal

Mengunci fondasi yang sudah diperbaiki agar semua fase berikutnya tidak merusak checkout, payment, order, dan loyalty.

### Backend

- Pastikan semua test backend pass:
  - `php artisan test`
  - test audit di `tests/Audit`
- Tambahkan test khusus untuk:
  - stock race condition pada checkout.
  - `shipping_address_id` milik user lain.
  - discount dan promo tidak bisa dipakai bersamaan.
  - loyalty points tidak bisa melebihi saldo.
  - payment webhook replay idempotent.
- Tambahkan endpoint health check:
  - `GET /api/health`
  - response minimal: `status`, `time`, `app_env`, `database`.
- Tambahkan request correlation ID middleware:
  - generate ID jika header tidak ada.
  - attach ke log.
  - return header `X-Request-ID`.
- Review semua protected route di `routes/api.php`.
- Pastikan endpoint ID-based memakai ownership check.

### Frontend

- Pastikan build pass:
  - `npm run build`
- Rapikan warning CSS `@import` order.
- Tambahkan error boundary sederhana untuk route utama.
- Tambahkan reusable loading, error, empty state component.
- Pastikan checkout tetap memakai backend calculate sebagai sumber total.

### Admin

- Tambahkan halaman admin sederhana untuk melihat:
  - order unpaid.
  - payment proof pending.
  - low stock.
  - complaint open.
  - return pending.

### QA

- Buat checklist manual regression:
  - register dan OTP.
  - login dan OTP.
  - add to cart.
  - checkout frame only.
  - checkout frame plus lens.
  - checkout produk resep tanpa prescription.
  - discount.
  - promo.
  - loyalty points.
  - Xendit payment.
  - manual payment proof.
  - confirm delivery.
  - complaint.
  - return.

### Acceptance Criteria

- Backend tests pass.
- Frontend build pass.
- Semua protected endpoint utama sudah punya ownership guard.
- Admin punya halaman ringkas untuk masalah operasional harian.
- Manual regression checklist tersedia.

## Phase 1 - Optical Product Foundation

### Goal

Membuat data produk optik lebih lengkap agar bisa mendukung fitur frame finder, prescription, lens configurator, SEO, dan product feed.

### Backend Data Model

Tambahkan atau audit field produk:

- `sku`
- `brand`
- `gender`
- `frame_shape`
- `frame_material`
- `frame_color`
- `lens_width`
- `bridge_width`
- `temple_length`
- `frame_width`
- `face_size_fit`
- `is_frame`
- `is_lens`
- `is_contact_lens`
- `is_sunglasses`
- `is_prescription_required`
- `prescription_supported`
- `min_sphere`
- `max_sphere`
- `min_cylinder`
- `max_cylinder`
- `has_blue_light_option`
- `has_photochromic_option`
- `has_high_index_option`
- `google_product_category`
- `gtin`
- `mpn`
- `condition`

Tambahkan model atau tabel baru:

- `LensOption`
  - name
  - type: single_vision, progressive, reading, blue_light, photochromic, high_index, anti_radiation
  - base_price
  - prescription_rules
  - is_active
- `LensCoating`
  - name
  - price
  - description
  - is_active
- `ProductCompatibility`
  - frame_product_id
  - lens_option_id
  - compatibility_rule

### Backend API

- `GET /api/products/filters`
  - return available filter values.
- Update product listing filter:
  - category
  - brand
  - frame shape
  - frame material
  - frame color
  - gender
  - face size fit
  - price range
  - prescription support
  - promo only
  - in stock only
- `GET /api/products/{slug}/recommendations`
  - similar frames.
  - compatible lenses.
  - related products.

### Frontend

- Upgrade product listing:
  - filter sidebar desktop.
  - filter bottom sheet mobile.
  - active filter chips.
  - clear filters.
  - sorting.
- Product detail:
  - frame size table.
  - fit guide.
  - compatible lens options.
  - delivery estimate.
  - product trust badges.
- Add comparison entry point:
  - compare by shape, material, size, weight, price, rating.

### Admin

- Product form harus mendukung field optik baru.
- Tambahkan bulk import/export CSV untuk produk.
- Tambahkan validasi admin:
  - frame wajib punya ukuran.
  - produk prescription wajib punya rule resep.
  - produk publik wajib punya gambar utama.
  - SKU unique.

### QA

- Test product filters.
- Test product detail for missing size data.
- Test product not in stock does not appear as buyable.
- Test deleted product remains visible in old order item references.

### Acceptance Criteria

- User bisa filter produk berdasarkan atribut optik.
- Admin bisa mengelola data optik lengkap dari Filament.
- Produk frame punya size guide.
- Product listing mobile tetap usable.

## Phase 2 - Prescription Builder

### Goal

Membuat user bisa menyimpan, memilih, dan memakai resep mata secara aman dan mudah.

### Backend Data Model

Tambahkan model `PrescriptionProfile`:

- `user_id`
- `label`
- `right_sphere`
- `right_cylinder`
- `right_axis`
- `right_add`
- `left_sphere`
- `left_cylinder`
- `left_axis`
- `left_add`
- `pd_single`
- `pd_right`
- `pd_left`
- `notes`
- `attachment_path`
- `verified_by`
- `verified_at`
- `is_default`

Validasi:

- sphere dan cylinder numeric.
- axis 0 sampai 180 jika cylinder diisi.
- ADD hanya untuk progressive/reading.
- PD wajib untuk lensa tertentu.
- Attachment hanya jpg, jpeg, png, webp, pdf.
- Maksimal attachment 4 MB.

### Backend API

- `GET /api/prescriptions`
- `POST /api/prescriptions`
- `GET /api/prescriptions/{id}`
- `PUT /api/prescriptions/{id}`
- `DELETE /api/prescriptions/{id}`
- `POST /api/prescriptions/{id}/set-default`

Semua endpoint wajib ownership guard.

### Frontend

- Halaman profile prescription:
  - list resep.
  - tambah resep.
  - edit resep.
  - upload file resep.
  - set default.
- Form prescription harus user friendly:
  - tab kanan/kiri.
  - hint cara baca resep.
  - validasi inline.
  - contoh format resep.
- Checkout:
  - pilih resep tersimpan.
  - input resep baru.
  - upload resep dan lanjutkan.

### Admin

- Admin bisa melihat resep pada order.
- Admin bisa menandai resep sebagai verified.
- Admin bisa memberi catatan jika resep perlu dikonfirmasi.

### QA

- Test user tidak bisa akses resep user lain.
- Test invalid axis ditolak.
- Test attachment invalid ditolak.
- Test checkout produk prescription tanpa resep ditolak.
- Test checkout dengan resep tersimpan berhasil.

### Acceptance Criteria

- User bisa menyimpan banyak resep.
- User bisa memilih resep saat checkout.
- Order item menyimpan snapshot prescription.
- Admin bisa membaca dan memverifikasi resep.

## Phase 3 - Frame Plus Lens Configurator

### Goal

Membuat flow pembelian kacamata terasa guided: pilih frame, pilih lensa, pilih coating, input resep, review harga.

### Backend Data Model

Tambahkan struktur snapshot di order item:

- `lens_option_id`
- `lens_coating_id`
- `prescription_profile_id`
- `lens_price`
- `coating_price`
- `configuration_snapshot`

Tambahkan service:

- `OpticalPricingService`
  - menghitung harga frame.
  - menghitung harga lensa.
  - menghitung coating.
  - validasi prescription compatibility.
  - return breakdown.

### Backend API

- `POST /api/optical/configure`
  - input: frame product, lens option, coating, prescription.
  - output: compatibility, price breakdown, warning.
- Update `POST /api/orders/calculate`
  - support optical configuration.
- Update `POST /api/orders`
  - persist optical snapshot.

### Frontend

- Product detail frame:
  - button `Pilih Frame dan Lensa`.
- Configurator stepper:
  1. Frame summary.
  2. Lens type.
  3. Coating.
  4. Prescription.
  5. Review.
- UX detail:
  - tampilkan rekomendasi lensa.
  - tampilkan alasan rekomendasi.
  - tampilkan warning jika resep tidak cocok.
  - sticky price summary.
  - mobile first.

### Admin

- Admin order detail menampilkan:
  - frame.
  - lens option.
  - coating.
  - prescription.
  - price breakdown.
  - customer note.
- Admin bisa update status produksi:
  - waiting_prescription_review
  - prescription_verified
  - lens_processing
  - ready_to_ship

### QA

- Test frame only.
- Test frame plus lens.
- Test incompatible prescription.
- Test high minus recommends high index.
- Test price frontend equals backend.
- Test order snapshot tidak berubah meski produk/lensa diubah setelah order.

### Acceptance Criteria

- User bisa membeli kacamata lengkap tanpa bingung.
- Total harga configurator sama dengan backend order calculate.
- Admin bisa melihat detail konfigurasi optik pada order.

## Phase 4 - Discovery, Recommendation, and Conversion

### Goal

Membantu user menemukan produk lebih cepat dan meningkatkan conversion rate.

### Backend

- [x] Search service:
  - [x] query title, brand, category, tags.
  - [x] typo tolerance dicatat sebagai future enhancement jika memakai external search seperti Meilisearch/Algolia; fallback database search aktif sekarang.
  - [x] fallback database search jika belum memakai search engine.
- Recommendation rules:
  - [x] similar by frame shape.
  - [x] similar by brand.
  - [x] similar by size.
  - [x] compatible lens.
- [x] frequently bought together.
- [x] Product comparison API:
  - [x] `POST /api/products/compare`

### Frontend

- [x] Search autocomplete:
  - [x] product suggestions.
  - [x] category suggestions.
  - [x] recent search.
- [x] Product compare:
  - [x] maksimal 4 produk.
  - [x] sticky compare bar.
  - [x] compare table.
- [x] Recently viewed.
- [x] Wishlist share link.
- [x] Face shape quiz:
  - [x] bentuk wajah.
  - [x] style preference.
  - [x] ukuran wajah.
  - [x] budget.
  - [x] hasil rekomendasi.
- [x] Virtual try-on v1:
  - [x] upload foto.
  - [x] overlay frame image.
  - [x] scale manual.
  - [x] save preview.

### Admin

- [x] Admin bisa set:
  - [x] product tags.
  - [x] recommendation priority.
  - [x] featured products.
  - [x] campaign landing products.

### QA

- [x] Test search tidak bocor produk unpublished/deleted.
- [x] Test compare maksimal 4 produk.
- [x] Test recommendation tidak menampilkan out of stock jika filter buyable aktif.
- [x] Test wishlist share tidak mengekspos data private user.

### Acceptance Criteria

- [x] User bisa search cepat dari navbar.
- [x] User bisa membandingkan produk.
- [x] User mendapat rekomendasi relevan pada product detail.
- [x] User bisa mengikuti quiz untuk rekomendasi frame.

## Phase 5 - Checkout, Payment, and Retention

### Goal

Mengurangi checkout abandonment dan membuat payment flow lebih jelas.

### Backend

- Guest checkout atau quick checkout:
  - opsi A: guest order dengan email dan phone.
  - opsi B: quick account creation setelah order.
  - tetap wajib validasi OTP sebelum melihat data sensitif.
- Cart persistence server-side:
  - `Cart`
  - `CartItem`
  - merge anonymous cart ke user cart setelah login.
- Abandoned cart:
  - schedule job.
  - email/WhatsApp reminder.
  - optional coupon recovery.
- Payment status polling:
  - `GET /api/orders/{id}/payment-status`
- Webhook retry log:
  - store gateway event.
  - idempotency key.
  - processed_at.

### Frontend

- Checkout stepper:
  1. Cart.
  2. Address.
  3. Prescription/lens.
  4. Shipping.
  5. Payment.
  6. Review.
- Payment waiting page:
  - polling status.
  - show timeout.
  - show retry sync.
  - manual instructions.
- Cart recovery UX:
  - restore cart banner.
  - saved cart indicator.
- Better error messages:
  - stok berubah.
  - promo expired.
  - shipping unavailable.
  - payment failed.

### Admin

- Payment dashboard:
  - Xendit pending.
  - manual proof pending.
  - failed/expired payments.
  - reconciliation export.
- Abandoned cart dashboard:
  - cart value.
  - last activity.
  - recovery status.

### QA

- E2E checkout:
  - guest checkout.
  - logged-in checkout.
  - Xendit redirect.
  - manual payment.
  - expired payment.
  - abandoned cart recovery.
- Test idempotent payment webhook.

### Acceptance Criteria

- User paham status payment setelah order.
- Cart tidak hilang jika user belum checkout.
- Admin bisa memproses pembayaran lebih cepat.
- Checkout mobile tidak membingungkan.

## Phase 6 - Admin Operations and Workflow

### Goal

Membuat admin panel menjadi pusat operasional toko, bukan hanya form CRUD.

### Admin Dashboard

Tambahkan KPI:

- Revenue today.
- Revenue this month.
- Order count by status.
- Average order value.
- Pending payment proof.
- Pending prescription review.
- Processing orders.
- Shipped orders.
- Complaint open.
- Return pending.
- Low stock products.
- Best-selling products.
- Top brands.

### Order Board

Tambahkan kanban:

- unpaid.
- paid.
- waiting_prescription_review.
- processing.
- lens_processing.
- ready_to_ship.
- shipped.
- delivered.
- completed.
- cancelled.

Fitur:

- drag status jika role diizinkan.
- bulk update tracking.
- print invoice.
- print shipping label.
- add internal note.
- customer notification trigger.

### Inventory Workflow

- Low stock threshold per product/variant.
- Reorder suggestion.
- Stock adjustment log.
- Stock opname page.
- Import stock CSV.
- Export inventory CSV.

### Role Permission

Role awal:

- owner
- admin
- finance
- warehouse
- customer_service
- content_manager

Permission contoh:

- finance bisa verifikasi payment.
- warehouse bisa update shipping/tracking.
- customer service bisa handle complaint/return.
- content manager bisa manage article/banner.
- owner bisa lihat finance report dan settings.

### QA

- Test each role cannot access unauthorized admin resource.
- Test admin action creates audit log.
- Test bulk action respects validation.

### Acceptance Criteria

- Admin bisa memproses order harian dari satu dashboard.
- Semua aksi penting admin terekam.
- Role permission membatasi akses sesuai pekerjaan.

## Phase 7 - Marketing, SEO, and Marketplace Readiness

### Goal

Meningkatkan traffic dan conversion dari search, ads, dan campaign.

### Backend

- Product structured data support:
  - product name.
  - image.
  - price.
  - availability.
  - brand.
  - rating.
  - review.
  - shipping.
  - return policy.
- Google Merchant Center feed:
  - XML or TSV feed.
  - product id/SKU.
  - title.
  - description.
  - link.
  - image_link.
  - price.
  - availability.
  - brand.
  - gtin/mpn.
  - condition.
  - shipping weight.
  - google_product_category.
- Campaign builder:
  - campaign name.
  - start/end date.
  - target products/categories/brands.
  - discount rules.
  - usage limits.
  - landing page.
- Referral customer:
  - referral code.
  - reward inviter.
  - reward invitee.
  - fraud guard.

### Frontend

- SEO metadata per route.
- Product JSON-LD.
- Category landing pages.
- Brand landing pages.
- Promo landing pages.
- Review request flow after completed order.
- Photo review upload.
- Loyalty landing page.
- Referral landing page.

### Admin

- SEO field editor:
  - meta title.
  - meta description.
  - canonical slug.
  - OG image.
- Merchant feed diagnostics:
  - missing SKU.
  - missing image.
  - missing brand.
  - missing availability.
  - invalid price.
- Campaign performance:
  - impressions if tracked.
  - clicks.
  - orders.
  - revenue.
  - conversion.

### QA

- Validate structured data with Google Rich Results Test.
- Validate merchant feed required fields.
- Test campaign expiry.
- Test referral self-referral blocked.
- Test review only from completed order.

### Acceptance Criteria

- Product pages eligible for richer Google product results.
- Merchant feed can be generated.
- Admin can launch campaign without developer deploy.
- Review and referral flows are abuse-resistant.

## Phase 8 - Observability, Analytics, and Performance

### Goal

Membuat sistem bisa dipantau, diukur, dan ditingkatkan secara berkelanjutan.

### Backend

- Integrasi error monitoring:
  - Sentry or equivalent.
- Business events:
  - product_viewed.
  - add_to_cart.
  - checkout_started.
  - shipping_selected.
  - payment_selected.
  - order_created.
  - payment_success.
  - order_cancelled.
  - complaint_created.
  - return_requested.
- Queue monitoring:
  - failed jobs.
  - retry count.
  - last failure reason.
- Payment monitoring:
  - webhook received.
  - webhook rejected.
  - webhook replay ignored.
  - sync payment result.

### Frontend

- Track funnel events.
- Track frontend errors.
- Track failed checkout reasons.
- Track search terms with no results.
- Track filter usage.
- Performance budget:
  - LCP target under 2.5s.
  - CLS target under 0.1.
  - INP target under 200ms.
- Image optimization:
  - WebP or AVIF.
  - responsive image sizes.
  - lazy loading.
  - placeholder.

### Admin

- Analytics dashboard:
  - funnel conversion.
  - checkout abandonment.
  - top failed checkout reason.
  - top no-result search terms.
  - product views to cart ratio.
  - cart to order ratio.
  - order to payment success ratio.

### QA

- Lighthouse baseline.
- Core Web Vitals baseline.
- Accessibility audit based on WCAG 2.2 AA target.
- Test keyboard navigation checkout.
- Test screen reader labels on forms.

### Acceptance Criteria

- Team bisa melihat alasan checkout gagal.
- Team bisa melihat produk dicari tetapi tidak ditemukan.
- Frontend performance punya target jelas.
- Accessibility tidak hanya visual, tetapi keyboard dan screen reader friendly.

## Phase 9 - Advanced Optical Commerce

### Goal

Menambahkan fitur pembeda yang membuat platform terasa premium.

### Features

- Virtual try-on v2:
  - camera mode.
  - face landmark.
  - frame scaling.
  - screenshot/share.
- AI-assisted frame recommendation:
  - berdasarkan quiz.
  - berdasarkan purchase history.
  - berdasarkan face shape.
- Prescription validation assistant:
  - detect missing fields.
  - warn impossible values.
  - recommend lens based on prescription.
- Store appointment booking:
  - pilih cabang.
  - pilih jadwal.
  - pilih layanan: eye test, pickup, fitting.
- Omnichannel pickup:
  - ship to address.
  - pickup at store.
  - reserve online, pay in store.
- Warranty and service tracking:
  - warranty period.
  - service claim.
  - lens replacement.

### Backend

- Appointment models.
- Store branch models.
- Warranty models.
- Service claim workflow.
- Recommendation service.

### Frontend

- Appointment booking page.
- Store locator.
- Warranty page.
- Service claim form.
- Enhanced virtual try-on.

### Admin

- Branch schedule management.
- Appointment calendar.
- Warranty claim management.
- Service claim workflow.

### QA

- Test appointment capacity.
- Test branch availability.
- Test warranty claim ownership.
- Test virtual try-on fallback if camera unavailable.

### Acceptance Criteria

- User bisa booking eye test or fitting.
- User bisa track warranty/service.
- Website punya fitur optik premium yang sulit ditiru e-commerce umum.

## Technical Backlog by Area

### Backend Backlog

- Health check endpoint.
- Correlation ID middleware.
- Activity log service.
- Optical pricing service.
- Prescription profile module.
- Lens option and coating module.
- Product filter API.
- Product recommendation API.
- Product comparison API.
- Merchant feed generator.
- Campaign builder.
- Referral customer module.
- Cart persistence module.
- Abandoned cart job.
- Payment event log.
- Admin role permission.
- Queue monitoring.
- Analytics event table or integration.

### Frontend Backlog

- Product filter UX.
- Mobile filter drawer.
- Product comparison UI.
- Prescription builder UI.
- Frame plus lens configurator.
- Checkout stepper.
- Payment waiting page.
- Recently viewed.
- Search autocomplete.
- Face shape quiz.
- Wishlist share.
- Review with photo.
- Referral page.
- Loyalty landing page.
- Virtual try-on v1.
- Empty/loading/error state components.
- Accessibility improvements.
- Performance image handling.

### Admin Backlog

- Operational dashboard.
- Order kanban.
- Payment proof dashboard.
- Prescription review queue.
- Low stock dashboard.
- Inventory adjustment log.
- Bulk order actions.
- Bulk product import/export.
- Campaign manager.
- Merchant feed diagnostics.
- SEO metadata editor.
- Analytics dashboard.
- Role and permission matrix.
- Admin activity log.

### QA Backlog

- Playwright E2E checkout.
- Payment webhook replay tests.
- Stock race condition tests.
- Ownership authorization tests.
- Product filter tests.
- Prescription validation tests.
- Optical configurator pricing tests.
- Admin permission tests.
- Merchant feed validation tests.
- Accessibility audit.
- Core Web Vitals baseline.

## Suggested Implementation Order

Urutan paling aman:

1. Phase 0 - Stabilization Baseline.
2. Phase 1 - Optical Product Foundation.
3. Phase 2 - Prescription Builder.
4. Phase 3 - Frame Plus Lens Configurator.
5. Phase 6 - Admin Operations and Workflow.
6. Phase 5 - Checkout, Payment, and Retention.
7. Phase 4 - Discovery, Recommendation, and Conversion.
8. Phase 7 - Marketing, SEO, and Marketplace Readiness.
9. Phase 8 - Observability, Analytics, and Performance.
10. Phase 9 - Advanced Optical Commerce.

Alasan:

- Data produk optik harus rapi sebelum recommendation, configurator, SEO, dan merchant feed.
- Prescription harus ada sebelum optical configurator.
- Admin operations harus diperkuat sebelum volume order diperbesar oleh marketing.
- Observability idealnya masuk sebelum campaign besar berjalan.

## Definition of Done Global

Sebuah fitur dianggap selesai jika:

- Backend validation lengkap.
- Ownership guard ada untuk data user.
- Database transaction dipakai untuk operasi finansial/inventory.
- Frontend punya loading, success, error, empty state.
- Admin dapat mengelola fitur tanpa developer.
- Test backend minimal feature/unit tersedia.
- Untuk flow kritis, E2E test tersedia.
- Dokumentasi admin atau catatan penggunaan tersedia.
- Audit report diperbarui.

## Referensi Standar

- OWASP API Security Top 10 2023: authorization, authentication, unrestricted resource consumption, business flow abuse, SSRF, misconfiguration, and unsafe third-party API consumption.
- Google Product structured data: product pages dapat menampilkan price, availability, reviews, and shipping information di search result.
- Google Merchant Center product data specification: product feed harus memiliki identifier, title, description, price, availability, brand, image, shipping, and product attributes.
- Core Web Vitals: LCP, CLS, and INP sebagai baseline performance UX.
- WCAG 2.2: target accessibility untuk keyboard navigation, contrast, focus state, labels, and cognitive usability.
- Baymard checkout UX research: checkout harus jelas, account/guest flow tidak boleh membingungkan, dan mobile checkout harus mudah dipahami.
