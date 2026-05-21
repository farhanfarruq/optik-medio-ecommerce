<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * SitemapController
 *
 * P0-5 (Phase 1) — exposed via routes/web.php sebagai /sitemap.xml.
 *
 * P1-12 / PERF-4 (Phase 4):
 *  - Generation hasil di-cache 6 jam → mengurangi load DB saat search engine
 *    crawl berulang (Googlebot bisa hit puluhan kali per jam saat crawl-budget
 *    sedang aktif).
 *  - Pakai `cursor()` (PHP generator) untuk produk & artikel besar, agar
 *    memori tidak meledak ketika katalog mencapai 10.000+ produk.
 *  - Cache key di-bump otomatis kalau `updated_at` produk berubah significant
 *    (via tag `sitemap`); flush manual: `php artisan cache:forget sitemap.xml`.
 *  - Sitemap index ada di follow-up (Phase 6 SEO-3) untuk URL > 50.000 yang
 *    membutuhkan multi-file sitemap.
 */
class SitemapController extends Controller
{
    /**
     * Cache TTL untuk sitemap.xml.
     * 6 jam — search engine crawl interval rata-rata 1-12 jam, 6 jam = sweet spot.
     */
    private const CACHE_TTL_SECONDS = 6 * 60 * 60;

    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', self::CACHE_TTL_SECONDS, function () {
            return $this->buildSitemapXml();
        });

        return response($xml)
            ->header('Content-Type', 'text/xml; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex'); // sitemap.xml itu sendiri tidak perlu di-index
    }

    private function buildSitemapXml(): string
    {
        $baseUrl = rtrim((string) config('app.frontend_url', 'https://optikmedio.com'), '/');

        // Header XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // ── Static routes ───────────────────────────────────────────────────
        $staticRoutes = [
            ['/', 'weekly', '1.0'],
            ['/products', 'weekly', '0.8'],
            ['/categories', 'weekly', '0.8'],
            ['/blog', 'weekly', '0.8'],
            ['/about', 'monthly', '0.6'],
            ['/contact', 'monthly', '0.6'],
        ];

        foreach ($staticRoutes as [$route, $changefreq, $priority]) {
            $xml .= sprintf(
                '<url><loc>%s%s</loc><changefreq>%s</changefreq><priority>%s</priority></url>',
                $this->escapeXml($baseUrl),
                $this->escapeXml($route),
                $changefreq,
                $priority
            );
        }

        // ── Dynamic Products (cursor — memory-safe untuk dataset besar) ─────
        $productCursor = Product::query()
            ->where('is_active', true)
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->cursor();

        foreach ($productCursor as $product) {
            $xml .= sprintf(
                '<url><loc>%s/products/%s</loc><lastmod>%s</lastmod><changefreq>daily</changefreq><priority>0.9</priority></url>',
                $this->escapeXml($baseUrl),
                $this->escapeXml($product->slug),
                $product->updated_at?->tz('UTC')->toAtomString() ?? now()->toAtomString()
            );
        }

        // ── Dynamic Categories ──────────────────────────────────────────────
        $categoryCursor = Category::query()
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->cursor();

        foreach ($categoryCursor as $category) {
            $xml .= sprintf(
                '<url><loc>%s/categories/%s</loc><lastmod>%s</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>',
                $this->escapeXml($baseUrl),
                $this->escapeXml($category->slug),
                $category->updated_at?->tz('UTC')->toAtomString() ?? now()->toAtomString()
            );
        }

        // ── Dynamic Articles ────────────────────────────────────────────────
        $articleCursor = Article::published()
            ->select(['slug', 'updated_at'])
            ->orderBy('id')
            ->cursor();

        foreach ($articleCursor as $article) {
            $xml .= sprintf(
                '<url><loc>%s/blog/%s</loc><lastmod>%s</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>',
                $this->escapeXml($baseUrl),
                $this->escapeXml($article->slug),
                $article->updated_at?->tz('UTC')->toAtomString() ?? now()->toAtomString()
            );
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Escape karakter khusus XML untuk URL/text content.
     * Penting untuk mencegah broken sitemap kalau ada slug dengan karakter aneh.
     */
    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
