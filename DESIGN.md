# Frontend Design Specification - Optik Medio E-Commerce

## 1. Overview
Project ini adalah frontend untuk Optik Medio, sebuah platform e-commerce khusus produk optik (frame, lensa, sunglasses) yang dibangun menggunakan **Vue.js 3**. Fokus utama adalah pengalaman belanja yang clean, premium, dan memudahkan pengguna dalam menginput data resep mata.

---

## 2. Tech Stack
- **Framework**: Vue.js 3 (Composition API)
- **State Management**: Pinia (Auth, Cart, Prescription)
- **Routing**: Vue Router
- **Styling**: Tailwind CSS (Modern & Responsive)
- **Icons**: Lucide Vue / Heroicons
- **API Client**: Axios (dengan interceptor untuk Laravel Sanctum)
- **Build Tool**: Vite

---

## 3. Design System & Aesthetics
### Visual Concept
- **Vibe**: Clean, Minimalist, Trustworthy, Modern Boutique.
- **Typography**: 
  - *Headings*: Playfair Display / Montserrat (Elegant/Modern).
  - *Body*: Inter / Roboto (Highly readable).
- **Color Palette**:
  - `Primary`: #1A1A1A (Deep Black - Premium feel)
  - `Secondary`: #C5A059 (Soft Gold - Accent for buttons/badges)
  - `Background`: #FFFFFF (Clean White)
  - `Surface`: #F9FAFB (Soft Gray for sections)
  - `Error`: #EF4444 (Validation/Alerts)

### UI Components
- **Buttons**: Rounded-md dengan hover transitions.
- **Cards**: Subtle shadow, clean borders, emphasis on product images.
- **Modals**: Glassmorphism effect for background overlays.

---

## 4. Core Pages Structure

### 4.1. Public Pages
- **Home**: Banner promo, kategori (Frame, Lens, Sunglasses), Featured Products.
- **Catalog (Product Listing)**: 
  - Sidebar filters: Category, Brand, Price Range.
  - Sorting: Newest, Price High-Low, Price Low-High.
  - Grid system (2 columns mobile, 4 columns desktop).
- **Product Detail**:
  - Image Carousel.
  - Variant Selector (Color/Size).
  - **Prescription Modal**: Triggered if `is_prescription_required` is true.

### 4.2. Checkout Flow
- **Cart Page**: Edit quantity, remove item, subtotal calculation.
- **Checkout Page**: 
  - Address Selector (CRUD modal).
  - Courier Selector (Integration with RajaOngkir).
  - Order Summary (Items + Shipping Cost).
  - Notes for seller.

### 4.3. User Area (Protected)
- **Dashboard**: Quick view of recent orders.
- **Order History**: Status tracking (Unpaid, Paid, Processed, Shipped, Completed).
- **Address Book**: Manage multiple shipping addresses.

---

## 5. Special Feature: Optical Prescription Form
Fitur ini muncul saat user membeli lensa atau frame dengan resep.
- **Data Points (OD & OS)**:
  - `SPH` (Spherical): Dropdown (-20.00 to +20.00)
  - `CYL` (Cylinder): Dropdown
  - `AXIS`: Input (0 - 180)
  - `ADD` (Addition): Dropdown
  - `PD` (Pupillary Distance): Input/Dropdown
- **Logic**: Simpan data resep dalam JSON format sesuai kontrak API `order_items.prescription`.

---

## 6. API Integration Guidelines
Endpoint utama yang akan digunakan (Base URL: `http://localhost:8000/api`):
- `POST /auth/login` & `POST /auth/register`
- `GET /products` (dengan query params: `category`, `search`, `min_price`, `max_price`)
- `GET /products/{slug}`
- `GET /shipping/provinces`, `/cities`, `/districts`
- `POST /shipping/cost` (Request: `destination_district_id`, `weight`)
- `POST /orders` (Request: `items`, `shipping_address_id`, `courier`, `shipping_cost`)

---

## 7. Responsive Breakpoints
- **Mobile (< 640px)**: Single column layouts, sticky "Add to Cart" button.
- **Tablet (640px - 1024px)**: 2-3 columns grid.
- **Desktop (> 1024px)**: Full feature display with sidebar filters.
