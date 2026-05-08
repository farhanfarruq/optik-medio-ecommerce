<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['app' => config('app.name'), 'version' => '1.0.0']));

Route::get('/sitemap.xml', [\App\Http\Controllers\API\SitemapController::class, 'index']);
