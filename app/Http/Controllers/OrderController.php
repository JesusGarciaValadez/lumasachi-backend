<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderDispositionStatus;
use App\Enums\OrderLifecycleStatus;
use App\Http\Requests\AssignOrderRequest;
use App\Http\Requests\CustomerApprovalRequest;
use App\Http\Requests\DeliverOrderRequest;
use App\Http\Requests\MarkReadyForDeliveryRequest;
use App\Http\Requests\MarkWorkCompletedRequest;
use App\Http\Requests\StoreOrderPaymentRequest;
use App\Http\Requests\StoreOrderRefundRequest;
use App\Http\Requests\StoreOrderWithItemsRequest;
use App\Http\Requests\SubmitBudgetRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderHistoryResource;
use App\Http\Resources\OrderPaymentResource;
use App\Http\Resources\OrderRefundResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\User;
use App\Services\LocaleResolver;
use App\Services\OrderLifecycleService;
use App\Services\OrderPaymentService;
use App\Services\OrderRefundService;
use App\Traits\CachesOrders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class OrderController extends Controller
{
    use CachesOrders;

    public function __construct(
        private OrderLifecycleService $lifecycleService,
        private OrderPaymentService $paymentService,
        private OrderRefundService $refundService,
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
            $orders = Order::with(['customer', 'assignedTo', 'createdBy', 'refunds'])
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
                ->orderByDesc('created_at')
                ->orderByDesc('id')
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
        $downPayment = ($validated['down_payment'] ?? null) === null
            ? null
            : (float)$validated['down_payment'];

        $order = $this->lifecycleService->customerApproval(
            $order,
            $validated['authorized_service_ids'],
            $downPayment,
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
        $actor = $this->authenticatedUser($request);

        try {
            $order = $this->lifecycleService->markReadyForDelivery($order, $actor);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Unable to mark order ready for delivery.', [
                'order_uuid' => $order->uuid,
                'user_id' => $actor->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'code' => 'orders.ready_for_delivery_failed',
                'message' => __('orders.messages.ready_for_delivery_failed'),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Unexpected error while marking order ready for delivery.', [
                'order_uuid' => $order->uuid,
                'user_id' => $actor->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'code' => 'orders.ready_for_delivery_failed',
                'message' => __('orders.messages.ready_for_delivery_failed'),
            ], 500);
        }

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
        $actor = $this->authenticatedUser($request);
        $validated = $request->validated();

        try {
            if (array_key_exists('amount', $validated)) {
                $result = $this->lifecycleService->recordDeliveryPayment($order, $validated['amount'], $actor);
                $order = $result['order'];
                $payment = $result['payment'];
                $delivered = $order->lifecycleStatus() === OrderLifecycleStatus::Delivered;

                return response()->json([
                    'code' => $delivered ? 'orders.delivered' : 'orders.payment_recorded',
                    'message' => __($delivered ? 'orders.messages.delivered' : 'orders.messages.payment_recorded'),
                    'payment' => $payment ? new OrderPaymentResource($payment->load('createdBy')) : null,
                    'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo', 'payments.createdBy', 'services.catalogItem'])),
                ]);
            }

            $order = $this->lifecycleService->deliverOrder($order, $actor);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Unable to deliver order.', [
                'order_uuid' => $order->uuid,
                'user_id' => $actor->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'code' => 'orders.delivery_failed',
                'message' => __('orders.messages.delivery_failed'),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Unexpected error while delivering order.', [
                'order_uuid' => $order->uuid,
                'user_id' => $actor->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'code' => 'orders.delivery_failed',
                'message' => __('orders.messages.delivery_failed'),
            ], 500);
        }

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
        $actor = $this->authenticatedUser($request);

        try {
            $payment = $this->paymentService->recordPayment(
                $order,
                $request->validated('amount'),
                $actor,
            );
        } catch (InvalidArgumentException $exception) {
            Log::warning('Unable to record payment for order.', [
                'order_uuid' => $order->uuid,
                'user_id' => $actor->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'code' => 'orders.payment_failed',
                'message' => __('orders.messages.payment_failed'),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Unexpected error while recording payment for order.', [
                'order_uuid' => $order->uuid,
                'user_id' => $actor->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'code' => 'orders.payment_failed',
                'message' => __('orders.messages.payment_failed'),
            ], 500);
        }

        return response()->json([
            'code' => 'orders.payment_recorded',
            'message' => __('orders.messages.payment_recorded'),
            'payment' => new OrderPaymentResource($payment->load('createdBy')),
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'motorInfo', 'payments.createdBy', 'services.catalogItem'])),
        ], 201);
    }

    /**
     * Request a refund without changing the order lifecycle or payment ledger.
     */
    public function requestRefund(StoreOrderRefundRequest $request, Order $order): JsonResponse
    {
        $sourcePayment = $request->validated('source_payment_uuid')
            ? $order->payments()->where('uuid', $request->validated('source_payment_uuid'))->first()
            : null;

        try {
            $refund = $this->refundService->requestRefund(
                $order,
                $request->validated('amount'),
                $request->validated('reason'),
                $sourcePayment,
                $this->authenticatedUser($request),
            );
        } catch (InvalidArgumentException $exception) {
            return $this->refundError($exception);
        }

        return response()->json([
            'code' => 'orders.refund_requested',
            'message' => __('orders.messages.refund_requested'),
            'refund' => new OrderRefundResource($refund->load(['sourcePayment', 'requestedBy'])),
        ], 201);
    }

    /**
     * Approve a requested refund.
     */
    public function approveRefund(Request $request, Order $order, OrderRefund $refund): JsonResponse
    {
        try {
            $refund = $this->refundService->approveRefund($refund, $this->authenticatedUser($request));
        } catch (InvalidArgumentException $exception) {
            return $this->refundError($exception);
        }

        return response()->json([
            'code' => 'orders.refund_approved',
            'message' => __('orders.messages.refund_approved'),
            'refund' => new OrderRefundResource($refund->load(['requestedBy', 'approvedBy'])),
        ]);
    }

    /**
     * Reject a requested refund.
     */
    public function rejectRefund(Request $request, Order $order, OrderRefund $refund): JsonResponse
    {
        try {
            $refund = $this->refundService->rejectRefund($refund, $this->authenticatedUser($request));
        } catch (InvalidArgumentException $exception) {
            return $this->refundError($exception);
        }

        return response()->json([
            'code' => 'orders.refund_rejected',
            'message' => __('orders.messages.refund_rejected'),
            'refund' => new OrderRefundResource($refund->load(['requestedBy', 'rejectedBy'])),
        ]);
    }

    /**
     * Process an approved refund while keeping the payment ledger unchanged.
     */
    public function processRefund(Request $request, Order $order, OrderRefund $refund): JsonResponse
    {
        try {
            $refund = $this->refundService->processRefund($refund, $this->authenticatedUser($request));
        } catch (InvalidArgumentException $exception) {
            return $this->refundError($exception);
        }

        return response()->json([
            'code' => 'orders.refund_processed',
            'message' => __('orders.messages.refund_processed'),
            'refund' => new OrderRefundResource($refund->load(['requestedBy', 'approvedBy', 'processedBy'])),
        ]);
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

            return (new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy', 'motorInfo', 'payments.createdBy', 'refunds.requestedBy', 'refunds.approvedBy', 'refunds.rejectedBy', 'refunds.processedBy', 'items.components', 'services.catalogItem'])))->resolve();
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
        $newLifecycleStatus = isset($validated['lifecycle_status'])
            ? OrderLifecycleStatus::from($validated['lifecycle_status'])
            : null;
        $newDispositionStatus = isset($validated['disposition_status'])
            ? OrderDispositionStatus::from($validated['disposition_status'])
            : null;

        unset($validated['lifecycle_status'], $validated['disposition_status']);

        if ($newLifecycleStatus) {
            $order = $this->lifecycleService->transition($order, $newLifecycleStatus, $actor, $validated);
        } elseif ($newDispositionStatus) {
            $order = $this->lifecycleService->setDisposition(
                $order,
                $newDispositionStatus,
                $actor,
                $validated['notes'] ?? null,
            );
            unset($validated['notes']);

            if ($validated !== []) {
                $order->update($validated);
            }
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
     * Cancel an order from its current lifecycle step.
     */
    public function cancelOrder(Request $request, Order $order): JsonResponse
    {
        $order = $this->lifecycleService->setDisposition(
            $order,
            OrderDispositionStatus::Cancelled,
            $this->authenticatedUser($request),
            null,
        );

        return response()->json([
            'code' => 'orders.cancelled',
            'message' => __('orders.messages.cancelled'),
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
        $newStatus = OrderLifecycleStatus::from($validated['lifecycle_status']);
        $actor = $this->authenticatedUser($request);

        $order = $newStatus === OrderLifecycleStatus::Delivered
            ? $this->lifecycleService->deliverOrder($order, $actor)
            : $this->lifecycleService->transition($order, $newStatus, $actor);

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

    private function refundError(InvalidArgumentException $exception): JsonResponse
    {
        return response()->json([
            'code' => 'orders.refund_invalid',
            'message' => $exception->getMessage(),
        ], 422);
    }
}
