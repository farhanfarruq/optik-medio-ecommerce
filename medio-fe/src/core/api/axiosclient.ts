import axios from 'axios';

const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
const apiOrigin = new URL(apiBaseUrl, window.location.origin).origin;

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

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    const requestUrl = String(error.config?.url || '');
    const shouldSkipRedirect =
      requestUrl.includes('/auth/me') ||
      requestUrl.includes('/auth/login') ||
      requestUrl.includes('/auth/register') ||
      requestUrl.includes('/auth/verify-otp') ||
      requestUrl.includes('/auth/resend-otp') ||
      requestUrl.includes('/auth/logout') ||
      requestUrl.includes('/sanctum/csrf-cookie');

    if (error.response?.status === 401 && !shouldSkipRedirect) {
      const currentPath = window.location.pathname;
      const isGuestPage = currentPath === '/login' || currentPath === '/register';

      if (!isGuestPage) {
        const redirect = `${currentPath}${window.location.search}${window.location.hash}`;
        window.location.href = `/login?redirect=${encodeURIComponent(redirect)}`;
      }
    }

    return Promise.reject(error);
  }
);
