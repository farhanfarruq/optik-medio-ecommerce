<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use Illuminate\Http\JsonResponse;

class PromoController extends Controller
{
    /**
     * Get all active promos
     */
    public function index(): JsonResponse
    {
        $promos = Promo::with(['buyProduct', 'getProduct', 'discountProduct', 'buyProducts', 'discountProducts'])
            ->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->get();

        return response()->json($promos);
    }
}
