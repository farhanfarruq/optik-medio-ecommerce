# Phase 1 — Audit UI & Fitur Existing

**Project:** Optik Medio E-Commerce
**Stack FE:** Vue 3.5 + Vite 5 + TypeScript + Tailwind 3.4 + Pinia + Vue Router 4
**Stack BE:** Laravel 13 + Filament 5 + MySQL + Xendit + RajaOngkir
**Total LOC views/components:** ~9.921 baris (27 views + 6 layout + 2 standalone)
**Tanggal audit:** 20 Mei 2026
**Auditor:** Kiro (Claude Opus 4.7)

> Audit ini adalah prasyarat redesign sesuai `PROMPT_REDESIGN_ECOMMERCE.md` Phase 1. Tidak boleh mulai redesign sebelum mapping selesai. Dokumen ini menjadi kontrak fitur yang **wajib dipertahankan** di Phase 2–10.

---

## 1. Daftar Route & Page

Sumber: `medio-fe/src/router/index.ts`. Semua route bersarang di `DefaultLayout`. Satu-satunya route eager-loaded adalah `Home` (LCP critical). Sisanya lazy via `() => import(...)`.

### 1.1 Public Routes

| Path | Name | Component | Title |
|---|---|---|---|
| `/` | Home | `views/Product.vue` | Optik Medio \| Pengalaman Belanja Optik |
| `/products` | Products | `views/Product.vue` (sama) | Produk \| Optik Medio |
| `/products/category/:slug` | ProductsByCategory | `views/Product.vue` | Kategori Produk \| Optik Medio |
| `/products/:slug` | ProductDetail | `views/ProductDetail.vue` | Detail Produk \| Optik Medio |
| `/compare` | ProductCompare | `views/ProductCompare.vue` | Bandingkan Produk \| Optik Medio |
| `/face-shape-quiz` | FaceShapeQuiz | `views/FaceShapeQuiz.vue` | Kuis Bentuk Wajah \| Optik Medio |
| `/virtual-try-on` | VirtualTryOn | `views/VirtualTryOn.vue` | Coba Virtual \| Optik Medio |
| `/cart` | Cart | `views/CartView.vue` | Keranjang \| Optik Medio |
| `/wishlist/shared/:token` | SharedWishlist | `views/SharedWishlist.vue` | Wishlist Dibagikan \| Optik Medio |
| `/blog` | Blog | `views/blog/ArticleList.vue` | Blog & Artikel \| Optik Medio |
| `/blog/:slug` | ArticleDetail | `views/blog/ArticleDetail.vue` | Artikel \| Optik Medio |
| `/c/:slug` | CategoryLanding | `views/CategoryLanding.vue` | Kategori \| Optik Medio |
| `/brand/:brand` | BrandLanding | `views/BrandLanding.vue` | Merek \| Optik Medio |
| `/loyalty` | Loyalty | `views/LoyaltyPage.vue` | Poin Loyalitas \| Optik Medio |
| `/appointment` | Appointment | `views/AppointmentPage.vue` | Booking Konsultasi \| Optik Medio |
| `/privacy` (alias `/kebijakan-privasi`) | Privacy | `views/legal/PrivacyView.vue` | (legal) |
| `/terms` (alias `/syarat-ketentuan`) | Terms | `views/legal/TermsView.vue` | (legal) |
| `/faq` | FAQ | `views/legal/FAQView.vue` | (legal) |

### 1.2 Guest-Only Routes (redirect ke `/profile` jika sudah login)

| Path | Name | Component |
|---|---|---|
| `/login` | Login | `views/Login.vue` |
| `/register` | Register | `views/Register.vue` |

### 1.3 Auth-Required Routes (redirect ke `/login` jika belum login)

`AUTH_REQUIRED_ROUTES = ['Profile', 'Addresses', 'Prescriptions', 'Orders', 'Wishlist', 'Warranty', 'Checkout', 'OrderDetail', 'AffiliateDashboard', 'WaitingPayment', 'Tracking', 'Complaint']`

| Path | Name | Component |
|---|---|---|
| `/profile` | Profile | `views/Profile.vue` |
| `/addresses` | Addresses | `views/Profile.vue` (section dalam Profile) |
| `/prescriptions` | Prescriptions | `views/Profile.vue` |
| `/orders` | Orders | `views/Profile.vue` |
| `/orders/:id` | OrderDetail | `views/OrderDetail.vue` |
| `/affiliate` | AffiliateDashboard | `views/Profile.vue` |
| `/wishlist` | Wishlist | `views/Profile.vue` |
| `/warranty` | Warranty | `views/Profile.vue` |
| `/checkout` | Checkout | `views/checkout/CheckoutView.vue` |
| `/waiting-payment/:id` | WaitingPayment | `views/checkout/WaitingPayment.vue` |
| `/tracking/:id` | Tracking | `views/Tracking.vue` |
| `/complaints/new` | Complaint | `views/Complaint.vue` |
| `/complaints/:id` | ComplaintDetail | `views/ComplaintDetail.vue` |

### 1.4 Guard Logic (router `beforeEach`)

1. Inisialisasi `authStore.fetchUser()` saat first load.
2. Auth-required tanpa user → redirect `/login?redirect=<original>`.
3. Guest-only saat sudah login → redirect `/profile`.
4. **CART-001:** akses `/checkout` dengan `cartStore.items.length === 0` → redirect `/cart`.
5. `afterEach` → set `document.title` dari `meta.title`.

> **Tidak boleh diubah** kecuali ada alasan kuat dan disetujui.

---

## 2. Daftar Fitur Per Page

### 2.1 Home & Catalog (`Product.vue` — 1.212 LOC, **god component**)

Dipakai untuk 3 route: Home, Products, ProductsByCategory.

**Fitur:**
- Hero / banner carousel (auto-rotate, dari `bannerRepository`)
- Category grid + toggle "lihat semua kategori" (`showAllCategories`)
- Mobile responsive flag (`isMobileView` via resize listener)
- Filter panel (toggle `showFilterPanel`):
  - Category filter (`categorySlug` via param)
  - Brand filter
  - Price range (`minPrice`, `maxPrice`)
  - In-stock only toggle
  - Prescription supported toggle
  - Active promo filter (`hasPromo`, `promoId`)
  - Sort: `latest`, dan opsi lain (perlu cek `productRepository`)
- Search query (sync dengan `?search=`)
- Lens showcase carousel
- Testimonial section
- Wishlist toggle dari grid (`wishlistStore`)
- Add to cart cepat dari grid (`cartStore`)
- Add to compare (`compareStore`)
- Toast feedback (`useToast`)
- SEO meta dynamic (`useSeoMeta`)
- PageHero dengan breadcrumb dinamis

### 2.2 Product Detail (`ProductDetail.vue` — 1.335 LOC, **god component**)

**Fitur:**
- Image gallery (main + thumbs, `activeImage` state)
- Product info: title, brand, SKU, harga, status stok
- Variant selection
- Quantity stepper
- Wishlist toggle
- Add to cart langsung
- **Lens configurator modal** (3-step):
  1. `lens` — pilih `LensOption` dari `opticalRepository`
  2. `coating` — pilih coating atau skip
  3. `prescription` form lengkap (OD/OS, sphere, cyl, axis, add untuk progressive, PD)
- Prescription support: `usesOdAxis`, `usesOsAxis` (cyl !== 0)
- Total harga real-time (frame + lens + coating)
- Related products
- Review section (perlu cek detail render)
- SEO meta dynamic
- "Added to cart" feedback state

### 2.3 Cart (`CartView.vue` — 386 LOC)

**Fitur:**
- Daftar item cart, dikelompokkan: frame + attached lens (parent/child via `parent_item_id`)
- Quantity update per item
- Remove item
- Promo:
  - List promo eligible (`cartStore.applicable_promos`)
  - Apply / lepas promo
  - Display free items dari `buy_x_get_y`
  - `transaction_discount`, `product_discount` types
  - Promo description formatting (`(\d+)\.00%` → `$1%`)
- Cart calculation refresh (subtotal, discount, total)
- Store status check (`is_closed` block checkout)
- Empty cart state
- CTA Checkout (disabled jika store closed atau cart kosong)
- Toast feedback

### 2.4 Checkout (`views/checkout/CheckoutView.vue` — 1.375 LOC, **god component**)

**Fitur:**
- Form pengiriman: recipient_name, phone, address, province/city/district (cascading dari `shippingRepository`), postal_code, courier (default `jne`), selected_service
- Fulfillment method toggle: `delivery` vs `store_pickup`
- Address management:
  - List saved addresses (`userAddresses`)
  - Modal pilih address (`showAddressModal`)
  - Auto-fill (`isAutoFilling`)
- Shipping calculation (RajaOngkir/JNE/POS/TIKI)
- Payment methods (`paymentMethods` dari `PaymentMethodItem[]`):
  - Provider `xendit` (auto checkout via Xendit modal)
  - Manual payment (transfer bank)
  - Bank account selection (`bankAccounts`, `requires_bank_selection` flag)
- Xendit modal:
  - Embedded checkout URL
  - Polling order status (interval)
  - Pickup query handling
  - Auto-redirect ke `/waiting-payment/:id`
- Store status (block jika closed)
- Analytics tracking: `trackCheckoutStarted`, `trackCheckoutFailed`
- Loading states untuk provinsi/kota/distrik

### 2.5 Waiting Payment (`views/checkout/WaitingPayment.vue`)

**Fitur:** polling status order, instruksi transfer/manual payment, link ke order detail.

### 2.6 Order Detail (`OrderDetail.vue` — 984 LOC, near-god)

**Fitur:** detail item, alamat, shipping, payment, status timeline, tombol konfirmasi terima, tombol tracking, tombol komplain.

### 2.7 Tracking (`Tracking.vue` — 299 LOC)

**Fitur:** integrasi dengan API kurir, tampilkan history status pengiriman.

### 2.8 Profile (`Profile.vue` — 1.524 LOC, **god component**)

Multi-route, satu komponen, render section berbeda berdasarkan `route.name`.

**Fitur per section:**
- **Profile (`/profile`):** edit nama, email, phone, password
- **Addresses (`/addresses`):** CRUD address (recipient, full address, province/city/district)
- **Prescriptions (`/prescriptions`):** CRUD `PrescriptionProfile` lengkap dengan OD/OS data
- **Orders (`/orders`):** list orders + filter tabs (Semua, Menunggu Bayar, Dikemas, Dikirim, Selesai, Dibatalkan), pagination, konfirmasi terima
- **Affiliate (`/affiliate`):** profile, summary, commissions list, earnings, payout form (bank_transfer)
- **Wishlist (`/wishlist`):** list wishlist items, remove, share
- **Loyalty:** poin + history pagination
- **Warranty:** komponen `WarrantyPage` di-embed
- Status normalization (`normalizeOrderStatus`) dan label/class mapping

### 2.9 Auth (Login/Register)

**Login (`Login.vue` — 67 LOC):**
- Form: email, password
- Toast error
- Handling 403 + `requires_otp` → redirect ke Register dengan step=otp

**Register (`Register.vue` — 192 LOC):**
- Form: name, email, phone, password, password_confirmation, register_as_affiliator, referral_code
- OTP verification step (6 digit input dengan auto-advance)
- Countdown resend OTP
- Step navigation: `register` ↔ `otp`

### 2.10 Other Pages

| Page | LOC | Fitur Utama |
|---|---|---|
| `AppointmentPage.vue` | 477 | Booking konsultasi optik (slot picker, form) |
| `ProductCompare.vue` | 150 | Tabel komparasi produk dari `compareStore` |
| `FaceShapeQuiz.vue` | 229 | Quiz interaktif → rekomendasi frame |
| `VirtualTryOn.vue` | 544 | AR/camera based try-on |
| `LoyaltyPage.vue` | 190 | Landing program loyalty |
| `BrandLanding.vue` | 81 | Landing per brand |
| `CategoryLanding.vue` | 118 | Landing per kategori |
| `ReferralPage.vue` | 269 | Program referral / affiliate share |
| `WarrantyPage.vue` | 401 | Klaim garansi + service |
| `Complaint.vue` / `ComplaintDetail.vue` | 208 / 194 | Form & detail komplain |
| `SharedWishlist.vue` | 83 | Wishlist publik via token |
| `blog/ArticleList.vue` + `blog/ArticleDetail.vue` | n/a | Blog content |
| `legal/{Privacy,Terms,FAQ}View.vue` | n/a | Halaman statis |

---

## 3. Komponen Layout Utama

`medio-fe/src/components/layout/`:

| File | LOC | Fungsi |
|---|---|---|
| `DefaultLayout.vue` | 34 | Shell global. Wrap `PromoBanner`, `TopNavBar`, `ToastContainer`, `<router-view>`, `Footer`, `BottomTabBar`. Set `--header-height` (72/108px) berdasarkan `cartStore.isPromoBannerVisible`. Hide footer di auth pages. |
| `TopNavBar.vue` | **483** | Header desktop+mobile. Logo, center links, search overlay, appointment/blog/user/cart icons (desktop), hamburger (mobile), mobile drawer slide-in. Scroll-aware (`isScrolled`, `isLightNav`). Search debounced 250ms via `productRepository.getSearchSuggestions`, recent searches di localStorage `medio_recent_searches`, analytics no-result tracking. |
| `Footer.vue` | 73 | Footer desktop only (hidden md:block). 4-column grid: brand+social, contact, navigation, store location. Pulls dari `settingRepository`. |
| `BottomTabBar.vue` | 85 | Mobile bottom nav (md:hidden). 5 tabs: Beranda, Produk, Booking, Profil, Cart. Active state via `route.path` + filled icon. Cart badge. `safe-area-inset-bottom`. |
| `AuthShell.vue` | 62 | Auth page wrapper. Split layout: aside hero (lg only) + form panel. Props: `eyebrow`, `title`, `description`, `panelTitle`, `panelSubtitle`. |
| `PageHero.vue` | 62 | Reusable page hero dengan background image + breadcrumb + back link + title + subtitle. Style inline (height 320px, gradient overlay). |

`medio-fe/src/components/`:

| File | LOC | Fungsi |
|---|---|---|
| `PromoBanner.vue` | 86 | Banner promo top-bar. Toggle visibility via `cartStore.isPromoBannerVisible`. |
| `HelloWorld.vue` | 93 | (sisa template Vite — bisa dihapus, tapi cek dulu pemakaiannya) |

`medio-fe/src/components/ui/`:

| File | LOC | Fungsi |
|---|---|---|
| `ToastContainer.vue` | ~78 | Toast notification host, dipakai oleh `useToast` composable |

> **Catatan: belum ada folder `components/products/`, `components/cart/`, `components/checkout/`, atau `components/profile/`.** Semua section-level masih inline di view (god components). REFACTOR_PLAN merekomendasikan extraction tapi belum dieksekusi.

---

## 4. State Penting (Pinia Stores)

`medio-fe/src/stores/`:

| Store | Fungsi |
|---|---|
| `authStore` | `user`, `isAuthenticated`, `hasInitialized`, `login`, `register`, `logout`, `fetchUser` |
| `cartStore` | `items`, `applicable_promos`, `appliedPromo`, `calculatedData`, `isPromoBannerVisible`, `setPromo`, `fetchPromos`, refresh calc |
| `wishlistStore` | toggle, list, share token |
| `compareStore` | list (max ~3-4 items) |
| (likely lain-lain) | banner, settings, dll terhubung via repositories |

### State UI yang sudah ada:
- **Loading:** `isLoading`, `isLoadingHistory`, `isLoadingOrders`, `isLoadingMoreOrders`, `isLoadingPayment`, `isProvLoading`, `isCityLoading`, `isDistLoading`, `isAutoFilling`, `isSuggestionLoading`, `isLoadingLensShowcase`, `isLensModalOpen`, `isCoatingsLoading`, `isPollingPayment`
- **Empty:** ditangani per-section (cart kosong, no orders, no wishlist, etc.)
- **Error:** `errorMessage`, `hasError`, `shippingError`, `checkoutError`
- **Success:** toast via `useToast`
- **Disabled:** `:disabled="isLoading"` di submit buttons
- **Authenticated:** `authStore.isAuthenticated` di TopNavBar, BottomTabBar, guards
- **Unauthenticated:** redirect chain via guard + `?redirect=` query

---

## 5. Design System Sudah Ada (di `style.css` + `tailwind.config.js`)

**Palette CSS variables (`:root`):**
- Primary: ink `#15120E`, graphite `#2B2926`
- Surface: ivory `#F7F3EC`, porcelain `#FCFAF6`, mist `#E7E1D8`, taupe `#B8A999`
- Accent: gold `#B88A44`, gold-soft `rgba(184,138,68,0.14)`
- Status: olive `#56604B` (success), optical-blue `#3F6F8F` (info), error `0 62% 42%`
- Material You-style HSL tokens: `--primary`, `--secondary`, `--surface-container-*`, `--on-surface`, `--outline`

**Typography:**
- Display/H1-3: Cormorant Garamond (serif) 500–700
- Body/H4-6/Label: Montserrat (sans) 400–700
- Tailwind aliases: `font-headline`, `font-body`, `font-label`

**Komponen utility classes (`.btn-primary`, `.btn-gold`, `.btn-outline`, `.input-field`, `.card`, `.premium-card`, `.product-card-base`, `.product-image-frame`, `.badge`, `.badge-gold`, `.badge-success`, `.badge-info`, `.alert-base`, `.alert-info`, `.alert-error`, `.container-premium`, `.container-commerce`, `.container-readable`):** sudah konsisten dan harus dipakai sebagai foundation di Phase 2.

**Radius:** default `rounded-lg` (8px), `premium` 8px, `drawer` 12px (sesuai aturan max-radius).

**Shadow:** `shadow-card` (subtle), `shadow-soft` (slightly elevated). Tidak boleh shadow tebal.

**Focus ring:** gold `2px` + offset ivory.

**Icon:** **Material Symbols Outlined** (variation FILL untuk active state). Bukan Heroicons/Lucide. Konsistensi: tetap pakai Material Symbols, jangan campur library.

> **Anti-pattern existing yang teridentifikasi (sesuai design-system MASTER.md):** sudah ada warning hindari emoji icon, decorative blob, card-in-card, hover layout shift, gradient orb. Phase 2 wajib patuh.

---

## 6. Bagian UI yang BOLEH Diubah Total

| Area | Alasan |
|---|---|
| **TopNavBar** (483 LOC) | Sudah complex, tetapi bisa di-restructure: extract `SearchOverlay`, `MobileDrawer`, `NavLinks` jadi child components. Tata letak boleh dipoles. |
| **Footer** layout columns | Tampilan boleh diperbaiki tanpa hilangkan link/contact/store. |
| **BottomTabBar** styling | Boleh dipoles (icon grouping, badge animation). Struktur 5-tab tetap. |
| **PromoBanner** | Bisa restyle. |
| **PageHero** | Tinggi 320px boleh dikurangi untuk mobile (instruksi mobile-addendum: hero pendek). |
| **AuthShell** aside imagery | Boleh ganti komposisi (tetap split layout). |
| **Product.vue** seluruh tampilan | Hero/banner, category grid, filter panel desktop+mobile, product grid, lens showcase, testimonial — semuanya boleh re-layout. Logic state harus tetap. |
| **ProductDetail.vue** layout gallery + info column | Gallery bisa dipoles. Lens configurator modal: UI boleh redesign, step state machine harus tetap. |
| **CartView** layout grouped item + summary | Boleh editorial. |
| **CheckoutView** stepper layout | Boleh restructure jadi sticky summary + section flow. Form fields tidak boleh berubah. |
| **Profile** sidebar + section panel | Boleh extract sub-components dan re-layout. |
| **Login / Register** form treatment | Visual boleh premium upgrade. |
| **Component-level surface card / badge / chip styling** | Bisa unify pakai existing utility classes. |

---

## 7. Bagian Logic yang TIDAK BOLEH Disentuh

| Area | Alasan |
|---|---|
| **Router definitions** (path, name, meta, guards) | Akan break SEO + auth flow. |
| **`AUTH_REQUIRED_ROUTES` / `GUEST_ONLY_ROUTES`** | Security boundary. |
| **CART-001 guard** (cart kosong → redirect /cart) | Mencegah checkout invalid. |
| **Pinia store shapes** (`cartStore.items`, `applicable_promos`, `appliedPromo`, `calculatedData.items[].is_free`, `calculatedData.promo_summary`, `parent_item_id`, `cart_id`, `cartStore.isPromoBannerVisible`, `wishlistStore`, `compareStore`, `authStore.user`, `authStore.isAuthenticated`, `authStore.hasInitialized`) | Banyak komponen dependsk. |
| **Repository contracts** (`productRepository.getSearchSuggestions`, `bannerRepository`, `settingRepository`, `opticalRepository.getLensCoatings`, `shippingRepository.getProvinces/getCities/getDistricts`, `orderRepository`, `prescriptionRepository`, `affiliateRepository`, `masterDataRepository.getStoreStatus`, `paymentMethodRepository`) | Backend contract. |
| **Form field names** di Checkout (`recipient_name`, `phone`, `address`, `province_id`, `province`, `city_id`, `city`, `district_id`, `district`, `postal_code`, `courier`, `selected_service`) | Sinkron ke backend payload. |
| **Form field names** di Register (`name`, `email`, `phone`, `password`, `password_confirmation`, `register_as_affiliator`, `referral_code`) | Backend payload. |
| **Prescription payload** (OD/OS sphere/cyl/axis/add, PD) | Optical domain logic. |
| **Promo type enum** (`buy_x_get_y`, `transaction_discount`, `product_discount`) | Backend logic. |
| **Order status enum** (`unpaid`, `paid`, `waiting_prescription_review`, `prescription_verified`, `lens_processing`, `processing`, `shipped`, `delivered`, `completed`, `cancelled`, `refunded`) | Workflow state machine. |
| **Xendit polling** + redirect ke waiting-payment | Payment integration. |
| **OTP flow** (Login 403 + `requires_otp` → Register step=otp) | Auth flow contract. |
| **Lens configurator step machine** (`lens` → `coating`/skip → `prescription`) | Domain UX. |
| **`fulfillmentMethod` toggle** (`delivery` / `store_pickup`) | Conditional rendering depend. |
| **Localstorage keys** (`medio_recent_searches`) | User data persistence. |
| **Analytics tracking** (`trackCheckoutStarted`, `trackCheckoutFailed`, `trackSearchNoResult`) | Observability. |
| **CSS variable contract** (`--header-height`, `--ink`, `--ivory`, dst) | Banyak component depend. |
| **`useSeoMeta`, `useToast`, `useWebVitals`, `useAnalytics`** | Cross-cutting concerns. |

---

## 8. Catatan Tambahan

### 8.1 God Components (Audit Phase 3 belum tuntas)
4 file masih > 1.000 LOC:
- `Profile.vue` 1.524
- `CheckoutView.vue` 1.375
- `ProductDetail.vue` 1.335
- `Product.vue` 1.212

REFACTOR_PLAN sudah merekomendasikan extraction, tetapi belum dilakukan. **Phase 9 (Component Refactor) prompt redesign akan menggunakan kesempatan ini sekaligus**, tetapi:
- Logic harus dipindah ke composables (`useProductList`, `useCheckout`, `useProductDetail`, `useProfile*`) sebelum/sambil extract komponen.
- Props dan event lama harus tetap kompatibel.

### 8.2 Test Coverage FE
**0 test FE** menurut audit komprehensif. Tidak ada test untuk dipertahankan, tetapi kalau redesign ingin tambah test (Vitest sudah set up), tambahan boleh tapi opsional.

### 8.3 Hooks Layout Khusus
- `DefaultLayout` set CSS var `--header-height` dinamis (72px tanpa banner, 108px dengan banner). Banyak section pakai `pt-[var(--header-height)]` atau offset. **JANGAN ubah kontrak ini.**
- `BottomTabBar` selalu render kecuali auth page. `<main>` punya `pb-24 md:pb-0` untuk hindari overlap dengan bottom tabs di mobile.

### 8.4 Icon Library
Project pakai **Material Symbols Outlined** (Google), bukan Lucide/Heroicons. Tetap pakai untuk konsistensi (instruksi prompt: "tetap pakai icon library yang sudah ada").

### 8.5 Halaman Yang Belum Ada (Tetapi Direferensikan)
- Tidak ada 404 / NotFound view (router tidak punya catch-all). **Optional improvement** di phase mendatang.

---

## 9. Risiko & Tradeoff Redesign

| Risiko | Mitigasi |
|---|---|
| Refactor god components sambil redesign visual sekaligus | Pisah commit: ekstrak ke composable + sub-components dulu (logic-preserving), baru polish UI per sub-component. |
| Mengubah CSS var `--header-height` | Konfirmasikan tinggi baru di semua page yang pakai `pt-[var(--header-height)]` (AuthShell, banyak hero). |
| Mengubah grid product card aspect ratio | Cek skeleton & gallery konsistensi, hindari CLS. |
| Mengubah label & tab order di Profile | Bisa break deep-link `?tab=...` jika ada (perlu cek). |
| Replace inline style PageHero dengan CSS class | Pastikan semua pemakai PageHero tetap render benar. |
| Mengubah search overlay UX | Pastikan recent search localstorage tetap dibaca dan suggest API tetap dipakai. |

---

## 10. Cara Verifikasi (Phase Berikutnya)

Untuk setiap phase setelah ini:
1. **Build check:** `cd medio-fe && npm run build` harus sukses.
2. **Type check:** `npm run typecheck` harus clean.
3. **Lint:** `npm run lint` harus pass.
4. **Manual smoke test (route walk-through):**
   - `/` → produk muncul, hero render, kategori clickable
   - `/products?search=x` → search input pre-filled
   - `/products/category/:slug` → filter category aktif
   - `/products/:slug` → gallery + add to cart + lens configurator (tanpa lens, dengan lens)
   - `/cart` → item display, qty change, remove, promo apply, checkout button
   - `/checkout` → form valid, courier select, payment select, Xendit modal open
   - `/login` & `/register` → submit + OTP flow
   - `/profile`, `/orders`, `/addresses`, `/prescriptions`, `/wishlist`, `/affiliate`, `/warranty` → semua section render
   - Mobile viewport 375px → bottom tab bar visible, drawer open dari TopNavBar, no horizontal scroll
5. **Visual responsive check:** 320 / 375 / 390 / 414 / 768 / 1024 / 1440 px.
6. **Cart calculation tetap match** sebelum & sesudah (free items, promo summary, subtotal).
7. **Auth state transitions:** login → redirect, logout → tab bar update, guard redirect berfungsi.

---

## 11. Sign-off

Audit Phase 1 selesai. Daftar di atas adalah **kontrak fitur** untuk redesign Phase 2–10.

**Berikutnya:** Phase 2 (Design System Baru) — hanya extension dari design system existing (jangan ditimpa, sudah cukup matang). Mayoritas effort akan di **Phase 3+ (App Shell, Homepage, Catalog, PDP, Cart, Checkout, Auth/Account)** — semua sebagai _re-layout_ + _component extraction_, bukan rewrite.

Konfirmasi dulu sebelum lanjut ke Phase 2 sehingga saya bisa pilih scope yang aman (mis. mulai dari Footer + PageHero + AuthShell yang kecil, atau langsung ke catalog/PDP yang impactful).
