# Manual Regression Checklist - Optik Medio

## Tujuan

Checklist ini dipakai sebelum release atau setelah perubahan besar pada checkout, payment, produk, order, loyalty, komplain, retur, dan admin panel.

Isi kolom hasil dengan:

- `PASS`
- `FAIL`
- `BLOCKED`
- `N/A`

## Informasi Run

- Tanggal:
- Tester:
- Branch / commit:
- Backend env:
- Frontend env:
- Browser / device:

## Auth dan Akun

| ID | Skenario | Expected Result | Hasil |
| --- | --- | --- | --- |
| AUTH-REG-001 | Register dengan name, email unik, password valid, password confirmation valid | Akun dibuat dan OTP terkirim |  |
| AUTH-REG-002 | Register dengan email yang sudah dipakai | Request ditolak dengan validasi email unique |  |
| AUTH-OTP-001 | Verify OTP valid | Email terverifikasi dan user login |  |
| AUTH-OTP-002 | Verify OTP expired/salah | Request ditolak 422 |  |
| AUTH-OTP-003 | Verify OTP salah lebih dari limit | Request ditolak 429 dan ada `retry_after` |  |
| AUTH-LOGIN-001 | Login user verified | Response `requires_otp: true`, sesi belum aktif |  |
| AUTH-LOGIN-002 | Logout user aktif | Session invalid dan CSRF token regenerate |  |

## Product dan Cart

| ID | Skenario | Expected Result | Hasil |
| --- | --- | --- | --- |
| PROD-001 | Buka katalog produk aktif | Produk aktif tampil, produk deleted/nonaktif tidak tampil |  |
| PROD-002 | Filter/search produk | Hasil sesuai filter dan tidak menampilkan produk tidak aktif |  |
| PROD-003 | Add product stok tersedia ke cart | Item masuk cart dengan quantity benar |  |
| PROD-004 | Add product stok 0 ke checkout | Checkout/calculate ditolak 422 |  |
| CART-001 | Add frame tanpa lensa | Payload checkout berisi satu item utama |  |
| CART-002 | Add frame plus lens | Payload checkout berisi frame dan lens dengan `linked_item_index` |  |
| CART-003 | Cart kosong lalu akses checkout | User diblokir atau diarahkan dengan pesan jelas |  |
| CART-004 | Remove parent frame dari cart | Lens pasangan ikut hilang |  |

## Checkout dan Harga

| ID | Skenario | Expected Result | Hasil |
| --- | --- | --- | --- |
| CHK-001 | Calculate order tanpa diskon/promo | Total = subtotal + shipping - level - loyalty |  |
| CHK-002 | Calculate dengan discount | Discount amount benar dan total tidak negatif |  |
| CHK-003 | Calculate dengan promo | Promo amount/free item benar sesuai tipe promo |  |
| CHK-004 | Kirim `discount_id` dan `promo_id` bersamaan | Ditolak 422 |  |
| CHK-005 | Pakai loyalty melebihi 5% subtotal | Backend membatasi maksimal 5% subtotal |  |
| CHK-006 | Pakai loyalty melebihi saldo | Ditolak atau dibatasi sesuai saldo, saldo tidak negatif |  |
| CHK-007 | Pakai `shipping_address_id` user lain | Ditolak 403 |  |
| CHK-008 | Toko tutup | Checkout/order creation diblokir |  |
| CHK-009 | Produk resep wajib tanpa prescription | Ditolak 422 |  |
| CHK-010 | Produk resep wajib dengan prescription valid | Order dapat dibuat |  |

## Payment

| ID | Skenario | Expected Result | Hasil |
| --- | --- | --- | --- |
| PAY-001 | Order Xendit sukses | Checkout URL tersedia dan order unpaid sampai webhook/sync |  |
| PAY-002 | Webhook Xendit token invalid | Ditolak 401, order/payment tidak berubah |  |
| PAY-003 | Webhook Xendit PAID valid | Payment success, order paid, log payment_verified dibuat |  |
| PAY-004 | Replay webhook PAID valid | Idempotent, email tidak terkirim dobel |  |
| PAY-005 | Upload bukti bayar manual file valid | File tersimpan dan order log `payment_proof_uploaded` dibuat |  |
| PAY-006 | Upload bukti bayar manual file invalid | Ditolak validasi |  |
| PAY-007 | Upload bukti bayar order user lain | Ditolak 403 |  |
| PAY-008 | Upload bukti bayar order cancelled/delivered | Ditolak 422 |  |

## Order Lifecycle

| ID | Skenario | Expected Result | Hasil |
| --- | --- | --- | --- |
| ORD-001 | User lihat list order | Hanya order miliknya tampil |  |
| ORD-002 | User buka order user lain | Ditolak 403 |  |
| ORD-003 | Admin/update status order | `order_logs` mencatat perubahan status |  |
| ORD-004 | Update tracking number | `order_logs` mencatat `tracking_updated` |  |
| ORD-005 | Confirm delivery dari status selain shipped | Ditolak 422 |  |
| ORD-006 | Confirm delivery dari shipped | Status jadi delivered dan loyalty earned masuk |  |
| ORD-007 | Tracking endpoint | Logs tampil kronologis |  |

## Loyalty dan Membership

| ID | Skenario | Expected Result | Hasil |
| --- | --- | --- | --- |
| LOY-001 | Redeem loyalty valid | Saldo berkurang dan log redeemed dibuat |  |
| LOY-002 | Redeem loyalty melebihi saldo | Ditolak, saldo tetap |  |
| LOY-003 | Confirm delivery order valid | Poin earned = max(1, floor(total_price / 10000)) |  |
| LOY-004 | Level membership berubah | Hanya satu level aktif per user |  |

## Complaint dan Return

| ID | Skenario | Expected Result | Hasil |
| --- | --- | --- | --- |
| COMP-001 | Buat komplain untuk order sendiri | Komplain dibuat |  |
| COMP-002 | Buat komplain untuk order user lain | Ditolak 403/422 |  |
| COMP-003 | Upload attachment komplain invalid | Ditolak validasi |  |
| COMP-004 | Admin resolve komplain | Status resolved dan resolved_at terisi |  |
| RET-001 | Buat return untuk order delivered/completed | Return dibuat |  |
| RET-002 | Buat return untuk order unpaid/cancelled | Ditolak 422 |  |

## Admin Panel

| ID | Skenario | Expected Result | Hasil |
| --- | --- | --- | --- |
| ADM-001 | Akses admin tanpa login | Redirect/ditolak |  |
| ADM-002 | Dashboard admin tampil | Revenue, order, low stock, payment proof, complaint, return terlihat |  |
| ADM-003 | Verifikasi payment proof manual | Order paid, verifier dan timestamp tercatat |  |
| ADM-004 | Update tracking | Tracking number tersimpan dan log dibuat |  |
| ADM-005 | Edit produk | Validasi field wajib aktif |  |
| ADM-006 | Hapus produk | Soft delete, tidak tampil publik, order lama tetap referensi |  |

## Frontend UX

| ID | Skenario | Expected Result | Hasil |
| --- | --- | --- | --- |
| UX-001 | Mobile product listing | Filter, card, dan tombol tidak overlap |  |
| UX-002 | Mobile checkout | Semua step/form bisa digunakan tanpa horizontal scroll |  |
| UX-003 | Error checkout | Pesan error jelas dan bisa ditindaklanjuti |  |
| UX-004 | Loading state | User melihat loading saat calculate/shipping/payment |  |
| UX-005 | Empty state | Cart/wishlist/order kosong punya pesan jelas |  |

## Release Gate

Release boleh lanjut jika:

- Semua P0 scenario PASS.
- Tidak ada FAIL pada payment, checkout, auth, ownership, dan order lifecycle.
- Backend test pass.
- Frontend build pass.
- Regression issue yang tersisa dicatat dengan owner dan target fix.
