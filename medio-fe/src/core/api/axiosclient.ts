import axios from 'axios';

function resolveApiBaseUrl() {
  const configuredUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
  const parsedUrl = new URL(configuredUrl, window.location.origin);
  const frontendHost = window.location.hostname;
  const isLocalApiHost = parsedUrl.hostname === 'localhost' || parsedUrl.hostname === '127.0.0.1';
  const isLocalFrontendHost = frontendHost === 'localhost' || frontendHost === '127.0.0.1';

  if (isLocalApiHost && isLocalFrontendHost && parsedUrl.hostname !== frontendHost) {
    parsedUrl.hostname = frontendHost;
  }

  return parsedUrl.toString();
}

export const apiBaseUrl = resolveApiBaseUrl();
export const apiOrigin = new URL(apiBaseUrl, window.location.origin).origin;

export const apiClient = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
  headers: {
    'Accept': 'application/json'
  },
});

export async function bootstrapCsrfCookie() {
  await axios.get(`${apiOrigin}/sanctum/csrf-cookie`, {
    withCredentials: true,
    headers: {
      'Accept': 'application/json',
    },
  });
}

// Flag untuk mencegah multiple redirect ke login secara bersamaan
let isRedirectingToLogin = false;
// Flag untuk mencegah multiple CSRF refresh secara bersamaan
let isRefreshingCsrf = false;
let csrfRefreshPromise: Promise<void> | null = null;

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const requestUrl = String(error.config?.url || '');
    const shouldSkipRedirect =
      requestUrl.includes('/auth/me') ||
      requestUrl.includes('/auth/login') ||
      requestUrl.includes('/auth/register') ||
      requestUrl.includes('/auth/verify-otp') ||
      requestUrl.includes('/auth/resend-otp') ||
      requestUrl.includes('/auth/logout') ||
      requestUrl.includes('/sanctum/csrf-cookie');

    // Handle 419 CSRF mismatch — refresh CSRF dan retry request sekali
    if (error.response?.status === 419 && !error.config?._csrfRetried) {
      if (!isRefreshingCsrf) {
        isRefreshingCsrf = true;
        csrfRefreshPromise = bootstrapCsrfCookie().finally(() => {
          isRefreshingCsrf = false;
          csrfRefreshPromise = null;
        });
      }
      // Tunggu CSRF refresh selesai lalu retry
      await csrfRefreshPromise;
      error.config._csrfRetried = true;
      return apiClient(error.config);
    }

    if (error.response?.status === 401 && !shouldSkipRedirect) {
      const currentPath = window.location.pathname;
      const isGuestPage = currentPath === '/login' || currentPath === '/register';

      // Hanya redirect sekali — cegah loop dari multiple parallel requests
      if (!isGuestPage && !isRedirectingToLogin) {
        isRedirectingToLogin = true;
        const redirect = `${currentPath}${window.location.search}${window.location.hash}`;
        // Delay kecil agar request lain yang sedang berjalan tidak trigger redirect lagi
        setTimeout(() => {
          window.location.href = `/login?redirect=${encodeURIComponent(redirect)}`;
        }, 100);
      }
    }

    return Promise.reject(error);
  }
);
