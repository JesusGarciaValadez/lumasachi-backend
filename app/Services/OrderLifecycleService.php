<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderService;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class OrderLifecycleService
{
    public function __construct(private OrderStatusStateMachine $statusStateMachine)
    {
    }

    /**
     * Create an order with motor info, items, and components inside a transaction.
     * After creation, transitions from RECEIVED → AWAITING_REVIEW.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createOrderWithMotorItems(array $validated, User $creator): Order
    {
        $motorInfo = $validated['motor_info'] ?? [];
        $items = $validated['items'] ?? [];
        // Remove nested data from top-level
        unset($validated['motor_info'], $validated['items']);

        $order = DB::transaction(function () use ($validated, $creator, $motorInfo, $items) {
            $order = Order::create(array_merge($validated, [
                'uuid' => Str::uuid7()->toString(),
                'status' => OrderStatus::Received->value,
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ]));

            // Create motor info
            $order->motorInfo()->create(array_merge(
                array_filter($motorInfo, fn ($v) => $v !== null && $v !== ''),
                [
                    'down_payment' => $motorInfo['down_payment'] ?? 0,
                    'total_cost' => 0,
                    'is_fully_paid' => false,
                ]
            ));

            // Create items and their components
            foreach ($items as $itemData) {
                $item = $order->items()->create([
                    'item_type' => $itemData['item_type'],
                    'is_received' => true,
                ]);

                foreach ($itemData['components'] ?? [] as $componentName) {
                    $item->components()->create([
                        'component_name' => $componentName,
                        'is_received' => true,
                    ]);
                }
            }

            return $order;
        });

        // Transition to AWAITING_REVIEW (triggers observer → history + notifications)
        $this->statusStateMachine->transition($order, OrderStatus::AwaitingReview, $creator);

        // Reload relationships for the caller
        return $order->load(['motorInfo', 'items.components']);
    }

    /**
     * Submit budget: create OrderService records for each service,
     * then transition to REVIEWED (observer auto-transitions to AWAITING_CUSTOMER_APPROVAL).
     *
     * @param  array<int, array{order_item_id: int, service_key: string, measurement: ?string}>  $servicesData
     */
    public function submitBudget(Order $order, array $servicesData, User $reviewer): Order
    {
        $this->assertStatus($order, [OrderStatus::AwaitingReview]);
        $this->assertOrderItemsBelongToOrder($order, array_column($servicesData, 'order_item_id'));

        DB::transaction(function () use ($servicesData) {
            foreach ($servicesData as $svcData) {
                $catalog = ServiceCatalog::where('service_key', $svcData['service_key'])->firstOrFail();

                OrderService::updateOrCreate(
                    [
                        'order_item_id' => $svcData['order_item_id'],
                        'service_key' => $svcData['service_key'],
                    ],
                    [
                        'measurement' => $svcData['measurement'] ?? null,
                        'notes' => $svcData['notes'] ?? null,
                        'is_budgeted' => true,
                        'base_price' => $catalog->base_price,
                        'net_price' => $catalog->net_price,
                    ]
                );
            }
        });

        $order->recalculateTotals();

        // Transition to REVIEWED → observer auto-transitions to AWAITING_CUSTOMER_APPROVAL
        $this->statusStateMachine->transition($order, OrderStatus::Reviewed, $reviewer);

        return $order->load('services.catalogItem');
    }

    /**
     * Customer approves selected services and optionally sets a down payment.
     * Transitions to READY_FOR_WORK.
     *
     * @param  array<int>  $authorizedServiceIds
     */
    public function customerApproval(Order $order, array $authorizedServiceIds, ?float $downPayment, User $approver): Order
    {
        $this->assertStatus($order, [OrderStatus::AwaitingCustomerApproval]);
        $this->assertBudgetedServicesBelongToOrder($order, $authorizedServiceIds);

        DB::transaction(function () use ($order, $authorizedServiceIds, $downPayment) {
            $order->services()->whereIn('order_services.id', $authorizedServiceIds)->update(['is_authorized' => true]);

            if ($downPayment !== null) {
                $order->motorInfo()->firstOrFail()->update(['down_payment' => $downPayment]);
            }
        });

        $order->recalculateTotals();

        $this->statusStateMachine->transition($order, OrderStatus::ReadyForWork, $approver);

        return $order;
    }

    /**
     * Mark selected services as completed.
     *
     * @param  array<int>  $completedServiceIds
     */
    public function markWorkCompleted(Order $order, array $completedServiceIds, User $technician): Order
    {
        $this->assertStatus($order, [OrderStatus::ReadyForWork, OrderStatus::InProgress]);
        $this->assertAuthorizedServicesBelongToOrder($order, $completedServiceIds);

        DB::transaction(function () use ($order, $completedServiceIds): void {
            $services = $order->services()
                ->whereIn('order_services.id', $completedServiceIds)
                ->where('order_services.is_authorized', true)
                ->get();

            foreach ($services as $service) {
                $service->update(['is_completed' => true]);
            }
        });

        return $order;
    }

    /**
     * Mark order as ready for delivery. Transitions to READY_FOR_DELIVERY.
     */
    public function markReadyForDelivery(Order $order, User $technician): Order
    {
        $this->assertStatus($order, [OrderStatus::ReadyForWork, OrderStatus::InProgress]);

        $order->recalculateTotals();

        $this->statusStateMachine->transition($order, OrderStatus::ReadyForDelivery, $technician);

        return $order;
    }

    /**
     * Deliver order. Transitions to DELIVERED.
     */
    public function deliverOrder(Order $order, User $actor): Order
    {
        $this->assertStatus($order, [OrderStatus::ReadyForDelivery]);
        $this->assertNoPendingPayment($order);

        $this->statusStateMachine->transition($order, OrderStatus::Delivered, $actor);

        return $order;
    }

    /**
     * Transition an order through the shared status state machine.
     */
    /**
     * @param array<string, mixed> $additionalAttributes
     */
    public function transition(
        Order       $order,
        OrderStatus $newStatus,
        User        $actor,
        array       $additionalAttributes = [],
    ): Order
    {
        return $this->statusStateMachine->transition($order, $newStatus, $actor, $additionalAttributes);
    }

    /**
     * Assert the order is in one of the expected statuses.
     *
     * @param  array<OrderStatus>  $expected
     *
     * @throws InvalidArgumentException
     */
    private function assertStatus(Order $order, array $expected): void
    {
        if (! in_array($order->status, $expected, true)) {
            $labels = array_map(fn (OrderStatus $expectedStatus) => $expectedStatus->value, $expected);

            throw new InvalidArgumentException(
                'Order must be in status ['.implode(', ', $labels)."] but is in [{$order->status->value}]."
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertNoPendingPayment(Order $order): void
    {
        if ($order->motorInfo()->first()?->hasPendingPayment()) {
            throw new InvalidArgumentException('Order must be fully paid before delivery.');
        }
    }

    /**
     * @param array<int, int> $orderItemIds
     *
     * @throws InvalidArgumentException
     */
    private function assertOrderItemsBelongToOrder(Order $order, array $orderItemIds): void
    {
        $uniqueOrderItemIds = array_values(array_unique($orderItemIds));

        $matchingItems = $order->items()
            ->whereIn('order_items.id', $uniqueOrderItemIds)
            ->count();

        if ($matchingItems !== count($uniqueOrderItemIds)) {
            throw new InvalidArgumentException('All order items must belong to the order.');
        }
    }

    /**
     * @param array<int, int> $serviceIds
     *
     * @throws InvalidArgumentException
     */
    private function assertBudgetedServicesBelongToOrder(Order $order, array $serviceIds): void
    {
        $uniqueServiceIds = array_values(array_unique($serviceIds));

        $matchingServices = $order->services()
            ->whereIn('order_services.id', $uniqueServiceIds)
            ->where('order_services.is_budgeted', true)
            ->count();

        if ($matchingServices !== count($uniqueServiceIds)) {
            throw new InvalidArgumentException('All approved services must be budgeted and belong to the order.');
        }
    }

    /**
     * @param array<int, int> $serviceIds
     *
     * @throws InvalidArgumentException
     */
    private function assertAuthorizedServicesBelongToOrder(Order $order, array $serviceIds): void
    {
        $uniqueServiceIds = array_values(array_unique($serviceIds));

        $matchingServices = $order->services()
            ->whereIn('order_services.id', $uniqueServiceIds)
            ->where('order_services.is_authorized', true)
            ->where('order_services.is_completed', false)
            ->count();

        if ($matchingServices !== count($uniqueServiceIds)) {
            throw new InvalidArgumentException('All completed services must be authorized and belong to the order.');
        }
    }
}
