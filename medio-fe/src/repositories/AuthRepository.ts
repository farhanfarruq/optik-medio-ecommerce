import { apiClient, bootstrapCsrfCookie } from '../core/api/axiosclient';

class AuthRepository {
  async login(credentials: any) {
    await bootstrapCsrfCookie();
    const { data } = await apiClient.post('/auth/login', credentials);
    return data;
  }

  async register(userData: any) {
    await bootstrapCsrfCookie();
    const { data } = await apiClient.post('/auth/register', userData);
    return data;
  }

  async verifyOtp(payload: { email: string; code: string }) {
    await bootstrapCsrfCookie();
    const { data } = await apiClient.post('/auth/verify-otp', payload);
    return data;
  }

  async resendOtp(email: string) {
    await bootstrapCsrfCookie();
    const { data } = await apiClient.post('/auth/resend-otp', { email });
    return data;
  }

  async logout() {
    await bootstrapCsrfCookie();
    await apiClient.post('/auth/logout');
  }

  async getUser() {
    const { data } = await apiClient.get('/auth/me');
    return data;
  }
}

export const authRepository = new AuthRepository();
