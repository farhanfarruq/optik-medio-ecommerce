# Optik Medio Frontend Redesign Blueprint

Dokumen ini hanya untuk frontend `medio-fe`. Tujuannya: menjadi brief presisi untuk mengubah seluruh tampilan menjadi ecommerce optik premium, elegan, mudah dipakai, dan tidak terasa seperti template AI.

## 1. Arah Produk

Optik Medio harus terasa seperti premium optical commerce: butik optik modern, produk jelas, proses beli aman, dan fitur teknis seperti resep, virtual try-on, appointment, garansi, referral, loyalty, dan komplain tetap mudah dipahami.

Target rasa visual:
- Premium, editorial, calm, clinical enough untuk optik, tetapi tetap hangat dan komersial.
- Bukan landing page generik. Halaman pertama harus langsung terasa sebagai ecommerce yang bisa dipakai.
- Prioritas utama: browsing produk, memilih frame/lensa, checkout, tracking, layanan purna jual.
- Mobile-first karena alur belanja, login OTP, checkout, dan tracking kemungkinan sering dipakai di ponsel.

## 2. Design System Baru

### Palette

Gunakan palette multi-netral, bukan satu warna dominan.

- Ink: `#15120E` untuk teks utama dan header gelap.
- Graphite: `#2B2926` untuk surface gelap, nav, footer.
- Ivory: `#F7F3EC` untuk background hangat.
- Porcelain: `#FCFAF6` untuk card dan form.
- Mist gray: `#E7E1D8` untuk border halus.
- Warm taupe: `#B8A999` untuk secondary surface.
- Antique gold: `#B88A44` untuk CTA, badge premium, active tab.
- Deep olive: `#56604B` untuk status positif dan trust cue.
- Rose clay: `#A65F55` untuk warning/return/error yang tidak norak.
- Optical blue: `#3F6F8F` hanya untuk informasi klinis, resep, tracking, dan link teknis.

Rules:
- CTA utama gold di atas surface terang atau ink di atas ivory.
- Gold jangan dipakai sebagai background besar. Pakai untuk aksen, border active, icon kecil, price highlight.
- Hindari cyan/green lama dari `design-system/optik-medio/MASTER.md`; `style.css` sudah lebih dekat ke arah premium hitam-gold tetapi masih perlu dirapikan.
- Hindari gradient ungu/biru, orb decoration, background bokeh, dan card bertumpuk.

### Typography

Rekomendasi:
- Heading: `Cormorant Garamond` atau `Cormorant`, weight 500-700.
- Body/UI: `Montserrat` atau `Inter`, weight 400-600.
- Angka harga dan order code: body font tabular style.

Rules:
- Heading serif hanya untuk hero, page title, section title editorial.
- UI padat seperti checkout, order detail, profile memakai body sans yang rapih.
- Jangan pakai font besar berlebihan di card kecil.
- Letter spacing 0 atau sedikit positif untuk label uppercase, tidak negatif.

### Layout

- Max content width: 1180-1240px.
- Desktop ecommerce grid: sidebar filter 280px + product grid fleksibel.
- Mobile: sticky search/filter controls, product card 2 kolom jika cukup, 1 kolom untuk item checkout/order.
- Section full-width band, bukan floating card besar.
- Card radius 8px; modal/drawer boleh 12px.
- Border tipis dan shadow sangat ringan. Premium terasa dari spacing, hierarchy, gambar, dan copy, bukan efek berat.

### Component Rules

- Semua tombol icon memakai Material Symbols yang sudah dipakai atau ganti konsisten ke icon library bila nanti ditambah.
- Semua klik harus punya hover, focus-visible, disabled, loading.
- Product card wajib punya image ratio stabil, badge promo, wishlist, compare, brand, nama, price, dan CTA jelas.
- Form field wajib punya label tetap, helper/error text, focus ring, dan spacing seragam.
- Empty state harus punya aksi berikutnya, bukan sekadar teks.
- Skeleton/loading harus meniru layout final agar tidak shift.
- Bottom tab mobile tetap ada, tetapi nav desktop harus terasa seperti ecommerce premium, bukan app demo.

## 3. Struktur Frontend Saat Ini

Stack:
- Vue 3 + Vite + TypeScript.
- Tailwind CSS.
- Pinia untuk auth, cart, wishlist, compare.
- Repository layer untuk product, order, shipping, master data, complaint, review, setting.

Layout global:
- `DefaultLayout.vue`: wrapper utama, menampilkan top nav, footer, bottom tab, router view. Ada pengecualian auth/full hero page.
- `TopNavBar.vue`: desktop/mobile nav, search overlay, suggestion produk/kategori, recent search, cart badge, user menu.
- `BottomTabBar.vue`: nav mobile ke home/product/cart/profile.
- `Footer.vue`: brand, kontak, navigasi, store location.
- `PageHero.vue`: breadcrumb/hero reusable.
- `PromoBanner.vue`: banner promo aktif dari cart store.
- `ToastContainer.vue`: notification stack.

## 4. Rincian Semua Halaman

### Home / Products / Products By Category

File: `src/views/Product.vue`

Routes:
- `/`
- `/products`
- `/products/category/:slug`

Isi saat ini:
- Hero/banner carousel dari `BannerRepository`.
- Category chips/grid dengan count produk.
- Product list dari `ProductRepository`.
- Lens showcase products.
- Brand filter.
- Filter metadata.
- Promo filter.
- Wishlist, cart, compare action.
- Load more.
- Store status dari `SettingRepository`.

Redesign:
- Above fold: premium commerce hero yang langsung mengarahkan ke produk, bukan hero kosong.
- Search/filter bar sticky: search, category, brand, price, promo, sort, frame/lens type.
- Category rail horizontal mobile, grid editorial desktop.
- Product cards 2 kolom mobile, 3-4 desktop, image ratio konsisten.
- Promo badge kecil, bukan banner ramai.
- Empty state untuk kategori/search tanpa hasil dengan CTA reset filter.
- Tambahkan trust strip: garansi optik, konsultasi, virtual try-on, pickup store.

### Product Detail

File: `src/views/ProductDetail.vue`

Route:
- `/products/:slug`

Isi saat ini:
- Image gallery, active image, carousel arrows.
- Product badges: best seller, promo.
- Variant colors/sizes.
- Frame size/profile table.
- Prescription section lengkap: PD tunggal/ganda, OD/OS, resep tersimpan, upload.
- Compatible lenses.
- Recommendations.
- Reviews.
- Add to cart, wishlist, buy lens only, choose frame first.
- Appointment link.
- Stores: cart, wishlist, auth.
- Repositories: product, review, optical, prescription.

Redesign:
- Desktop split: gallery kiri sticky, purchase panel kanan sticky.
- Mobile: image carousel full width, price/CTA sticky bottom.
- Tabs/accordions: Detail, Ukuran Frame, Resep, Ulasan, Rekomendasi.
- Prescription UI harus terasa klinis dan tenang: table compact, helper text jelas, validation visible.
- CTA hierarchy: `Tambah ke Keranjang`, `Beli Sekarang`, secondary `Tambah Wishlist`, `Bandingkan`.
- Lens-only flow diberi modal/drawer pilihan agar tidak membingungkan.
- Review dibuat credible: rating summary, verified purchase badge, empty state.

### Product Compare

File: `src/views/ProductCompare.vue`

Route:
- `/compare`

Isi saat ini:
- Compare store items.
- Load detail produk.
- Table attribute comparison.
- Empty/minimal state saat kurang dari 2 produk.
- Actions: clear, add product, view product, remove.

Redesign:
- Sticky first column untuk atribut.
- Product header sticky saat scroll horizontal.
- Highlight difference rows.
- Empty state premium: pilih minimal 2 produk, CTA ke katalog.
- Mobile: comparison cards carousel atau table horizontal dengan affordance scroll.

### Cart

File: `src/views/CartView.vue`

Route:
- `/cart`

Isi saat ini:
- Grouped cart: frame + attached lens.
- Discount calculation.
- Promo selection.
- Store closed notice.
- Empty cart.
- Checkout button.
- Uses cart store, master data, setting/data repository.

Redesign:
- Cart item sebagai order-review list, bukan card dekoratif.
- Frame dan lens dibuat bundle visual yang jelas.
- Quantity/action/delete mudah diakses.
- Promo module compact dengan applied state.
- Order summary sticky desktop dan fixed bottom mobile.
- Store closed notice sebagai alert slim dengan reason dan jadwal buka.

### Checkout

File: `src/views/checkout/CheckoutView.vue`

Route:
- `/checkout`

Isi saat ini:
- Fulfillment delivery/store pickup.
- Address form dan user addresses.
- Province/city/district/postal code.
- Promo exclusive dan discount code.
- Loyalty points.
- Shipping results.
- Payment methods, bank accounts.
- Order summary.
- Pay now/pay later.
- Store status.
- Uses cart, auth, shipping, order, master data.

Redesign:
- Checkout stepper jelas: Fulfillment, Address/Pickup, Shipping, Payment, Review.
- Desktop 2 kolom: form kiri, summary kanan sticky.
- Mobile: accordion step, summary collapsed sticky.
- Field labels konsisten; validation tidak hanya warna merah.
- Promo/loyalty jadi satu `Savings` module agar tidak tersebar.
- Payment method cards dengan logo/icon, status selected, fee/notes.
- Bank transfer detail jangan muncul sebelum metode dipilih.
- Disabled checkout button harus menjelaskan alasan.

### Waiting Payment

File: `src/views/checkout/WaitingPayment.vue`

Route:
- `/waiting-payment/:id`

Isi saat ini:
- Load order.
- Xendit/COD/manual transfer states.
- Polling payment status.
- Expired state.
- Upload proof.
- Copy payment text.
- Buttons: back to cart, check status.

Redesign:
- Payment status hero compact: unpaid, pending, expired, paid.
- Countdown/instruction panel.
- Payment detail card with copy buttons per field.
- Upload proof as clean dropzone.
- Timeline next steps.
- Strong CTA: cek status, lihat pesanan, hubungi CS.

### Login

File: `src/views/Login.vue`

Route:
- `/login`

Isi saat ini:
- Email/password.
- Error/loading state.
- Redirect after login.
- Link register.

Redesign:
- Auth layout split desktop: brand/product imagery left, form right.
- Mobile single panel.
- Show/hide password.
- Inline error and forgot password placeholder if supported.
- CTA clear: Masuk, daftar akun.
- Trust copy kecil: pesanan, resep, loyalty tersimpan aman.

### Register

File: `src/views/Register.vue`

Route:
- `/register`

Isi saat ini:
- Register step and OTP step.
- Name, email, phone, password, confirmation, referral code.
- Optional affiliate checkbox.
- OTP input handling, resend OTP.

Redesign:
- Two-step progress visible.
- OTP cells polished with paste support visual.
- Affiliate option as secondary expandable panel, not heavy checkbox block.
- Referral code has applied/pending state.
- Password helper requirements.

### Profile Multi-Section

File: `src/views/Profile.vue`

Routes:
- `/profile`
- `/addresses`
- `/prescriptions`
- `/orders`
- `/affiliate`
- `/wishlist`
- `/warranty` maps to `Profile.vue`

Isi saat ini:
- Account info.
- Loyalty history and level.
- Addresses.
- Prescriptions create/upload/default.
- Orders with load more.
- Wishlist sharing.
- Affiliate dashboard/apply.
- Warranty embedded.
- Logout.
- Uses auth, wishlist, order, shipping, affiliate, prescription.

Redesign:
- Account shell: sidebar desktop, segmented tabs mobile.
- Profile overview top with membership/points/order status summary.
- Address cards with default badge, edit/delete clear.
- Prescription cards with OD/OS summary and default state.
- Orders list with status chips and primary action.
- Wishlist grid reusable product cards.
- Affiliate section has metrics, payout info, referral link, status.
- Warranty embedded follows warranty page styling.

### Order Detail

File: `src/views/OrderDetail.vue`

Route:
- `/orders/:id`

Isi saat ini:
- Order status timeline.
- Items.
- Activity logs.
- Cost breakdown.
- Return/refund.
- Shipping protection claim.
- Review form per item.
- Confirm delivery, booking pickup.
- Uses order, return, review, complaint repositories.

Redesign:
- Header: order number, status, date, total, primary next action.
- Timeline horizontal desktop, vertical mobile.
- Item cards include image, variant, lens/prescription relation.
- Cost breakdown as receipt.
- Review form drawer/modal to reduce page height.
- Return/complaint/protection separated into support panel.
- Copy order code/tracking code with feedback.

### Tracking

File: `src/views/Tracking.vue`

Route:
- `/tracking/:id`

Isi saat ini:
- Progress steps.
- Timeline logs.
- Tracking number.
- Confirm delivery.
- Complaint link.
- COD note.

Redesign:
- Delivery status hero with courier/tracking number.
- Timeline as readable logistics feed.
- Confirm delivery as sticky CTA only when allowed.
- Complaint CTA secondary with calm warning copy.
- Empty timeline state with support link.

### Complaint Form

File: `src/views/Complaint.vue`

Route:
- `/complaints/new`

Isi saat ini:
- Complaint or shipping protection claim mode.
- Order selection.
- Contact phone.
- Detail problem.
- Attachment.
- Submit.

Redesign:
- Form wizard: select order, issue type, detail, attachment, review.
- Attachment dropzone.
- Explain SLA and required evidence.
- If from query order id, prefill order and show compact order card.

### Complaint Detail

File: `src/views/ComplaintDetail.vue`

Route:
- `/complaints/:id`

Isi saat ini:
- Complaint detail.
- Related order.
- Resolved date.
- Contact phone.
- Attachment.
- Admin notes.
- Handler.

Redesign:
- Status header with complaint id and resolution state.
- Detail sections: order, issue, evidence, response.
- Admin notes styled as official response.
- Back action and support escalation.

### Warranty / Service

File: `src/views/WarrantyPage.vue`

Route:
- `/warranty` via `Profile.vue`, component also supports embedded.

Isi saat ini:
- Login gate.
- Tabs: warranties, claims, new claim.
- Warranty list.
- Claim list and detail.
- New claim form with warranty, type, description, image upload.

Redesign:
- Warranty cards with product, warranty number, expiry, eligibility.
- Claim timeline/detail accordion.
- Claim form with clear evidence upload and type selector.
- Login gate premium, not empty white page.

### Shared Wishlist

File: `src/views/SharedWishlist.vue`

Route:
- `/wishlist/shared/:token`

Isi saat ini:
- Public wishlist from token.
- Invalid link.
- Empty wishlist.
- Product grid.

Redesign:
- Public landing feel: owner/context if available, product grid, CTA to shop.
- Invalid and empty states with helpful next action.
- Product cards must match catalog.

### Appointment

File: `src/views/AppointmentPage.vue`

Route:
- `/appointment`

Isi saat ini:
- Branch selection.
- Service options.
- Availability slots.
- Submit appointment.
- My appointments list.
- Detail modal.
- Login gate for my appointments.

Redesign:
- Booking flow: branch, service, date/time, contact notes, confirmation.
- Branch cards include address, phone, map link.
- Slots as segmented chips with disabled states.
- My appointment cards with status and cancel/detail.
- Prefill from query should be visually confirmed.

### Face Shape Quiz

File: `src/views/FaceShapeQuiz.vue`

Route:
- `/face-shape-quiz`

Isi saat ini:
- Preference choices: face shape, style, size, budget.
- Product recommendations.
- Loading and empty state.
- CTA open all products.

Redesign:
- Interactive quiz with progress and selection cards.
- Visual face shape icons/illustrations, but refined line style.
- Result page with explanation and recommended products.
- CTA to save preferences or browse filtered catalog.

### Virtual Try-On

File: `src/views/VirtualTryOn.vue`

Route:
- `/virtual-try-on`

Isi saat ini:
- Upload face photo.
- Search frame suggestions.
- Select product.
- Transform controls: scale, x, y, rotation.
- Save/download preview.
- Saved previews.
- Mobile sheet controls.

Redesign:
- Tool interface, not marketing page.
- Workspace: photo canvas dominant, product selector side/bottom.
- Controls use sliders with numeric affordance.
- Saved preview rail.
- Privacy copy for uploaded image.
- Empty state instructs upload without long text blocks.

### Category Landing

File: `src/views/CategoryLanding.vue`

Route:
- `/c/:slug`

Isi saat ini:
- Category detail and description.
- Product list.
- Loading state.
- SEO breadcrumb JSON-LD.

Redesign:
- Editorial category header with category story and compact filters.
- Product grid same as catalog.
- Related categories/brands below.

### Brand Landing

File: `src/views/BrandLanding.vue`

Route:
- `/brand/:brand`

Isi saat ini:
- Brand product listing.
- Loading state.

Redesign:
- Brand header with logo/wordmark placeholder, count, value proposition.
- Product grid and sort/filter.
- Brand trust/product highlight module.

### Loyalty

File: `src/views/LoyaltyPage.vue`

Route:
- `/loyalty`

Isi saat ini:
- How it works.
- Membership levels.
- Point history.
- Current/next level.
- Login-aware history.

Redesign:
- Membership dashboard hero if logged in.
- Level ladder with current marker.
- How it works compact, icon-based.
- Point history table/list.
- CTA to shop or login.

### Referral

File: `src/views/ReferralPage.vue`

Routes:
- `/referral`
- `/referral/:code`

Isi saat ini:
- How referral works.
- Apply code.
- My code if logged in.
- Copy code/link.
- Recent uses.

Redesign:
- Separate states: public referral landing, logged-in referral dashboard.
- Code card with copy actions.
- Benefits grid and terms.
- Recent use list as compact activity feed.

### Blog List

File: `src/views/blog/ArticleList.vue`

Route:
- `/blog`

Isi saat ini:
- Fetch articles.
- Search.
- Pagination.
- Loading skeleton.
- Empty/error states.
- Article cards with featured image/tags.

Redesign:
- Editorial grid with featured first article.
- Search and category/tag filter.
- Article cards refined, image ratio stable.
- Pagination elegant with clear current page.

### Article Detail

File: `src/views/blog/ArticleDetail.vue`

Route:
- `/blog/:slug`

Isi saat ini:
- Fetch article.
- Featured image.
- Tags, author.
- Related articles.
- Loading skeleton and not found.

Redesign:
- Article page with readable width 720-780px.
- Strong metadata hierarchy.
- Related articles grid below.
- CTA to products/quiz where relevant.

### FAQ

File: `src/views/legal/FAQView.vue`

Route:
- `/faq`

Isi saat ini:
- FAQ from settings.
- Categories.
- Accordion.
- Loading/empty state.
- Contact CTA.

Redesign:
- Searchable FAQ with category pills.
- Accordion with strong focus/keyboard state.
- Contact support card.

### Privacy

File: `src/views/legal/PrivacyView.vue`

Route:
- `/privacy`

Isi saat ini:
- Static policy sections: data collected, use, security, updates.

Redesign:
- Legal layout with table of contents desktop.
- Readable text width, clear section rhythm.
- Last updated placeholder if available.

### Terms

File: `src/views/legal/TermsView.vue`

Route:
- `/terms`

Isi saat ini:
- Static terms: ordering, payment, shipping/returns.

Redesign:
- Same legal template as privacy.
- Highlight important ecommerce terms in callout blocks.

## 5. Fase Implementasi

### Phase 1: Foundation

Files:
- [x] `src/style.css`
- [x] `tailwind.config.*`
- [x] `components/layout/*`
- [x] reusable product card / form / button components if added.

Scope:
- [x] Token warna, typography, spacing, radius, shadow.
- [x] Top nav, bottom tab, footer, toast, promo banner.
- [x] Product card base, button variants, input variants, status badge.

Acceptance:
- [x] Semua halaman langsung memakai palette dan typography baru.
- [x] Tidak ada warna cyan/green lama kecuali optical blue untuk info.
- [x] Mobile nav dan search tetap jalan.

### Phase 2: Commerce Core

Files:
- [x] `Product.vue`
- [x] `ProductDetail.vue`
- [x] `ProductCompare.vue`
- [x] `CartView.vue`
- [x] `CheckoutView.vue`
- [x] `WaitingPayment.vue`

Scope:
- [x] Catalog, PDP, compare, cart, checkout, payment.
- [x] Sticky summary/CTA.
- [x] Loading, empty, error states.
- [x] Product card consistency.

Acceptance:
- [x] Alur browse -> detail -> cart -> checkout terasa satu sistem.
- [x] Tidak ada layout shift besar pada image/card.
- [x] Checkout jelas per step.

### Phase 3: Account & Post-Purchase

Files:
- [x] `Login.vue`
- [x] `Register.vue`
- [x] `Profile.vue`
- [x] `OrderDetail.vue`
- [x] `Tracking.vue`
- [x] `Complaint.vue`
- [x] `ComplaintDetail.vue`
- [x] `WarrantyPage.vue`
- [x] `SharedWishlist.vue`

Scope:
- [x] Auth, dashboard pelanggan, order, tracking, warranty, complaint, wishlist.

Acceptance:
- [x] Semua route private punya shell konsisten.
- [x] Status order/complaint/warranty mudah discan.
- [x] Form support punya hierarchy dan error state jelas.

### Phase 4: Service, Content, Growth

Files:
- [x] `AppointmentPage.vue`
- [x] `FaceShapeQuiz.vue`
- [x] `VirtualTryOn.vue`
- [x] `CategoryLanding.vue`
- [x] `BrandLanding.vue`
- [x] `LoyaltyPage.vue`
- [x] `ReferralPage.vue`
- [x] `blog/*`
- [x] `legal/*`

Scope:
- [x] Booking, quiz, try-on, landing SEO, loyalty/referral, blog, legal.

Acceptance:
- [x] Tool pages terasa usable.
- [x] Blog/legal punya readability bagus.
- [x] Loyalty/referral tidak terlihat seperti promo template.

## 6. Master Prompt Implementasi

Gunakan prompt ini saat meminta agent mengubah tampilan frontend.

```text
Kamu adalah senior frontend engineer + UI/UX designer. Redesign seluruh frontend Vue 3/Tailwind di `medio-fe` untuk Optik Medio, ecommerce optik premium. Ikuti `design-system/optik-medio/FRONTEND_REDESIGN_BLUEPRINT.md` sebagai source of truth.

Tujuan:
- Ubah seluruh UI menjadi premium optical ecommerce: elegan, profesional, mudah digunakan, bukan template AI.
- Pertahankan seluruh fungsi, repository calls, Pinia stores, route names, query params, API payload, dan behavior bisnis.
- Fokus FE saja. Jangan ubah backend contract.

Visual direction:
- Palette: ink `#15120E`, graphite `#2B2926`, ivory `#F7F3EC`, porcelain `#FCFAF6`, mist gray `#E7E1D8`, warm taupe `#B8A999`, antique gold `#B88A44`, deep olive `#56604B`, rose clay `#A65F55`, optical blue `#3F6F8F`.
- Typography: serif premium untuk heading besar, sans bersih untuk UI/body.
- Radius 8px default. Shadow ringan. Border halus. Hindari orb, gradient AI, card bertumpuk, hero dekoratif kosong.
- Mobile-first. Pastikan 375px, 768px, 1024px, 1440px tidak overlap.

UX rules:
- Semua tombol/link punya hover, focus-visible, disabled, loading.
- Semua form punya label tetap, helper/error text, dan focus ring.
- Semua loading/empty/error state harus didesain.
- Product cards konsisten di catalog, wishlist, related, category, brand.
- Checkout dan post-purchase harus sangat jelas, dengan sticky summary/CTA sesuai viewport.
- Jangan hapus fitur: wishlist, compare, cart, prescription, lens flow, promo, loyalty, referral, warranty, complaint, appointment, virtual try-on, blog, FAQ.

Implementation rules:
- Gunakan Vue SFC dan Tailwind sesuai pattern repo.
- Jangan mengganti business logic kecuali perlu untuk state UI.
- Jangan menambah dependency kecuali benar-benar perlu.
- Jika membuat komponen reusable, buat naming jelas dan gunakan di halaman yang relevan.
- Jalankan `npm run build` setelah perubahan.
```

## 7. Prompt Phase 1

```text
Implement Phase 1 dari `design-system/optik-medio/FRONTEND_REDESIGN_BLUEPRINT.md`.

Ubah foundation dan layout global:
- `src/style.css`
- Tailwind config bila perlu.
- `components/layout/DefaultLayout.vue`
- `components/layout/TopNavBar.vue`
- `components/layout/BottomTabBar.vue`
- `components/layout/Footer.vue`
- `components/layout/PageHero.vue`
- `components/PromoBanner.vue`
- `components/ui/ToastContainer.vue`

Hasil yang wajib:
- Token warna/typography premium optical ecommerce.
- Button, input, card, badge, alert, skeleton base classes.
- Top nav desktop premium dengan search yang tetap jalan.
- Mobile nav/bottom tab clean dan tidak overlap.
- Footer premium, ringkas, readable.
- Promo banner dan toast mengikuti visual baru.

Jangan ubah route, store, repository, atau business behavior.
Verifikasi dengan build.
```

## 8. Prompt Phase 2

```text
Implement Phase 2 commerce core dari `design-system/optik-medio/FRONTEND_REDESIGN_BLUEPRINT.md`.

Ubah:
- `src/views/Product.vue`
- `src/views/ProductDetail.vue`
- `src/views/ProductCompare.vue`
- `src/views/CartView.vue`
- `src/views/checkout/CheckoutView.vue`
- `src/views/checkout/WaitingPayment.vue`

Wajib:
- Catalog premium dengan filter/search/category/brand/promo/sort yang mudah dipakai.
- Product detail dengan gallery kuat, purchase panel sticky, prescription UI klinis, CTA jelas.
- Compare table readable dan mobile horizontal.
- Cart dengan bundle frame+lens jelas, summary sticky, promo module.
- Checkout stepper/accordion, fulfillment delivery/pickup, address, shipping, payment, savings, summary.
- Waiting payment dengan status, countdown/instruction, payment detail copy, upload proof.

Pertahankan semua API calls, computed data, stores, dan event handler.
Verifikasi build.
```

## 9. Prompt Phase 3

```text
Implement Phase 3 account dan post-purchase dari `design-system/optik-medio/FRONTEND_REDESIGN_BLUEPRINT.md`.

Ubah:
- `src/views/Login.vue`
- `src/views/Register.vue`
- `src/views/Profile.vue`
- `src/views/OrderDetail.vue`
- `src/views/Tracking.vue`
- `src/views/Complaint.vue`
- `src/views/ComplaintDetail.vue`
- `src/views/WarrantyPage.vue`
- `src/views/SharedWishlist.vue`

Wajib:
- Auth screens premium dan mobile-friendly.
- Profile shell dengan sidebar desktop dan tabs mobile.
- Address, prescription, orders, wishlist, affiliate, warranty sections konsisten.
- Order detail dengan timeline, receipt, item cards, review, return, complaint/protection.
- Tracking dengan logistics feed dan CTA konfirmasi.
- Complaint/warranty forms jelas, upload evidence rapi.
- Shared wishlist publik menggunakan product card yang sama.

Jangan hapus route yang memakai `Profile.vue` sebagai multi-section.
Verifikasi build.
```

## 10. Prompt Phase 4

```text
Implement Phase 4 service, content, dan growth dari `design-system/optik-medio/FRONTEND_REDESIGN_BLUEPRINT.md`.

Ubah:
- `src/views/AppointmentPage.vue`
- `src/views/FaceShapeQuiz.vue`
- `src/views/VirtualTryOn.vue`
- `src/views/CategoryLanding.vue`
- `src/views/BrandLanding.vue`
- `src/views/LoyaltyPage.vue`
- `src/views/ReferralPage.vue`
- `src/views/blog/ArticleList.vue`
- `src/views/blog/ArticleDetail.vue`
- `src/views/legal/FAQView.vue`
- `src/views/legal/PrivacyView.vue`
- `src/views/legal/TermsView.vue`

Wajib:
- Appointment sebagai booking flow jelas.
- Face shape quiz interactive dengan result yang mendorong browse produk.
- Virtual try-on sebagai tool workspace, bukan landing page.
- Category/brand landing SEO-friendly dan pakai grid produk konsisten.
- Loyalty/referral dashboard clean, tidak seperti promo template.
- Blog editorial dan legal readable.
- FAQ searchable/accordion dengan category pills.

Pertahankan semua data flow dan SEO meta/JSON-LD yang sudah ada.
Verifikasi build.
```

## 11. Checklist Final

- Build berhasil.
- Semua route di router bisa dibuka.
- Tidak ada teks overlap di 375px.
- Product image punya aspect ratio stabil.
- CTA penting terlihat tanpa scroll berlebihan.
- Checkout bisa selesai secara visual tanpa kebingungan.
- Auth private-route redirect tetap bekerja.
- Empty/loading/error state terlihat profesional.
- Fokus keyboard terlihat.
- Warna lama cyan/green tidak dominan.
- UI tidak memakai gradient/orb/bokeh dekoratif.
