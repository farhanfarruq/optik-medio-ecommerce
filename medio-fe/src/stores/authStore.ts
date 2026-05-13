import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authRepository } from '../repositories/AuthRepository';
import { apiClient } from '../core/api/axiosclient';

export const useAuthStore = defineStore('auth', () => {
  const user = ref<any>(null);
  const hasInitialized = ref(false);

  const isAuthenticated = computed(() => !!user.value);

  /**
   * Sync cart lokal ke server setelah login.
   * Ambil items dari cartStore (localStorage) dan kirim ke /api/cart/sync.
   */
  async function syncCartAfterLogin(): Promise<void> {
    try {
      // Import dinamis untuk menghindari circular dependency
      const { useCartStore } = await import('./cartStore');
      const cartStore = useCartStore();

      if (cartStore.items.length === 0) return;

      const itemsPayload = cartStore.items.map((item: any) => ({
        product_id:              item.id,
        quantity:                item.quantity || 1,
        variant:                 item.variant || null,
        prescription:            item.prescription || null,
        lens_option_id:          item.lens_option_id || null,
        lens_coating_id:         item.lens_coating_id || null,
        prescription_profile_id: item.prescription_profile_id || null,
        configuration_snapshot:  item.configuration_snapshot || null,
      }));

      await apiClient.post('/cart/sync', { items: itemsPayload });
    } catch {
      // Silent — cart sync tidak boleh block login flow
    }
  }

  async function login(credentials: any) {
    const response = await authRepository.login(credentials);

    if (response.requires_otp) {
      throw { response: { data: response, status: 403 } };
    }

    user.value = response.user ?? await authRepository.getUser();
    hasInitialized.value = true;

    // Sync cart lokal ke server setelah login berhasil
    await syncCartAfterLogin();
  }

  async function register(userData: any) {
    const response = await authRepository.register(userData);
    return response;
  }

  async function verifyOtp(email: string, code: string) {
    const response = await authRepository.verifyOtp({ email, code });
    user.value = response.user ?? await authRepository.getUser();
    hasInitialized.value = true;

    // Sync cart setelah OTP verified (login via OTP)
    await syncCartAfterLogin();

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
