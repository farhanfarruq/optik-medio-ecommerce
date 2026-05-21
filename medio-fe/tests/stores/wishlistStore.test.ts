import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

vi.mock('../../src/core/api/axiosclient', () => ({
  apiClient: {
    get: vi.fn().mockResolvedValue({ data: { data: [] } }),
    post: vi.fn().mockResolvedValue({ data: { share_url: 'https://example.test/wishlist/abc' } }),
    delete: vi.fn().mockResolvedValue({ data: {} }),
  },
  apiOrigin: 'http://localhost:8000',
  apiBaseUrl: 'http://localhost:8000/api',
  bootstrapCsrfCookie: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('../../src/repositories/AuthRepository', () => ({
  authRepository: {
    me: vi.fn().mockResolvedValue({ id: 1, name: 'Test' }),
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    verifyOtp: vi.fn(),
    resendOtp: vi.fn(),
  },
}));

beforeEach(() => {
  setActivePinia(createPinia());
});

import { useWishlistStore } from '../../src/stores/wishlistStore';

describe('wishlistStore', () => {
  it('starts empty', () => {
    const wishlist = useWishlistStore();
    expect(wishlist.items).toEqual([]);
  });

  it('isWishlisted returns false initially', () => {
    const wishlist = useWishlistStore();
    expect(wishlist.isWishlisted(1)).toBe(false);
  });

  it('isWishlisted returns true after adding to local state', () => {
    const wishlist = useWishlistStore();
    // simulasikan state setelah fetchWishlist sukses
    wishlist.items.push({ id: 42, name: 'X', price: 100, image_url: '' } as any);
    expect(wishlist.isWishlisted(42)).toBe(true);
  });
});
