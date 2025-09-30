<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderHistoryRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\OrderHistoryResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Traits\CachesOrderHistories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class OrderHistoryController extends Controller
{
    use CachesOrderHistories;

    /**
     * Display a listing of order histories.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', OrderHistory::class);

        // Build normalized filters and pagination signature for cache key
        $filters = [
            'order_id' => $request->input('order_id'),
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
            'page' => (int) $request->input('page', 1),
            'per_page' => (int) $request->input('per_page', 15),
        ];

        $key = self::indexKeyFor($filters);
        $hit = Cache::has($key);

        $payload = Cache::remember($key, now()->addSeconds(self::ttlIndex()), function () use ($request) {
            $query = OrderHistory::with(['createdBy', 'order.attachments']);

            if ($request->filled('order_id')) {
                $query->where('order_id', $request->order_id);
            }
            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            $paginator = $query->orderBy('created_at', 'desc')
                ->paginate($request->integer('per_page', 15));

            // Preserve paginator structure in cache
            return OrderHistoryResource::collection($paginator)
                ->response()
                ->getData(true);
        });

        return response()->json($payload)
            ->header('X-Cache', $hit ? 'HIT' : 'MISS');
    }

    /**
     * Store a newly created order history.
     */
    public function store(StoreOrderHistoryRequest $request): JsonResponse
    {
        $orderHistory = OrderHistory::create(array_merge(
            $request->validated(),
            [
                'uuid' => Str::uuid7()->toString(),
                'created_by' => $request->user()->id,
            ]
        ));

        $orderHistory->load(['createdBy', 'order.attachments']);

        return response()->json(
            ['data' => new OrderHistoryResource($orderHistory)],
            Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified order history.
     */
    public function show(OrderHistory $orderHistory): JsonResponse
    {
        Gate::authorize('view', $orderHistory);

        $key = self::showKeyFor($orderHistory->uuid);
        $hit = Cache::has($key);

        $payload = Cache::remember($key, now()->addSeconds(self::ttlShow()), function () use ($orderHistory) {
            $orderHistory->load(['createdBy', 'order.attachments']);

            return ['data' => (new OrderHistoryResource($orderHistory))->resolve()];
        });

        return response()->json($payload)
            ->header('X-Cache', $hit ? 'HIT' : 'MISS');
    }

    /**
     * Remove the specified order history.
     */
    public function destroy(OrderHistory $orderHistory): JsonResponse
    {
        Gate::authorize('delete', $orderHistory);

        $orderHistory->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get the order associated with this history entry.
     */
    public function order(OrderHistory $orderHistory, Order $order): JsonResponse
    {
        Gate::authorize('view', $orderHistory);

        // Verify that the order history belongs to the specified order
        if ($orderHistory->order_id !== $order->id) {
            return response()->json([
                'message' => 'Order history does not belong to the specified order.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Load relationships directly on the provided $order instance to ensure categories are present
        Gate::authorize('view', $order);
        $order->loadMissing(['customer', 'assignedTo', 'createdBy', 'updatedBy', 'categories']);

        return response()->json([
            'order' => new OrderResource($order),
        ]);
    }

    /**
     * Get attachments for the order associated with this history entry.
     */
    public function orderAttachments(OrderHistory $orderHistory, Order $order): JsonResponse
    {
        Gate::authorize('view', $orderHistory);

        // Verify that the order history belongs to the specified order
        if ($orderHistory->order_id !== $order->id) {
            return response()->json([
                'message' => 'Order history does not belong to the specified order.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Load the order relationship with attachments if not already loaded
        $orderHistory->loadMissing(['order.attachments']);

        $attachments = $orderHistory->order->attachments;

        return response()->json([
            'attachments' => AttachmentResource::collection($attachments),
        ]);
    }
}
