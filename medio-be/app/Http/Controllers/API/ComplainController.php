<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Complain;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplainController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $complains = Complain::query()
            ->with(['order:id,order_number,status'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Daftar komplain berhasil diambil.',
            'data' => $complains,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $complain = Complain::query()
            ->with(['order:id,order_number,status'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail komplain berhasil diambil.',
            'data' => $complain,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'complaint_type' => 'nullable|in:general,shipping_protection',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'contact_phone' => 'nullable|string|max:20',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf,mp4,mov,webm|max:15360',
        ]);

        $order = null;
        if (!empty($validated['order_id'])) {
            $order = Order::query()
                ->whereKey($validated['order_id'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
        }

        $complaintType = $validated['complaint_type'] ?? 'general';

        if ($complaintType === 'shipping_protection') {
            if (! $order) {
                return response()->json([
                    'message' => 'Klaim proteksi pengiriman wajib terkait dengan pesanan.',
                ], 422);
            }

            if (! $order->shipping_protection_opted) {
                return response()->json([
                    'message' => 'Pesanan ini tidak menggunakan proteksi pengiriman.',
                ], 422);
            }

            if (! in_array($order->status, ['shipped', 'delivered', 'completed'], true)) {
                return response()->json([
                    'message' => 'Klaim proteksi pengiriman baru dapat diajukan setelah pesanan dikirim.',
                ], 422);
            }

            $hasActiveClaim = Complain::query()
                ->where('user_id', $request->user()->id)
                ->where('order_id', $order->id)
                ->where('complaint_type', 'shipping_protection')
                ->whereIn('status', ['open', 'in_progress'])
                ->exists();

            if ($hasActiveClaim) {
                return response()->json([
                    'message' => 'Klaim proteksi pengiriman untuk pesanan ini sedang berjalan.',
                ], 422);
            }
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('complaints', 'public');
        }

        $complain = Complain::create([
            'user_id' => $request->user()->id,
            'order_id' => $order?->id,
            'complaint_type' => $complaintType,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'contact_phone' => $validated['contact_phone'] ?? $request->user()->phone,
            'attachment_path' => $attachmentPath,
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Komplain berhasil dikirim.',
            'data' => $complain->fresh(['order:id,order_number,status']),
        ], 201);
    }
}
