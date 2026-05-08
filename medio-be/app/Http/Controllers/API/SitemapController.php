<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $products = Product::where('is_active', true)->get();
        $categories = Category::all();
        $articles = Article::published()->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Base URL dari frontend (di-set di env)
        $baseUrl = config('app.frontend_url', 'https://optikmedio.com');

        // Static routes
        $staticRoutes = [
            '/',
            '/products',
            '/categories',
            '/blog',
            '/about',
            '/contact'
        ];

        foreach ($staticRoutes as $route) {
            $xml .= '<url>';
            $xml .= '<loc>' . $baseUrl . $route . '</loc>';
            $xml .= '<changefreq>weekly</changefreq>';
            $xml .= '<priority>' . ($route === '/' ? '1.0' : '0.8') . '</priority>';
            $xml .= '</url>';
        }

        // Dynamic Products
        foreach ($products as $product) {
            $xml .= '<url>';
            $xml .= '<loc>' . $baseUrl . '/products/' . $product->slug . '</loc>';
            $xml .= '<lastmod>' . $product->updated_at->tz('UTC')->toAtomString() . '</lastmod>';
            $xml .= '<changefreq>daily</changefreq>';
            $xml .= '<priority>0.9</priority>';
            $xml .= '</url>';
        }

        // Dynamic Categories
        foreach ($categories as $category) {
            $xml .= '<url>';
            $xml .= '<loc>' . $baseUrl . '/categories/' . $category->slug . '</loc>';
            $xml .= '<lastmod>' . $category->updated_at->tz('UTC')->toAtomString() . '</lastmod>';
            $xml .= '<changefreq>weekly</changefreq>';
            $xml .= '<priority>0.7</priority>';
            $xml .= '</url>';
        }

        // Dynamic Articles
        foreach ($articles as $article) {
            $xml .= '<url>';
            $xml .= '<loc>' . $baseUrl . '/blog/' . $article->slug . '</loc>';
            $xml .= '<lastmod>' . $article->updated_at->tz('UTC')->toAtomString() . '</lastmod>';
            $xml .= '<changefreq>weekly</changefreq>';
            $xml .= '<priority>0.8</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml)->header('Content-Type', 'text/xml');
    }
}
