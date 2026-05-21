// TOOL-1 (Phase 5): ESLint flat config (v9+).
//
// Memakai @vue/eslint-config-typescript untuk integrasi Vue + TS yang clean,
// plus eslint-plugin-vuejs-accessibility untuk catch a11y issue di template.
// eslint-config-prettier menonaktifkan rule yang konflik dengan Prettier
// (pakai Prettier sebagai single source of truth untuk formatting).

import vueA11y from 'eslint-plugin-vuejs-accessibility';
import vueTsConfig from '@vue/eslint-config-typescript';
import prettier from 'eslint-config-prettier';

export default [
  // Ignore generated / vendor folders
  {
    ignores: [
      'dist/**',
      'node_modules/**',
      '.vite/**',
      'stats.html',
      'public/**',
      // scripts/ punya konvensi sendiri (Node ESM helpers)
      '../scripts/**',
    ],
  },

  // Vue + TS recommended config
  ...vueTsConfig(),

  // Vue accessibility plugin
  {
    plugins: { 'vuejs-accessibility': vueA11y },
    rules: {
      'vuejs-accessibility/alt-text': 'warn',
      'vuejs-accessibility/anchor-has-content': 'warn',
      'vuejs-accessibility/aria-props': 'error',
      'vuejs-accessibility/click-events-have-key-events': 'warn',
      'vuejs-accessibility/heading-has-content': 'warn',
      'vuejs-accessibility/iframe-has-title': 'error',
      'vuejs-accessibility/label-has-for': 'off', // banyak Tailwind label tidak punya `for`
      'vuejs-accessibility/no-autofocus': 'warn',
      'vuejs-accessibility/no-redundant-roles': 'warn',
      'vuejs-accessibility/role-has-required-aria-props': 'error',
    },
  },

  // Project-wide rules tweaks
  {
    rules: {
      'vue/multi-word-component-names': 'off', // banyak page component single-word (Profile, Cart, dst)
      'vue/no-v-html': 'warn', // diingatkan, tapi kita sudah pakai DOMPurify
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
      'no-console': ['warn', { allow: ['warn', 'error'] }], // logger.ts tetap OK karena pakai eslint-disable
    },
  },

  // Disable rules yang konflik dengan Prettier (last in array)
  prettier,
];
