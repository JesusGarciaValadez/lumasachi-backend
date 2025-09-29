<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Requests\AssignOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderHistoryResource;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use App\Traits\CachesOrders;

final class OrderController extends Controller
{
    use CachesOrders;
    /**
     * Display a listing of all orders.
     *
     * @return JsonResponse
     */
public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $key = self::indexKeyFor($user);
        $hit = Cache::has($key);

        $payload = Cache::remember($key, now()->addSeconds(self::ttlIndex()), function () use ($user) {
            $orders = Order::with(['customer', 'assignedTo', 'createdBy', 'categories'])
                ->when($user->isCustomer(), function ($query) use ($user) {
                    $query->where('customer_id', $user->id);
                })
                ->when($user->isEmployee(), function ($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                        ->orWhere('created_by', $user->id);
                })
                ->when($user->isAdministrator() || $user->isSuperAdministrator(), function ($query) use ($user) {
                    // No additional query modification needed for administrators
                })
                ->get();

            return OrderResource::collection($orders)->resolve();
        });

        return response()->json($payload)
            ->header('X-Cache', $hit ? 'HIT' : 'MISS');
    }

    /**
     * Store a newly created order in storage.
     *
     * @param StoreOrderRequest $request
     * @return JsonResponse
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $categories = $validated['categories'] ?? [];
        unset($validated['categories']);

        $order = DB::transaction(function () use ($validated, $request, $categories) {
            $order = Order::create(array_merge($validated, [
                'uuid' => method_exists(Str::class, 'uuid7')
                    ? Str::uuid7()->toString()
                    : (method_exists(Str::class, 'orderedUuid')
                        ? (string) Str::orderedUuid()
                        : (string) Str::uuid()),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id
            ]));
            if (!empty($categories)) {
                $order->categories()->sync($categories);
            }
            return $order;
        });

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'categories']))
        ], 201);
    }

    /**
     * Display the specified order.
     *
     * @param Order $order
     * @return JsonResponse
     */
public function show(Order $order): JsonResponse
    {
        $key = self::showKeyFor($order->uuid);
        $hit = Cache::has($key);

        $payload = Cache::remember($key, now()->addSeconds(self::ttlShow()), function () use ($order) {
            return (new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy', 'categories'])))->resolve();
        });

        return response()->json($payload)
            ->header('X-Cache', $hit ? 'HIT' : 'MISS');
    }

    /**
     * Update the specified order in storage.
     *
     * @param UpdateOrderRequest $request
     * @param Order $order
     * @return JsonResponse
     */
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        $categoriesProvided = array_key_exists('categories', $validated);
        $categories = $categoriesProvided ? $validated['categories'] : null;
        unset($validated['categories']);

        $oldIds = $categoriesProvided ? $order->categories()->pluck('id')->toArray() : null;

        DB::transaction(function () use ($order, $validated, $request, $categories, $categoriesProvided, $oldIds) {
            $order->update(array_merge($validated, [
                'updated_by' => $request->user()->id
            ]));

            if ($categoriesProvided) {
                $order->categories()->sync($categories);
                $newIds = $order->categories()->pluck('id')->toArray();

                $sortedOldIds = $oldIds;
                $sortedNewIds = $newIds;
                sort($sortedOldIds);
                sort($sortedNewIds);
                if ($sortedOldIds !== $sortedNewIds) {
                    OrderHistory::create([
                        'uuid' => Str::uuid7()->toString(),
                        'order_id' => $order->id,
                        'field_changed' => OrderHistory::FIELD_CATEGORIES,
                        'old_value' => $sortedOldIds,
                        'new_value' => $sortedNewIds,
                        'created_by' => $request->user()->id
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Order updated successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy', 'categories']))
        ]);
    }

    /**
     * Remove the specified order from storage.
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function destroy(Order $order): JsonResponse
    {
        $order->delete();

        return response()->json([
            'message' => 'Order deleted successfully.'
        ]);
    }

    /**
     * Update the status of an order.
     *
     * @param UpdateOrderStatusRequest $request
     * @param Order $order
     * @return JsonResponse
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        // Update order status (observer will handle history tracking)
        $order->update([
            'status' => $validated['status'],
            'updated_by' => $request->user()->id
        ]);

        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy', 'categories']))
        ]);
    }

    /**
     * Assign an order to an employee.
     *
     * @param AssignOrderRequest $request
     * @param Order $order
     * @return JsonResponse
     */
    public function assign(AssignOrderRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        // Update order assignment (observer will handle history tracking)
        $order->update([
            'assigned_to' => $validated['assigned_to'],
            'updated_by' => $request->user()->id
        ]);

        return response()->json([
            'message' => 'Order assigned successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy', 'categories']))
        ]);
    }

    /**
     * Get the history of an order.
     *
     * @param Request $request
     * @param Order $order
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function history(Request $request, Order $order)
    {
        $query = $order->orderHistories()
            ->with(['createdBy', 'order.attachments']);

        // Filter by field if provided
        if ($request->has('field')) {
            $query->where('field_changed', $request->input('field'));
        }

        // Paginate results
        $history = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return OrderHistoryResource::collection($history);
    }

}
