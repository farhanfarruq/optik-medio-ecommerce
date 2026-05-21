# Source Data — Optik Medio Catalog

Folder ini berisi file JSON sumber katalog produk yang digunakan oleh Artisan command `php artisan import:optik-products`.

## File

| File | Isi | Ukuran perkiraan |
|---|---|---|
| `data_optik_lengkap.json` | Frame kacamata lengkap | ~1.6 MB |
| `data_sunglasses.json` | Kacamata hitam (sunglasses) | ~243 KB |
| `data_lensa_kontak.json` | Lensa kontak | ~9 KB |
| `data_semua_merek.json` | Informasi merek lensa (display only, harga & stok dipaksa 0) | ~50 KB |
| `backup_sunglasses.json` | Backup historis sunglasses | ~213 KB |

## Cara Pakai

```bash
# Auto-detect dari folder ini
php artisan import:optik-products

# Atau gunakan --file untuk path custom
php artisan import:optik-products --file=database/seeders/data/data_sunglasses.json

# Mode upsert (tidak truncate data lama)
php artisan import:optik-products --skip-truncate

# Download gambar lokal (default: simpan URL CDN)
php artisan import:optik-products --download-images
```

## Lokasi Sebelumnya

File-file ini sebelumnya disimpan di root repo (`./` dan `medio-be/`). Mulai 19 Mei 2026, semuanya dipindah ke folder ini agar:

1. Tidak mencemari root project
2. Tidak ikut ter-deploy ke folder publik backend
3. Posisi konsisten dengan konvensi Laravel (`database/seeders/data/`)

`ImportOptikProducts.php` sudah diupdate untuk mencari di sini terlebih dahulu, dengan fallback ke path lama untuk backward compatibility.
