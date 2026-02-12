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
                'uuid' => method_exists(Str::class, 'uuid7')
                    ? Str::uuid7()->toString()
                    : (string) Str::uuid(),
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
        $order->update([
            'status' => OrderStatus::AwaitingReview->value,
            'updated_by' => $creator->id,
        ]);

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
                        'is_budgeted' => true,
                        'base_price' => $catalog->base_price,
                        'net_price' => $catalog->net_price,
                    ]
                );
            }
        });

        $order->recalculateTotals();

        // Transition to REVIEWED → observer auto-transitions to AWAITING_CUSTOMER_APPROVAL
        $order->update([
            'status' => OrderStatus::Reviewed->value,
            'updated_by' => $reviewer->id,
        ]);

        return $order->load('services');
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

        DB::transaction(function () use ($order, $authorizedServiceIds, $downPayment) {
            $order->services()->whereIn('order_services.id', $authorizedServiceIds)->update(['is_authorized' => true]);

            if ($downPayment !== null) {
                $order->motorInfo->update(['down_payment' => $downPayment]);
            }
        });

        $order->recalculateTotals();

        $order->update([
            'status' => OrderStatus::ReadyForWork->value,
            'updated_by' => $approver->id,
        ]);

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

        DB::transaction(function () use ($order, $completedServiceIds): void {
            $services = $order->services()
                ->whereIn('order_services.id', $completedServiceIds)
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

        $order->update([
            'status' => OrderStatus::ReadyForDelivery->value,
            'updated_by' => $technician->id,
        ]);

        return $order;
    }

    /**
     * Deliver order. Transitions to DELIVERED.
     */
    public function deliverOrder(Order $order, User $actor): Order
    {
        $this->assertStatus($order, [OrderStatus::ReadyForDelivery]);

        $order->update([
            'status' => OrderStatus::Delivered->value,
            'updated_by' => $actor->id,
        ]);

        return $order;
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
}
