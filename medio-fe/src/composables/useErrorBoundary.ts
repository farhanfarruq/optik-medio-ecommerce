/**
 * useErrorBoundary — global error handler untuk Vue app.
 * Menangkap unhandled errors dan promise rejections,
 * log ke console (dan bisa dikirim ke error monitoring service).
 */

import { onErrorCaptured, ref } from 'vue';
import { logger } from '../core/utils/logger';

export interface AppError {
  message: string;
  stack?: string;
  timestamp: string;
  url: string;
  type: 'vue' | 'unhandled' | 'promise';
}

const errorLog = ref<AppError[]>([]);
const MAX_LOG = 50;

function captureError(error: unknown, type: AppError['type'] = 'unhandled'): void {
  const err = error instanceof Error ? error : new Error(String(error));

  const entry: AppError = {
    message: err.message,
    stack: err.stack,
    timestamp: new Date().toISOString(),
    url: window.location.href,
    type,
  };

  // Simpan ke log lokal (maks 50 entry)
  errorLog.value = [entry, ...errorLog.value].slice(0, MAX_LOG);

  // Log ke console dengan format yang jelas
  logger.error(`[AppError:${type}]`, err.message, err);

  // TODO: kirim ke error monitoring service (Sentry, Bugsnag, dll)
  // Contoh: Sentry.captureException(err, { extra: { type, url: entry.url } });
}

/**
 * Setup global error handlers — panggil sekali di main.ts.
 */
export function setupGlobalErrorHandlers(): void {
  // Unhandled promise rejections
  window.addEventListener('unhandledrejection', (event) => {
    captureError(event.reason, 'promise');
  });

  // Unhandled JS errors
  window.addEventListener('error', (event) => {
    if (event.error) {
      captureError(event.error, 'unhandled');
    }
  });
}

/**
 * Vue component error boundary — gunakan di komponen parent.
 */
export function useErrorBoundary() {
  const hasError = ref(false);
  const currentError = ref<AppError | null>(null);

  onErrorCaptured((err: unknown) => {
    captureError(err, 'vue');
    hasError.value = true;
    currentError.value = {
      message: err instanceof Error ? err.message : String(err),
      stack: err instanceof Error ? err.stack : undefined,
      timestamp: new Date().toISOString(),
      url: window.location.href,
      type: 'vue',
    };
    return false; // prevent propagation
  });

  function reset(): void {
    hasError.value = false;
    currentError.value = null;
  }

  return { hasError, currentError, errorLog, reset };
}
