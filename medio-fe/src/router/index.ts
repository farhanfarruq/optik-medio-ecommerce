import { createRouter, createWebHistory } from 'vue-router';
import DefaultLayout from '../components/layout/DefaultLayout.vue';
import Home from '../views/Product.vue';
import ProductDetail from '../views/ProductDetail.vue';
import Login from '../views/Login.vue';
import Register from '../views/Register.vue';
import CartView from '../views/CartView.vue';
import Profile from '../views/Profile.vue';
import CheckoutView from '../views/checkout/CheckoutView.vue';
import OrderDetail from '../views/OrderDetail.vue';
import WaitingPayment from '../views/checkout/WaitingPayment.vue';
import Tracking from '../views/Tracking.vue';
import Complaint from '../views/Complaint.vue';

// Rute yang memerlukan autentikasi
const AUTH_REQUIRED_ROUTES = ['Profile', 'Addresses', 'Orders', 'Wishlist', 'Checkout', 'OrderDetail', 'AffiliateDashboard', 'WaitingPayment', 'Tracking', 'Complaint'];

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
      { path: 'products/:slug', name: 'ProductDetail', component: ProductDetail, meta: { title: 'Detail Produk | Optik Medio' } },
      { path: 'login', name: 'Login', component: Login, meta: { title: 'Masuk | Optik Medio' } },
      { path: 'register', name: 'Register', component: Register, meta: { title: 'Daftar | Optik Medio' } },
      { path: 'cart', name: 'Cart', component: CartView, meta: { title: 'Keranjang | Optik Medio' } },
      { path: 'profile', name: 'Profile', component: Profile, meta: { title: 'Profil | Optik Medio' } },
      { path: 'addresses', name: 'Addresses', component: Profile, meta: { title: 'Alamat | Optik Medio' } },
      { path: 'orders', name: 'Orders', component: Profile, meta: { title: 'Pesanan | Optik Medio' } },
      { path: 'affiliate', name: 'AffiliateDashboard', component: Profile, meta: { title: 'Affiliate | Optik Medio' } },
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

  next();
});

router.afterEach((to) => {
  document.title = (to.meta.title as string | undefined) || 'Optik Medio | Curated Lens Experience';
});

export default router;
