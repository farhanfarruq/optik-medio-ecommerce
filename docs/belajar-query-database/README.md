# Belajar Query Database Optik Medio

Dokumen ini untuk latihan membaca database project Optik Medio lewat terminal.
Gunakan dulu query `SELECT`. Query `INSERT`, `UPDATE`, dan `DELETE` ada di bagian akhir dan diberi contoh transaksi agar bisa dibatalkan.

## 1. Masuk database lewat terminal

Dari root project:

```bash
cd medio-be
```

Cek nama database tanpa membuka password:

```bash
grep -E "^(DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE)=" .env
```

Berdasarkan `.env` saat ini, database memakai MySQL dengan nama:

```text
ecommerce_db
```

Masuk ke MySQL:

```bash
mysql -u root -p ecommerce_db
```

Alternatif jika ingin lewat Laravel:

```bash
php artisan db
```

Perintah dasar di dalam MySQL:

```sql
SHOW TABLES;
DESCRIBE products;
DESCRIBE orders;
EXIT;
```

## 2. Tabel penting project

Tabel utama yang sering dipakai:

| Tabel | Fungsi |
| --- | --- |
| `users` | Data akun pelanggan/admin |
| `categories` | Kategori produk |
| `products` | Katalog produk optik |
| `product_variants` | Variasi produk, misalnya warna atau ukuran |
| `shipping_addresses` | Alamat pengiriman user |
| `orders` | Pesanan pelanggan |
| `order_items` | Detail item dalam pesanan |
| `payments` | Data pembayaran pesanan |
| `payment_methods` | Metode pembayaran |
| `store_branches` | Cabang toko |
| `appointments` | Jadwal appointment pelanggan |
| `product_reviews` | Ulasan produk |
| `warranties` | Data garansi produk |
| `service_claims` | Klaim servis/garansi |

## 3. Query dasar

Lihat 10 user terbaru:

```sql
SELECT id, name, email, role, created_at
FROM users
ORDER BY created_at DESC
LIMIT 10;
```

Lihat kategori aktif:

```sql
SELECT id, name, slug
FROM categories
WHERE is_active = 1
ORDER BY name;
```

Lihat produk aktif:

```sql
SELECT id, name, brand, price, stock
FROM products
WHERE is_active = 1
ORDER BY name
LIMIT 20;
```

Cari produk berdasarkan kata kunci:

```sql
SELECT id, name, brand, price, stock
FROM products
WHERE name LIKE '%lensa%'
   OR brand LIKE '%lensa%'
ORDER BY name;
```

Lihat produk stok menipis:

```sql
SELECT id, name, sku, stock
FROM products
WHERE stock <= 5
ORDER BY stock ASC, name ASC;
```

## 4. Query relasi produk

Produk beserta kategorinya:

```sql
SELECT
  p.id,
  p.name AS product_name,
  c.name AS category_name,
  p.brand,
  p.price,
  p.stock
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
ORDER BY p.name
LIMIT 30;
```

Varian dari satu produk:

```sql
SELECT
  p.name AS product_name,
  pv.sku,
  pv.name AS variant_name,
  pv.color,
  pv.lens_size,
  pv.stock,
  pv.price
FROM product_variants pv
JOIN products p ON p.id = pv.product_id
WHERE p.id = 1
ORDER BY pv.name;
```

Rating rata-rata produk:

```sql
SELECT
  p.id,
  p.name,
  ROUND(AVG(pr.rating), 2) AS avg_rating,
  COUNT(pr.id) AS total_review
FROM products p
LEFT JOIN product_reviews pr
  ON pr.product_id = p.id
 AND pr.is_approved = 1
GROUP BY p.id, p.name
ORDER BY avg_rating DESC, total_review DESC
LIMIT 20;
```

## 5. Query pesanan

Lihat pesanan terbaru:

```sql
SELECT
  o.id,
  o.order_number,
  u.name AS customer_name,
  o.status,
  o.total_price,
  o.created_at
FROM orders o
LEFT JOIN users u ON u.id = o.user_id
ORDER BY o.created_at DESC
LIMIT 20;
```

Detail item dalam satu pesanan:

```sql
SELECT
  o.order_number,
  oi.product_name,
  oi.product_price,
  oi.quantity,
  oi.subtotal
FROM order_items oi
JOIN orders o ON o.id = oi.order_id
WHERE o.order_number = 'GANTI_DENGAN_NOMOR_ORDER';
```

Pesanan yang belum dibayar:

```sql
SELECT id, order_number, status, total_price, created_at
FROM orders
WHERE paid_at IS NULL
ORDER BY created_at DESC;
```

Pesanan yang sudah dikirim tapi belum selesai:

```sql
SELECT id, order_number, status, tracking_number, shipped_at
FROM orders
WHERE shipped_at IS NOT NULL
  AND delivered_at IS NULL
ORDER BY shipped_at DESC;
```

Total penjualan per status:

```sql
SELECT
  status,
  COUNT(*) AS total_order,
  SUM(total_price) AS total_nominal
FROM orders
GROUP BY status
ORDER BY total_order DESC;
```

## 6. Query pembayaran

Pembayaran terbaru:

```sql
SELECT
  p.id,
  o.order_number,
  p.provider,
  p.payment_method,
  p.gross_amount,
  p.status,
  p.paid_at
FROM payments p
JOIN orders o ON o.id = p.order_id
ORDER BY p.created_at DESC
LIMIT 20;
```

Pesanan dengan status pembayaran:

```sql
SELECT
  o.order_number,
  o.status AS order_status,
  p.status AS payment_status,
  p.gross_amount,
  p.paid_at
FROM orders o
LEFT JOIN payments p ON p.order_id = o.id
ORDER BY o.created_at DESC
LIMIT 20;
```

Metode pembayaran aktif:

```sql
SELECT id, name, code, type, provider
FROM payment_methods
WHERE is_active = 1
ORDER BY type, name;
```

## 7. Query pelanggan dan alamat

User dengan jumlah order:

```sql
SELECT
  u.id,
  u.name,
  u.email,
  COUNT(o.id) AS total_order,
  COALESCE(SUM(o.total_price), 0) AS total_belanja
FROM users u
LEFT JOIN orders o ON o.user_id = u.id
GROUP BY u.id, u.name, u.email
ORDER BY total_belanja DESC
LIMIT 20;
```

Alamat utama pelanggan:

```sql
SELECT
  u.name,
  sa.recipient_name,
  sa.phone,
  sa.city,
  sa.district,
  sa.address
FROM shipping_addresses sa
JOIN users u ON u.id = sa.user_id
WHERE sa.is_default = 1
ORDER BY u.name;
```

## 8. Query appointment cabang

Jadwal appointment terbaru:

```sql
SELECT
  a.appointment_number,
  u.name AS user_name,
  sb.name AS branch_name,
  a.appointment_date,
  a.appointment_time,
  a.service_type,
  a.status
FROM appointments a
LEFT JOIN users u ON u.id = a.user_id
LEFT JOIN store_branches sb ON sb.id = a.branch_id
ORDER BY a.appointment_date DESC, a.appointment_time DESC
LIMIT 20;
```

Jumlah appointment per cabang:

```sql
SELECT
  sb.name AS branch_name,
  COUNT(a.id) AS total_appointment
FROM store_branches sb
LEFT JOIN appointments a ON a.branch_id = sb.id
GROUP BY sb.id, sb.name
ORDER BY total_appointment DESC;
```

Appointment hari ini:

```sql
SELECT appointment_number, customer_name, customer_phone, appointment_time, status
FROM appointments
WHERE appointment_date = CURDATE()
ORDER BY appointment_time;
```

## 9. Query laporan sederhana

Penjualan per hari:

```sql
SELECT
  DATE(created_at) AS tanggal,
  COUNT(*) AS total_order,
  SUM(total_price) AS total_penjualan
FROM orders
GROUP BY DATE(created_at)
ORDER BY tanggal DESC
LIMIT 30;
```

Produk paling sering dibeli:

```sql
SELECT
  oi.product_id,
  oi.product_name,
  SUM(oi.quantity) AS total_terjual,
  SUM(oi.subtotal) AS total_nominal
FROM order_items oi
GROUP BY oi.product_id, oi.product_name
ORDER BY total_terjual DESC
LIMIT 20;
```

Kategori dengan produk terbanyak:

```sql
SELECT
  c.name AS category_name,
  COUNT(p.id) AS total_product
FROM categories c
LEFT JOIN products p ON p.category_id = c.id
GROUP BY c.id, c.name
ORDER BY total_product DESC;
```

Produk aktif tapi belum pernah dibeli:

```sql
SELECT p.id, p.name, p.stock
FROM products p
LEFT JOIN order_items oi ON oi.product_id = p.id
WHERE p.is_active = 1
  AND oi.id IS NULL
ORDER BY p.name;
```

## 10. Latihan aman untuk menulis data

Gunakan transaksi agar bisa dibatalkan dengan `ROLLBACK`.

Tambah kategori latihan lalu batalkan:

```sql
START TRANSACTION;

INSERT INTO categories (name, slug, description, is_active, created_at, updated_at)
VALUES ('Latihan Query', 'latihan-query', 'Kategori latihan dari terminal', 0, NOW(), NOW());

SELECT id, name, slug
FROM categories
WHERE slug = 'latihan-query';

ROLLBACK;
```

Update stok produk lalu batalkan:

```sql
START TRANSACTION;

SELECT id, name, stock
FROM products
WHERE id = 1;

UPDATE products
SET stock = stock + 1,
    updated_at = NOW()
WHERE id = 1;

SELECT id, name, stock
FROM products
WHERE id = 1;

ROLLBACK;
```

Simulasi hapus data lalu batalkan:

```sql
START TRANSACTION;

DELETE FROM categories
WHERE slug = 'latihan-query';

ROLLBACK;
```

## 11. Urutan belajar yang disarankan

1. Jalankan `SHOW TABLES;` dan `DESCRIBE nama_tabel;`.
2. Latihan `SELECT`, `WHERE`, `ORDER BY`, dan `LIMIT`.
3. Latihan `JOIN` antara `orders`, `users`, dan `order_items`.
4. Latihan `GROUP BY` untuk laporan penjualan.
5. Baru coba `INSERT`, `UPDATE`, dan `DELETE` dalam `START TRANSACTION` + `ROLLBACK`.

