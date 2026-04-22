import { apiClient } from '../core/api/axiosclient';

class AuthRepository {
  async login(credentials: any) {
    const { data } = await apiClient.post('/auth/login', credentials);
    return data;
  }

  async register(userData: any) {
    const { data } = await apiClient.post('/auth/register', userData);
    return data;
  }

  async logout() {
    await apiClient.post('/auth/logout');
  }

  async getUser() {
    const { data } = await apiClient.get('/auth/me');
    return data;
  }
}

export const authRepository = new AuthRepository();