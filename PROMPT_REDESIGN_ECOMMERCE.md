# Prompt Redesign Total UI/UX Ecommerce Optik Medio

Gunakan prompt ini untuk meminta AI/frontend agent merombak seluruh tampilan website ecommerce tanpa menghapus fitur yang sudah ada.

## Master Prompt

Kamu adalah senior product designer, UI engineer, dan frontend architect untuk website ecommerce optik bernama Optik Medio. Tugas kamu adalah mengubah seluruh struktur tampilan website ini menjadi ecommerce modern yang berbeda dari template ecommerce umum, tetap professional, mudah digunakan, cepat dipahami, elegan, responsive, accessible, dan tetap mempertahankan semua fitur, route, data flow, store, API integration, form validation, auth flow, cart flow, checkout flow, product browsing, product detail, search/filter, wishlist jika ada, user account, admin/customer flow jika ada, serta semua behavior yang sudah berjalan.

Jangan menghapus fitur, komponen fungsional, route, state management, test, atau integrasi yang sudah ada. Kamu hanya boleh mengubah struktur layout, hierarchy visual, komposisi screen, styling, spacing, typography, interaction states, responsive behavior, dan component presentation. Jika ada fitur yang terlihat buruk secara UI, redesign fitur tersebut tanpa menghilangkan capability-nya.

Gunakan referensi kualitas dari:
- Apple Store: product focus, clean hierarchy, premium whitespace.
- Nike: bold editorial commerce, dynamic product storytelling.
- Aesop: sophisticated typography, calm luxury, strong content rhythm.
- SSENSE: fashion-forward grid, editorial product discovery.
- Farfetch: premium catalog experience, strong filtering and browsing.
- Bang & Olufsen: high-end product presentation, immersive product detail.
- Shopify flagship stores: practical conversion flow, reliable ecommerce patterns.

Buat hasil akhir tidak terlihat seperti clone dari salah satu referensi. Ambil prinsipnya, bukan menyalin visualnya. Arah visual: premium optical commerce, clinical precision, lifestyle editorial, curated product discovery, conversion-focused checkout, dan interface yang terasa unik tetapi tetap familiar untuk pembeli.

## Global Design Direction

Buat design system baru:
- Visual tone: premium, clean, confident, slightly editorial, not generic marketplace.
- Layout: gunakan kombinasi asymmetric product grids, editorial feature bands, sticky commerce controls, dense but readable catalog tools, dan product-focused detail pages.
- Typography: gunakan hierarchy yang tajam; headline elegan, body sangat mudah dibaca, label dan price jelas.
- Color: hindari tampilan satu warna monoton. Gunakan base netral professional dengan aksen brand yang kuat, plus status colors untuk promo, error, success, stock, dan checkout.
- Components: semua button, input, card, tab, filter, badge, modal, toast, navbar, footer, product card, cart item, checkout step, auth shell, dan account panels harus konsisten.
- Interaction: hover, focus, active, selected, disabled, loading, empty, error, success state wajib tersedia.
- Responsiveness: desain harus bagus di 375px, 768px, 1024px, 1440px.
- Accessibility: semantic HTML, keyboard navigation, visible focus state, contrast baik, label form jelas, aria hanya saat diperlukan.

## Hard Rules

1. Jangan hapus fitur atau flow yang sudah ada.
2. Jangan mengganti business logic kecuali diperlukan untuk menyambungkan UI baru.
3. Jangan membuat landing page marketing kosong; ecommerce experience harus langsung terasa dari layar pertama.
4. Jangan menggunakan emoji sebagai icon UI; gunakan icon library yang sudah ada atau Lucide/Heroicons jika tersedia.
5. Jangan memakai decorative gradient blob/orb/bokeh sebagai identitas utama.
6. Jangan membuat card di dalam card.
7. Jangan membuat hover yang menggeser layout.
8. Jangan membuat teks terpotong, overlap, atau keluar container.
9. Jangan mengubah API contract, store shape, route name, atau test expectation tanpa alasan kuat.
10. Setiap perubahan harus bisa diverifikasi secara visual dan fungsional.

## Phase 1: Audit UI dan Fitur Existing

Audit seluruh app sebelum redesign.

Output yang harus dibuat:
- Daftar route/page yang ada.
- Daftar fitur per page.
- Daftar komponen layout utama.
- Daftar state penting: loading, empty, error, success, disabled, authenticated, unauthenticated.
- Daftar bagian UI yang boleh diubah total.
- Daftar bagian logic yang tidak boleh disentuh.

Instruksi:
Telusuri struktur project, views, components, stores, router, tests, dan style system. Pahami alur user dari masuk website sampai checkout. Jangan mulai redesign sebelum mapping fitur selesai.

## Phase 2: Design System Baru

Buat design system bernama "Optik Medio Editorial Commerce System".

Harus mencakup:
- Color tokens: background, surface, elevated, text, muted, border, primary, secondary, accent, danger, success, warning, info.
- Typography scale: display, h1, h2, h3, body, small, label, price, caption.
- Spacing scale: section, container, grid gap, component padding.
- Radius scale: kecil dan professional, default maksimal 8px kecuali avatar/image khusus.
- Shadow/elevation: halus, tidak berlebihan.
- Motion: 120-220ms, ease-out, no layout shift.
- Component rules: button, input, select, checkbox, radio, tabs, modal, drawer, product card, price block, stock badge, filter chip, cart row, checkout step.

Arahan visual:
Gabungkan premium retail, optical clinic precision, dan editorial lifestyle commerce. Produk harus menjadi pusat visual. UI harus terasa curated, bukan marketplace generik.

## Phase 3: App Shell dan Navigation

Redesign struktur global:
- Header harus mendukung browsing, search, cart, auth/account, dan responsive mobile nav.
- Navbar desktop harus bersih, sticky bila membantu conversion.
- Mobile nav harus cepat, jelas, dan tidak memenuhi layar tanpa alasan.
- Footer harus berisi link penting, trust signals, kontak, dan brand information dengan layout rapi.
- AuthShell harus terlihat premium dan meyakinkan, bukan form standar.

Referensi:
Apple untuk clarity, SSENSE untuk editorial restraint, Shopify stores untuk commerce utility.

Pastikan:
- Search mudah ditemukan.
- Cart selalu jelas.
- Account/auth state jelas.
- Tidak ada navigasi yang hilang di mobile.

## Phase 4: Homepage / Storefront

Ubah homepage menjadi ecommerce storefront yang langsung menjual.

Struktur yang disarankan:
- First viewport: brand/product signal kuat, hero commerce dengan produk atau kategori utama, CTA jelas.
- Quick category entry: frame, sunglasses, lens, promo, new arrival, best seller.
- Editorial product discovery: koleksi berdasarkan kebutuhan, gaya, atau aktivitas.
- Featured products: grid tidak monoton, tetap scanable.
- Trust band: original product, professional optics, warranty, store/service information.
- Education strip: tips memilih frame/lensa tanpa terasa seperti blog panjang.

Jangan buat hero template generik. Buat komposisi unik: split editorial grid, horizontal product rail, floating commerce summary, atau visual merchandising layout yang tetap responsive.

## Phase 5: Product Listing / Catalog

Redesign catalog agar kuat untuk browsing dan conversion.

Harus ada:
- Filter/sort yang usable di desktop dan mobile.
- Product grid dengan hierarchy: image, brand/name, price, discount, availability, rating jika ada.
- Quick add/quick view jika fitur sudah ada.
- Empty state yang membantu user lanjut mencari.
- Loading skeleton.
- Mobile filter drawer.
- Sticky sorting/filter summary jika cocok.

Visual direction:
Gunakan grid editorial seperti SSENSE/Farfetch, tetapi lebih hangat dan cocok untuk optik. Produk harus mudah dibandingkan.

## Phase 6: Product Detail Page

Redesign PDP menjadi pengalaman premium.

Harus ada:
- Gallery produk besar, jelas, responsive.
- Product title, price, stock, variant, quantity, CTA add to cart.
- Detail lensa/frame/specification yang mudah discan.
- Trust/payment/shipping/warranty info.
- Related products atau recommended collection jika sudah ada.
- Sticky add-to-cart bar di mobile jika membantu.

Referensi:
Bang & Olufsen untuk product presentation, Apple Store untuk clarity, Farfetch untuk commerce detail.

## Phase 7: Cart dan Checkout

Redesign cart dan checkout dengan fokus conversion.

Cart:
- Item row jelas.
- Quantity control mudah.
- Price summary transparan.
- Promo/discount jika ada.
- Empty cart tetap mengarahkan ke belanja.

Checkout:
- Stepper atau section flow jelas.
- Form rapi dan accessible.
- Order summary sticky di desktop.
- Payment/shipping state jelas.
- Error validation dekat input.
- CTA final kuat dan aman.

Jangan membuat checkout terlalu eksperimental. Bagian ini harus familiar, cepat, dan minim friksi.

## Phase 8: Auth, Account, dan Supporting Pages

Redesign:
- Login/register/reset password.
- Profile/account page.
- Order history jika ada.
- Address management jika ada.
- Static/support pages jika ada.

Auth visual:
Premium, trust-building, minimal distraction. Gunakan layout yang punya brand character, bukan card form biasa. Tetap jaga form labels, password visibility, validation, loading state.

## Phase 9: Component Refactor

Setelah page redesign, rapikan komponen:
- Extract reusable components hanya bila dipakai di beberapa tempat.
- Samakan button/input/card/badge styles.
- Hapus styling duplikat yang membuat UI tidak konsisten.
- Pastikan props dan events lama tetap kompatibel.
- Jangan refactor logic besar jika tidak diperlukan.

Komponen prioritas:
- ProductCard
- ProductGrid
- AppHeader
- AppFooter
- AuthShell
- CartItem
- CheckoutSummary
- FilterPanel
- EmptyState
- LoadingSkeleton

## Phase 10: QA Visual, Responsive, dan Functional

Wajib cek:
- 375px mobile
- 768px tablet
- 1024px laptop
- 1440px desktop

Checklist:
- Tidak ada teks overlap.
- Tidak ada button text overflow.
- Hover/focus tidak menggeser layout.
- Keyboard navigation bisa dipakai.
- Semua route lama masih ada.
- Semua fitur lama masih berjalan.
- Cart flow berjalan.
- Auth flow berjalan.
- Checkout flow berjalan.
- Tests existing tetap pass atau diperbarui hanya jika UI expectation valid berubah.


## Mobile-First Addendum

Gunakan bagian ini untuk memastikan seluruh redesign tetap kuat di mobile. Website ecommerce Optik Medio harus terasa seperti mobile commerce app yang ringan, cepat, dan premium, bukan versi desktop yang diperkecil.

### Mobile Design Principles

Prioritas mobile:
- Produk harus langsung terlihat dan mudah dibeli.
- Navigasi harus ringkas, jelas, dan bisa dipakai satu tangan.
- Search, filter, cart, dan checkout harus selalu mudah ditemukan.
- Setiap tap target minimal 44px.
- Text tidak boleh terlalu kecil, overlap, truncate sembarangan, atau keluar container.
- Sticky element tidak boleh menutupi CTA, form field, product image, atau checkout summary.
- Gunakan bottom sheet/drawer untuk filter, sort, cart preview, dan menu jika lebih nyaman daripada full page.

### Mobile Header dan Navigation

Redesign mobile header:
- Tinggi compact, brand tetap terlihat.
- Icon search, cart, account/menu jelas.
- Cart count selalu terlihat jika ada item.
- Mobile menu menggunakan drawer atau bottom sheet yang rapi.
- Search mobile boleh menjadi full-screen search overlay dengan recent/popular/category shortcut jika data tersedia.

Jangan:
- Menumpuk terlalu banyak link di header.
- Membuat hamburger menu tanpa akses cepat ke search/cart.
- Membuat sticky header terlalu tinggi.

### Mobile Homepage

Homepage mobile harus langsung terasa ecommerce.

Struktur yang disarankan:
- Hero pendek dan product-focused, bukan banner tinggi kosong.
- CTA utama terlihat tanpa scroll terlalu jauh.
- Category chips horizontal scroll.
- Featured products dalam 2-column grid atau product rail.
- Promo/trust band compact.
- Editorial section dibuat ringkas, tidak seperti artikel panjang.

Gunakan visual yang kuat tetapi hemat ruang. Pastikan first viewport menampilkan brand/product signal, CTA, dan hint produk/kategori.

### Mobile Catalog

Catalog mobile harus nyaman untuk browsing cepat.

Harus ada:
- Sticky compact bar untuk filter dan sort.
- Filter dalam bottom sheet/drawer.
- Applied filters tampil sebagai chips yang bisa dihapus.
- Product grid stabil: 2 kolom untuk mobile normal, 1 kolom hanya jika konten produk kompleks.
- Product card tidak terlalu tinggi.
- Price dan CTA tetap mudah discan.
- Loading skeleton sesuai ukuran card.
- Empty state memberi tombol kembali ke kategori/search.

Jangan:
- Menaruh filter panjang di atas produk.
- Membuat image ratio berubah-ubah sampai grid tidak rapi.
- Menggunakan hover-only interaction untuk fitur penting.

### Mobile Product Detail

PDP mobile harus conversion-focused.

Harus ada:
- Product gallery swipeable.
- Image ratio stabil.
- Title, price, stock, variant, dan CTA dalam urutan yang jelas.
- Sticky add-to-cart bar di bawah jika CTA utama mudah hilang saat scroll.
- Accordion untuk specification, shipping, warranty, dan care detail.
- Related products dalam horizontal rail atau 2-column grid.

CTA sticky:
- Tidak boleh menutupi konten.
- Harus memperhitungkan safe area.
- Harus punya state disabled/loading/success.

### Mobile Cart dan Checkout

Cart mobile:
- Item tampil sebagai row/card ringkas.
- Quantity stepper mudah disentuh.
- Remove action jelas tetapi tidak mudah tersentuh tidak sengaja.
- Order summary ringkas.
- CTA checkout sticky di bawah jika cart panjang.

Checkout mobile:
- Gunakan single-column flow.
- Section bertahap: contact, shipping, payment, review.
- Label input selalu terlihat.
- Error message dekat field.
- Order summary bisa collapsible.
- CTA final sticky hanya jika tidak mengganggu form.
- Keyboard mobile tidak boleh membuat field penting tertutup.

Jangan membuat checkout terlalu eksperimental di mobile. Familiar lebih penting daripada unik untuk bagian pembayaran.

### Mobile Auth dan Account

Auth mobile:
- Form harus cepat diisi.
- Input tinggi nyaman.
- Password visibility toggle.
- Error dan loading state jelas.
- Brand visual cukup, tidak mengorbankan form.

Account mobile:
- Gunakan list navigation atau segmented tabs.
- Order history mudah discan.
- Address dan profile form single-column.

### Mobile QA Checklist

Wajib verifikasi:
- 320px: tidak pecah untuk device kecil.
- 375px: baseline mobile utama.
- 390px/414px: iPhone common widths.
- 768px: tablet portrait.
- Touch target minimal 44px.
- Bottom sticky CTA tidak menutupi konten.
- Header/search/cart tetap bisa diakses.
- Filter drawer bisa dibuka, diterapkan, dan ditutup.
- Product card tetap rapi saat nama produk panjang.
- Checkout form tetap bisa dipakai saat keyboard muncul.
- Tidak ada horizontal scroll kecuali carousel/chips yang memang disengaja.

## Prompt Eksekusi Mobile

Gunakan prompt ini saat ingin fokus mobile:

```text
Lanjutkan redesign mobile-first untuk Optik Medio berdasarkan PROMPT_REDESIGN_ECOMMERCE.md.

Fokus viewport 320px, 375px, 390px, 414px, dan 768px.
Pertahankan seluruh fitur, route, store, API, validation, dan behavior existing.
Ubah layout mobile agar terasa seperti premium mobile ecommerce app: cepat, jelas, menarik, conversion-focused, dan mudah dipakai satu tangan.

Prioritaskan:
1. Mobile header, search, cart, dan navigation.
2. Homepage mobile storefront.
3. Catalog mobile dengan filter/sort bottom sheet.
4. Product detail mobile dengan gallery dan sticky add-to-cart.
5. Cart dan checkout mobile yang familiar dan minim friksi.
6. Auth/account mobile yang rapi dan accessible.

Sebelum edit, audit komponen terkait. Setelah edit, verifikasi responsive behavior dan pastikan tidak ada fitur existing yang hilang.
```

## Output Yang Diminta Dari Agent

Untuk setiap phase, berikan:
- File yang diubah.
- Ringkasan perubahan 1-2 kalimat.
- Fitur yang dipertahankan.
- Risiko atau tradeoff.
- Cara verifikasi.

Jangan berikan teori panjang. Implementasikan phase demi phase dengan commit kecil atau perubahan terstruktur. Jika menemukan fitur yang tidak jelas, baca kode terlebih dahulu dan pertahankan behavior existing.

## Prompt Eksekusi Singkat Per Phase

Gunakan format ini setiap kali menjalankan phase:

```text
Lanjutkan Phase [nomor]: [nama phase] untuk project Optik Medio.

Ikuti PROMPT_REDESIGN_ECOMMERCE.md sebagai sumber instruksi utama.
Pertahankan semua fitur, route, store, API, validation, dan behavior existing.
Ubah hanya struktur visual, layout, styling, interaction states, dan component presentation kecuali perubahan logic kecil diperlukan untuk menyambungkan UI.

Sebelum edit:
1. Audit file terkait.
2. Jelaskan rencana singkat.
3. Implementasikan perubahan.
4. Jalankan pengecekan relevan.
5. Laporkan file berubah, fitur dipertahankan, dan cara verifikasi.
```

