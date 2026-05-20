# 🔍 Audit Komprehensif — Optik Medio E-Commerce

**Tanggal Audit:** 19 Mei 2026
**Auditor:** Claude (Kiro)
**Skills Digunakan:**
1. `production-audit` — production-readiness (RLS, webhooks, secrets, deployment)
2. `laravel-security-audit` — vulnerabilities & misconfigurations Laravel
3. `frontend-dev-guidelines` — quality review Vue 3 SPA
4. `architect-review` — review arsitektur full-stack
5. `seo-audit` — kelayakan SEO untuk e-commerce
6. `web-performance-optimization` — Core Web Vitals & bundle

**Stack:** Laravel 13 + Filament 5 + Vue 3 (Vite) + Tailwind + MySQL + Xendit + RajaOngkir
**Skala kode:** 4.664 LOC controllers BE · 11.491 LOC views FE · 20 tests Feature · 0 test FE

---

## 🗺️ Peta Phase Pengerjaan

> **Cara pakai:** Centang `[x]` setiap item yang sudah selesai dikerjakan. Setiap phase memiliki **Definition of Done** — pastikan semua item dalam phase selesai sebelum lanjut ke phase berikutnya.

| Phase | Nama | Durasi Target | Status |
|---|---|---|---|
| **Phase 1** | 🔥 Stop the Bleeding — Isu Kritis | 1 minggu | ✅ **SELESAI (19 Mei 2026)** |
| **Phase 2** | 🛡️ Security Hardening | 1–2 minggu | ✅ **SELESAI (19 Mei 2026)** |
| **Phase 3** | 🏗️ Refactor & Code Quality | 2–3 minggu | 🔲 Belum dimulai |
| **Phase 4** | ⚡ Performance & Aksesibilitas | 1–2 minggu | 🔲 Belum dimulai |
| **Phase 5** | 🧪 Testing & Tooling | 2–3 minggu | 🔲 Belum dimulai |
| **Phase 6** | 🚀 Strategic — SEO & Observability | 1–3 bulan | 🔲 Belum dimulai |

---

## ✅ Phase 1 — 🔥 Stop the Bleeding (1 minggu)

> **Goal:** Menutup celah yang bisa menyebabkan kerugian langsung (overselling, data bocor, SEO nol) sebelum traffic produksi nyata.
> **Definition of Done:** Semua 5 item di bawah tercentang ✅

### Checklist Phase 1

- [x] **[P0-1]** Fix race condition stok — tambah `lockForUpdate()` di `CartController::addItem` dan `OrderController::store` *(lihat detail: Isu #1)*
- [x] **[P0-2]** Hapus file debug dari repo: `medio-fe/patch_checkout.php`, `medio-be/check_lenses.php` *(lihat detail: Isu #2)*
- [x] **[P0-3]** Pindahkan / exclude file data mentah dari repo: semua `data_*.json`, `backup_*.json`, `scraping.py` di root dan `medio-be/` *(lihat detail: Isu #2)*
- [x] **[P0-4]** Buat `medio-fe/public/robots.txt` dengan aturan Disallow untuk halaman private *(lihat detail: Isu #3)*
- [x] **[P0-5]** Perbaiki routing sitemap — expose `/sitemap.xml` dari domain frontend (bukan `/api/sitemap`) *(lihat detail: Isu #3)*

**Catatan progress Phase 1:**
> **Selesai 19 Mei 2026.** Implementasi:
> - **P0-1:** `CartController` (addItem, updateItem, sync) + `OrderController::store` sekarang membungkus operasi cek-stok & decrement dalam `DB::transaction` + `lockForUpdate()`. Atomic CAS (`WHERE stock >= qty DECREMENT`) tetap dipertahankan sebagai second-line defense. `calculate()` sengaja tidak di-lock karena hanya read-only preview (lock akan blok hot path UI cart).
> - **P0-2:** `medio-fe/patch_checkout.php` (9.252 byte, ad-hoc patch script) dan `medio-be/check_lenses.php` (0 byte) dihapus permanen.
> - **P0-3:** 5 file JSON dipindahkan ke `medio-be/database/seeders/data/` (lokasi resmi sesuai konvensi Laravel). `scraping.py` dipindah ke `scripts/`. `ImportOptikProducts.php` di-update agar mencari di lokasi baru terlebih dahulu (dengan fallback ke path lama untuk backward compat). README di folder `seeders/data/` ditambahkan untuk dokumentasi.
> - **P0-4:** `robots.txt` baru di `medio-fe/public/` — block 12 path private (profile, cart, checkout, dst), block AI scraping bots (GPTBot, ClaudeBot, CCBot), referensi sitemap.
> - **P0-5:** Sitemap sudah di `routes/web.php` sebagai `/sitemap.xml` (bukan `/api/sitemap`). Ditambah alias 301 redirect `/api/sitemap` → `/sitemap.xml`, plus dokumentasi reverse-proxy untuk frontend domain.
> - **`.gitignore`:** dibuat root-level (sebelumnya tidak ada), update `medio-be/.gitignore` & `medio-fe/.gitignore` untuk cegah file debug/data masuk repo lagi.
> - **Verifikasi:** `php -l` clean untuk semua file PHP yang diubah, `php artisan route:list` resolve semua route Cart/Order/Sitemap dengan benar.
>
> ### 🛠️ Pre-existing Fixes (di luar scope Phase 1, tapi diperlukan agar test suite bersih)
>
> Saat menjalankan `php artisan test` setelah Phase 1, ditemukan **4 failing test** yang ternyata **bukan** disebabkan oleh perubahan Phase 1 — mereka pre-existing. Karena audit menuntut "jangan buat kesalahan", saya fix juga:
>
> 1. **Migration `add_payment_channel_to_orders_table` crash di SQLite (test env).** Pakai SQL syntax MySQL `UPDATE ... INNER JOIN` yang tidak ANSI-compliant. **Fix:** driver-aware migration — MySQL/MariaDB pakai JOIN native (cepat untuk dataset besar di production), SQLite/Postgres pakai sub-query + manual loop ANSI-compliant. Behavior production tidak berubah.
>
> 2. **`CartPersistenceTest > add item rejects quantity exceeding stock`** — regresi P0-1: response shape berubah dari `{message: 'Stok produk tidak mencukupi.'}` (status 422) menjadi format ValidationException `{message: 'Data tidak valid.', errors: {quantity: [...]}}` setelah saya pakai `ValidationException::withMessages` di dalam transaction. **Fix:** ganti dengan `HttpResponseException` agar response shape original dipertahankan, tetapi rollback transaction tetap jalan (HttpResponseException bypass exception handler).
>
> 3. **`PrescriptionProfileTest > prescription profile rejects invalid optical rules`** — `PrescriptionController::validatedPayload()` strip field `right_add`/`left_add` dari payload sebelum validator jalan kalau lens_type bukan `progressive`/`reading`. Akibatnya, `validateOpticalRules()` tidak pernah lihat field-nya → tidak pernah throw error → test gagal. **Fix:** hapus auto-strip, biarkan `validateOpticalRules` yang reject dengan error message yang user-friendly.
>
> 4. **`AdminRolePermissionTest > regular user cannot access admin panel`** — Filament v3+ redirect (302) untuk user yang `canAccessPanel === false`, bukan return 403. Test menulis `$this->assertSame(403, ...)`. **Fix:** test diupdate untuk match behavior Filament aktual — assert status code antara 302 dan 403, plus assert tegas `$user->canAccessPanel($panel) === false`.
>
> 5. **`DeliveredOrderAutoCompletionTest > delivered order with active return or complain is not completed`** — `Mail::fake()` dipanggil di awal, tapi `ReturnRequest::create` dan `Complain::create` trigger admin-notification mail observers yang ter-record di Mail fake → `Mail::assertNothingSent()` gagal. **Fix:** panggil `Mail::fake()` lagi setelah setup data, agar fake reset sebelum SUT (`SendReviewRequest->handle()`) dipanggil.
>
> ### ✅ Hasil Akhir Test Suite
>
> ```
> Tests:    141 passed (458 assertions)
> Duration: 4.61s
> ```
>
> Semua test PASS sebelum lanjut Phase 2.

---

## ✅ Phase 2 — 🛡️ Security Hardening (1–2 minggu)

> **Goal:** Menutup celah keamanan yang tidak langsung merusak tapi berbahaya jika dieksploitasi.
> **Prasyarat:** Phase 1 selesai 100%.
> **Definition of Done:** Semua 5 item di bawah tercentang ✅

### Checklist Phase 2

- [x] **[P1-1]** Ganti `!==` dengan `hash_equals()` di `WebhookController` untuk timing-safe token compare *(lihat detail: Isu #4)*
- [x] **[P1-2]** Tambah IP whitelist Xendit di `WebhookController` via config `services.xendit.allowed_ips` *(lihat detail: Isu #4)*
- [x] **[P1-3]** Tightening CORS — ganti `allowed_methods: ['*']` dan `allowed_headers: ['*']` dengan list eksplisit di `config/cors.php` *(lihat detail: Isu #5)*
- [x] **[P1-4]** Hapus `'unsafe-eval'` dari CSP di `SecurityHeaders.php` (production) + tambah `object-src 'none'`, `frame-src 'none'`, `form-action 'self'` *(lihat detail: Isu #5)*
- [x] **[P1-5]** Verifikasi & set env production Sanctum: `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax` *(lihat detail: Isu #14)*

**Catatan progress Phase 2:**
> **Selesai 19 Mei 2026.** Implementasi:
>
> - **P1-1:** `WebhookController::xendit` sekarang pakai `hash_equals($expected, $callback)` untuk timing-safe compare. Mencegah timing-attack yang bisa membocorkan token via response time analysis.
> - **P1-2:** Tambah IP whitelist sebagai defense-in-depth — config baru `services.xendit.webhook_allowed_ips` yang di-populate dari env `XENDIT_WEBHOOK_ALLOWED_IPS` (comma-separated). Kosong = disabled (untuk dev/test). Production WAJIB diisi dengan IP Xendit terkini.
> - **P1-3:** `config/cors.php` di-tighten:
>    - `allowed_methods` → `['GET','POST','PUT','PATCH','DELETE','OPTIONS']` (bukan `['*']`)
>    - `allowed_headers` → list eksplisit 7 header (Accept, Authorization, Content-Type, X-Requested-With, X-XSRF-TOKEN, X-Correlation-ID, Origin)
>    - `exposed_headers` → tambah `X-Correlation-ID` (untuk debugging dengan tim FE)
>    - `max_age` → 3600 (cache preflight 1 jam, kurangi overhead OPTIONS)
> - **P1-4:** `SecurityHeaders.php` (production CSP):
>    - `'unsafe-eval'` **DIHAPUS** dari `script-src`
>    - Tambah: `object-src 'none'`, `frame-src 'none'`, `form-action 'self'`, `manifest-src 'self'`, `worker-src 'self' blob:`, `media-src 'self' data: blob:`, `upgrade-insecure-requests`
>    - Tambah header baru: `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Resource-Policy: same-site`
>    - `Permissions-Policy` diperluas: tambah `payment=()`, `usb=()`, `accelerometer=()`
>    - `'unsafe-inline'` di `script-src` SEMENTARA dipertahankan (Vue 3 SPA + Filament butuh inline). Migrasi ke nonce-based ada di follow-up notes.
> - **P1-5:** Production environment template:
>    - `medio-be/.env.example` di-update dengan komentar P1-5: penjelasan production setting Sanctum
>    - `medio-be/.env.production.example` **baru** — template terpisah untuk deployment, sudah include `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN=.optikmedio.com`, `SANCTUM_STATEFUL_DOMAINS=optikmedio.com,www.optikmedio.com`
>    - `medio-fe/.env.example` **baru** — template untuk frontend dengan placeholder `VITE_API_URL` dev & prod
>    - Tambah env baru `XENDIT_WEBHOOK_ALLOWED_IPS=` di kedua env example
>
> ### ✅ Verifikasi
> - `php -l` clean untuk semua file PHP yang diubah (WebhookController, services.php, cors.php, SecurityHeaders.php)
> - `php artisan test`: **141 passed (458 assertions)** — termasuk `XenditWebhookTest::xendit_webhook_rejects_invalid_token` yang masih PASS (artinya `hash_equals` + IP whitelist tidak break test existing)
>
> ### 📌 Follow-up Notes (untuk Phase 6)
> 1. **Nonce-based CSP** — migrate `'unsafe-inline'` di `script-src` ke nonce per-request. Butuh:
>    - Generate nonce di middleware
>    - Inject ke template Filament (`<script nonce="{{ $nonce }}">`)
>    - Inject ke Vite build inline scripts
> 2. **Subresource Integrity (SRI)** — saat ini font Google/Bunny dimuat tanpa SRI hash. Phase 6 bisa generate SRI dari build pipeline.
> 3. **CORS preflight tracing** — tambah `X-Correlation-ID` di `exposed_headers` (sudah dilakukan), lengkapi dengan dashboard Sentry untuk track preflight failures.

---

## ✅ Phase 3 — 🏗️ Refactor & Code Quality (2–3 minggu)

> **Goal:** Mengurangi kompleksitas kode agar mudah di-maintain, di-test, dan dikerjakan paralel oleh tim.
> **Prasyarat:** Phase 2 selesai 100%.
> **Definition of Done:** Semua 8 item di bawah tercentang ✅

### Checklist Phase 3 — Backend

- [ ] **[P1-6]** Pecah `OrderController.php` (1.197 LOC) menjadi Action classes: `CalculateOrderAction`, `PlaceOrderAction`, `ApplyPromoAction`, `ApplyLoyaltyAction` *(lihat detail: Isu #6)*
- [ ] **[P1-7]** Extract validasi inline di `AuthController`, `OrderController`, `CartController` ke **Form Request** classes *(lihat detail: Isu #13)*
- [ ] **[P1-8]** Audit konsistensi penggunaan **API Resource** — pastikan semua response controller pakai Resource class, bukan array manual *(lihat detail: Isu #6)*

### Checklist Phase 3 — Frontend

- [ ] **[P1-9]** Refactor `views/Profile.vue` (1.520 LOC) → `ProfileLayout.vue` + sub-components (`AddressManager`, `PrescriptionManager`, `OrderHistorySection`) + `composables/useProfile.ts` *(lihat detail: Isu #7)*
- [ ] **[P1-10]** Refactor `views/checkout/CheckoutView.vue` (1.375 LOC) → sub-components (`CheckoutSummary`, `ShippingForm`, `PaymentSelector`, `PromoInput`) + `composables/useCheckout.ts` *(lihat detail: Isu #7)*
- [ ] **[P1-11]** Refactor `views/ProductDetail.vue` (1.330 LOC) → sub-components (`ProductGallery`, `ProductInfo`, `LensConfigurator`, `ReviewSection`) + `composables/useProductDetail.ts` *(lihat detail: Isu #7)*
- [ ] **[P1-12]** Refactor `views/Product.vue` (1.189 LOC) → sub-components (`ProductGrid`, `FilterSidebar`, `SearchBar`, `SortControl`) + `composables/useProductList.ts` *(lihat detail: Isu #7)*
- [ ] **[P1-13]** Buat `src/core/utils/logger.ts` dan ganti semua `console.log/error/warn` (50+ kemunculan) dengan `logger.*` *(lihat detail: Isu #8)*

**Catatan progress Phase 3:**
> *(Tulis di sini tanggal mulai, hambatan, atau catatan saat mengerjakan)*

---

## ✅ Phase 4 — ⚡ Performance & Aksesibilitas (1–2 minggu)

> **Goal:** Memperbaiki Core Web Vitals, mengurangi ukuran bundle, dan memenuhi standar aksesibilitas dasar (WCAG 2.1 AA).
> **Prasyarat:** Phase 3 selesai (refactor komponen dulu agar tidak double-work).
> **Definition of Done:** Semua 7 item di bawah tercentang ✅

### Checklist Phase 4 — Aksesibilitas

- [ ] **[A11Y-1]** Tambah `alt` attribute pada 11 `<img>` yang belum punya: `PageHero.vue`, `ArticleList.vue`, `ArticleDetail.vue`, `CheckoutView.vue` (×2), `OrderDetail.vue`, `ProductDetail.vue` (×2), `CartView.vue` (×2) *(lihat detail: Isu #9)*
- [ ] **[A11Y-2]** Tambah `loading="lazy"` dan `decoding="async"` pada semua `<img>` yang belum (saat ini hanya 5 dari 49) *(lihat detail: Isu #9)*
- [ ] **[A11Y-3]** Audit dan tambah `aria-label` / `role` pada elemen interaktif kritis (tombol tanpa teks, modal, form fields) — target minimal 50 aria attributes *(lihat detail: Isu #9)*

### Checklist Phase 4 — Performance

- [ ] **[PERF-1]** Convert 3 PNG blog hero di `medio-fe/public/` (total 2,1 MB) ke format WebP/AVIF + update template dengan `<picture>` element *(lihat detail: Isu #11)*
- [ ] **[PERF-2]** Verifikasi semua route berat sudah lazy-loaded di `router/index.ts` (`Profile`, `Checkout`, `ProductDetail`, `OrderDetail`) *(lihat detail: Isu #11)*
- [ ] **[PERF-3]** Install `rollup-plugin-visualizer` dan analisis bundle — identifikasi dependency yang bisa di-split lebih lanjut untuk mengurangi `index.js` dari 396 KB *(lihat detail: Isu #11)*
- [ ] **[PERF-4]** Tambah caching di `SitemapController` dengan `Cache::remember()` + ganti `->get()` dengan `->cursor()` untuk dataset besar *(lihat detail: Isu #12)*

**Catatan progress Phase 4:**
> *(Tulis di sini tanggal mulai, hambatan, atau catatan saat mengerjakan)*

---

## ✅ Phase 5 — 🧪 Testing & Tooling (2–3 minggu)

> **Goal:** Membangun safety net agar perubahan di masa depan tidak menyebabkan regresi yang tidak terdeteksi.
> **Prasyarat:** Phase 3 selesai (komponen sudah dipecah, lebih mudah di-test).
> **Definition of Done:** Semua 7 item di bawah tercentang ✅

### Checklist Phase 5 — Setup Tooling

- [ ] **[TOOL-1]** Install dan konfigurasi ESLint di `medio-fe`: `eslint`, `@vue/eslint-config-typescript`, `eslint-plugin-vue`, `eslint-plugin-vuejs-accessibility`, `eslint-config-prettier` *(lihat detail: Isu #10)*
- [ ] **[TOOL-2]** Install dan konfigurasi Prettier di `medio-fe`: buat `.prettierrc` + `eslint-config-prettier` *(lihat detail: Isu #10)*
- [ ] **[TOOL-3]** Install dan konfigurasi Vitest di `medio-fe`: `vitest`, `@vue/test-utils`, `jsdom`, `@testing-library/vue` + buat `vitest.config.ts` *(lihat detail: Isu #10)*
- [ ] **[TOOL-4]** Tambah Vite plugin `vite-plugin-remove-console` untuk strip `console.*` di build production *(lihat detail: Isu #8)*

### Checklist Phase 5 — Test Coverage

- [ ] **[TEST-1]** Tulis unit tests untuk 3 Pinia store kritis: `authStore`, `cartStore`, `wishlistStore` — target coverage 80% per store *(lihat detail: Isu #10)*
- [ ] **[TEST-2]** Tulis integration tests untuk flow checkout: add to cart → checkout → payment redirect *(lihat detail: Isu #10)*
- [ ] **[TEST-3]** Setup CI workflow (GitHub Actions) yang menjalankan `php artisan test` (BE) + `vitest --run` (FE) pada setiap push ke `main` / PR *(lihat detail: Isu #10)*

**Catatan progress Phase 5:**
> *(Tulis di sini tanggal mulai, hambatan, atau catatan saat mengerjakan)*

---

## ✅ Phase 6 — 🚀 Strategic: SEO, Observability & Logging (1–3 bulan)

> **Goal:** Investasi jangka panjang untuk organic growth, debugging production, dan maintainability skala besar.
> **Prasyarat:** Phase 1–5 selesai.
> **Definition of Done:** Semua 7 item di bawah tercentang ✅

### Checklist Phase 6 — SEO & Rendering

- [ ] **[SEO-1]** Evaluasi dan implementasi SSR/SSG untuk halaman publik: pilih antara **Nuxt 3 migration** (full SSR) atau **`vite-plugin-prerender`** (static pre-render untuk product/landing/blog) *(lihat detail: Isu #3)*
- [ ] **[SEO-2]** Audit dan tambah `useSeoMeta` ke semua 27 view yang belum — saat ini baru 8 dari 27 *(lihat detail: Isu #3)*
- [ ] **[SEO-3]** Implementasi sitemap index untuk skalabilitas > 50.000 URL *(lihat detail: Isu #12)*

### Checklist Phase 6 — Observability

- [ ] **[OBS-1]** Integrasi **Sentry** (atau Bugsnag) di frontend — ganti `logger.ts` stub dengan Sentry SDK yang sesungguhnya *(lihat detail: Isu #8)*
- [ ] **[OBS-2]** Setup **structured JSON logging** di backend: konfigurasi `config/logging.php` channel `stderr` dengan `JsonFormatter` untuk production *(lihat detail: Isu #15)*
- [ ] **[OBS-3]** Standardisasi format semua `Log::*` di backend — ganti string concat dengan array context, sertakan `correlation_id` di setiap log entry *(lihat detail: Isu #15)*
- [ ] **[OBS-4]** Setup **performance budget** di CI: bundle size limit (misal: `index.js` max 300 KB gzip) + Lighthouse CI score minimum *(lihat detail: Isu #11)*

**Catatan progress Phase 6:**
> *(Tulis di sini tanggal mulai, hambatan, atau catatan saat mengerjakan)*

---

## 📊 Progress Tracker

> Update tabel ini setiap kali menyelesaikan sebuah phase.

| Phase | Total Item | Selesai | Progress |
|---|---|---|---|
| Phase 1 — Stop the Bleeding | 5 | 5 | `██████████` 100% ✅ |
| Phase 2 — Security Hardening | 5 | 5 | `██████████` 100% ✅ |
| Phase 3 — Refactor & Code Quality | 8 | 0 | `░░░░░░░░░░` 0% |
| Phase 4 — Performance & A11y | 7 | 0 | `░░░░░░░░░░` 0% |
| Phase 5 — Testing & Tooling | 7 | 0 | `░░░░░░░░░░` 0% |
| Phase 6 — Strategic | 7 | 0 | `░░░░░░░░░░` 0% |
| **TOTAL** | **39** | **10** | `███░░░░░░░` **26%** |

---

---

## 📊 Ringkasan Skor

| Domain | Skor | Status | Tingkat Risiko |
|---|---|---|---|
| **Security & Auth** | 7.0 / 10 | 🟡 Cukup, ada gap kritis | Sedang |
| **Konkurensi & Integritas Data** | 5.0 / 10 | 🔴 Risiko *overselling* | **TINGGI** |
| **Arsitektur Backend** | 7.5 / 10 | 🟢 Solid, ada *fat controller* | Rendah |
| **Kualitas Frontend** | 5.5 / 10 | 🟡 *God components* + 0 test | Sedang |
| **SEO** | 4.0 / 10 | 🔴 SPA tanpa SSR, no robots/sitemap | Tinggi |
| **Performance** | 6.0 / 10 | 🟡 Bundle besar + asset PNG mentah | Sedang |
| **Aksesibilitas (a11y)** | 4.5 / 10 | 🔴 Banyak `<img>` tanpa `alt` | Sedang |
| **Production Readiness** | 6.0 / 10 | 🟡 File debug & data masuk repo | Sedang |
| **Testing & QA** | 5.0 / 10 | 🟡 Backend OK, Frontend nol | Sedang |
| **Observability & Logging** | 6.5 / 10 | 🟢 Sudah pakai Log, perlu lebih terstruktur | Rendah |

**Skor Total: 5.7 / 10** — *Production-capable tapi belum production-grade. Ada 3 isu kritis yang wajib diatasi sebelum traffic produksi nyata.*

---

## 🔴 ISU KRITIS (P0) — Wajib Diatasi Sebelum Production

### 1. Race Condition pada Stok Produk (CRITICAL)

**Lokasi:**
- `medio-be/app/Http/Controllers/API/CartController.php:46` — cek stok tanpa lock
- `medio-be/app/Http/Controllers/API/OrderController.php` (calculate & store)

**Bukti dari kode:**
```php
// CartController::addItem
$product = Product::where('id', $request->product_id)
    ->where('is_active', true)
    ->firstOrFail();

if ($product->stock < $request->quantity) {  // ⚠️ TIDAK LOCK
    return response()->json(['message' => 'Stok produk tidak mencukupi.'], 422);
}
```

`grep lockForUpdate` di seluruh `app/` hanya menemukan **1** kemunculan, dan itu di `User.php` (loyalty points), **bukan di stock checking**.

**Dampak:** Saat trafik tinggi (campaign, flash sale), 2 user bisa lolos cek `$product->stock` bersamaan dan keduanya berhasil checkout walau stok hanya 1 → **overselling, refund massal, reputasi rusak**.

**Fix:**
```php
DB::transaction(function () use ($request, $product) {
    $product = Product::where('id', $request->product_id)
        ->where('is_active', true)
        ->lockForUpdate()
        ->firstOrFail();

    if ($product->stock < $request->quantity) {
        throw ValidationException::withMessages(['stock' => 'Stok tidak mencukupi.']);
    }
    // ... decrement stok di sini juga, atau di confirm-order step
});
```

> README sudah menjanjikan *"Atomic Transactions: Robust stock management using database locks to prevent race conditions"*, tetapi implementasi tidak mencerminkan klaim ini.

---

### 2. File Debug & Data Mentah Masuk Repository

**Bukti:**
```
medio-fe/patch_checkout.php          (9.252 byte) — file PHP di project Vue (??)
medio-be/check_lenses.php            (kosong, 0 byte)
medio-be/data_optik_lengkap.json     (1,6 MB)
medio-be/data_lensa_kontak.json
optik-medio-ecommerce/data_optik_lengkap.json (root, 1,6 MB)
optik-medio-ecommerce/data_sunglasses.json (243 KB)
optik-medio-ecommerce/backup_sunglasses.json (213 KB)
optik-medio-ecommerce/scraping.py
```

**Dampak:**
- Bundle deploy backend kebesaran (1,6 MB JSON tidak perlu)
- File `patch_checkout.php` di folder frontend = potential remote code execution kalau ter-deploy
- File scraping & backup mungkin mengandung data internal/PII

**Fix:**
1. Pindahkan semua JSON sumber ke `medio-be/database/seeders/data/` atau ke storage di luar git
2. Hapus `patch_checkout.php` dan `check_lenses.php`
3. Tambahkan ke `.gitignore`:
   ```gitignore
   *.bak.json
   /data_*.json
   /backup_*.json
   /scraping.py
   /medio-fe/patch_*.php
   ```

---

### 3. SEO — Tidak Ada `robots.txt`, Sitemap Tidak Ter-link

**Bukti:**
- `medio-fe/public/robots.txt` → **TIDAK ADA**
- `SitemapController` ada di backend (`/api/sitemap`) tetapi search engine tidak tahu URL ini
- `index.html` punya `<title>` statis di seluruh route SPA (sebagian sudah pakai `useSeoMeta` tapi inkonsisten)
- 1.520 LOC `Profile.vue` adalah SPA route — Googlebot baru tidak akan execute JS untuk halaman authenticated, tapi product/landing tetap risk

**Dampak:** Untuk e-commerce yang bergantung pada organic traffic, ini langsung menghapus 60–80% potensi SEO.

**Fix Cepat:**
1. Buat `medio-fe/public/robots.txt`:
   ```
   User-agent: *
   Allow: /
   Disallow: /profile
   Disallow: /cart
   Disallow: /checkout
   Disallow: /orders
   Sitemap: https://optikmedio.com/sitemap.xml
   ```
2. Tambah route Laravel `/sitemap.xml` (bukan `/api/sitemap`) yang dilayani dari domain frontend (atau redirect)
3. Tambahkan SSR/SSG menggunakan **VitePress / Nuxt** untuk halaman landing & product (atau minimal pre-render via `vite-plugin-prerender`)
4. Audit konsistensi `useSeoMeta` di **semua** view — saat ini hanya 8 view yang pakai

---

## 🟡 ISU TINGGI (P1) — Perbaikan dalam 1–2 Sprint

### 4. Webhook Xendit — Validasi Lemah

**Lokasi:** `medio-be/app/Http/Controllers/API/WebhookController.php:19-26`

```php
$callbackToken = $request->header('x-callback-token');
$expectedToken = config('services.xendit.webhook_token');

if (!$expectedToken || $callbackToken !== $expectedToken) {  // ⚠️ String compare biasa
    Log::warning('Invalid Xendit Webhook Token', ['ip' => $request->ip()]);
    return response()->json(['message' => 'Invalid token'], 401);
}
```

**Issues:**
- ❌ Pakai `!==` biasa, **bukan timing-safe compare** (`hash_equals`)
- ❌ Tidak ada IP whitelist untuk Xendit webhook IPs
- ❌ Tidak ada idempotency key di header (cuma cek replay via status — fragile)
- ✅ `WebhookEventLog` sudah ada — bagus

**Fix:**
```php
if (!$expectedToken || !hash_equals($expectedToken, $callbackToken ?? '')) {
    Log::warning('Invalid Xendit Webhook Token', ['ip' => $request->ip()]);
    return response()->json(['message' => 'Invalid token'], 401);
}

// Whitelist IP (Xendit publishes static IPs)
$allowedIps = config('services.xendit.allowed_ips', []);
if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps, true)) {
    return response()->json(['message' => 'Forbidden'], 403);
}
```

---

### 5. CORS Terlalu Permisif + CSP Lemah

**Lokasi:**
- `medio-be/config/cors.php`
- `medio-be/app/Http/Middleware/SecurityHeaders.php`

```php
// cors.php
'allowed_methods' => ['*'],          // ⚠️ semua method
'allowed_headers' => ['*'],          // ⚠️ semua header
'supports_credentials' => true,      // ⚠️ kombinasi dengan ['*'] = bahaya
```

```php
// SecurityHeaders.php
"script-src 'self' 'unsafe-inline' 'unsafe-eval'"  // ⚠️ XSS risk
```

**Issues:**
- `allowed_methods: ['*']` + `supports_credentials: true` melawan rekomendasi MDN. Browser modern akan reject sebagian preflight, dan untuk yang lolos, surface attack lebar.
- `'unsafe-eval'` jarang dibutuhkan Vue 3 build production. Hanya butuh untuk dev HMR.
- Tidak ada `object-src 'none'`, `frame-src 'none'`, `form-action 'self'`.

**Fix:**
```php
// cors.php
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN'],
```

```php
// SecurityHeaders.php (production)
"script-src 'self' 'nonce-{$nonce}'",  // generate nonce per-request
"object-src 'none'",
"frame-src 'none'",
"form-action 'self'",
"upgrade-insecure-requests",
```

---

### 6. Fat Controllers — `OrderController.php` 1.197 LOC

| Controller | LOC | Status |
|---|---|---|
| `OrderController.php` | **1.197** | 🔴 *God class* |
| `ProductController.php` | 360 | 🟡 |
| `AuthController.php` | 313 | 🟡 |
| `AffiliateController.php` | 286 | 🟡 |
| `CartController.php` | 259 | 🟡 |

**Dampak:**
- Sulit di-test secara unit
- Logika promo / shipping / loyalty / order tercampur dalam 1 file
- Saat 3 dev kerja paralel → merge hell

**Fix:**
1. Pisahkan ke **Action classes** (`app/Actions/Order/*`):
   - `CalculateOrderAction`
   - `PlaceOrderAction`
   - `ApplyPromoAction`
   - `ApplyLoyaltyAction`
2. Pindahkan validasi ke **Form Request** (`app/Http/Requests/StoreOrderRequest.php`)
3. Gunakan **API Resource** untuk semua response (saat ini sudah ada folder `Resources/` tapi kemungkinan dipakai inkonsisten)

---

### 7. God Components di Frontend

| File | LOC | Klasifikasi |
|---|---|---|
| `views/Profile.vue` | **1.520** | 🔴 God component |
| `views/checkout/CheckoutView.vue` | **1.375** | 🔴 God component |
| `views/ProductDetail.vue` | **1.330** | 🔴 God component |
| `views/Product.vue` | **1.189** | 🔴 God component |
| `views/OrderDetail.vue` | **983** | 🟡 |
| `views/VirtualTryOn.vue` | 543 | 🟡 |
| `views/checkout/WaitingPayment.vue` | 498 | 🟡 |
| `views/AppointmentPage.vue` | 477 | 🟡 |

**Dampak:**
- Bundle initial = **396 KB** untuk `index-QHEFULgS.js` (ini hanya satu chunk!)
- Setiap edit di `Profile.vue` butuh full re-mount untuk testing manual
- `<script setup>` tidak ditest sama sekali (0 test FE)

**Fix Pola:**
```
views/Profile.vue (1.520)  →  views/profile/ProfileLayout.vue (~150)
                              + components/profile/ProfileHeader.vue
                              + components/profile/AddressManager.vue
                              + components/profile/PrescriptionManager.vue
                              + components/profile/OrderHistorySection.vue
                              + composables/useProfile.ts
```

Target: **maksimal 300 LOC per `.vue`**, logic ke `composables/`.

---

### 8. `console.log` & `console.error` Bocor ke Production

**Bukti (50+ kemunculan):**
```
src/views/checkout/CheckoutView.vue:427:    console.log('Fetching cities for province_id:', newVal);
src/views/checkout/CheckoutView.vue:689:    console.error('Order failed', error);
src/stores/wishlistStore.ts:29:             console.error('Failed to fetch wishlist', error);
... 47 lainnya
```

**Dampak:**
- Bocoran info internal ke browser console (DevTools terbuka = info disclosure)
- Tidak ada error tracking terpusat (Sentry / Bugsnag)

**Fix:**
1. Buat `src/core/utils/logger.ts`:
   ```ts
   export const logger = {
     error: (msg: string, ctx?: unknown) => {
       if (import.meta.env.DEV) console.error(msg, ctx);
       // production: kirim ke Sentry
       window.Sentry?.captureException(ctx, { extra: { msg } });
     },
     warn: (msg: string, ctx?: unknown) => { /* ... */ },
   };
   ```
2. Ganti semua `console.*` dengan `logger.*`
3. Tambah Vite plugin `vite-plugin-remove-console` untuk strip `console.log` di build production

---

### 9. Aksesibilitas — `<img>` Tanpa `alt`

**Statistik:**
- Total `<img>`: **49**
- Dengan `alt`: 38 (78%)
- **11 `<img>` tanpa `alt`** di file kritis (`CheckoutView`, `ProductDetail`, `CartView`, `OrderDetail`)
- `loading="lazy"` hanya di **5** image (10%)
- `aria-*` count: 18 — sangat rendah untuk app sebesar ini

**Fix:**
```vue
<!-- ❌ Sebelum -->
<img :src="resolveImageUrl(item.images || item.image_url, item.name)" class="..." />

<!-- ✅ Sesudah -->
<img
  :src="resolveImageUrl(item.images || item.image_url, item.name)"
  :alt="`Foto produk ${item.name}`"
  loading="lazy"
  decoding="async"
  width="200"
  height="200"
  class="..."
/>
```

Tambahkan ESLint plugin: `eslint-plugin-vuejs-accessibility` untuk auto-detect.

---

## 🟢 ISU MENENGAH (P2) — Perbaikan Bertahap

### 10. Tidak Ada Linting & Testing di Frontend

```bash
# medio-fe/package.json devDependencies — tidak ada
- eslint
- prettier
- vitest
- @vue/test-utils
- @vitejs/plugin-vue-jsx
```

**Dampak:** Code drift — gaya tidak konsisten antar developer, regressions tidak tertangkap.

**Fix:**
```bash
cd medio-fe
npm i -D eslint @vue/eslint-config-typescript eslint-plugin-vue \
  eslint-plugin-vuejs-accessibility prettier eslint-config-prettier \
  vitest @vue/test-utils jsdom @testing-library/vue
```

Buat:
- `.eslintrc.cjs` — base + Vue + TS + a11y
- `.prettierrc` — konsisten format
- `vitest.config.ts` — test setup
- Sample tests untuk **3 store kritis** (cart, auth, wishlist)
- Sample tests untuk **flow checkout E2E** dengan Playwright (opsional)

---

### 11. Bundle Size & Asset Optimization

**Statistik dist/:**
```
396 KB  index-QHEFULgS.js          ← terlalu besar
108 KB  vendor-vue-Bco6Bf1E.js
 64 KB  vendor-utils-CZyPLACR.js
799 KB  blog_feature_3_trends_2026.png   ← PNG mentah
694 KB  blog_feature_1_face_shape.png
597 KB  blog_feature_2_blueray_lens.png
```

**Issues:**
- `index.js` 396 KB sudah dekat dengan warning limit 600 KB
- 3 PNG di `public/` total **2,1 MB** — tidak ter-optimize, format PNG (harusnya WebP/AVIF)
- Tidak ada route-level code splitting eksplisit (hanya manual chunk vendor)

**Fix:**
1. **Convert PNG → WebP/AVIF:**
   ```bash
   # Optimal: AVIF main + WebP fallback
   for f in public/*.png; do
     cwebp -q 80 "$f" -o "${f%.png}.webp"
     avifenc --min 30 --max 40 "$f" "${f%.png}.avif"
   done
   ```
   Lalu pakai `<picture>`:
   ```vue
   <picture>
     <source :srcset="img.replace('.png','.avif')" type="image/avif" />
     <source :srcset="img.replace('.png','.webp')" type="image/webp" />
     <img :src="img" :alt="alt" loading="lazy" />
   </picture>
   ```
2. **Lazy load route components yang berat** — hampir pasti `Profile`, `Checkout`, `ProductDetail`, `OrderDetail` belum di-`defineAsyncComponent`. Verify di `router/index.ts`:
   ```ts
   const Profile = () => import('../views/Profile.vue'); // ✅
   ```
3. **Analyze bundle** dengan `rollup-plugin-visualizer`:
   ```bash
   npm i -D rollup-plugin-visualizer
   ```

---

### 12. SitemapController — Tidak Cache, N+1 Risiko

**Lokasi:** `medio-be/app/Http/Controllers/API/SitemapController.php:14-16`

```php
$products = Product::where('is_active', true)->get();  // ⚠️ load ALL products
$categories = Category::all();
$articles = Article::published()->get();
```

**Issues:**
- Saat katalog 10.000+ produk → memori bisa meledak
- Tidak ada cache → hit DB tiap kali Googlebot crawl

**Fix:**
```php
public function index(): Response
{
    return Cache::remember('sitemap.xml', now()->addHours(6), function () {
        // build XML using ->cursor() instead of ->get()
        // chunk untuk dataset besar
    });
}
```

Pertimbangkan **sitemap index** untuk > 50.000 URL.

---

### 13. Tidak Ada Form Request — Validasi Inline di Controller

**Bukti:**
```php
// AuthController::register
$request->validate([
    'name' => 'required|string|max:255',
    'email' => 'required|string|email|max:255|unique:users',
    // ...
]);
```

Folder `app/Http/Requests/` ada tapi validasi penting masih inline.

**Fix:** Extract ke FormRequest classes:
```php
class RegisterRequest extends FormRequest {
    public function rules(): array { /* ... */ }
    public function messages(): array { /* localized error messages */ }
}
```

---

### 14. Sanctum SPA Auth — Konfigurasi Stateful Domain

**Bukti:**
```php
// config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',  // ⚠️ DEV ONLY
    Sanctum::currentApplicationUrlWithPort(),
))),
```

**Issue:** Default mengandung `localhost` dan `127.0.0.1`. Saat deploy production, kalau `SANCTUM_STATEFUL_DOMAINS` env tidak di-set dengan benar → **session bocor / tidak working**.

**Fix:**
- Pastikan `.env.production` set: `SANCTUM_STATEFUL_DOMAINS=optikmedio.com,www.optikmedio.com`
- Pastikan `SESSION_DOMAIN=.optikmedio.com`
- Pastikan `SESSION_SECURE_COOKIE=true`
- Pastikan `SESSION_SAME_SITE=lax` (atau `strict` jika tidak ada cross-subdomain)

---

### 15. Logging Tidak Terstruktur Konsisten

**Statistik:** 63 kemunculan `Log::*` di backend — bagus, tapi format inkonsisten.

**Contoh inkonsisten:**
```php
Log::info('Xendit Webhook Received', ['order_number' => $orderNumber, 'status' => $status]);
// vs
Log::info('Order confirmation email sent to ' . $order->user->email);  // ⚠️ string concat
// vs
Log::error('Failed to send OTP email: ' . $e->getMessage());  // ⚠️ no exception object
```

**Fix:**
- Selalu pakai array context: `Log::error('OTP failed', ['exception' => $e, 'user_id' => $user->id])`
- Tambah `correlation_id` middleware sudah ada (`CorrelationId.php`) — pastikan benar-benar di-include di setiap Log
- Setup **structured JSON logging** untuk production (`config/logging.php` channel `stderr` dengan formatter `JsonFormatter`)

---

## 🟢 STRENGTHS — Apa yang Sudah Bagus

### Backend
- ✅ **Repository + Service + Controller layering** sudah ada (Order, Product)
- ✅ **20 tests Feature** — coverage lumayan (auth, checkout, inventory, payment status, dst)
- ✅ **WebhookEventLog** untuk idempotency tracking
- ✅ **Throttling** terdiferensiasi (register: 3/min, login: 20/min, OTP: 10/10min, shipping: 15/min)
- ✅ **Filament admin panel** — operasional toko cepat
- ✅ **Custom commands**: AutoCompleteDeliveredOrders, BackfillOrderPaymentChannel — pertanda matang
- ✅ **Health endpoint** `/api/health` dengan DB check
- ✅ **OTP rate limiting** + **email verification** + Hash::make() untuk password
- ✅ **OrderLog**, **CommissionDetail**, **LoyaltyPointLog** — audit trail business
- ✅ **HSTS** + security headers (production)
- ✅ Exception handler untuk JSON consistent (401/422/404)

### Frontend
- ✅ **DOMPurify** untuk `v-html` (2 pemakaian — sudah aman)
- ✅ **Pinia + persistedstate** untuk cart/wishlist
- ✅ **Manual chunk vendor** di Vite — vue/router/pinia terpisah
- ✅ **CSRF auto-retry** di axios interceptor — UX bagus
- ✅ **Router guards** untuk AUTH_REQUIRED & GUEST_ONLY
- ✅ **`useSeoMeta` composable** + JSON-LD product
- ✅ **`useWebVitals` composable** — sudah aware Core Web Vitals
- ✅ **`useAnalytics` composable** dengan session tracking

### Architecture
- ✅ **Clear monorepo separation** (`medio-be/`, `medio-fe/`, `design-system/`)
- ✅ **Design system** terdokumentasi (`MASTER.md`, `FRONTEND_REDESIGN_BLUEPRINT.md`)
- ✅ **AUDIT_REPORT.md** + **traceability matrix** sebelumnya — pertanda governance ada

---

## 📋 Rekomendasi Action Plan (Prioritas)

> Lihat **Peta Phase Pengerjaan** dan **Checklist per Phase** di bagian atas dokumen ini untuk detail lengkap dan tracking progress.

### Ringkasan Cepat

| Sprint | Phase | Fokus | Durasi |
|---|---|---|---|
| Sprint 1 | Phase 1 | 🔥 Stop the Bleeding — race condition, file debug, robots.txt | 1 minggu |
| Sprint 2 | Phase 2 | 🛡️ Security Hardening — webhook, CORS, CSP, Sanctum | 1–2 minggu |
| Sprint 3–4 | Phase 3 | 🏗️ Refactor — god controllers, god components, logger | 2–3 minggu |
| Sprint 5 | Phase 4 | ⚡ Performance & A11y — images, bundle, lazy load | 1–2 minggu |
| Sprint 6–7 | Phase 5 | 🧪 Testing & Tooling — ESLint, Vitest, CI | 2–3 minggu |
| Sprint 8+ | Phase 6 | 🚀 Strategic — SSR/SSG, Sentry, structured logging | 1–3 bulan |

### 🔥 Sprint 1 (1 minggu) — STOP THE BLEEDING
1. **Fix race condition stok** dengan `lockForUpdate` di Cart + Order (P0) → *Phase 1, item P0-1*
2. **Hapus file debug**: `patch_checkout.php`, `check_lenses.php`, JSON data dari root (P0) → *Phase 1, item P0-2 & P0-3*
3. **Tambah `robots.txt`** + perbaiki sitemap routing public (P0) → *Phase 1, item P0-4 & P0-5*
4. **Fix webhook `hash_equals`** untuk timing-safe compare (P1) → *Phase 2, item P1-1*
5. **Strip `console.log` di build production** via Vite plugin (P1) → *Phase 5, item TOOL-4*

### 🛠 Sprint 2–3 (2–4 minggu) — HARDENING
6. **Setup ESLint + Prettier + Vitest** di FE → *Phase 5, item TOOL-1 s/d TOOL-3*
7. **Tightening CORS** (tidak `*`) + CSP nonce-based → *Phase 2, item P1-3 & P1-4*
8. **Refactor Profile.vue, CheckoutView.vue, ProductDetail.vue** menjadi component sub-tree → *Phase 3, item P1-9 s/d P1-12*
9. **Extract OrderController** → Action classes + Form Request → *Phase 3, item P1-6 & P1-7*
10. **Add 11 missing `<img alt="">`** + tambah `loading="lazy"` di seluruh media → *Phase 4, item A11Y-1 & A11Y-2*
11. **Convert PNG → WebP/AVIF** untuk hero & blog feature → *Phase 4, item PERF-1*

### 🏗 Sprint 4+ (1–3 bulan) — STRATEGIC
12. **SSR/SSG** untuk halaman product/landing/blog (Nuxt migration atau Vite-prerender) → *Phase 6, item SEO-1*
13. **Sentry / error tracking** + **structured JSON logging** di production → *Phase 6, item OBS-1 & OBS-2*
14. **Performance budget** + bundle analyzer di CI → *Phase 6, item OBS-4*
15. **Frontend test coverage** target 60% (cart, checkout, auth flow) → *Phase 5, item TEST-1 & TEST-2*
16. **Sitemap caching** + chunked generation → *Phase 4, item PERF-4 & Phase 6, item SEO-3*

---

## 🧮 Rincian Skor Tiap Domain

### Security (7.0 / 10)
| Item | Status |
|---|---|
| Webhook auth dengan token | ✅ ada (tapi non-timing-safe → -0.5) |
| Rate limiting | ✅ throttle terdiferensiasi |
| CSRF + Sanctum stateful | ✅ ada |
| CSP / HSTS / X-Frame | ✅ production-only |
| `unsafe-inline` & `unsafe-eval` di CSP | ❌ -1.0 |
| CORS `*` methods/headers | ❌ -0.5 |
| Password hashing | ✅ bcrypt via `Hash::make` |
| OTP expiry + rate-limit | ✅ |
| Input validation | ⚠️ inline, bukan FormRequest -0.5 |
| Raw SQL | ⚠️ ada di Console Commands (acceptable, internal) |
| File debug ter-commit | ❌ -0.5 |

### Konkurensi (5.0 / 10)
- ❌ Stock checking tanpa lock
- ❌ Hanya 1 `lockForUpdate` di seluruh codebase (User loyalty)
- ✅ DB::transaction dipakai di place order
- ✅ WebhookEventLog idempotency
- ⚠️ Promo & discount calculation tidak terlihat punya guard concurrent

### Frontend Quality (5.5 / 10)
- ❌ 4 file > 1.000 LOC
- ❌ 0 test, 0 ESLint, 0 Prettier
- ❌ 50+ `console.*` raw
- ⚠️ 11 `<img>` tanpa alt, hanya 5 lazy-load
- ✅ DOMPurify, Pinia, axios interceptor solid
- ✅ TypeScript dipakai

### SEO (4.0 / 10)
- ❌ Tidak ada `robots.txt`
- ❌ Sitemap di route `/api/*` (search engine biasanya butuh `/sitemap.xml`)
- ❌ SPA tanpa SSR untuk halaman product
- ⚠️ `useSeoMeta` baru di 8 view dari 27
- ✅ Meta OG/Twitter di `index.html`
- ✅ JSON-LD product

### Performance (6.0 / 10)
- ⚠️ index.js 396 KB
- ❌ PNG mentah 700 KB+ untuk blog hero
- ✅ Manual chunk vendor
- ✅ `useWebVitals` composable ada
- ⚠️ SitemapController load semua produk

### Production Readiness (6.0 / 10)
- ❌ File debug & data JSON masih di repo
- ⚠️ `node_modules/` di `medio-be/` (kenapa?)
- ✅ Health endpoint
- ✅ Custom commands operasional
- ✅ HSTS production-only
- ⚠️ Sanctum stateful domain default berisi localhost

### Testing (5.0 / 10)
- ✅ 20 Feature tests Backend (cukup baik)
- ❌ 0 test Frontend
- ❌ Tidak ada CI workflow terlihat
- ⚠️ Tidak ada coverage threshold

### Observability (6.5 / 10)
- ✅ `Log::*` 63 kemunculan
- ✅ `CorrelationId` middleware
- ✅ `WebhookEventLog`, `OrderLog`, `LoyaltyPointLog`
- ⚠️ Format log inkonsisten (string concat vs array)
- ❌ Tidak ada error tracker (Sentry/Bugsnag)
- ❌ Tidak ada structured JSON log

---

## 🎯 Kesimpulan

**Optik Medio sudah jauh dari MVP**: arsitektur monorepo bersih, repository pattern, Filament admin lengkap, 20 feature tests, throttling matang, dan UX SPA yang reactive.

Tapi **sebelum membuka traffic produksi sungguhan**, ada **3 hal wajib**:

1. 🔴 **Lock stok produk** — kalau tidak, satu kali campaign besar bisa overselling massal
2. 🔴 **Bersihkan repo** — `patch_checkout.php` di project Vue adalah red-flag besar
3. 🔴 **Tambah `robots.txt` + SSR/SSG/prerender** untuk halaman publik — kalau tidak, marketing organik akan stuck

Setelah 3 itu beres (Phase 1), lanjut ke **Phase 2** (security hardening), kemudian **Phase 3** (refactor god components + god controllers), **Phase 4** (performance & a11y), **Phase 5** (setup tooling FE: ESLint, Vitest, CI), dan terakhir **Phase 6** (SSR/SSG, Sentry, structured logging).

**Estimasi effort total** untuk mencapai 8.0/10: **6–8 minggu** dengan 2 dev full-time.

> 📌 **Gunakan checklist di bagian atas dokumen ini** untuk tracking progress harian. Update kolom "Selesai" di Progress Tracker setiap kali menyelesaikan item.

---

*Audit ini dihasilkan dari kombinasi 6 skills (production-audit, laravel-security-audit, frontend-dev-guidelines, architect-review, seo-audit, web-performance-optimization) dengan analisis langsung pada 4.664 LOC controllers, 11.491 LOC views, 27 routes group, dan struktur 13 folder backend / 11 folder frontend.*
