/**
 * OBS-1 (Phase 6): TEMPLATE untuk integrasi Sentry frontend.
 *
 * Setup pertama:
 * 1. Install: `npm i @sentry/vue`
 * 2. Set env: `VITE_SENTRY_DSN=https://...@sentry.io/...`
 * 3. Import & call `setupSentry(app, router)` di src/main.ts SEBELUM
 *    `app.mount(...)`.
 * 4. Rename file ini dari .example.ts → .ts.
 *
 * Setelah setup, `logger.ts` otomatis akan kirim error/warn ke Sentry
 * (lewat global `window.Sentry` API) — TIDAK perlu refactor call-site.
 *
 * Reference:
 *   https://docs.sentry.io/platforms/javascript/guides/vue/
 */

// import * as Sentry from '@sentry/vue';
// import type { App } from 'vue';
// import type { Router } from 'vue-router';

// export function setupSentry(app: App, router: Router): void {
//   const dsn = import.meta.env.VITE_SENTRY_DSN;
//   if (!dsn) return; // dev / staging tanpa DSN

//   Sentry.init({
//     app,
//     dsn,
//     environment: import.meta.env.MODE,
//     release: import.meta.env.VITE_APP_VERSION ?? 'unknown',
//     integrations: [
//       Sentry.browserTracingIntegration({ router }),
//       Sentry.replayIntegration({
//         maskAllText: true,
//         blockAllMedia: true,
//       }),
//     ],
//     tracesSampleRate: 0.1, // 10% trace
//     replaysSessionSampleRate: 0.05,
//     replaysOnErrorSampleRate: 1.0,
//     beforeSend(event) {
//       // Filter PII / data sensitif sebelum kirim ke Sentry.
//       // Contoh: hapus password, token, credit card.
//       if (event.request?.cookies) delete event.request.cookies;
//       return event;
//     },
//   });

//   // Expose ke window agar `logger.ts` bisa pakai tanpa import circular.
//   (window as unknown as { Sentry: typeof Sentry }).Sentry = Sentry;
// }

export {};
