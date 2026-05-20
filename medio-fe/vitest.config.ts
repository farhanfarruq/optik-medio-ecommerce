// TOOL-3 (Phase 5): Vitest configuration.
//
// Pakai jsdom environment untuk DOM simulation di Vue component tests.
// Auto-mock Pinia setup di tests/setup.ts.
//
// Run: npm run test       (watch mode)
// Run: npm run test:run   (single run, CI-mode)

import { fileURLToPath, URL } from 'node:url';
import { defineConfig, mergeConfig } from 'vitest/config';
import viteConfig from './vite.config';

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      globals: true, // expose `describe`, `it`, `expect` global
      setupFiles: ['./tests/setup.ts'],
      include: ['tests/**/*.{test,spec}.{ts,vue}', 'src/**/*.{test,spec}.{ts,vue}'],
      exclude: ['node_modules/**', 'dist/**', '.vite/**'],
      coverage: {
        provider: 'v8',
        reporter: ['text', 'html', 'lcov'],
        exclude: [
          'node_modules/**',
          'dist/**',
          '**/*.config.{js,ts}',
          'tests/**',
          'src/main.ts',
        ],
        thresholds: {
          // Initial baseline — naikkan target seiring coverage tumbuh.
          lines: 5,
          functions: 5,
          branches: 5,
          statements: 5,
        },
      },
    },
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
  })
);
