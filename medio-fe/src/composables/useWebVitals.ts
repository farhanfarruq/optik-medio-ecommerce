/**
 * useWebVitals — measure Core Web Vitals (LCP, CLS, INP/FID, TTFB).
 *
 * Strategi pengiriman:
 * - LCP: kirim sekali saat nilai final tersedia
 * - CLS: kirim sekali saat page hide
 * - INP: kirim nilai TERBURUK sekali saat page hide (bukan setiap interaksi)
 * - TTFB: kirim sekali saat mount
 *
 * Tidak memerlukan library eksternal.
 */

import { apiClient } from '../core/api/axiosclient';

interface VitalEntry {
  name: string;
  value: number;
  rating: 'good' | 'needs-improvement' | 'poor';
}

function getRating(name: string, value: number): VitalEntry['rating'] {
  const thresholds: Record<string, [number, number]> = {
    LCP:  [2500, 4000],
    CLS:  [0.1,  0.25],
    INP:  [200,  500],
    FID:  [100,  300],
    TTFB: [800,  1800],
  };

  const [good, poor] = thresholds[name] || [0, 0];
  if (value <= good) return 'good';
  if (value <= poor) return 'needs-improvement';
  return 'poor';
}

function sendVital(entry: VitalEntry): void {
  apiClient.post('/events', {
    event_type: 'filter_used',
    payload: {
      type:   'web_vital',
      name:   entry.name,
      value:  Math.round(entry.value),
      rating: entry.rating,
      url:    window.location.pathname,
    },
  }).catch(() => {/* silent */});

  if (import.meta.env.DEV) {
    const icon = entry.rating === 'good' ? '🟢' : entry.rating === 'needs-improvement' ? '🟡' : '🔴';
    console.log(`${icon} [WebVital] ${entry.name}: ${Math.round(entry.value)}${entry.name === 'CLS' ? '' : 'ms'} (${entry.rating})`);
  }
}

export function setupWebVitals(): void {
  if (typeof window === 'undefined' || !('PerformanceObserver' in window)) {
    return;
  }

  // ── LCP — kirim sekali saat nilai final ──────────────────────────────────
  try {
    let lcpSent = false;
    const lcpObserver = new PerformanceObserver((list) => {
      if (lcpSent) return;
      const entries = list.getEntries();
      const last = entries[entries.length - 1] as any;
      if (last) {
        lcpSent = true;
        sendVital({ name: 'LCP', value: last.startTime, rating: getRating('LCP', last.startTime) });
        lcpObserver.disconnect();
      }
    });
    lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });
  } catch { /* not supported */ }

  // ── CLS — akumulasi, kirim sekali saat page hide ─────────────────────────
  try {
    let clsValue = 0;
    let clsSent  = false;
    const clsObserver = new PerformanceObserver((list) => {
      for (const entry of list.getEntries() as any[]) {
        if (!entry.hadRecentInput) {
          clsValue += entry.value;
        }
      }
    });
    clsObserver.observe({ type: 'layout-shift', buffered: true });

    window.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden' && !clsSent) {
        clsSent = true;
        sendVital({ name: 'CLS', value: clsValue, rating: getRating('CLS', clsValue) });
      }
    }, { once: true });
  } catch { /* not supported */ }

  // ── INP — simpan nilai TERBURUK, kirim sekali saat page hide ─────────────
  // PENTING: jangan kirim setiap interaksi — itu akan spam ratusan request
  try {
    let worstInp = 0;
    let inpSent  = false;

    const inpObserver = new PerformanceObserver((list) => {
      for (const entry of list.getEntries() as any[]) {
        if (entry.duration > worstInp) {
          worstInp = entry.duration;
        }
      }
    });
    inpObserver.observe({ type: 'event', buffered: true, durationThreshold: 40 } as any);

    window.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden' && !inpSent && worstInp > 0) {
        inpSent = true;
        sendVital({ name: 'INP', value: worstInp, rating: getRating('INP', worstInp) });
      }
    }, { once: true });
  } catch { /* not supported */ }

  // ── TTFB — kirim sekali saat mount ───────────────────────────────────────
  try {
    const navEntries = performance.getEntriesByType('navigation') as PerformanceNavigationTiming[];
    if (navEntries.length > 0) {
      const ttfb = navEntries[0].responseStart;
      if (ttfb > 0) {
        sendVital({ name: 'TTFB', value: ttfb, rating: getRating('TTFB', ttfb) });
      }
    }
  } catch { /* not supported */ }
}
