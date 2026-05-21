<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MerchantFeedController extends Controller
{
    /**
     * GET /api/merchant-feed
     * Generate Google Merchant Center product feed dalam format TSV.
     * Hanya produk aktif, punya gambar, punya harga, dan punya SKU/GTIN/MPN.
     *
     * Query params:
     *   format=tsv (default) | json
     *   limit=500 (default)
     */
    public function index(Request $request): Response|\Illuminate\Http\JsonResponse
    {
        $format = $request->query('format', 'tsv');
        $limit  = min((int) $request->query('limit', 500), 2000);

        $products = Product::with(['category', 'productImages'])
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereNotNull('price')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $rows = $products->map(fn (Product $p) => $this->buildRow($p));

        if ($format === 'json') {
            return response()->json([
                'total'    => $rows->count(),
                'products' => $rows->values(),
            ]);
        }

        // TSV format
        $headers = [
            'id', 'title', 'description', 'link', 'image_link',
            'price', 'availability', 'brand', 'condition',
            'google_product_category', 'gtin', 'mpn',
            'item_group_id', 'gender', 'color', 'material',
        ];

        $tsv = implode("\t", $headers) . "\n";
        foreach ($rows as $row) {
            $tsv .= implode("\t", array_map(
                fn ($v) => str_replace(["\t", "\n", "\r"], ' ', (string) ($v ?? '')),
                array_values($row)
            )) . "\n";
        }

        return response($tsv, 200, [
            'Content-Type'        => 'text/tab-separated-values; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="merchant-feed.tsv"',
        ]);
    }

    /**
     * GET /api/merchant-feed/diagnostics
     * Laporan produk yang tidak memenuhi syarat feed.
     */
    public function diagnostics(): \Illuminate\Http\JsonResponse
    {
        $products = Product::with(['productImages'])
            ->where('is_active', true)
            ->get();

        $issues = $products->map(function (Product $p) {
            $problems = [];

            if (! $p->sku && ! $p->gtin && ! $p->mpn) {
                $problems[] = 'missing_identifier';
            }
            if ($p->productImages->isEmpty()) {
                $problems[] = 'missing_image';
            }
            if (! $p->brand) {
                $problems[] = 'missing_brand';
            }
            if (! $p->price || $p->price <= 0) {
                $problems[] = 'missing_price';
            }
            if ($p->stock <= 0) {
                $problems[] = 'out_of_stock';
            }
            if (! $p->google_product_category) {
                $problems[] = 'missing_google_category';
            }

            return [
                'id'       => $p->id,
                'name'     => $p->name,
                'sku'      => $p->sku,
                'problems' => $problems,
                'eligible' => empty($problems),
            ];
        })->filter(fn ($r) => ! empty($r['problems']))->values();

        $eligible = $products->count() - $issues->count();

        return response()->json([
            'total_products'   => $products->count(),
            'eligible'         => $eligible,
            'ineligible'       => $issues->count(),
            'issues'           => $issues,
        ]);
    }

    private function buildRow(Product $product): array
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $imageUrl    = $product->productImages->first()?->image_url
            ?? ($product->images[0] ?? null);

        // Resolve image URL
        if ($imageUrl && ! str_starts_with($imageUrl, 'http')) {
            $imageUrl = rtrim(config('app.url'), '/') . '/storage/' . ltrim($imageUrl, '/');
        }

        $availability = $product->stock > 0 ? 'in stock' : 'out of stock';
        $price        = number_format((float) $product->price, 2, '.', '') . ' IDR';

        return [
            'id'                      => $product->sku ?: 'PROD-' . $product->id,
            'title'                   => $product->name,
            'description'             => strip_tags($product->description ?? $product->name),
            'link'                    => $frontendUrl . '/products/' . $product->slug,
            'image_link'              => $imageUrl ?? '',
            'price'                   => $price,
            'availability'            => $availability,
            'brand'                   => $product->brand ?? 'Optik Medio',
            'condition'               => $product->condition ?? 'new',
            'google_product_category' => $product->google_product_category ?? '',
            'gtin'                    => $product->gtin ?? '',
            'mpn'                     => $product->mpn ?? $product->sku ?? '',
            'item_group_id'           => $product->sku ? substr($product->sku, 0, 10) : '',
            'gender'                  => $product->gender ?? '',
            'color'                   => $product->frame_color ?? '',
            'material'                => $product->frame_material ?? '',
        ];
    }
}
