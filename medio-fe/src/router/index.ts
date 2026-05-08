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
      { path: '', name: 'Home', component: Home },
      { path: 'products', name: 'Products', component: Home },
      { path: 'products/category/:slug', name: 'ProductsByCategory', component: Home },
      { path: 'products/:slug', name: 'ProductDetail', component: ProductDetail },
      { path: 'login', name: 'Login', component: Login },
      { path: 'register', name: 'Register', component: Register },
      { path: 'cart', name: 'Cart', component: CartView },
      { path: 'profile', name: 'Profile', component: Profile },
      { path: 'addresses', name: 'Addresses', component: Profile },
      { path: 'orders', name: 'Orders', component: Profile },
      { path: 'affiliate', name: 'AffiliateDashboard', component: Profile },
      { path: 'wishlist', name: 'Wishlist', component: Profile },
      { path: 'checkout', name: 'Checkout', component: CheckoutView },
      { path: 'waiting-payment/:id', name: 'WaitingPayment', component: WaitingPayment },
      { path: 'tracking/:id', name: 'Tracking', component: Tracking },
      { path: 'complaints/new', name: 'Complaint', component: Complaint },
      { path: 'orders/:id', name: 'OrderDetail', component: OrderDetail },
      // Blog routes
      { path: 'blog', name: 'Blog', component: () => import('../views/blog/ArticleList.vue') },
      { path: 'blog/:slug', name: 'ArticleDetail', component: () => import('../views/blog/ArticleDetail.vue') },
      // Halaman Legal statis
      { path: 'privacy', name: 'Privacy', component: () => import('../views/legal/PrivacyView.vue') },
      { path: 'terms', name: 'Terms', component: () => import('../views/legal/TermsView.vue') },
      { path: 'faq', name: 'FAQ', component: () => import('../views/legal/FAQView.vue') },
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

  // Fetch user jika ada token tapi belum ada data user
  if (authStore.token && !authStore.user) {
    try {
      await authStore.fetchUser();
    } catch {
      authStore.logout();
    }
  }

  const isAuthenticated = !!authStore.token && !!authStore.user;

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

export default router;
