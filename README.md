# Optik Medio E-commerce

Optik Medio is a high-performance, specialized E-commerce platform designed specifically for optical retail businesses. It combines the power of **Laravel** and **Vue.js** to deliver a seamless shopping experience for eyewear, contact lenses, and prescription services.

---

## 🌟 Key Features

### 🛒 Specialized E-commerce Core
- **Product Catalog**: Beautifully curated display for frames, sunglasses, and contact lenses.
- **Prescription Lens Pairing**: Unique logic to attach prescription lenses to frames during the checkout process.
- **Prescription Management**: Store and manage user eye prescription data securely.

### 💳 Robust Checkout & Payments
- **Shipping Integration**: Real-time shipping cost calculation using **RajaOngkir API**.
- **Payment Gateway**: Seamless online payments integrated with **Xendit**, supporting Virtual Accounts, E-Wallets, and manual transfers.
- **Order Tracking**: Comprehensive order status updates and tracking history.

### 🤝 Affiliate & Loyalty System
- **Affiliate Program**: Users can register as affiliators, share referral codes, and earn commissions on successful referrals.
- **Loyalty Points**: Reward system where users earn points for every purchase, redeemable for future discounts.
- **Promo & Discounts**: Support for both transaction-wide coupons and specific product promotions (Buy X Get Y, etc.).

### 🔒 Security & Performance
- **OTP Verification**: Secure email-based OTP for registration and sensitive actions.
- **Atomic Transactions**: Robust stock management using database locks to prevent race conditions during checkout.
- **State Management**: Reactive and fast frontend powered by **Pinia** and **Vue 3**.

---

## 🛠 Technology Stack

| Layer | Technology |
|---|---|
| **Backend** | [Laravel 11](https://laravel.com/) |
| **Frontend** | [Vue.js 3](https://vuejs.org/) (Vite) |
| **State Management** | [Pinia](https://pinia.vuejs.org/) |
| **Styling** | [Tailwind CSS](https://tailwindcss.com/) |
| **Database** | MySQL |
| **Payments** | [Xendit](https://www.xendit.co/) |
| **Shipping** | [RajaOngkir](https://rajaongkir.com/) |

---

## 📂 Project Structure

```bash
optik-medio-ecommerce/
├── medio-be/          # Laravel Backend API
│   ├── app/           # Core Logic (Controllers, Repositories, Models)
│   ├── database/      # Migrations & Seeders
│   └── routes/        # API Endpoints
├── medio-fe/          # Vue.js Frontend (SPA)
│   ├── src/           # Source code
│   │   ├── views/     # Page Components
│   │   ├── stores/    # Pinia Stores
│   │   └── core/      # API Clients & Utilities
└── data/              # Initial product data (JSON)
```

---

## 🚀 Installation & Setup

### 1. Prerequisites
- PHP >= 8.2
- Composer
- Node.js >= 18 & NPM
- MySQL

### 2. Backend Setup (`medio-be`)
```bash
cd medio-be
composer install
cp .env.example .env
# Configure your DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### 3. Frontend Setup (`medio-fe`)
```bash
cd medio-fe
npm install
# Configure your VITE_API_URL in .env
npm run dev
```

---

## ⚙️ Configuration

Ensure the following keys are set in your `medio-be/.env`:

- `RAJAONGKIR_API_KEY`: Your RajaOngkir Pro/Starter key.
- `XENDIT_SECRET_KEY`: Your Xendit secret key for payments.
- `MAIL_HOST`, `MAIL_PORT`, etc.: For sending OTP emails.

---

## 📜 License

This project is licensed under the [MIT License](LICENSE).

---

Developed with ❤️ by **Optik Medio Team**.
