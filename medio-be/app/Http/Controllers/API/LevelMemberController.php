<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LevelMember;
use Illuminate\Http\JsonResponse;

class LevelMemberController extends Controller
{
    public function index(): JsonResponse
    {
        $levels = LevelMember::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json($levels);
    }
}
