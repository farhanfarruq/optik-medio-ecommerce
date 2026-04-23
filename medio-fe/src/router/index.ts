import { createRouter, createWebHistory } from 'vue-router';
import DefaultLayout from '../components/layout/DefaultLayout.vue';
import Home from '../views/Product.vue'; // Assuming Home is the product list for now
import ProductDetail from '../views/ProductDetail.vue';
import Login from '../views/Login.vue';
import Register from '../views/Register.vue';
import CartView from '../views/CartView.vue';
import Profile from '../views/Profile.vue';
import CheckoutView from '../views/checkout/CheckoutView.vue';
import OrderDetail from '../views/OrderDetail.vue';

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
      { path: 'checkout', name: 'Checkout', component: CheckoutView },
      { path: 'orders/:id', name: 'OrderDetail', component: OrderDetail },
    ]
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes
});

router.beforeEach(async (_to, _from, next) => {
  const authStore = (await import('../stores/authStore')).useAuthStore();
  
  if (authStore.token && !authStore.user) {
    await authStore.fetchUser();
  }

  next();
});

export default router;