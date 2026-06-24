# Prompt Revisi Laporan Magang Panemu dan Studi Kasus Optik Medio

Gunakan prompt ini untuk melanjutkan penyusunan laporan.

```text
Kamu adalah agentic AI yang bekerja di dalam repository proyek saya. Tugasmu adalah menyusun dan merevisi LAPORAN MAGANG dalam format LaTeX sesuai panduan resmi ITDA.

KONTEKS WAJIB:
1. Jenis laporan adalah LAPORAN MAGANG, bukan Laporan Kerja Praktik.
2. Tempat magang resmi adalah PT. Panemu Solusi Industri, Kulon Progo, Yogyakarta.
3. Optik Medio bukan instansi magang; posisikan hanya sebagai studi kasus penerapan.
4. Optik Medio adalah proyek studi kasus/proyek implementasi mandiri untuk menerapkan pengalaman magang.
5. Selama magang, pekerjaan berkaitan dengan pengembangan dan pemeliharaan sistem e-commerce, termasuk menerima atau memahami issue/bug, debugging, perbaikan fitur, pengujian pada server development, dan pemahaman alur production.
6. Saya banyak belajar dari sistem e-commerce yang ditangani perusahaan/klien, tetapi laporan tidak boleh membuka detail internal sistem tersebut.
7. Jangan memasukkan source code internal, nama database internal, endpoint rahasia, credential, token, secret key, webhook secret, data transaksi, data pelanggan, tiket issue internal, konfigurasi server, screenshot dashboard internal, atau informasi production.
8. Semua screenshot, diagram, dan pembahasan teknis rinci harus menggunakan proyek Optik Medio yang ada di repository ini.
9. Stack dan tools harus mengikuti repository aktual, bukan asumsi. Audit package.json, composer.json, route, controller, migration, store, repository frontend, dan resource admin.
10. Repository ini memakai batas utama:
    - frontend: Vue 3, Vite, TypeScript, Tailwind CSS, Pinia, Vue Router, Axios.
    - backend: Laravel 13, Laravel Sanctum, Filament, Xendit SDK, modul shipping/ongkos kirim, dan integrasi pihak ketiga terkait.
11. RajaOngkir boleh dibahas sebagai integrasi ekspedisi/ongkos kirim jika tersedia atau sebagai pola third-party yang diterapkan pada studi kasus. Jangan menampilkan API key.
12. Xendit boleh dibahas sebagai payment gateway jika tersedia pada repository. Jangan menampilkan secret key, webhook secret, token, payload transaksi asli, atau data pelanggan.

JUDUL YANG DISARANKAN:
Implementasi Sistem E-Commerce Optik Medio Berbasis Web sebagai Studi Kasus Penerapan Pengalaman Magang pada PT. Panemu Solusi Industri

STRUKTUR LAPORAN:
1. Halaman Sampul/Cover.
2. Halaman Judul.
3. Lembar Pengesahan kampus.
4. Lembar Pengesahan tempat magang.
5. Kata Pengantar.
6. Daftar Isi.
7. Daftar Gambar.
8. Daftar Tabel.
9. Daftar Lampiran jika ada.
10. Daftar Singkatan dan Lambang jika ada.
11. BAB I PENDAHULUAN.
12. BAB II PELAKSANAAN.
13. BAB III HASIL DAN PEMBAHASAN.
14. BAB IV PENUTUP.
15. Daftar Pustaka.
16. Lampiran.

ARAH ISI:
BAB I:
- Latar belakang menjelaskan pentingnya pengembangan dan pemeliharaan e-commerce, issue/bug, development server, production, dan alasan Optik Medio dipakai sebagai studi kasus aman.
- Tujuan menjelaskan pelaksanaan magang di Panemu dan penerapan pengalaman ke Optik Medio.
- Manfaat dibagi untuk mahasiswa, PT. Panemu Solusi Industri, kampus, dan studi kasus Optik Medio.
- Waktu dan tempat magang berisi PT. Panemu Solusi Industri, Kulon Progo, Yogyakarta.

BAB II:
- Profil PT. Panemu Solusi Industri. Jangan mengarang sejarah, visi, misi, struktur organisasi, atau alamat resmi. Pakai placeholder jika belum ada.
- Lingkup kegiatan magang: issue/bug, debugging, testing development, pemahaman production, dan penerapan ke Optik Medio.
- Teknologi dan tools berdasarkan repository aktual.
- Jadwal kegiatan mingguan dengan placeholder tanggal.

BAB III:
- Analisis kebutuhan sistem Optik Medio.
- Perancangan sistem: use case, flowchart checkout, DFD, arsitektur, ERD/struktur data.
- Implementasi frontend Vue, store Pinia, repository API, Laravel API, Filament admin.
- Integrasi ekspedisi/RajaOngkir jika tersedia.
- Integrasi pembayaran Xendit jika tersedia.
- Pengujian black-box.
- Kendala teknis hanya yang terbukti. Jika belum ada bukti, pakai placeholder.
- Hubungkan hasil dengan pengalaman magang di Panemu tanpa membuka data rahasia.

BAB IV:
- Kesimpulan menjawab tujuan magang dan penerapan studi kasus.
- Saran terkait pengembangan Optik Medio, dokumentasi, keamanan credential, pengujian sebelum production, dan validasi data resmi.

OUTPUT:
1. File LaTeX lengkap yang bisa dikompilasi.
2. references.bib.
3. checklist-final.md.
4. 00-audit-repository.md.
5. Prompt revisi ini jika diminta.

ATURAN PLACEHOLDER:
- Jangan mengarang NIM, tanggal, alamat lengkap, pembimbing, struktur organisasi, sejarah perusahaan, visi, misi, data legal, atau hasil pengujian.
- Gunakan placeholder seperti [ISI DATA RESMI PT. PANEMU SOLUSI INDUSTRI], [ISI NIM], [ISI TANGGAL], [TANDA GAMBAR: nama gambar].
- Semua placeholder harus dikumpulkan di checklist-final.md.

VALIDASI:
- Compile dengan XeLaTeX.
- Jika IEEEtran.bst tidak tersedia, boleh gunakan style numerik yang tersedia seperti ieeetr dan catat di checklist.
- Jangan mengubah source code aplikasi kecuali diminta.
```
