<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    public function index(): JsonResponse
    {
        $faqs = Faq::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('question')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar FAQ berhasil diambil.',
            'data' => $faqs,
        ]);
    }
}
