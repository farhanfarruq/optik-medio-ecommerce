import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { visualizer } from 'rollup-plugin-visualizer'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    // PERF-3 (Phase 4): bundle visualizer.
    // Saat `npm run build`, file `medio-fe/stats.html` di-generate untuk
    // analisis interaktif size per chunk. Buka di browser, sortir by size.
    // Set ANALYZE=1 untuk auto-open: `ANALYZE=1 npm run build`.
    visualizer({
      filename: 'stats.html',
      title: 'Optik Medio Frontend — Bundle Analysis',
      gzipSize: true,
      brotliSize: true,
      open: process.env.ANALYZE === '1',
    }),
  ],

  server: {
    proxy: {
      // Proxy /storage requests to Laravel backend to bypass CORS on canvas operations
      '/storage': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        secure: false,
      },
    },
  },

  // P1-13 (Phase 3 / TOOL-4 Phase 5):
  // Strip semua console.* dan debugger dari production bundle.
  // logger.ts sudah dibuat untuk centralized logging — apapun yang lolos
  // (mis. dari dependency 3rd-party) akan di-drop di sini sebagai safety net.
  esbuild: {
    drop: process.env.NODE_ENV === 'production' ? ['console', 'debugger'] : [],
  },

  build: {
    // Target modern browsers untuk output lebih kecil
    target: 'es2020',

    // Chunk splitting untuk lazy loading yang lebih efisien
    rollupOptions: {
      output: {
        manualChunks: {
          // Vendor chunk: Vue ecosystem
          'vendor-vue': ['vue', 'vue-router', 'pinia'],
          // Vendor chunk: utilities
          'vendor-utils': ['axios', 'dompurify', '@vueuse/core'],
        },
      },
    },

    // Batas warning chunk size (kB)
    chunkSizeWarningLimit: 600,

    // Minify CSS
    cssMinify: true,
  },

  // Optimasi dependency pre-bundling
  optimizeDeps: {
    include: ['vue', 'vue-router', 'pinia', 'axios'],
  },
})
