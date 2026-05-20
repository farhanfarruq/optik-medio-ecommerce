<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders middleware
 *
 * Set HTTP security headers untuk semua response.
 *
 * P1-4 (Phase 2):
 *  - `'unsafe-eval'` DIHAPUS dari script-src production (Vite 5 build output
 *    tidak butuh eval untuk Vue 3 production bundle)
 *  - Ditambah: object-src 'none', frame-src 'none', form-action 'self',
 *    worker-src 'self' blob:, manifest-src 'self', base-uri tetap 'self'
 *  - 'unsafe-inline' di script-src dipertahankan sementara — migrasi ke
 *    nonce-based CSP ada di Phase 6 (membutuhkan rework template). Lihat
 *    AUDIT_KOMPREHENSIF.md untuk follow-up note.
 *  - 'unsafe-inline' di style-src tetap (Vue scoped style + Tailwind butuh ini)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), accelerometer=()'
        );

        // Cross-Origin headers — proteksi tambahan terhadap Spectre-class attacks.
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');

        // CSP hanya di production agar tidak memblokir resource local
        // (http://localhost, Filament admin dev, Vite HMR, dsb).
        if (app()->environment('production')) {
            $frontendUrl = config('app.frontend_url');
            $frontendOrigin = '';

            if ($frontendUrl) {
                $scheme = parse_url($frontendUrl, PHP_URL_SCHEME);
                $host = parse_url($frontendUrl, PHP_URL_HOST);
                $port = parse_url($frontendUrl, PHP_URL_PORT);

                if ($scheme && $host) {
                    $frontendOrigin = $scheme . '://' . $host . ($port ? ':' . $port : '');
                }
            }

            $connectSources = array_filter([
                "'self'",
                $frontendOrigin,
            ]);

            $response->headers->set(
                'Content-Security-Policy',
                implode('; ', [
                    "default-src 'self'",
                    "base-uri 'self'",
                    "frame-ancestors 'self'",
                    "frame-src 'none'",
                    "object-src 'none'",
                    "form-action 'self'",
                    "manifest-src 'self'",
                    "worker-src 'self' blob:",
                    "img-src 'self' data: blob: https:",
                    "media-src 'self' data: blob:",
                    "font-src 'self' data: https://fonts.gstatic.com https://fonts.googleapis.com https://fonts.bunny.net",
                    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net",
                    // FOLLOW-UP (Phase 6 — OBS-1 area): migrate ke nonce-based CSP
                    // dengan menghapus 'unsafe-inline'. Butuh rework template
                    // Filament + Vue inline scripts.
                    "script-src 'self' 'unsafe-inline'",
                    'connect-src ' . implode(' ', $connectSources),
                    'upgrade-insecure-requests',
                ])
            );

            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        return $response;
    }
}
