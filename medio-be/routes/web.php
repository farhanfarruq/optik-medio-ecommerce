<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Routes di file ini diakses dari domain backend (mis. api.optikmedio.com)
| sebagai HTML/XML, bukan JSON API.
|
| Untuk SEO, sitemap.xml WAJIB dapat diakses dari domain frontend
| (https://optikmedio.com/sitemap.xml). Di production, gunakan reverse-proxy
| (Nginx/Cloudflare) dengan rule:
|
|     location = /sitemap.xml {
|         proxy_pass https://api.optikmedio.com/sitemap.xml;
|     }
|
| Atau redirect dari root frontend ke endpoint backend.
|
*/

Route::get('/', fn () => response()->json(['app' => config('app.name'), 'version' => '1.0.0']));

// Sitemap utama — dilayani di root domain (sesuai konvensi RFC 9309 & robots.txt)
Route::get('/sitemap.xml', [\App\Http\Controllers\API\SitemapController::class, 'index'])
    ->name('sitemap');

// Backward-compat: alias /api/sitemap → /sitemap.xml
// (search engine tidak akan crawl /api/* karena di-disallow di robots.txt,
// tapi alias ini tetap ada agar tooling internal yang sudah pakai URL lama
// tidak break.)
Route::get('/api/sitemap', fn () => redirect('/sitemap.xml', 301));
