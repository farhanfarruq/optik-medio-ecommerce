<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\ReturnRequestMail;
use App\Models\ReturnRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReturnController extends Controller
{
    /**
     * Customer mengajukan pengembalian barang
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id'    => 'required|exists:orders,id',
            'reason'      => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
        ]);

        $userId = $request->user()->id;

        // Verifikasi order milik user ini dan sudah delivered
        $order = \App\Models\Order::where('id', $validated['order_id'])
            ->where('user_id', $userId)
            ->firstOrFail();

        if (strtolower($order->status) !== 'delivered') {
            return response()->json([
                'message' => 'Pengajuan return hanya bisa dilakukan setelah barang diterima.',
            ], 422);
        }

        // Cek apakah sudah ada pengajuan untuk order ini
        $existing = ReturnRequest::where('order_id', $validated['order_id'])
            ->where('user_id', $userId)
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'Anda sudah mengajukan pengembalian untuk pesanan ini.',
            ], 422);
        }

        $returnRequest = ReturnRequest::create([
            'user_id'     => $userId,
            'order_id'    => $validated['order_id'],
            'reason'      => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status'      => 'pending',
        ]);

        // Kirim notifikasi email ke admin
        try {
            $adminEmail = config('mail.from.address');
            Mail::to($adminEmail)->send(new ReturnRequestMail($returnRequest, $request->user(), $order));
        } catch (\Exception $e) {
            Log::error('Failed to send return request email: ' . $e->getMessage());
        }

        return response()->json([
            'message'        => 'Pengajuan pengembalian berhasil dikirim. Tim kami akan menghubungi Anda.',
            'return_request' => $returnRequest,
        ], 201);
    }
}
