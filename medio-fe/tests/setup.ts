// Vitest global setup — runs sebelum semua test file.
//
// 1. Setup Pinia testing (otomatis fresh store per test)
// 2. Stub `import.meta.env` untuk prevent error di logger.ts
// 3. Suppress console noise saat test (kecuali assert manual)

import { afterEach, beforeEach, vi } from 'vitest';
import { config } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

// Setup fresh Pinia instance per test
beforeEach(() => {
  setActivePinia(createPinia());
});

afterEach(() => {
  vi.restoreAllMocks();
});

// Vue Test Utils global config — register stub jika diperlukan
config.global.stubs = {
  'router-link': true,
  'router-view': true,
  Teleport: true,
};

// Stub window.matchMedia (jsdom tidak provide)
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
});

// Stub IntersectionObserver
class MockIntersectionObserver {
  observe = vi.fn();
  unobserve = vi.fn();
  disconnect = vi.fn();
}
// eslint-disable-next-line @typescript-eslint/no-explicit-any
(window as any).IntersectionObserver = MockIntersectionObserver;
