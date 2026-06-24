# Roadmap Codex - Optik Medio E-Commerce

## Fokus

Pekerjaan hanya untuk repository Optik Medio E-Commerce. Tidak memakai konteks DDoS, Slowloris, Snort, PCAP, flood signal, atau dashboard defensive lab.

## Hasil audit stack aktual

- Frontend: `medio-fe`
- Stack frontend: Vue.js 3, Vite, TypeScript, Tailwind CSS, Pinia, `pinia-plugin-persistedstate`, Vue Router, Axios, DOMPurify.
- Backend: `medio-be`
- Stack backend: Laravel 13, PHP 8.3, Laravel Sanctum, Filament, Resend Laravel, Symfony HTML Sanitizer, Xendit PHP.
- Database: mengikuti `DB_CONNECTION` pada `medio-be/.env`; default project Laravel biasanya MySQL, tetapi nilai aktual harus diverifikasi dari environment lokal.
- Frontend env utama: `VITE_API_URL`.
- Backend env penting: `APP_URL`, `FRONTEND_URL`, `DB_CONNECTION`, `XENDIT_SECRET_KEY`, `XENDIT_WEBHOOK_TOKEN`, `RAJAONGKIR_API_KEY`.

## Struktur kerja

1. Audit manifest dan struktur folder.
2. Cocokkan dokumentasi dengan stack aktual.
3. Hindari data fiktif; gunakan `[ISI DATA ASLI]`.
4. Simpan gambar, diagram, dan screenshot di `docs/kp/assets`.
5. Jalankan validasi yang tersedia.
6. Perbarui status validasi pada laporan jika hasil final sudah diputuskan.

## Modul yang teridentifikasi

- Auth dan user.
- Kategori dan produk.
- Variant produk dan atribut optik.
- Cart dan order.
- Payment status dan webhook Xendit.
- Shipping/expedition.
- Promo dan discount.
- Appointment.
- Complaint/complain.
- Review produk.
- Article dan FAQ.
- Affiliate/referral/commission.
- Admin panel Filament.

## Command menjalankan project

Backend:

```bash
cd medio-be
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Frontend:

```bash
cd medio-fe
npm install
cp .env.example .env
npm run dev
```

Pastikan `medio-fe/.env` mengisi `VITE_API_URL` sesuai URL backend.

## Command validasi

Frontend:

```bash
cd medio-fe
npm run build
npm run lint
npm run typecheck
npm run test:run
```

Backend:

```bash
cd medio-be
php artisan test
```

## Aset laporan yang perlu dilengkapi

- `docs/kp/assets/logo-optik-medio.*`
- `docs/kp/assets/struktur-organisasi.*`
- `docs/kp/assets/arsitektur-sistem.*`
- `docs/kp/assets/flowchart-pelanggan.*`
- `docs/kp/assets/flowchart-admin.*`
- `docs/kp/assets/dfd-level-0.*`
- `docs/kp/assets/dfd-level-1.*`
- `docs/kp/assets/erd-database.*`
- `docs/kp/assets/screenshot-beranda.*`
- `docs/kp/assets/screenshot-detail-produk.*`
- `docs/kp/assets/screenshot-checkout.*`
- `docs/kp/assets/screenshot-dashboard-admin.*`

## Status dokumen

- `docs/kp/laporan_kp_optik_medio.tex`: dibuat.
- `docs/kp/roadmap_codex.md`: dibuat.
- `docs/kp/assets/README.md`: dibuat sebagai daftar placeholder aset.

## Status validasi 18 Juni 2026

- `cd medio-fe && npm run build`: berhasil.
- `cd medio-fe && npm run lint`: berhasil dengan 265 warning dan 0 error.
- `cd medio-fe && npm run typecheck`: berhasil.
- `cd medio-fe && npm run test:run`: berhasil, 6 file test dan 45 test passed.
- `cd medio-be && php artisan test`: awalnya gagal pada `HealthEndpointTest` karena response `/api/health` belum mengirim `database`; setelah patch `medio-be/routes/api.php`, berhasil dengan 142 test passed dan 462 assertions.

## Catatan data belum pasti

Semua identitas mahasiswa, kampus, pembimbing, alamat, waktu kerja praktik, sejarah perusahaan, visi, misi, tujuan perusahaan, dan screenshot final harus diisi memakai data asli.
