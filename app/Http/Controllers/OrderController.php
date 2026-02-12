<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AssignOrderRequest;
use App\Http\Requests\CustomerApprovalRequest;
use App\Http\Requests\DeliverOrderRequest;
use App\Http\Requests\MarkReadyForDeliveryRequest;
use App\Http\Requests\MarkWorkCompletedRequest;
use App\Http\Requests\StoreOrderWithItemsRequest;
use App\Http\Requests\SubmitBudgetRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderHistoryResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderLifecycleService;
use App\Traits\CachesOrders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class OrderController extends Controller
{
    use CachesOrders;

    public function __construct(private OrderLifecycleService $lifecycleService) {}

    /**
     * Display a listing of all orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $key = self::indexKeyFor($user);
        $hit = Cache::has($key);

        $payload = Cache::remember($key, now()->addSeconds(self::ttlIndex()), function () use ($user) {
            $orders = Order::with(['customer', 'assignedTo', 'createdBy'])
                ->when($user->isCustomer(), function ($query) use ($user) {
                    $query->where('customer_id', $user->id);
                })
                ->when($user->isEmployee(), function ($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                        ->orWhere('created_by', $user->id);
                })
                ->when($user->isAdministrator() || $user->isSuperAdministrator(), function ($query) {
                    // No additional query modification needed for administrators
                })
                ->get();

            return OrderResource::collection($orders)->resolve();
        });

        return response()->json($payload)
            ->header('X-Cache', $hit ? 'HIT' : 'MISS');
    }

    /**
     * Store a newly created order with motor info, items, and components.
     */
    public function store(StoreOrderWithItemsRequest $request): JsonResponse
    {
        $order = $this->lifecycleService->createOrderWithMotorItems(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'motorInfo', 'items.components'])),
        ], 201);
    }

    /**
     * Submit budget for an order (services with prices from catalog).
     */
    public function submitBudget(SubmitBudgetRequest $request, Order $order): JsonResponse
    {
        $order = $this->lifecycleService->submitBudget(
            $order,
            $request->validated('services'),
            $request->user()
        );

        return response()->json([
            'message' => 'Budget submitted successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo', 'items.components', 'services'])),
        ]);
    }

    /**
     * Customer approval of selected services.
     */
    public function customerApproval(CustomerApprovalRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        $order = $this->lifecycleService->customerApproval(
            $order,
            $validated['authorized_service_ids'],
            $validated['down_payment'] ?? null,
            $request->user()
        );

        return response()->json([
            'message' => 'Services approved successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo', 'services'])),
        ]);
    }

    /**
     * Mark selected services as work completed.
     */
    public function markWorkCompleted(MarkWorkCompletedRequest $request, Order $order): JsonResponse
    {
        $order = $this->lifecycleService->markWorkCompleted(
            $order,
            $request->validated('completed_service_ids'),
            $request->user()
        );

        return response()->json([
            'message' => 'Work marked as completed.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo', 'services'])),
        ]);
    }

    /**
     * Mark order as ready for delivery.
     */
    public function markReadyForDelivery(MarkReadyForDeliveryRequest $request, Order $order): JsonResponse
    {
        $order = $this->lifecycleService->markReadyForDelivery($order, $request->user());

        return response()->json([
            'message' => 'Order marked as ready for delivery.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo'])),
        ]);
    }

    /**
     * Deliver order.
     */
    public function deliverOrder(DeliverOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->lifecycleService->deliverOrder($order, $request->user());

        return response()->json([
            'message' => 'Order delivered successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo'])),
        ]);
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order): JsonResponse
    {
        $key = self::showKeyFor($order->uuid);
        $hit = Cache::has($key);

        $payload = Cache::remember($key, now()->addSeconds(self::ttlShow()), function () use ($order) {
            return (new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy', 'motorInfo', 'items.components', 'services'])))->resolve();
        });

        return response()->json($payload)
            ->header('X-Cache', $hit ? 'HIT' : 'MISS');
    }

    /**
     * Update the specified order in storage.
     */
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($order, $validated, $request) {
            $order->update(array_merge($validated, [
                'updated_by' => $request->user()->id,
            ]));
        });

        return response()->json([
            'message' => 'Order updated successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy'])),
        ]);
    }

    /**
     * Remove the specified order from storage.
     */
    public function destroy(Order $order): JsonResponse
    {
        $order->delete();

        return response()->json([
            'message' => 'Order deleted successfully.',
        ]);
    }

    /**
     * Update the status of an order.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        // Update order status (observer will handle history tracking)
        $order->update([
            'status' => $validated['status'],
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Order status updated successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy'])),
        ]);
    }

    /**
     * Assign an order to an employee.
     */
    public function assign(AssignOrderRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        // Update order assignment (observer will handle history tracking)
        $order->update([
            'assigned_to' => $validated['assigned_to'],
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Order assigned successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy'])),
        ]);
    }

    /**
     * Get the history of an order.
     *
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
