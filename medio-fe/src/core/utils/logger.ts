/**
 * logger — wrapper terpusat untuk semua logging frontend.
 *
 * Tujuan (P1-13 Phase 3):
 * 1. Hilangkan kebocoran info ke browser console di production
 *    (`console.log`/`error`/`warn` tetap aktif kalau dibiarkan).
 * 2. Siapkan integrasi error tracker (Sentry / Bugsnag) di Phase 6
 *    tanpa harus refactor lagi — cukup isi `window.Sentry?.captureException`
 *    di dalam fungsi ini.
 * 3. Kurangi noise saat dev: tetap log via console, tapi sembunyikan
 *    di production build (kecuali error / warn yang punya nilai diagnostic).
 *
 * Usage:
 * ```ts
 * import { logger } from '@/core/utils/logger';
 * logger.error('Failed to fetch wishlist', error);
 * logger.warn('Image fallback', { url });
 * logger.info('Cart synced', { count: items.length });
 * logger.debug('Calc trace', payload); // hanya muncul di dev
 * ```
 */

type LogContext = unknown;

const isDev = import.meta.env.DEV === true;
const isProd = import.meta.env.PROD === true;

/**
 * Kirim error ke tracker eksternal (Sentry / Bugsnag) jika tersedia.
 * Phase 6 (OBS-1) akan inject SDK-nya — saat ini stub aman.
 */
function reportToTracker(level: 'error' | 'warn', message: string, ctx?: LogContext): void {
  if (!isProd) return;

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const sentry = (window as any).Sentry as
    | { captureException?: (e: unknown, opts?: unknown) => void; captureMessage?: (m: string, l?: string) => void }
    | undefined;

  if (!sentry) return;

  try {
    if (ctx instanceof Error) {
      sentry.captureException?.(ctx, { extra: { message }, level });
    } else if (ctx !== undefined) {
      sentry.captureMessage?.(`${message} | ${safeStringify(ctx)}`, level);
    } else {
      sentry.captureMessage?.(message, level);
    }
  } catch {
    // tracker boleh gagal diam-diam; jangan ganggu UX
  }
}

function safeStringify(value: unknown): string {
  try {
    if (value instanceof Error) return value.message;
    return JSON.stringify(value);
  } catch {
    return String(value);
  }
}

/**
 * Pilih primary context dari rest args.
 * - Kalau hanya 1 arg → itu primary
 * - Kalau lebih → cari Error instance dulu, fallback ke arg pertama
 * - Sisa args di-attach ke `extra` untuk debugging tracker
 */
function pickContext(rest: unknown[]): LogContext {
  if (rest.length === 0) return undefined;
  if (rest.length === 1) return rest[0];

  const errorIdx = rest.findIndex((r) => r instanceof Error);
  if (errorIdx >= 0) {
    const err = rest[errorIdx] as Error;
    // Attach extra args sebagai metadata di error.cause
    const extras = rest.filter((_, i) => i !== errorIdx);
    if (extras.length > 0) {
      try {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (err as any).cause = (err as any).cause ?? { extras };
      } catch {
        // some Error subclasses are frozen
      }
    }
    return err;
  }

  return rest;
}

export const logger = {
  /** Untuk error yang user-affecting atau crash — selalu di-report. */
  error(message: string, ...rest: unknown[]): void {
    const ctx = pickContext(rest);
    if (isDev) {
      // eslint-disable-next-line no-console
      console.error(message, ...rest);
    }
    reportToTracker('error', message, ctx);
  },

  /** Untuk situasi suspicious tapi tidak fatal (fallback, retry, dst). */
  warn(message: string, ...rest: unknown[]): void {
    const ctx = pickContext(rest);
    if (isDev) {
      // eslint-disable-next-line no-console
      console.warn(message, ...rest);
    }
    reportToTracker('warn', message, ctx);
  },

  /** Info operasional — hanya muncul di dev. */
  info(message: string, ...rest: unknown[]): void {
    if (isDev) {
      // eslint-disable-next-line no-console
      console.info(message, ...rest);
    }
  },

  /** Debug trace — hanya muncul di dev. */
  debug(message: string, ...rest: unknown[]): void {
    if (isDev) {
      // eslint-disable-next-line no-console
      console.debug(message, ...rest);
    }
  },
};

export type Logger = typeof logger;
