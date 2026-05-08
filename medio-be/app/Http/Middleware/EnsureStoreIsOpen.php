<?php

namespace App\Http\Middleware;

use App\Models\StoreClose;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $currentStoreClose = StoreClose::currentActive();

        if ($currentStoreClose) {
            return response()->json([
                'success' => false,
                'message' => 'Toko sedang tutup. Checkout sementara tidak tersedia.',
                'data' => [
                    'is_closed' => true,
                    'current_close' => $currentStoreClose,
                ],
            ], 423);
        }

        return $next($request);
    }
}
