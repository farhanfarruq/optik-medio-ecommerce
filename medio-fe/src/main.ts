import { createApp } from 'vue';
import { createPinia } from 'pinia';
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate';
import router from './router';
import './style.css';
import App from './App.vue';
import { setupGlobalErrorHandlers } from './composables/useErrorBoundary';
import { setupWebVitals } from './composables/useWebVitals';

const app = createApp(App);
const pinia = createPinia();
pinia.use(piniaPluginPersistedstate);

// Setup global error handlers sebelum mount
setupGlobalErrorHandlers();

// Setup Core Web Vitals measurement
setupWebVitals();

// Vue global error handler
app.config.errorHandler = (err, _instance, info) => {
  console.error('[Vue Error]', err, info);
};

// Vue global warning handler (dev only)
app.config.warnHandler = (msg, _instance, trace) => {
  if (import.meta.env.DEV) {
    console.warn('[Vue Warn]', msg, trace);
  }
};

app.use(pinia);
app.use(router);

app.mount('#app');
