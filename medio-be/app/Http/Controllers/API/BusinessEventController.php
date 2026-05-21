<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BusinessEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessEventController extends Controller
{
    /**
     * POST /api/events
     * Terima business event dari frontend.
     * Rate-limited, tidak perlu auth.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string|max:80',
            'payload'    => 'nullable|array',
            'session_id' => 'nullable|string|max:64',
        ]);

        // Whitelist event types yang boleh dikirim dari frontend
        $allowed = [
            BusinessEvent::PRODUCT_VIEWED,
            BusinessEvent::ADD_TO_CART,
            BusinessEvent::CHECKOUT_STARTED,
            BusinessEvent::SHIPPING_SELECTED,
            BusinessEvent::PAYMENT_SELECTED,
            BusinessEvent::SEARCH_NO_RESULT,
            BusinessEvent::CHECKOUT_FAILED,
            BusinessEvent::FILTER_USED,
        ];

        if (! in_array($request->event_type, $allowed, true)) {
            return response()->json(['message' => 'Event type not allowed.'], 422);
        }

        BusinessEvent::record(
            eventType: $request->event_type,
            payload:   $request->payload ?? [],
            userId:    auth()->id(),
            sessionId: $request->session_id,
        );

        return response()->json(['ok' => true]);
    }
}
