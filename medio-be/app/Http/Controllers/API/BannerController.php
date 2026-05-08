<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    public function index(): JsonResponse
    {
        $banners = Banner::query()
            ->with(['product:id,name,slug', 'category:id,name,slug'])
            ->visible()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Banner $banner) => [
                'id' => $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'image_path' => $banner->image_path,
                'cta_label' => $banner->cta_label,
                'link_type' => $banner->link_type,
                'product' => $banner->product ? [
                    'id' => $banner->product->id,
                    'name' => $banner->product->name,
                    'slug' => $banner->product->slug,
                ] : null,
                'category' => $banner->category ? [
                    'id' => $banner->category->id,
                    'name' => $banner->category->name,
                    'slug' => $banner->category->slug,
                ] : null,
                'external_url' => $banner->external_url,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Daftar banner berhasil diambil.',
            'data' => $banners,
        ]);
    }
}
