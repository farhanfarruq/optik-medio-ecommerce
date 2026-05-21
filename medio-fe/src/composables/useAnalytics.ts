/**
 * useAnalytics — composable untuk tracking business events ke backend.
 * Fire-and-forget: tidak throw error, tidak block UI.
 *
 * Event types yang didukung:
 * - product_viewed
 * - add_to_cart
 * - checkout_started
 * - shipping_selected
 * - payment_selected
 * - search_no_result
 * - checkout_failed
 * - filter_used
 */

import { apiClient } from '../core/api/axiosclient';

// Session ID sederhana berbasis localStorage
function getSessionId(): string {
  const key = 'om_session_id';
  let id = sessionStorage.getItem(key);
  if (!id) {
    id = Math.random().toString(36).substring(2) + Date.now().toString(36);
    sessionStorage.setItem(key, id);
  }
  return id;
}

async function track(eventType: string, payload: Record<string, unknown> = {}): Promise<void> {
  try {
    await apiClient.post('/events', {
      event_type: eventType,
      payload,
      session_id: getSessionId(),
    });
  } catch {
    // Observability tidak boleh merusak UX
  }
}

export function useAnalytics() {
  return {
    trackProductViewed: (productId: number, slug: string, name: string) =>
      track('product_viewed', { product_id: productId, slug, name }),

    trackAddToCart: (productId: number, name: string, price: number, quantity: number) =>
      track('add_to_cart', { product_id: productId, name, price, quantity }),

    trackCheckoutStarted: (itemCount: number, subtotal: number) =>
      track('checkout_started', { item_count: itemCount, subtotal }),

    trackShippingSelected: (courier: string, service: string, cost: number) =>
      track('shipping_selected', { courier, service, cost }),

    trackPaymentSelected: (paymentMethod: string) =>
      track('payment_selected', { payment_method: paymentMethod }),

    trackSearchNoResult: (query: string) =>
      track('search_no_result', { query }),

    trackCheckoutFailed: (reason: string, detail?: string) =>
      track('checkout_failed', { reason, detail }),

    trackFilterUsed: (filters: Record<string, unknown>) =>
      track('filter_used', { filters }),
  };
}
