<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TrackOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

final class PublicOrderController extends Controller
{
    /**
     * Look up an order by UUID + creation date (no auth required).
     */
    public function lookup(TrackOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $order = Order::where('uuid', $validated['uuid'])
            ->whereDate('created_at', $validated['created_date'])
            ->with(['motorInfo', 'items.components', 'services', 'orderHistories'])
            ->first();

        if (! $order) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo', 'items.components', 'services'])),
        ]);
    }
}
