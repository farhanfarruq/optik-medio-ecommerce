import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

// Mock module-level: stub axios client SEBELUM import store agar
// store.calculateCart() tidak mencoba akses network nyata.
vi.mock('../../src/core/api/axiosclient', () => ({
  apiClient: {
    get: vi.fn().mockResolvedValue({ data: [] }),
    post: vi.fn().mockResolvedValue({ data: { items: [], subtotal: 0, total: 0 } }),
    put: vi.fn().mockResolvedValue({ data: {} }),
    delete: vi.fn().mockResolvedValue({ data: {} }),
  },
  apiOrigin: 'http://localhost:8000',
  apiBaseUrl: 'http://localhost:8000/api',
  bootstrapCsrfCookie: vi.fn().mockResolvedValue(undefined),
}));

// Stub crypto.randomUUID (not always present in jsdom older versions).
beforeEach(() => {
  setActivePinia(createPinia());
  let counter = 0;
  vi.stubGlobal('crypto', {
    randomUUID: () => `mock-uuid-${++counter}`,
  });
});

import { useCartStore } from '../../src/stores/cartStore';

function makeFrame(overrides: Partial<any> = {}) {
  return {
    id: 1,
    name: 'Test Frame',
    price: 500_000,
    stock: 5,
    quantity: 1,
    image_url: '',
    ...overrides,
  };
}

describe('cartStore', () => {
  it('starts empty', () => {
    const cart = useCartStore();
    expect(cart.items).toEqual([]);
    expect(cart.cartTotal).toBe(0);
  });

  describe('addToCart', () => {
    it('adds a frame with auto-generated cart_id', () => {
      const cart = useCartStore();
      cart.addToCart(makeFrame() as any);
      expect(cart.items).toHaveLength(1);
      expect(cart.items[0].cart_id).toBe('mock-uuid-1');
      expect(cart.items[0].quantity).toBe(1);
    });

    it('adds a frame + lens (linked via parent_item_id)', () => {
      const cart = useCartStore();
      const frame = makeFrame();
      const lens = { id: 99, name: 'Lens X', price: 200_000, stock: 100, quantity: 1, image_url: '' };
      cart.addToCart(frame as any, undefined, lens as any);

      expect(cart.items).toHaveLength(2);
      const lensItem = cart.items.find((i) => i.id === 99);
      expect(lensItem?.parent_item_id).toBe(cart.items[0].cart_id);
    });

    it('totals price across items', () => {
      const cart = useCartStore();
      cart.addToCart(makeFrame({ id: 1, price: 100_000 }) as any);
      cart.addToCart(makeFrame({ id: 2, price: 250_000 }) as any);
      expect(cart.cartTotal).toBe(350_000);
    });
  });

  describe('updateQuantity', () => {
    it('increments quantity within stock', () => {
      const cart = useCartStore();
      cart.addToCart(makeFrame({ stock: 5 }) as any);
      const id = cart.items[0].cart_id!;
      cart.updateQuantity(id, +2);
      expect(cart.items[0].quantity).toBe(3);
    });

    it('does not exceed stock', () => {
      const cart = useCartStore();
      cart.addToCart(makeFrame({ stock: 2 }) as any);
      const id = cart.items[0].cart_id!;
      cart.updateQuantity(id, +5); // would be 6 → blocked
      expect(cart.items[0].quantity).toBe(1);
    });

    it('does not go below 1', () => {
      const cart = useCartStore();
      cart.addToCart(makeFrame() as any);
      const id = cart.items[0].cart_id!;
      cart.updateQuantity(id, -5);
      expect(cart.items[0].quantity).toBe(1);
    });

    it('ignores unknown cart_id', () => {
      const cart = useCartStore();
      cart.addToCart(makeFrame() as any);
      cart.updateQuantity('non-existent', 1);
      expect(cart.items[0].quantity).toBe(1);
    });
  });

  describe('removeFromCart', () => {
    it('removes a single item', () => {
      const cart = useCartStore();
      cart.addToCart(makeFrame() as any);
      const id = cart.items[0].cart_id!;
      cart.removeFromCart(id);
      expect(cart.items).toHaveLength(0);
    });

    it('removing parent also removes its children (linked lens)', () => {
      const cart = useCartStore();
      const frame = makeFrame();
      const lens = { id: 99, name: 'Lens', price: 100_000, stock: 100, quantity: 1, image_url: '' };
      cart.addToCart(frame as any, undefined, lens as any);
      expect(cart.items).toHaveLength(2);

      const parentId = cart.items[0].cart_id!;
      cart.removeFromCart(parentId);
      expect(cart.items).toHaveLength(0);
    });

    it('removing child does not remove parent', () => {
      const cart = useCartStore();
      const frame = makeFrame();
      const lens = { id: 99, name: 'Lens', price: 100_000, stock: 100, quantity: 1, image_url: '' };
      cart.addToCart(frame as any, undefined, lens as any);

      const lensCartId = cart.items[1].cart_id!;
      cart.removeFromCart(lensCartId);
      expect(cart.items).toHaveLength(1);
      expect(cart.items[0].id).toBe(1); // parent frame still here
    });
  });
});
