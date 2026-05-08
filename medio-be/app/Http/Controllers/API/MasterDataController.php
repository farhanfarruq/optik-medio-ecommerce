<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\PaymentMethod;
use App\Models\StoreClose;
use Illuminate\Http\JsonResponse;

class MasterDataController extends Controller
{
    public function banks(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Daftar rekening toko berhasil diambil.',
            'data' => Bank::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function paymentMethods(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Daftar metode pembayaran berhasil diambil.',
            'data' => PaymentMethod::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeStatus(): JsonResponse
    {
        $currentStoreClose = StoreClose::currentActive();

        return response()->json([
            'success' => true,
            'message' => 'Status operasional toko berhasil diambil.',
            'data' => [
                'is_closed' => (bool) $currentStoreClose,
                'current_close' => $currentStoreClose,
            ],
        ]);
    }
}
