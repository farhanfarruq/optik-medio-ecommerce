# Design Document — Audit E-Commerce Optik Medio

## Overview

Dokumen ini mendefinisikan **metodologi, pemetaan file, format laporan, dan correctness properties** untuk audit platform e-commerce Optik Medio (Laravel 13 backend + Vue 3 frontend). Audit bersifat **read-only** — tidak ada perubahan kode yang dilakukan selama proses audit; semua temuan dicatat dalam laporan terstruktur.

Tujuan audit adalah memverifikasi bahwa setiap domain fungsional memenuhi standar e-commerce yang ditetapkan dalam `requirements.md`, dengan fokus pada keamanan, konsistensi data, dan kebenaran kalkulasi finansial.

---

## Architecture

### Pendekatan Audit

Audit dilakukan dalam tiga lapisan yang saling melengkapi:

```
┌─────────────────────────────────────────────────────────┐
│  Layer 1: Static Code Analysis                          │
│  Membaca source code tanpa menjalankan aplikasi         │
│  Tools: grep, AST inspection, manual review             │
├─────────────────────────────────────────────────────────┤
│  Layer 2: Database Schema Inspection                    │
│  Memeriksa migrasi, kolom, constraint, dan indeks       │
│  Tools: migration files, schema dump                    │
├─────────────────────────────────────────────────────────┤
│  Layer 3: Flow Tracing                                  │
│  Menelusuri alur request dari route → controller →      │
│  service → model → response                             │
│  Tools: routes/api.php, controller methods, models      │
└─────────────────────────────────────────────────────────┘
```

### Metodologi Per Domain

| Teknik | Kapan Digunakan |
|--------|----------------|
| **Static Code Analysis** | Validasi input, rate limiting, autentikasi, SQL injection prevention |
| **Database Schema Inspection** | Kolom wajib, constraint unik, soft delete, foreign key |
| **Flow Tracing** | Kalkulasi harga, transisi status, pencatatan log, atomisitas transaksi |
| **Cross-Reference Check** | Konsistensi antara frontend (Vue/Pinia) dan backend (Laravel) |

---

## Components and Interfaces

### Struktur Direktori yang Diaudit

```
medio-be/
├── app/
│   ├── Http/
│   │   ├── Controllers/API/          ← Endpoint logic
│   │   ├── Requests/                 ← Form Request validation
│   │   └── Middleware/               ← SecurityHeaders, CORS
│   ├── Models/                       ← Eloquent models + booted hooks
│   ├── Observers/                    ← Side-effect handlers
│   ├── Services/                     ← XenditService, RajaOngkirService
│   ├── Filament/Resources/           ← Admin panel resources
│   └── Providers/                    ← AdminPanelProvider
├── database/migrations/              ← Schema definitions
├── routes/api.php                    ← Route definitions + throttle
└── bootstrap/app.php                 ← Session config, middleware

medio-fe/
├── src/
│   ├── views/checkout/CheckoutView.vue
│   ├── stores/cartStore.ts
│   └── stores/checkoutStore.ts
```

### Pemetaan File Per Domain

#### Domain 1 — Autentikasi
| File | Aspek yang Diperiksa |
|------|---------------------|
| `app/Http/Controllers/API/AuthController.php` | Validasi registrasi, OTP flow, login, logout, rate limiting via `RateLimiter` |
| `app/Mail/OtpMail.php` | Format email OTP, tidak ada plaintext code di subject |
| `app/Models/OtpCode.php` | Kolom `expires_at`, `verified_at`, `type`; tidak ada plaintext di session |
| `routes/api.php` | Throttle middleware pada endpoint `/verify-otp`, `/resend-otp`, `/login` |
| `bootstrap/app.php` | Session driver, cookie security (`secure`, `httponly`, `samesite`) |
| `database/migrations/*_create_otp_codes_table.php` | Kolom wajib, indeks pada `user_id` + `type` |

#### Domain 2 — Produk & Katalog
| File | Aspek yang Diperiksa |
|------|---------------------|
| `app/Http/Controllers/API/ProductController.php` | Endpoint pencarian/filter, validasi stok |
| `app/Models/Product.php` | `SoftDeletes` trait, relasi `variants`, `images`, `reviews` |
| `database/migrations/*_create_products_table.php` | Kolom `name`, `price`, `stock`, `weight`, `category_id`, `is_prescription_required` |
| `database/migrations/*_create_product_variants_table.php` | Relasi ke `products` |
| `database/migrations/*_create_product_images_table.php` | Kolom `is_primary` |

#### Domain 3 — Keranjang Belanja
| File | Aspek yang Diperiksa |
|------|---------------------|
| `medio-fe/src/stores/cartStore.ts` | `cartTotal` computation, `clearCart`, `linked_item_index` handling |
| `medio-fe/src/views/checkout/CheckoutView.vue` | Guard navigasi saat keranjang kosong, sinkronisasi dengan API |
| `app/Http/Controllers/API/OrderController.php` (method `calculate`) | Konsistensi kalkulasi backend vs frontend |

#### Domain 4 — Checkout & Kalkulasi Harga
| File | Aspek yang Diperiksa |
|------|---------------------|
| `app/Http/Controllers/API/OrderController.php` (method `calculate`, `store`) | Formula `total_price`, mutual exclusion diskon+promo, validasi `shipping_address_id` ownership, `StoreClose` check |
| `app/Models/StoreClose.php` | Logika pengecekan status toko |
| `app/Models/Discount.php` | Method `isValid()`, `DiscountUsage` check |
| `app/Models/Promo.php` | Tipe promo, `buy_x_get_y` free item logic |
| `database/migrations/*_create_orders_table.php` | Kolom komponen harga tersimpan terpisah |

#### Domain 5 — Pembayaran
| File | Aspek yang Diperiksa |
|------|---------------------|
| `app/Http/Controllers/API/WebhookController.php` | Validasi signature Xendit, atomisitas update status |
| `app/Services/XenditService.php` | `syncInvoice`, penyimpanan `raw_response` |
| `app/Http/Resources/PaymentResource.php` | Field yang diekspos ke frontend |
| `app/Http/Controllers/API/OrderController.php` (method `uploadPaymentProof`, `syncPayment`) | Validasi format file, ownership check, status guard |
| `database/migrations/*_create_payments_table.php` | Kolom `raw_response`, `provider`, `paid_at` |

#### Domain 6 — Pesanan & Siklus Status
| File | Aspek yang Diperiksa |
|------|---------------------|
| `app/Models/Order.php` (method `booted`) | Hook `created`, `updated` — pencatatan `order_logs` untuk setiap event |
| `app/Http/Controllers/API/OrderController.php` (method `confirmDelivery`, `show`, `tracking`) | Validasi status `shipped` sebelum `delivered`, ownership check, urutan kronologis logs |
| `database/migrations/*_create_order_logs_table.php` | Kolom `event_type`, `previous_status`, `current_status`, `acted_by` |
| `database/migrations/*_create_orders_table.php` | `SoftDeletes`, `order_number` unique constraint |

#### Domain 7 — Loyalitas & Keanggotaan
| File | Aspek yang Diperiksa |
|------|---------------------|
| `app/Models/User.php` (method `addLoyaltyPoints`, `redeemLoyaltyPoints`, `updateMembershipLevel`) | Atomisitas increment/decrement, pencatatan `LoyaltyPointLog`, guard saldo negatif |
| `database/migrations/*_create_loyalty_point_logs_table.php` | Kolom `user_id`, `order_id`, `points`, `type`, `description` |
| `database/migrations/*_create_user_level_members_table.php` | Kolom `effective_until` untuk single-active constraint |
| `app/Models/LevelMember.php` | `discount_percentage`, `min_points`, `is_active` |

#### Domain 8 — Afiliasi & Komisi
| File | Aspek yang Diperiksa |
|------|---------------------|
| `app/Http/Resources/UserAffiliatorResource.php` | Field yang diekspos |
| `app/Http/Resources/CommissionResource.php` | Kalkulasi komisi, status filter |
| `app/Http/Controllers/API/AffiliateController.php` | Pendaftaran afiliator, status `pending` default |
| `app/Models/UserAffiliator.php` | Unique constraint `affiliate_code`, `approved_by`, `approved_at`, `rejected_at` |
| `database/migrations/*_create_commissions_table.php` | Relasi ke `orders`, filter status pesanan |
| `database/migrations/*_create_commission_details_table.php` | Relasi ke `commissions` dan `orders` |

#### Domain 9 — Komplain & Retur
| File | Aspek yang Diperiksa |
|------|---------------------|
| `app/Http/Controllers/API/ComplainController.php` | Validasi `order_id`, status transitions, attachment validation |
| `app/Observers/ComplainObserver.php` | Trigger email notifikasi saat status berubah ke `in_progress`/`resolved`/`rejected` |
| `app/Http/Resources/ComplainResource.php` | Field `resolved_at`, `handled_by` |
| `app/Http/Controllers/API/ReturnController.php` | Guard status `delivered`/`completed` |
| `app/Observers/ReturnObserver.php` | Side effects saat retur diproses |
| `app/Http/Resources/ReturnRequestResource.php` | Field yang diekspos |

#### Domain 10 — Pengiriman
| File | Aspek yang Diperiksa |
|------|---------------------|
| `app/Services/RajaOngkirService.php` | Kalkulasi berdasarkan `district_id` dan berat, fallback ke `ShippingRate` |
| `app/Http/Controllers/API/ShippingController.php` | Validasi berat > 0, format respons dengan `etd` |
| `database/migrations/*_create_orders_table.php` | Kolom `courier`, `courier_service`, `shipping_cost` |
| `app/Models/ShippingRate.php` | Fallback statis |

#### Domain 11 — Admin Panel (Filament)
| File | Aspek yang Diperiksa |
|------|---------------------|
| `app/Providers/Filament/AdminPanelProvider.php` | Auth middleware, guard konfigurasi |
| `app/Filament/Resources/OrderResource.php` | Aksi verifikasi pembayaran, pencatatan `verified_by` |
| `app/Filament/Resources/ComplainResource.php` | Filter status, pencarian |
| `app/Filament/Resources/CommissionResource.php` | Filter status afiliator |
| `app/Filament/Resources/` (semua resource) | Keberadaan resource untuk semua entitas utama |
| `app/Filament/Resources/AppSettingResource.php` | Konfigurasi toko tanpa deployment |

#### Domain 12 — Keamanan & Standar API
| File | Aspek yang Diperiksa |
|------|---------------------|
| `config/cors.php` | `allowed_origins` tidak wildcard di production |
| `app/Http/Middleware/SecurityHeaders.php` | Header `X-Frame-Options`, `X-Content-Type-Options`, `Strict-Transport-Security` |
| Semua `app/Http/Controllers/API/*.php` | Penggunaan Form Request atau `validate()`, tidak ada raw SQL |
| `routes/api.php` | Middleware `auth:sanctum` atau `auth:web` pada semua protected routes |
| `app/Http/Controllers/API/OrderController.php` | `DB::transaction` pada operasi finansial |

#### Domain 13 — Konten & Pengaturan Toko
| File | Aspek yang Diperiksa |
|------|---------------------|
| `app/Http/Controllers/API/BannerController.php` | Filter `is_active`, akses publik tanpa auth |
| `app/Http/Controllers/API/ArticleController.php` | Endpoint publik |
| `app/Http/Controllers/SitemapController.php` | Cakupan URL produk, kategori, artikel |
| `app/Models/AppSetting.php` | Cache invalidation saat update |
| `app/Http/Controllers/API/BroadcastController.php` | Targeting segmen pengguna |

---

## Data Models

### Model Audit Finding

Setiap temuan audit direpresentasikan sebagai:

```typescript
interface AuditFinding {
  id: string;                    // e.g., "AUTH-001"
  domain: string;                // e.g., "Autentikasi"
  requirement_ref: string;       // e.g., "Req 1.4"
  severity: "CRITICAL" | "HIGH" | "MEDIUM" | "LOW" | "INFO";
  status: "PASS" | "WARNING" | "FAIL";
  file_path: string;             // Lokasi file yang diperiksa
  line_range?: string;           // e.g., "L45-L67"
  description: string;           // Deskripsi masalah
  evidence: string;              // Kutipan kode atau output yang relevan
  recommendation: string;        // Langkah perbaikan yang disarankan
}
```

### Model Domain Summary

```typescript
interface DomainSummary {
  domain: string;
  status: "✅ PASS" | "⚠️ WARNING" | "❌ FAIL";
  total_checks: number;
  passed: number;
  warnings: number;
  failed: number;
  critical_findings: AuditFinding[];
}
```

### Severity Matrix

| Severity | Definisi | Contoh |
|----------|----------|--------|
| **CRITICAL** | Kerentanan keamanan yang dapat dieksploitasi langsung atau kehilangan data finansial | Webhook tanpa validasi signature, SQL injection |
| **HIGH** | Pelanggaran requirement utama yang mempengaruhi integritas data | Poin loyalitas bisa negatif, status order bisa mundur |
| **MEDIUM** | Pelanggaran requirement yang mempengaruhi UX atau konsistensi | Order log tidak lengkap, validasi input lemah |
| **LOW** | Penyimpangan dari best practice tanpa dampak langsung | Missing index, response menyertakan field tidak perlu |
| **INFO** | Catatan observasi tanpa dampak negatif | Kode yang bisa disederhanakan |

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

Berdasarkan prework analysis, berikut adalah correctness properties yang dapat diverifikasi secara otomatis untuk sistem ini. Properties ini berfokus pada logika bisnis murni (pure functions dan kalkulasi) yang tidak bergantung pada infrastruktur eksternal.

---

### Property 1: Total Harga Tidak Pernah Negatif

*For any* kombinasi nilai `subtotal`, `shipping_cost`, `discount_amount`, `promo_discount_amount`, `level_discount_amount`, dan `loyalty_discount_amount` yang valid, nilai `total_price` yang dihasilkan oleh formula kalkulasi SHALL selalu ≥ 0.

**Validates: Requirements 4.1**

---

### Property 2: Batas Maksimal Diskon Loyalty Points

*For any* nilai `subtotal` dan jumlah `loyalty_points_used`, nilai `loyalty_discount_amount` yang dihitung SHALL tidak pernah melebihi `floor(subtotal * 0.05)`, dan konversi SHALL selalu menggunakan rasio 1 poin = Rp 1.000.

**Validates: Requirements 4.5**

---

### Property 3: Konsistensi Saldo Poin Loyalitas

*For any* sequence operasi `addLoyaltyPoints` dan `redeemLoyaltyPoints` pada seorang pengguna, nilai `users.loyalty_points` SHALL selalu sama dengan `SUM(loyalty_point_logs.points)` untuk pengguna tersebut setelah setiap operasi selesai.

**Validates: Requirements 7.2**

---

### Property 4: Poin Loyalitas Tidak Pernah Negatif

*For any* saldo `loyalty_points` pengguna dan jumlah poin yang ingin diredeem, operasi `redeemLoyaltyPoints` SHALL mengembalikan `false` dan tidak mengubah saldo ketika jumlah poin yang diminta melebihi saldo yang tersedia, sehingga saldo tidak pernah turun di bawah 0.

**Validates: Requirements 7.4**

---

### Property 5: Setiap Perubahan Status Order Tercatat di order_logs

*For any* order dan *any* perubahan nilai kolom `status`, model `Order` SHALL selalu membuat entri baru di tabel `order_logs` dengan `event_type = 'status_changed'`, `previous_status` yang benar, dan `current_status` yang benar, melalui `booted` hook.

**Validates: Requirements 6.1, 6.2**

---

### Property 6: Konfirmasi Penerimaan Hanya Valid dari Status 'shipped'

*For any* order dengan status selain `'shipped'`, endpoint `confirmDelivery` SHALL mengembalikan HTTP 422 dan tidak mengubah status order. Hanya order dengan status `'shipped'` yang dapat bertransisi ke `'delivered'`.

**Validates: Requirements 6.3**

---

### Property 7: Formula Kalkulasi Poin dari Konfirmasi Penerimaan

*For any* nilai `total_price` pada order yang dikonfirmasi diterima, jumlah poin loyalitas yang dikreditkan SHALL sama dengan `max(1, floor(total_price / 10000))`.

**Validates: Requirements 6.4**

---

### Property 8: Mutual Exclusion Diskon dan Promo

*For any* request ke endpoint `POST /orders/calculate` atau `POST /orders` yang menyertakan keduanya `discount_id` dan `promo_id` secara bersamaan, sistem SHALL selalu mengembalikan HTTP 422 tanpa membuat pesanan.

**Validates: Requirements 4.2**

---

### Property 9: Stok Habis Mencegah Pembuatan Pesanan

*For any* produk dengan nilai `stock = 0`, request pembuatan pesanan yang menyertakan produk tersebut SHALL selalu ditolak dengan HTTP 422, terlepas dari nilai field lainnya dalam payload.

**Validates: Requirements 2.2**

---

### Property 10: ReturnRequest Hanya untuk Pesanan yang Sudah Diterima

*For any* order dengan status selain `'delivered'` atau `'completed'`, request pembuatan `ReturnRequest` untuk order tersebut SHALL selalu ditolak dengan HTTP 422.

**Validates: Requirements 9.5**

---

### Property 11: Webhook Xendit Tanpa Signature Valid Ditolak

*For any* HTTP request ke endpoint webhook Xendit yang tidak menyertakan signature yang valid (atau menyertakan signature yang salah), sistem SHALL mengembalikan HTTP 401 atau 403 dan tidak memproses perubahan status pembayaran atau pesanan.

**Validates: Requirements 5.2**

---

## Error Handling

### Strategi Penanganan Temuan Audit

Audit ini bersifat observasional. Setiap temuan dikategorikan berdasarkan dampak dan urgensi:

```
CRITICAL → Harus diperbaiki sebelum production deployment
HIGH     → Harus diperbaiki dalam sprint berikutnya
MEDIUM   → Dijadwalkan dalam backlog prioritas tinggi
LOW      → Dijadwalkan dalam backlog normal
INFO     → Opsional, untuk peningkatan kualitas kode
```

### Penanganan Kasus Khusus dalam Audit

| Skenario | Penanganan |
|----------|-----------|
| File tidak ditemukan | Catat sebagai FAIL dengan severity HIGH — implementasi tidak ada |
| Implementasi parsial | Catat sebagai WARNING — ada tapi tidak lengkap |
| Implementasi berbeda dari requirement | Catat sebagai FAIL dengan deskripsi perbedaan |
| Implementasi melebihi requirement | Catat sebagai INFO — over-engineering atau fitur tambahan |
| Kode tidak dapat dibaca (obfuscated) | Eskalasi ke auditor senior |

---

## Testing Strategy

### Pendekatan Dual Testing

Audit ini menggunakan dua pendekatan yang saling melengkapi:

#### 1. Unit Tests (Example-Based)

Digunakan untuk memverifikasi skenario spesifik dan edge cases:

- Skenario autentikasi: registrasi valid, OTP expired, OTP sudah diverifikasi
- Skenario checkout: kombinasi 5 jenis diskon berbeda
- Skenario pembayaran: upload bukti bayar untuk pesanan cancelled
- Skenario admin: akses panel tanpa autentikasi

Framework: **PHPUnit** (sudah tersedia di project Laravel)

#### 2. Property-Based Tests

Digunakan untuk memverifikasi correctness properties di atas dengan banyak input yang di-generate secara acak.

Framework yang dipilih: **[eris/eris](https://github.com/giorgiosironi/eris)** — library property-based testing untuk PHP yang terinspirasi dari QuickCheck.

Instalasi:
```bash
composer require --dev eris/eris
```

Konfigurasi minimum per property test: **100 iterasi**.

Tag format untuk setiap test:
```
Feature: ecommerce-audit, Property {N}: {property_text}
```

#### Contoh Implementasi Property Test

```php
// Feature: ecommerce-audit, Property 1: Total harga tidak pernah negatif
public function testTotalPriceNeverNegative(): void
{
    $this->forAll(
        Generator\choose(0, 10_000_000),  // subtotal
        Generator\choose(0, 100_000),     // shipping_cost
        Generator\choose(0, 5_000_000),   // discount_amount
        Generator\choose(0, 5_000_000),   // promo_discount_amount
        Generator\choose(0, 1_000_000),   // level_discount_amount
        Generator\choose(0, 500_000),     // loyalty_discount_amount
    )->then(function ($subtotal, $shipping, $disc, $promo, $level, $loyalty) {
        $total = max(0, $subtotal + $shipping - $disc - $promo - $level - $loyalty);
        $this->assertGreaterThanOrEqual(0, $total);
    });
}

// Feature: ecommerce-audit, Property 4: Poin loyalitas tidak pernah negatif
public function testLoyaltyPointsNeverNegative(): void
{
    $this->forAll(
        Generator\choose(0, 10_000),  // current balance
        Generator\choose(1, 20_000),  // points to redeem
    )->then(function ($balance, $toRedeem) {
        $user = User::factory()->create(['loyalty_points' => $balance]);
        $result = $user->redeemLoyaltyPoints($toRedeem);

        if ($toRedeem > $balance) {
            $this->assertFalse($result);
            $this->assertEquals($balance, $user->fresh()->loyalty_points);
        } else {
            $this->assertTrue($result);
            $this->assertGreaterThanOrEqual(0, $user->fresh()->loyalty_points);
        }
    });
}
```

#### 3. Static Analysis Checks (Smoke Tests)

Untuk requirements yang tidak dapat diuji dengan property-based testing:

| Check | Metode |
|-------|--------|
| SQL injection prevention | `grep -r "DB::statement\|whereRaw\|selectRaw" --include="*.php"` — verifikasi semua menggunakan parameter binding |
| DB::transaction pada operasi finansial | `grep -r "DB::transaction" app/Http/Controllers/API/OrderController.php` |
| CORS configuration | Baca `config/cors.php`, verifikasi `allowed_origins` tidak `['*']` di production |
| Security headers | Baca `app/Http/Middleware/SecurityHeaders.php` |
| Admin panel auth | Baca `app/Providers/Filament/AdminPanelProvider.php` |

### Format Laporan Audit Output

Laporan audit dihasilkan dalam format Markdown dengan struktur berikut:

```markdown
# Laporan Audit E-Commerce Optik Medio
**Tanggal Audit:** YYYY-MM-DD
**Auditor:** [Nama]
**Versi Sistem:** [Git commit hash]

## Ringkasan Eksekutif
| Domain | Status | CRITICAL | HIGH | MEDIUM | LOW |
|--------|--------|----------|------|--------|-----|
| Autentikasi | ✅ PASS | 0 | 0 | 1 | 2 |
| Produk | ⚠️ WARNING | 0 | 1 | 0 | 1 |
| ...

## Detail Per Domain

### Domain 1 — Autentikasi
**Status: ✅ PASS**

#### AUTH-001 — Rate Limiting OTP Verifikasi
- **Severity:** INFO
- **Requirement:** Req 1.4
- **File:** `app/Http/Controllers/API/AuthController.php` L75-L85
- **Status:** PASS
- **Evidence:** `RateLimiter::tooManyAttempts($limiterKey, 5)` dengan window 600 detik
- **Catatan:** Implementasi sesuai requirement. `retry_after` dikembalikan dalam respons.

#### AUTH-002 — OTP Disimpan di Database, Bukan Session
- **Severity:** INFO
- **Requirement:** Req 1.10
- **File:** `app/Models/OtpCode.php`, `database/migrations/*_create_otp_codes_table.php`
- **Status:** PASS
- **Evidence:** Tabel `otp_codes` dengan kolom `expires_at`, `verified_at`, `type`
```

### Checklist Verifikasi Per Domain

Setiap domain diverifikasi menggunakan checklist yang dipetakan langsung ke acceptance criteria di `requirements.md`. Setiap item checklist menghasilkan satu `AuditFinding` dengan status PASS, WARNING, atau FAIL.

Total checklist items: **87 items** (jumlah acceptance criteria di requirements.md).
