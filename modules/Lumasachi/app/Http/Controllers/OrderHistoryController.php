<?php

namespace Modules\Lumasachi\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Http\Requests\StoreOrderHistoryRequest;
use Modules\Lumasachi\app\Http\Resources\OrderHistoryResource;
use Modules\Lumasachi\app\Http\Resources\AttachmentResource;
use Illuminate\Support\Facades\Gate;


final class OrderHistoryController extends Controller
{
    /**
     * Display a listing of order histories.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', OrderHistory::class);

        $query = OrderHistory::with(['createdBy', 'order', 'attachments']);

        // Filter by order_id if provided
        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        // Filter by date range if provided
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $orderHistories = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json(OrderHistoryResource::collection($orderHistories));
    }

    /**
     * Store a newly created order history.
     */
    public function store(StoreOrderHistoryRequest $request): JsonResponse
    {
        $orderHistory = OrderHistory::create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()->id]
        ));

        $orderHistory->load(['createdBy', 'order', 'attachments']);

        return response()->json(
            ['data' => new OrderHistoryResource($orderHistory)], 
            Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified order history.
     */
    public function show(Request $request, OrderHistory $orderHistory): JsonResponse
    {
        Gate::authorize('view', $orderHistory);

        $orderHistory->load(['createdBy', 'order', 'attachments']);

        return response()->json(['data' => new OrderHistoryResource($orderHistory)]);
    }

    /**
     * Remove the specified order history.
     */
    public function destroy(Request $request, OrderHistory $orderHistory): JsonResponse
    {
        Gate::authorize('delete', $orderHistory);

        $orderHistory->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get the order associated with this history entry.
     */
    public function order(Request $request, OrderHistory $orderHistory, Order $order): JsonResponse
    {
        Gate::authorize('view', $orderHistory);

        // Verify that the order history belongs to the specified order
        if ($orderHistory->order_id !== $order->id) {
            return response()->json([
                'message' => 'Order history does not belong to the specified order.'
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'order' => new \Modules\Lumasachi\app\Http\Resources\OrderResource($orderHistory->order)
        ]);
    }

    /**
     * Get attachments for the order associated with this history entry.
     */
    public function orderAttachments(Request $request, OrderHistory $orderHistory, Order $order): JsonResponse
    {
        Gate::authorize('view', $orderHistory);

        // Verify that the order history belongs to the specified order
        if ($orderHistory->order_id !== $order->id) {
            return response()->json([
                'message' => 'Order history does not belong to the specified order.'
            ], Response::HTTP_NOT_FOUND);
        }

        $attachments = $orderHistory->order->attachments;

        return response()->json([
            'attachments' => AttachmentResource::collection($attachments)
        ]);
    }
}
