<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // CSP hanya di production agar tidak memblokir resource local (http://localhost, Filament admin, dsb)
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
                    "img-src 'self' data: blob: https:",
                    "font-src 'self' data: https://fonts.gstatic.com https://fonts.googleapis.com https://fonts.bunny.net",
                    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net",
                    "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                    'connect-src ' . implode(' ', $connectSources),
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
