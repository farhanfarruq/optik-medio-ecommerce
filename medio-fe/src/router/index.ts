import { createRouter, createWebHistory } from 'vue-router';
import DefaultLayout from '../components/layout/DefaultLayout.vue';

// PERF-2 (Phase 4): semua route component dilazy-load via dynamic import.
// Sebelum perubahan: 16 import eager (Home, ProductDetail, Login, Profile,
// CheckoutView, OrderDetail, dll) memaksa Vite memasukkan semua ke initial
// `index.js` chunk (~396 KB di build sebelumnya).
//
// Sesudah perubahan: hanya DefaultLayout yang eager (shell layout app).
// Setiap route component dimuat saat dibutuhkan → initial bundle lebih kecil
// dan TTI lebih cepat. Vue Router akan otomatis cache module yang sudah dimuat.
//
// Catatan: Home (Product.vue) sengaja TIDAK di-lazy karena route entry
// pertama (LCP critical). Sisanya di-lazy via () => import().

import Home from '../views/Product.vue';

// Rute yang memerlukan autentikasi
const AUTH_REQUIRED_ROUTES = ['Profile', 'Addresses', 'Prescriptions', 'Orders', 'Wishlist', 'Warranty', 'Checkout', 'OrderDetail', 'AffiliateDashboard', 'WaitingPayment', 'Tracking', 'Complaint'];

// Rute yang hanya bisa diakses saat BELUM login
const GUEST_ONLY_ROUTES = ['Login', 'Register'];

// Lazy import helpers — chunk name comments membantu debugging di DevTools.
const ProductDetail = () => import(/* webpackChunkName: "product-detail" */ '../views/ProductDetail.vue');
const Login = () => import(/* webpackChunkName: "auth" */ '../views/Login.vue');
const Register = () => import(/* webpackChunkName: "auth" */ '../views/Register.vue');
const CartView = () => import(/* webpackChunkName: "cart" */ '../views/CartView.vue');
const Profile = () => import(/* webpackChunkName: "profile" */ '../views/Profile.vue');
const ProductCompare = () => import(/* webpackChunkName: "product-compare" */ '../views/ProductCompare.vue');
const SharedWishlist = () => import(/* webpackChunkName: "shared-wishlist" */ '../views/SharedWishlist.vue');
const FaceShapeQuiz = () => import(/* webpackChunkName: "face-shape-quiz" */ '../views/FaceShapeQuiz.vue');
const VirtualTryOn = () => import(/* webpackChunkName: "virtual-try-on" */ '../views/VirtualTryOn.vue');
const CheckoutView = () => import(/* webpackChunkName: "checkout" */ '../views/checkout/CheckoutView.vue');
const OrderDetail = () => import(/* webpackChunkName: "order-detail" */ '../views/OrderDetail.vue');
const WaitingPayment = () => import(/* webpackChunkName: "checkout" */ '../views/checkout/WaitingPayment.vue');
const Tracking = () => import(/* webpackChunkName: "tracking" */ '../views/Tracking.vue');
const Complaint = () => import(/* webpackChunkName: "complaint" */ '../views/Complaint.vue');

const routes = [
  {
    path: '/',
    component: DefaultLayout,
    children: [
      { path: '', name: 'Home', component: Home, meta: { title: 'Optik Medio | Pengalaman Belanja Optik' } },
      { path: 'products', name: 'Products', component: Home, meta: { title: 'Produk | Optik Medio' } },
      { path: 'products/category/:slug', name: 'ProductsByCategory', component: Home, meta: { title: 'Kategori Produk | Optik Medio' } },
      { path: 'compare', name: 'ProductCompare', component: ProductCompare, meta: { title: 'Bandingkan Produk | Optik Medio' } },
      { path: 'face-shape-quiz', name: 'FaceShapeQuiz', component: FaceShapeQuiz, meta: { title: 'Kuis Bentuk Wajah | Optik Medio' } },
      { path: 'virtual-try-on', name: 'VirtualTryOn', component: VirtualTryOn, meta: { title: 'Coba Virtual | Optik Medio' } },
      { path: 'products/:slug', name: 'ProductDetail', component: ProductDetail, meta: { title: 'Detail Produk | Optik Medio' } },
      { path: 'login', name: 'Login', component: Login, meta: { title: 'Masuk | Optik Medio' } },
      { path: 'register', name: 'Register', component: Register, meta: { title: 'Daftar | Optik Medio' } },
      { path: 'cart', name: 'Cart', component: CartView, meta: { title: 'Keranjang | Optik Medio' } },
      { path: 'profile', name: 'Profile', component: Profile, meta: { title: 'Profil | Optik Medio' } },
      { path: 'addresses', name: 'Addresses', component: Profile, meta: { title: 'Alamat | Optik Medio' } },
      { path: 'prescriptions', name: 'Prescriptions', component: Profile, meta: { title: 'Resep Optik | Optik Medio' } },
      { path: 'orders', name: 'Orders', component: Profile, meta: { title: 'Pesanan | Optik Medio' } },
      { path: 'affiliate', name: 'AffiliateDashboard', component: Profile, meta: { title: 'Afiliasi | Optik Medio' } },
      { path: 'wishlist/shared/:token', name: 'SharedWishlist', component: SharedWishlist, meta: { title: 'Wishlist Dibagikan | Optik Medio' } },
      { path: 'wishlist', name: 'Wishlist', component: Profile, meta: { title: 'Wishlist | Optik Medio' } },
      { path: 'checkout', name: 'Checkout', component: CheckoutView, meta: { title: 'Pembayaran | Optik Medio' } },
      { path: 'waiting-payment/:id', name: 'WaitingPayment', component: WaitingPayment, meta: { title: 'Menunggu Pembayaran | Optik Medio' } },
      { path: 'tracking/:id', name: 'Tracking', component: Tracking, meta: { title: 'Pelacakan Pesanan | Optik Medio' } },
      { path: 'complaints/new', name: 'Complaint', component: Complaint, meta: { title: 'Komplain | Optik Medio' } },
      { path: 'complaints/:id', name: 'ComplaintDetail', component: () => import('../views/ComplaintDetail.vue'), meta: { title: 'Detail Komplain | Optik Medio' } },
      { path: 'orders/:id', name: 'OrderDetail', component: OrderDetail, meta: { title: 'Detail Pesanan | Optik Medio' } },
      // Blog routes
      { path: 'blog', name: 'Blog', component: () => import('../views/blog/ArticleList.vue'), meta: { title: 'Blog & Artikel | Optik Medio' } },
      { path: 'blog/:slug', name: 'ArticleDetail', component: () => import('../views/blog/ArticleDetail.vue'), meta: { title: 'Artikel | Optik Medio' } },
      // Halaman Legal statis
      { path: 'privacy', alias: ['/kebijakan-privasi'], name: 'Privacy', component: () => import('../views/legal/PrivacyView.vue'), meta: { title: 'Kebijakan Privasi | Optik Medio' } },
      { path: 'terms', alias: ['/syarat-ketentuan'], name: 'Terms', component: () => import('../views/legal/TermsView.vue'), meta: { title: 'Syarat & Ketentuan | Optik Medio' } },
      { path: 'faq', name: 'FAQ', component: () => import('../views/legal/FAQView.vue'), meta: { title: 'FAQ | Optik Medio' } },
      // Referral
      { path: 'referral', name: 'Referral', component: () => import('../views/ReferralPage.vue'), meta: { title: 'Program Referral | Optik Medio' } },
      { path: 'referral/:code', name: 'ReferralLanding', component: () => import('../views/ReferralPage.vue'), meta: { title: 'Bergabung dengan Referral | Optik Medio' } },
      // Appointment & Warranty
      { path: 'appointment', name: 'Appointment', component: () => import('../views/AppointmentPage.vue'), meta: { title: 'Booking Konsultasi | Optik Medio' } },
      { path: 'warranty', name: 'Warranty', component: Profile, meta: { title: 'Garansi & Servis | Optik Medio' } },
      // Category, Brand, Loyalty landing pages
      { path: 'c/:slug', name: 'CategoryLanding', component: () => import('../views/CategoryLanding.vue'), meta: { title: 'Kategori | Optik Medio' } },
      { path: 'brand/:brand', name: 'BrandLanding', component: () => import('../views/BrandLanding.vue'), meta: { title: 'Merek | Optik Medio' } },
      { path: 'loyalty', name: 'Loyalty', component: () => import('../views/LoyaltyPage.vue'), meta: { title: 'Poin Loyalitas | Optik Medio' } },
    ]
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(_to, _from, savedPosition) {
    if (savedPosition) return savedPosition;
    return { top: 0, behavior: 'smooth' };
  }
});

router.beforeEach(async (to, _from, next) => {
  const { useAuthStore } = await import('../stores/authStore');
  const authStore = useAuthStore();

  if (!authStore.hasInitialized) {
    try {
      await authStore.fetchUser();
    } catch {
      // fetchUser sudah handle 401 dengan set user = null di dalam fungsinya.
      // Error lain (network, 500) — anggap tidak terautentikasi, jangan logout.
      // hasInitialized sudah di-set di finally dalam fetchUser.
    }
  }

  const isAuthenticated = !!authStore.user;

  // Redirect ke /login jika halaman butuh autentikasi
  if (AUTH_REQUIRED_ROUTES.includes(to.name as string) && !isAuthenticated) {
    return next({ name: 'Login', query: { redirect: to.fullPath } });
  }

  // Redirect ke /profile jika sudah login tapi masih ke halaman guest
  if (GUEST_ONLY_ROUTES.includes(to.name as string) && isAuthenticated) {
    return next({ name: 'Profile' });
  }

  // CART-001: Guard keranjang kosong — redirect ke /cart jika checkout tanpa item
  if (to.name === 'Checkout') {
    const { useCartStore } = await import('../stores/cartStore');
    const cartStore = useCartStore();
    if (cartStore.items.length === 0) {
      return next({ name: 'Cart' });
    }
  }

  next();
});

router.afterEach((to) => {
  document.title = (to.meta.title as string | undefined) || 'Optik Medio | Pengalaman Belanja Optik';
});

export default router;
