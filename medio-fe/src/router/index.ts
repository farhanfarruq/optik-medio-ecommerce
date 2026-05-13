import { createRouter, createWebHistory } from 'vue-router';
import DefaultLayout from '../components/layout/DefaultLayout.vue';
import Home from '../views/Product.vue';
import ProductDetail from '../views/ProductDetail.vue';
import Login from '../views/Login.vue';
import Register from '../views/Register.vue';
import CartView from '../views/CartView.vue';
import Profile from '../views/Profile.vue';
import ProductCompare from '../views/ProductCompare.vue';
import SharedWishlist from '../views/SharedWishlist.vue';
import FaceShapeQuiz from '../views/FaceShapeQuiz.vue';
import VirtualTryOn from '../views/VirtualTryOn.vue';
import CheckoutView from '../views/checkout/CheckoutView.vue';
import OrderDetail from '../views/OrderDetail.vue';
import WaitingPayment from '../views/checkout/WaitingPayment.vue';
import Tracking from '../views/Tracking.vue';
import Complaint from '../views/Complaint.vue';

// Rute yang memerlukan autentikasi
const AUTH_REQUIRED_ROUTES = ['Profile', 'Addresses', 'Prescriptions', 'Orders', 'Wishlist', 'Checkout', 'OrderDetail', 'AffiliateDashboard', 'WaitingPayment', 'Tracking', 'Complaint'];

// Rute yang hanya bisa diakses saat BELUM login
const GUEST_ONLY_ROUTES = ['Login', 'Register'];

const routes = [
  {
    path: '/',
    component: DefaultLayout,
    children: [
      { path: '', name: 'Home', component: Home, meta: { title: 'Optik Medio | Curated Lens Experience' } },
      { path: 'products', name: 'Products', component: Home, meta: { title: 'Produk | Optik Medio' } },
      { path: 'products/category/:slug', name: 'ProductsByCategory', component: Home, meta: { title: 'Kategori Produk | Optik Medio' } },
      { path: 'compare', name: 'ProductCompare', component: ProductCompare, meta: { title: 'Compare Produk | Optik Medio' } },
      { path: 'face-shape-quiz', name: 'FaceShapeQuiz', component: FaceShapeQuiz, meta: { title: 'Face Shape Quiz | Optik Medio' } },
      { path: 'virtual-try-on', name: 'VirtualTryOn', component: VirtualTryOn, meta: { title: 'Virtual Try-On | Optik Medio' } },
      { path: 'products/:slug', name: 'ProductDetail', component: ProductDetail, meta: { title: 'Detail Produk | Optik Medio' } },
      { path: 'login', name: 'Login', component: Login, meta: { title: 'Masuk | Optik Medio' } },
      { path: 'register', name: 'Register', component: Register, meta: { title: 'Daftar | Optik Medio' } },
      { path: 'cart', name: 'Cart', component: CartView, meta: { title: 'Keranjang | Optik Medio' } },
      { path: 'profile', name: 'Profile', component: Profile, meta: { title: 'Profil | Optik Medio' } },
      { path: 'addresses', name: 'Addresses', component: Profile, meta: { title: 'Alamat | Optik Medio' } },
      { path: 'prescriptions', name: 'Prescriptions', component: Profile, meta: { title: 'Resep Optik | Optik Medio' } },
      { path: 'orders', name: 'Orders', component: Profile, meta: { title: 'Pesanan | Optik Medio' } },
      { path: 'affiliate', name: 'AffiliateDashboard', component: Profile, meta: { title: 'Affiliate | Optik Medio' } },
      { path: 'wishlist/shared/:token', name: 'SharedWishlist', component: SharedWishlist, meta: { title: 'Shared Wishlist | Optik Medio' } },
      { path: 'wishlist', name: 'Wishlist', component: Profile, meta: { title: 'Wishlist | Optik Medio' } },
      { path: 'checkout', name: 'Checkout', component: CheckoutView, meta: { title: 'Checkout | Optik Medio' } },
      { path: 'waiting-payment/:id', name: 'WaitingPayment', component: WaitingPayment, meta: { title: 'Menunggu Pembayaran | Optik Medio' } },
      { path: 'tracking/:id', name: 'Tracking', component: Tracking, meta: { title: 'Pelacakan Pesanan | Optik Medio' } },
      { path: 'complaints/new', name: 'Complaint', component: Complaint, meta: { title: 'Komplain | Optik Medio' } },
      { path: 'complaints/:id', name: 'ComplaintDetail', component: () => import('../views/ComplaintDetail.vue'), meta: { title: 'Detail Komplain | Optik Medio' } },
      { path: 'orders/:id', name: 'OrderDetail', component: OrderDetail, meta: { title: 'Detail Pesanan | Optik Medio' } },
      // Blog routes
      { path: 'blog', name: 'Blog', component: () => import('../views/blog/ArticleList.vue'), meta: { title: 'Blog & Artikel | Optik Medio' } },
      { path: 'blog/:slug', name: 'ArticleDetail', component: () => import('../views/blog/ArticleDetail.vue'), meta: { title: 'Artikel | Optik Medio' } },
      // Halaman Legal statis
      { path: 'privacy', name: 'Privacy', component: () => import('../views/legal/PrivacyView.vue'), meta: { title: 'Kebijakan Privasi | Optik Medio' } },
      { path: 'terms', name: 'Terms', component: () => import('../views/legal/TermsView.vue'), meta: { title: 'Syarat & Ketentuan | Optik Medio' } },
      { path: 'faq', name: 'FAQ', component: () => import('../views/legal/FAQView.vue'), meta: { title: 'FAQ | Optik Medio' } },
      // Referral
      { path: 'referral', name: 'Referral', component: () => import('../views/ReferralPage.vue'), meta: { title: 'Program Referral | Optik Medio' } },
      { path: 'referral/:code', name: 'ReferralLanding', component: () => import('../views/ReferralPage.vue'), meta: { title: 'Bergabung dengan Referral | Optik Medio' } },
      // Appointment & Warranty
      { path: 'appointment', name: 'Appointment', component: () => import('../views/AppointmentPage.vue'), meta: { title: 'Booking Appointment | Optik Medio' } },
      { path: 'warranty', name: 'Warranty', component: () => import('../views/WarrantyPage.vue'), meta: { title: 'Garansi & Servis | Optik Medio' } },
      // Category, Brand, Loyalty landing pages
      { path: 'c/:slug', name: 'CategoryLanding', component: () => import('../views/CategoryLanding.vue'), meta: { title: 'Kategori | Optik Medio' } },
      { path: 'brand/:brand', name: 'BrandLanding', component: () => import('../views/BrandLanding.vue'), meta: { title: 'Merek | Optik Medio' } },
      { path: 'loyalty', name: 'Loyalty', component: () => import('../views/LoyaltyPage.vue'), meta: { title: 'Loyalty Points | Optik Medio' } },
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
      await authStore.logout({ silent: true });
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
  document.title = (to.meta.title as string | undefined) || 'Optik Medio | Curated Lens Experience';
});

export default router;
