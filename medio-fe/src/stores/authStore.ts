import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authRepository } from '../repositories/AuthRepository';

export const useAuthStore = defineStore('auth', () => {
  const user = ref<any>(null);
  const token = ref<string | null>(localStorage.getItem('auth_token'));

  const isAuthenticated = computed(() => !!token.value);

  async function login(credentials: any) {
    const response = await authRepository.login(credentials);
    token.value = response.token;
    user.value = response.user;
    localStorage.setItem('auth_token', response.token);
  }

  function logout() {
    authRepository.logout();
    token.value = null;
    user.value = null;
    localStorage.removeItem('auth_token');
  }

  async function fetchUser() {
    if (!token.value) return;
    try {
      user.value = await authRepository.getUser();
    } catch (error) {
      console.error('Failed to fetch user', error);
      logout();
    }
  }

  return { user, token, isAuthenticated, login, logout, fetchUser };
});