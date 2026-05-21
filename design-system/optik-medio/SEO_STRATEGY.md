# SEO Strategy — Optik Medio (Phase 6)

Dokumen ini berisi keputusan strategis SEO yang membutuhkan input stakeholder
(tim engineering + product owner + marketing). Implementasi penuh ditunda
hingga ada konsensus.

## SEO-1: SSR/SSG untuk halaman publik

### Konteks

Saat ini app berbasis Vue 3 SPA (Vite) — semua HTML di-render client-side.
Googlebot modern mendukung JS rendering, tapi ada beberapa kelemahan:

1. **Time to Render**: Googlebot tunggu hingga JS execute sebelum index. Untuk
   product/blog yang ribuan, ini bisa habiskan crawl budget.
2. **OG/Twitter card**: scraper Facebook/Twitter/WhatsApp **tidak** execute JS —
   `useSeoMeta` yang kita pakai (DOM manipulation) **tidak terbaca** mereka.
3. **Performance: First Contentful Paint** — SPA butuh JS payload sebelum
   tampil; SSR/SSG bisa langsung serve HTML statis.

### Opsi

#### Opsi A: Migrasi ke Nuxt 3 (full SSR)

**Pros:**
- Best-in-class SEO untuk dynamic content (product, article)
- Build-in head management via `useHead()` yang bekerja di SSR
- Auto-route, auto-import, dan tooling terintegrasi
- Migration path Vue 3 → Nuxt 3 relatively smooth (composables compatible)

**Cons:**
- **Effort: 3-4 minggu** untuk migrate 27 view + router + Pinia store
- Butuh server Node.js production (bukan static hosting)
- Build pipeline lebih kompleks
- Filament admin tetap di Laravel — perlu integrasi cookie session lintas-app

**Rekomendasi:** kalau organic traffic > 30% dari total acquisition, atau kalau
target pasar bergantung pada SEO content (blog, product discovery via Google),
**Nuxt 3 worth it**.

#### Opsi B: Vite Plugin Prerender (static HTML untuk public route)

**Pros:**
- **Effort: 1 minggu** — install `vite-ssg` atau `vite-plugin-prerender`
- HTML statis untuk public route (Home, Product List, Product Detail, Article,
  Legal pages) di-generate saat build
- Tidak butuh server Node — hosting di static host (Cloudflare Pages, Vercel)
- Authenticated route tetap SPA-rendered (Profile, Cart, Checkout)

**Cons:**
- Product detail content perlu **fetch saat build** (atau pakai ISR-like
  revalidation) — extra build complexity
- Saat ada product baru, harus rebuild atau pakai webhook revalidation
- Tidak full SSR — beberapa edge case dynamic content tetap rely on JS

**Rekomendasi:** kalau katalog product lebih stabil (< 100 product baru/bulan)
dan target SEO terutama untuk landing/blog, **prerender approach lebih
proporsional**.

### Decision Matrix

| Kriteria | Nuxt 3 | Vite Prerender | SPA (Status Quo) |
|---|---|---|---|
| SEO score (Lighthouse) | 95+ | 90+ | 70-80 |
| OG/Twitter card di crawler | ✅ | ✅ | ❌ |
| Engineering effort | 3-4 minggu | 1 minggu | 0 |
| Hosting cost | Server Node | Static | Static |
| Product launch friction | Hot-reload | Webhook rebuild | Hot-reload |

**Saran tim engineering:** mulai dari **Vite Prerender** (Opsi B) sebagai
incremental win. Kalau setelah 6 bulan data analytics menunjukkan organic search
adalah channel utama dan butuh dynamic SSR (fresh content frequency tinggi),
upgrade ke **Nuxt 3** (Opsi A).

---

## SEO-3: Sitemap index untuk > 50.000 URL

### Konteks

Saat ini `SitemapController` generate satu file sitemap dengan semua product +
category + article. Standar Google: **maksimal 50.000 URL atau 50 MB per
sitemap file**.

Kalau katalog tumbuh > 50.000 active product, sitemap akan tertolak.

### Implementasi (siap pakai saat dibutuhkan)

```php
// routes/web.php
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/sitemap-products.xml', [SitemapController::class, 'products']);
Route::get('/sitemap-categories.xml', [SitemapController::class, 'categories']);
Route::get('/sitemap-articles.xml', [SitemapController::class, 'articles']);

// SitemapController::index — return SITEMAP INDEX yang point ke sub-sitemap
public function index(): Response
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach (['products', 'categories', 'articles'] as $kind) {
        $xml .= '<sitemap>';
        $xml .= '<loc>' . config('app.frontend_url') . "/sitemap-{$kind}.xml</loc>";
        $xml .= '<lastmod>' . now()->toAtomString() . '</lastmod>';
        $xml .= '</sitemap>';
    }
    $xml .= '</sitemapindex>';
    return response($xml)->header('Content-Type', 'text/xml');
}
```

### Trigger

Implementasi ditunda hingga:
- Active product mendekati 40.000 (80% dari batas), ATAU
- Total URL gabungan (product + category + article) > 50.000.

Saat itu, refactor di atas hanya butuh ~2 hari engineering effort.

---

## Action Items per Sprint Berikutnya

| Item | Owner | Estimasi |
|---|---|---|
| Putuskan Nuxt vs Prerender berdasarkan analytics 6 bulan | Product + Engineering | 1 minggu eval |
| POC Vite Prerender untuk halaman product detail | Engineering | 3 hari |
| Setup webhook revalidation kalau pilih Prerender | Engineering | 2 hari |
| Implementasi sitemap index (kalau katalog mendekati 40k) | Engineering | 2 hari |
