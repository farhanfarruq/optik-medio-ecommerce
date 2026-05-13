import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],

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
