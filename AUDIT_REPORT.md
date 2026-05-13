# Laporan Audit E-Commerce Optik Medio

## Ringkasan Eksekutif

Status awal: **IN PROGRESS**.

Implementasi audit dimulai dari kontrol P0/P1 yang mempengaruhi keamanan, kepemilikan data, stok, dan kalkulasi finansial. Traceability memakai `requirements.md` sebagai sumber kebenaran dengan total **103 acceptance criteria**.

## Temuan dan Perbaikan Awal

| ID | Severity | Requirement | Status | Evidence |
| --- | --- | --- | --- | --- |
| AUDIT-001 | MEDIUM | Design consistency | PASS | `.kiro/specs/ecommerce-audit/design.md` diperbarui dari 87 menjadi 103 checklist items. |
| AUTH-001 | HIGH | Req 1.4, 1.5 | PASS | `medio-be/routes/api.php` memakai throttle untuk verify/resend OTP; `AuthController` mengembalikan `retry_after` untuk verify limit. |
| CHKOUT-001 | CRITICAL | Req 4.1 | PASS | `OrderController@calculate` dan `@store` memakai formula `max(0, subtotal + shipping - discount - promo - level - loyalty)`. |
| CHKOUT-002 | HIGH | Req 4.2 | PASS | `OrderController` menolak payload yang mengirim `discount_id` dan `promo_id` bersamaan. |
| CHKOUT-003 | CRITICAL | Req 4.7, 4.10 | PASS | `OrderController@calculate` dan `@store` memvalidasi `shipping_address_id` milik user login. Req 4.7 dan 4.10 adalah kontrol duplikat. |
| PROD-001 | HIGH | Req 2.2, 2.7 | PASS | `OrderController@calculate` memvalidasi stok, dan `@store` melakukan decrement atomik dengan `where('stock', '>=', quantity)`. |
| PROD-002 | HIGH | Req 2.4 | PASS | `OrderController@calculate` dan `@store` menolak produk resep wajib tanpa data resep pada item utama. |
| PAY-001 | CRITICAL | Req 5.2 | PASS | `WebhookController@xendit` menolak callback tanpa `x-callback-token` yang sesuai konfigurasi. |
| PAY-002 | HIGH | Req 5.3, 5.4, 5.5, 5.9 | PASS | `uploadPaymentProof` memvalidasi ownership, status order, file type, ukuran 4 MB, dan payment provider manual. |
| ORDER-001 | HIGH | Req 6.1, 6.2, 6.8 | PASS | `Order` membuat `order_logs`; `tracking` mengembalikan logs kronologis dan ownership-guarded. |
| LOYAL-001 | HIGH | Req 7.3, 7.4 | PASS | `User::redeemLoyaltyPoints` memakai row lock dan gagal bila saldo tidak cukup. |
| CART-001 | HIGH | Req 3.2, 3.3, 3.5 | PASS | `cartStore` memakai payload checkout yang sama untuk calculate/store, termasuk `linked_item_index`, dan checkout clear cart setelah order berhasil. |

## Traceability Matrix

| Domain | Requirement | Status Awal |
| --- | --- | --- |
| Autentikasi | Req 1.1 - 1.10 | PARTIAL PASS, perlu test OTP expired/resend limit lebih lengkap |
| Produk & Katalog | Req 2.1 - 2.8 | PARTIAL PASS, perlu audit ProductReview selesai-order |
| Keranjang | Req 3.1 - 3.6 | PARTIAL PASS, payload calculate/store sudah disamakan |
| Checkout | Req 4.1 - 4.10 | PARTIAL PASS, kontrol P0 diterapkan |
| Pembayaran | Req 5.1 - 5.10 | PARTIAL PASS, webhook dan upload proof sudah guarded |
| Pesanan | Req 6.1 - 6.9 | PARTIAL PASS, lifecycle dasar tersedia |
| Loyalitas | Req 7.1 - 7.7 | PARTIAL PASS, redeem atomic diperkuat |
| Afiliasi | Req 8.1 - 8.7 | NOT AUDITED |
| Komplain & Retur | Req 9.1 - 9.7 | NOT AUDITED |
| Pengiriman | Req 10.1 - 10.6 | PARTIAL PASS, perlu verifikasi fallback RajaOngkir |
| Admin Panel | Req 11.1 - 11.8 | NOT AUDITED |
| Security API | Req 12.1 - 12.10 | PARTIAL PASS, perlu static audit raw SQL/CORS/security headers |
| Konten | Req 13.1 - 13.5 | NOT AUDITED |

## Verifikasi Wajib

- `cd medio-be && composer test`
- `cd medio-be && php artisan route:list`
- `cd medio-fe && npm run build`
- Manual checkout: cart kosong, stok habis, produk resep tanpa prescription, frame + lensa, diskon, promo, loyalty points, toko tutup.
