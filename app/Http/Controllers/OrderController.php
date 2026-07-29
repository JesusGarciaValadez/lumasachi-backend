<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\AssignOrderRequest;
use App\Http\Requests\CustomerApprovalRequest;
use App\Http\Requests\DeliverOrderRequest;
use App\Http\Requests\MarkReadyForDeliveryRequest;
use App\Http\Requests\MarkWorkCompletedRequest;
use App\Http\Requests\StoreOrderPaymentRequest;
use App\Http\Requests\StoreOrderWithItemsRequest;
use App\Http\Requests\SubmitBudgetRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderHistoryResource;
use App\Http\Resources\OrderPaymentResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\LocaleResolver;
use App\Services\OrderLifecycleService;
use App\Services\OrderPaymentService;
use App\Traits\CachesOrders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

final class OrderController extends Controller
{
    use CachesOrders;

    public function __construct(
        private OrderLifecycleService $lifecycleService,
        private OrderPaymentService   $paymentService,
    )
    {
    }

    /**
     * Display a listing of all orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $locale = app(LocaleResolver::class)->resolve($request);
        $key = self::indexKeyFor($user, locale: $locale);
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
            $this->authenticatedUser($request)
        );

        return response()->json([
            'code' => 'orders.created',
            'message' => __('orders.messages.created'),
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'motorInfo', 'payments.createdBy', 'items.components'])),
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
            $this->authenticatedUser($request)
        );

        return response()->json([
            'code' => 'orders.budget_submitted',
            'message' => __('orders.messages.budget_submitted'),
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo', 'payments.createdBy', 'items.components', 'services.catalogItem'])),
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
            $this->authenticatedUser($request)
        );

        return response()->json([
            'code' => 'orders.services_approved',
            'message' => __('orders.messages.services_approved'),
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo', 'payments.createdBy', 'services.catalogItem'])),
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
            $this->authenticatedUser($request)
        );

        return response()->json([
            'code' => 'orders.work_completed',
            'message' => __('orders.messages.work_completed'),
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo', 'services.catalogItem'])),
        ]);
    }

    /**
     * Mark order as ready for delivery.
     */
    public function markReadyForDelivery(MarkReadyForDeliveryRequest $request, Order $order): JsonResponse
    {
        $order = $this->lifecycleService->markReadyForDelivery($order, $this->authenticatedUser($request));

        return response()->json([
            'code' => 'orders.ready_for_delivery',
            'message' => __('orders.messages.ready_for_delivery'),
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo', 'services.catalogItem'])),
        ]);
    }

    /**
     * Deliver order.
     */
    public function deliverOrder(DeliverOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->lifecycleService->deliverOrder($order, $this->authenticatedUser($request));

        return response()->json([
            'code' => 'orders.delivered',
            'message' => __('orders.messages.delivered'),
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo', 'services.catalogItem'])),
        ]);
    }

    /**
     * Record a received payment without modifying existing ledger entries.
     */
    public function recordPayment(StoreOrderPaymentRequest $request, Order $order): JsonResponse
    {
        $payment = $this->paymentService->recordPayment(
            $order,
            $request->validated('amount'),
            $this->authenticatedUser($request),
        );

        return response()->json([
            'code' => 'orders.payment_recorded',
            'message' => __('orders.messages.payment_recorded'),
            'payment' => new OrderPaymentResource($payment->load('createdBy')),
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo', 'payments.createdBy', 'services.catalogItem'])),
        ], 201);
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $locale = app(LocaleResolver::class)->resolve($request);
        $key = self::showKeyFor($order->uuid, $locale);
        $hit = Cache::has($key);

        $payload = Cache::remember($key, now()->addSeconds(self::ttlShow()), function () use ($order, $locale) {
            app()->setLocale($locale);

            return (new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy', 'motorInfo', 'payments.createdBy', 'items.components', 'services.catalogItem'])))->resolve();
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
        $actor = $this->authenticatedUser($request);
        $newStatus = isset($validated['status'])
            ? OrderStatus::from($validated['status'])
            : null;

        unset($validated['status']);

        if ($newStatus) {
            $order = $this->lifecycleService->transition($order, $newStatus, $actor, $validated);
        } elseif ($validated !== []) {
            $order->update([
                ...$validated,
                'updated_by' => $actor->id,
            ]);
        }

        return response()->json([
            'code' => 'orders.updated',
            'message' => __('orders.messages.updated'),
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
            'code' => 'orders.deleted',
            'message' => __('orders.messages.deleted'),
        ]);
    }

    /**
     * Update the status of an order.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        $order = $this->lifecycleService->transition(
            $order,
            OrderStatus::from($validated['status']),
            $this->authenticatedUser($request),
        );

        return response()->json([
            'code' => 'orders.status_updated',
            'message' => __('orders.messages.status_updated'),
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
            'updated_by' => $this->authenticatedUser($request)->id,
        ]);

        return response()->json([
            'code' => 'orders.assigned',
            'message' => __('orders.messages.assigned'),
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy'])),
        ]);
    }

    /**
     * Get the history of an order.
     *
     * @return AnonymousResourceCollection
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

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
