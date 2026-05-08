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
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'contact_phone' => 'nullable|string|max:20',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
        ]);

        $order = null;
        if (!empty($validated['order_id'])) {
            $order = Order::query()
                ->whereKey($validated['order_id'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('complaints', 'public');
        }

        $complain = Complain::create([
            'user_id' => $request->user()->id,
            'order_id' => $order?->id,
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
