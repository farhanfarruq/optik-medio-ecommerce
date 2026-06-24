# Checklist Final Laporan Magang Optik Medio

## Status artefak

- [x] File LaTeX laporan Magang dibuat: `laporan_magang_optik_medio.tex`.
- [x] File bibliography dibuat: `references.bib`.
- [x] Audit repository dibuat: `00-audit-repository.md`.
- [x] Struktur laporan memakai format Magang ITDA empat bab.
- [x] BAB III memakai judul `HASIL DAN PEMBAHASAN`.
- [x] BAB IV memakai judul `PENUTUP`.
- [x] Source code aplikasi tidak diubah.
- [x] Konteks tempat magang direvisi menjadi PT. Panemu Solusi Industri, Kulon Progo, Yogyakarta.
- [x] Optik Medio diposisikan sebagai proyek studi kasus/penerapan pengalaman magang, bukan instansi magang.
- [x] Batasan kerahasiaan ditulis: tidak membuka source code internal, credential, endpoint, data pelanggan, data transaksi, konfigurasi server, screenshot dashboard internal, atau informasi production.
- [x] Kompilasi berhasil dengan `xelatex`, `bibtex`, `xelatex`, `xelatex`.
- [x] PDF hasil kompilasi dibuat: `laporan_magang_optik_medio.pdf`.
- [x] Times New Roman tersedia di sistem LaTeX.

## Placeholder identitas dan pengesahan

- [ ] `[ISI NIM]`
- [ ] `[ISI NAMA DOSEN PEMBIMBING]`
- [ ] `[ISI NIP DOSEN PEMBIMBING]`
- [ ] `[ISI NAMA KETUA PROGRAM STUDI]`
- [ ] `[ISI NIP KETUA PROGRAM STUDI]`
- [ ] `[ISI NAMA PEMBIMBING LAPANGAN]`
- [ ] `[ISI JABATAN PEMBIMBING LAPANGAN]`
- [ ] `[ISI TEMPAT PENGESAHAN]`
- [ ] `[ISI TANGGAL PENGESAHAN]`

## Placeholder data magang

- [ ] `[ISI TANGGAL MULAI MAGANG]`
- [ ] `[ISI TANGGAL SELESAI MAGANG]`
- [ ] `[ISI ALAMAT RESMI PT. PANEMU SOLUSI INDUSTRI]`
- [ ] `[ISI DIVISI ATAU PENEMPATAN]`
- [ ] `[ISI JAM KERJA MAGANG]`
- [ ] `[ISI NAMA PEMBIMBING LAPANGAN]`
- [ ] `[PERLU VALIDASI PEMBIMBING: rumusan judul final]`
- [ ] `[PERLU VALIDASI PEMBIMBING: subjudul teknologi final]`

## Placeholder profil perusahaan

- [ ] `[ISI SEJARAH RESMI PT. PANEMU SOLUSI INDUSTRI]`
- [ ] `[ISI VISI RESMI PT. PANEMU SOLUSI INDUSTRI]`
- [ ] `[ISI MISI RESMI PT. PANEMU SOLUSI INDUSTRI]`
- [ ] `[ISI STRUKTUR ORGANISASI RESMI PT. PANEMU SOLUSI INDUSTRI]`
- [ ] `[ISI PRODUK DAN LAYANAN RESMI PT. PANEMU SOLUSI INDUSTRI]`
- [ ] `[TANDA GAMBAR: logo resmi PT. Panemu Solusi Industri]`
- [ ] `[TANDA GAMBAR: struktur organisasi PT. Panemu Solusi Industri]`
- [ ] `[TANDA GAMBAR: foto lokasi magang jika diperlukan]`

## Placeholder aset sistem

- [ ] `[TANDA GAMBAR: arsitektur sistem Vue dan Laravel]`
- [ ] `[TANDA GAMBAR: flowchart pelanggan]`
- [ ] `[TANDA GAMBAR: flowchart admin]`
- [ ] `[TANDA GAMBAR: DFD level 0]`
- [ ] `[TANDA GAMBAR: DFD level 1]`
- [ ] `[TANDA GAMBAR: ERD atau struktur data]`
- [ ] `[TANDA GAMBAR: alur integrasi ekspedisi atau RajaOngkir]`
- [ ] `[TANDA GAMBAR: alur integrasi pembayaran Xendit]`
- [ ] `[TANDA GAMBAR: rancangan halaman katalog]`
- [ ] `[TANDA GAMBAR: rancangan halaman detail produk]`
- [ ] `[TANDA GAMBAR: rancangan halaman keranjang]`
- [ ] `[TANDA GAMBAR: rancangan halaman checkout]`
- [ ] `[TANDA GAMBAR: screenshot halaman katalog]`
- [ ] `[TANDA GAMBAR: screenshot halaman detail produk]`
- [ ] `[TANDA GAMBAR: screenshot halaman keranjang]`
- [ ] `[TANDA GAMBAR: screenshot halaman checkout]`
- [ ] `[TANDA GAMBAR: screenshot dashboard admin Filament]`

## Catatan format

- [x] Jika sistem LaTeX tidak memiliki Times New Roman, laporan memakai fallback TeX Gyre Termes.
- [x] Jalankan `xelatex`, `bibtex`, lalu `xelatex` dua kali dari folder `docs/magang`.
- [x] Style `IEEEtran.bst` tidak tersedia pada TeX Live lokal, sehingga laporan memakai `ieeetr` agar bibliography tetap numerik dan kompilasi berhasil.
- [ ] Semua screenshot harus berasal dari proyek Optik Medio, bukan sistem internal perusahaan atau klien.
- [ ] API key RajaOngkir, Xendit secret key, webhook secret, token, dan credential lain tidak boleh ditampilkan.
- [ ] Isi semua placeholder sebelum diserahkan ke pembimbing.
- [ ] Validasi judul dan cakupan teknologi dengan pembimbing karena prompt awal menyebut React/WhatsApp, sedangkan repository aktual memakai Vue/Laravel/Xendit.
