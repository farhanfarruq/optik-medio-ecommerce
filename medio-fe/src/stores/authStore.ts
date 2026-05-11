import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authRepository } from '../repositories/AuthRepository';

export const useAuthStore = defineStore('auth', () => {
  const user = ref<any>(null);
  const hasInitialized = ref(false);

  const isAuthenticated = computed(() => !!user.value);

  async function login(credentials: any) {
    const response = await authRepository.login(credentials);

    if (response.requires_otp) {
      throw { response: { data: response, status: 403 } };
    }

    user.value = response.user ?? await authRepository.getUser();
    hasInitialized.value = true;
  }

  async function register(userData: any) {
    const response = await authRepository.register(userData);
    return response;
  }

  async function verifyOtp(email: string, code: string) {
    const response = await authRepository.verifyOtp({ email, code });
    user.value = response.user ?? await authRepository.getUser();
    hasInitialized.value = true;
    return response;
  }

  async function resendOtp(email: string) {
    return await authRepository.resendOtp(email);
  }

  async function logout(options: { silent?: boolean } = {}) {
    try {
      await authRepository.logout();
    } catch (error) {
      if (!options.silent) {
        throw error;
      }
    } finally {
      user.value = null;
      hasInitialized.value = true;
    }
  }

  async function fetchUser() {
    try {
      user.value = await authRepository.getUser();
      return user.value;
    } catch (error: any) {
      if (error.response?.status === 401) {
        user.value = null;
        return null;
      }

      throw error;
    } finally {
      hasInitialized.value = true;
    }
  }

  return { user, hasInitialized, isAuthenticated, login, register, verifyOtp, resendOtp, logout, fetchUser };
});
