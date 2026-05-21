import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('../../src/repositories/AuthRepository', () => ({
  authRepository: {
    login: vi.fn().mockResolvedValue({ user: { id: 1, name: 'Test' } }),
    register: vi.fn().mockResolvedValue({ user: { id: 1, name: 'Test' } }),
    verifyOtp: vi.fn().mockResolvedValue({ user: { id: 1, name: 'Test' } }),
    resendOtp: vi.fn().mockResolvedValue({}),
    logout: vi.fn().mockResolvedValue({}),
    me: vi.fn().mockResolvedValue({ id: 1, name: 'Test' }),
  },
}));

vi.mock('../../src/core/api/axiosclient', () => ({
  apiClient: {
    get: vi.fn().mockResolvedValue({ data: {} }),
    post: vi.fn().mockResolvedValue({ data: {} }),
    put: vi.fn().mockResolvedValue({ data: {} }),
    delete: vi.fn().mockResolvedValue({ data: {} }),
  },
  apiOrigin: 'http://localhost:8000',
  apiBaseUrl: 'http://localhost:8000/api',
  bootstrapCsrfCookie: vi.fn().mockResolvedValue(undefined),
}));

beforeEach(() => {
  setActivePinia(createPinia());
});

import { useAuthStore } from '../../src/stores/authStore';

describe('authStore', () => {
  it('starts unauthenticated', () => {
    const auth = useAuthStore();
    expect(auth.user).toBe(null);
    expect(auth.isAuthenticated).toBe(false);
    expect(auth.hasInitialized).toBe(false);
  });

  it('sets user on successful login', async () => {
    const auth = useAuthStore();
    await auth.login({ email: 'a@b.com', password: 'secret' });
    expect(auth.user).toMatchObject({ id: 1, name: 'Test' });
    expect(auth.isAuthenticated).toBe(true);
  });

  it('sets user on successful verifyOtp', async () => {
    const auth = useAuthStore();
    await auth.verifyOtp('a@b.com', '123456');
    expect(auth.user).toMatchObject({ id: 1 });
  });

  it('clears user on logout', async () => {
    const auth = useAuthStore();
    await auth.login({ email: 'a@b.com', password: 'secret' });
    expect(auth.isAuthenticated).toBe(true);
    await auth.logout();
    expect(auth.user).toBe(null);
    expect(auth.isAuthenticated).toBe(false);
  });
});
