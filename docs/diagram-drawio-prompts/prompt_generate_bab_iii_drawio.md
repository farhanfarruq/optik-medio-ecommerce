  # Prompt Pembuatan Diagram BAB III Optik Medio

Gunakan prompt ini di Codex/agent lokal dari root repo:

```bash
codex exec "$(cat docs/diagram-drawio-prompts/prompt_generate_bab_iii_drawio.md)"
```

Target output wajib:

- Folder: `docs/kp/diagrams/`
- Format: file `.drawio` valid diagrams.net/draw.io XML
- Satu file per gambar, memakai nama berurutan:
  - `gambar-3.1-arsitektur-sistem.drawio`
  - `gambar-3.2-flowchart-pelanggan.drawio`
  - `gambar-3.3-flowchart-admin.drawio`
  - `gambar-3.4-dfd-level-0.drawio`
  - `gambar-3.5-dfd-level-1.drawio`
  - `gambar-3.6-dfd-level-2-checkout.drawio`
  - `gambar-3.7-erd-sistem.drawio`
  - `gambar-3.8-alur-status-pesanan-pembayaran.drawio`
  - `gambar-3.20-integrasi-rajaongkir.drawio`
  - `gambar-3.21-integrasi-xendit.drawio`

## Tugas

Buat 10 diagram BAB III laporan KP Optik Medio dalam format `.drawio` valid diagrams.net.

Jangan membuat gambar raster. Jangan membuat Mermaid. Jangan membuat PlantUML.
Output harus file XML diagrams.net yang bisa dibuka langsung di https://app.diagrams.net/.

Sebelum membuat diagram, baca sumber repo berikut:

- `medio-be/routes/api.php`
- `medio-be/app/Models/`
- `medio-be/database/migrations/`
- `medio-be/app/Http/Controllers/`
- `medio-fe/src/router/`
- `medio-fe/src/views/`
- `medio-fe/src/stores/`
- `docs/kp/laporan_kp_optik_medio.tex`
- `docs/kp/audit_lanjutan_bab_iii_optik_medio.md`

Gunakan fakta dari repo. Jika ada detail bisnis yang tidak bisa dipastikan dari repo, pakai label umum dan jangan mengarang data.

## Konteks Sistem

Sistem adalah e-commerce Optik Medio dengan frontend Vue, backend Laravel API, admin panel, database, dan integrasi eksternal.

Entitas utama yang perlu dipertimbangkan:

- pengguna/pelanggan
- admin
- produk
- kategori
- alamat pengiriman
- pesanan
- item pesanan
- pembayaran
- metode pembayaran
- ekspedisi/pengiriman
- ulasan produk
- diskon
- keranjang/wishlist bila ada di frontend

Integrasi eksternal:

- RajaOngkir untuk pengecekan ongkir/layanan pengiriman
- Xendit untuk invoice/checkout pembayaran dan webhook status pembayaran

## Aturan Visual

- Bahasa diagram: Indonesia.
- Judul diagram harus sesuai nomor gambar.
- Gunakan bentuk standar:
  - flowchart: terminator, process, decision, data/store
  - DFD: external entity, process, data store, data flow
  - ERD: entity/table, atribut penting, relasi cardinality
  - arsitektur/integrasi: layer/component box dan arrow berlabel
- Layout rapi kiri-ke-kanan atau atas-ke-bawah.
- Hindari teks terlalu panjang dalam satu shape.
- Tidak perlu warna berlebihan. Pakai palet sederhana: biru untuk aplikasi, hijau untuk database/data store, kuning untuk eksternal, abu-abu untuk aktor.
- Setiap file harus hanya berisi satu diagram.
- Setiap diagram harus punya label `Gambar 3.x ...` di bagian atas.

## Diagram Yang Dibuat

### Gambar 3.1 Arsitektur sistem e-commerce Optik Medio

Buat diagram arsitektur berlapis:

- Pelanggan mengakses frontend Vue melalui browser.
- Admin mengakses panel/admin backend.
- Frontend berkomunikasi dengan Laravel API.
- Laravel API berkomunikasi dengan database.
- Laravel API terhubung ke Xendit untuk pembayaran.
- Laravel API terhubung ke RajaOngkir untuk ongkir.
- Laravel API menangani autentikasi, katalog, keranjang/checkout, pesanan, pembayaran, pengiriman, ulasan.

### Gambar 3.2 Flowchart pelanggan

Buat flowchart alur pelanggan:

1. Mulai.
2. Buka website.
3. Registrasi/login bila diperlukan.
4. Telusuri katalog produk.
5. Lihat detail produk.
6. Pilih produk/varian/resep optik bila diperlukan.
7. Tambah ke keranjang atau checkout.
8. Isi/pilih alamat pengiriman.
9. Pilih ekspedisi/layanan dan hitung ongkir.
10. Buat pesanan.
11. Pilih metode pembayaran.
12. Bayar melalui checkout/invoice Xendit.
13. Sistem menerima status pembayaran.
14. Pelanggan memantau status pesanan.
15. Pesanan diterima.
16. Selesai.

Tambahkan decision untuk login, stok/produk tersedia, ongkir tersedia, dan pembayaran berhasil.

### Gambar 3.3 Flowchart admin

Buat flowchart alur admin:

1. Mulai.
2. Admin login.
3. Dashboard admin.
4. Kelola produk/kategori/stok.
5. Kelola pesanan masuk.
6. Verifikasi status pembayaran.
7. Proses pesanan.
8. Atur pengiriman/resi/status.
9. Kelola ulasan/komplain bila ada.
10. Laporan/monitoring.
11. Selesai.

Tambahkan decision untuk autentikasi valid, pembayaran sudah lunas, stok cukup, dan pesanan selesai/dibatalkan.

### Gambar 3.4 DFD Level 0 sistem Optik Medio

Buat context diagram DFD Level 0:

- Proses tunggal: Sistem E-Commerce Optik Medio.
- External entity:
  - Pelanggan
  - Admin
  - Xendit
  - RajaOngkir
- Aliran data:
  - Pelanggan: data akun, pencarian produk, data alamat, data checkout, pembayaran, status pesanan.
  - Admin: data produk, data pesanan, data pengiriman, laporan.
  - Xendit: permintaan invoice/checkout, status pembayaran/webhook.
  - RajaOngkir: data tujuan/berat/kurir, hasil ongkir/layanan.

### Gambar 3.5 DFD Level 1 sistem Optik Medio

Buat DFD Level 1 dengan proses:

- 1.0 Autentikasi dan Akun
- 2.0 Manajemen Katalog
- 3.0 Keranjang dan Checkout
- 4.0 Pembayaran
- 5.0 Pengiriman
- 6.0 Manajemen Pesanan
- 7.0 Ulasan dan Layanan Pelanggan bila didukung repo

Data store:

- D1 Users
- D2 Products/Categories
- D3 Shipping Addresses
- D4 Orders
- D5 Order Items
- D6 Payments/Payment Methods
- D7 Expeditions/Shipping Rates
- D8 Reviews/Complaints bila didukung repo

Tampilkan aliran data antar proses, aktor Pelanggan/Admin, dan integrasi Xendit/RajaOngkir.

### Gambar 3.6 DFD Level 2 proses checkout

Rinci proses 3.0 Keranjang dan Checkout:

- 3.1 Validasi keranjang
- 3.2 Ambil data produk dan stok
- 3.3 Validasi alamat pengiriman
- 3.4 Hitung ongkir via RajaOngkir
- 3.5 Hitung subtotal, diskon, ongkir, total
- 3.6 Buat order
- 3.7 Simpan order items
- 3.8 Buat pembayaran/invoice Xendit
- 3.9 Kirim instruksi pembayaran ke pelanggan

Data store:

- Products
- Shipping Addresses
- Orders
- Order Items
- Discounts bila ada
- Payments

External entity:

- Pelanggan
- RajaOngkir
- Xendit

### Gambar 3.7 ERD sistem Optik Medio

Buat ERD dari migrations/models repo. Prioritaskan tabel:

- users
- categories
- products
- shipping_addresses
- orders
- order_items
- payments
- payment_methods
- expeditions
- shipping_rates bila ada
- discounts
- discount_usages
- product_reviews
- complains/return_requests/warranties bila relevan

Tampilkan primary key, foreign key, atribut penting, dan cardinality.

Relasi minimal:

- users 1..n shipping_addresses
- users 1..n orders
- categories 1..n products
- orders 1..n order_items
- products 1..n order_items
- orders 1..n payments atau orders 1..1 payment sesuai model/migration
- payment_methods 1..n payments
- expeditions 1..n shipping_rates/order shipping bila didukung
- users 1..n product_reviews
- products 1..n product_reviews
- discounts 1..n discount_usages bila ada

### Gambar 3.8 Alur status pesanan dan pembayaran

Buat state diagram/alur status:

- Order dibuat.
- Menunggu pembayaran.
- Xendit checkout/invoice dibuat.
- Pelanggan membayar.
- Webhook/status Xendit diterima.
- Jika sukses: pembayaran lunas, pesanan diproses.
- Jika gagal/kedaluwarsa: pembayaran gagal/kedaluwarsa, pesanan dibatalkan atau menunggu pembayaran ulang.
- Setelah diproses: dikirim, diterima, selesai.
- Cabang opsional: dibatalkan, retur/komplain bila didukung repo.

Pisahkan status pembayaran dan status pesanan dengan swimlane atau dua baris paralel.

### Gambar 3.20 Alur integrasi RajaOngkir

Buat diagram integrasi sequence/flow:

1. Pelanggan memilih alamat dan kurir/layanan.
2. Frontend mengirim request ongkir ke Laravel API.
3. Laravel API mengambil data alamat, berat item, ekspedisi.
4. Laravel API mengirim request ke RajaOngkir.
5. RajaOngkir mengembalikan daftar layanan dan ongkir.
6. Laravel API menyimpan atau meneruskan hasil ongkir.
7. Frontend menampilkan opsi ongkir.
8. Pelanggan memilih layanan.
9. Ongkir masuk perhitungan checkout.

Tambahkan path error:

- alamat tidak lengkap
- layanan tidak tersedia
- request API gagal

### Gambar 3.21 Alur integrasi Xendit

Buat diagram integrasi sequence/flow:

1. Pelanggan konfirmasi checkout.
2. Frontend mengirim request buat pesanan/pembayaran ke Laravel API.
3. Laravel API membuat order dan payment record.
4. Laravel API request invoice/checkout ke Xendit.
5. Xendit mengembalikan checkout URL/invoice ID.
6. Laravel API menyimpan transaction/external ID dan checkout URL.
7. Frontend mengarahkan pelanggan ke checkout Xendit.
8. Pelanggan menyelesaikan pembayaran.
9. Xendit mengirim webhook ke Laravel API.
10. Laravel API memvalidasi webhook.
11. Laravel API memperbarui status payment dan order.
12. Pelanggan/admin melihat status terbaru.

Tambahkan path error:

- invoice gagal dibuat
- pembayaran expired/failed
- webhook tidak valid

## Validasi Wajib

Setelah membuat file:

1. Pastikan folder `docs/kp/diagrams/` ada.
2. Pastikan ada 10 file `.drawio`.
3. Pastikan setiap file berisi XML diagrams.net valid dengan elemen `<mxfile>`, `<diagram>`, dan `<mxGraphModel>`.
4. Jangan menaruh output di luar `docs`.
5. Jalankan pemeriksaan sederhana dengan perintah:

```bash
node -e "const fs=require('fs'),p='docs/kp/diagrams'; const files=fs.readdirSync(p).filter(f=>f.endsWith('.drawio')); console.log(files.length); for (const f of files) { const s=fs.readFileSync(p+'/'+f,'utf8'); if(!s.includes('<mxfile')||!s.includes('<diagram')||!s.includes('<mxGraphModel')) throw new Error(f+' bukan drawio valid'); }"
```

Laporkan hanya:

- daftar file yang dibuat
- hasil validasi
- catatan singkat jika ada asumsi repo yang tidak bisa dipastikan
