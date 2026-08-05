<?php

declare(strict_types=1);

use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Mail\OrderCreatedMail;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderAuditNotification;
use App\Notifications\OrderCreatedNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderPaidNotification;
use App\Notifications\OrderReadyForDeliveryNotification;
use App\Notifications\OrderReadyForWorkNotification;
use App\Notifications\OrderReceivedNotification;
use App\Notifications\OrderReviewedNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders order created mail in each recipient locale', function () {
    $employee = User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $spanishCustomer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
        'locale' => 'es',
    ]);
    $englishCustomer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
        'locale' => 'en',
    ]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $spanishCustomer->id,
        'assigned_to' => $employee->id,
        'lifecycle_status' => OrderLifecycleStatus::ReadyForWork->value,
        'priority' => OrderPriority::HIGH->value,
        'title' => 'Rectificado Ñ',
    ]);
    $order->load(['customer', 'assignedTo']);

    app()->setLocale('en');
    $spanishMail = (new OrderCreatedNotification($order))->toMail($spanishCustomer);
    $spanishContent = $spanishMail->render();

    expect($spanishMail->locale)->toBe('es');
    $this->assertStringContainsString('Nueva orden creada', $spanishContent);
    $this->assertStringContainsString('Estatus: Lista para trabajo', $spanishContent);
    $this->assertStringContainsString('Prioridad: Alta', $spanishContent);
    $this->assertStringContainsString('Rectificado Ñ', $spanishContent);
    $this->assertStringNotContainsString('Status: Ready for Work', $spanishContent);

    app()->setLocale('es');
    $englishMail = (new OrderCreatedNotification($order))->toMail($englishCustomer);
    $englishContent = $englishMail->render();

    expect($englishMail->locale)->toBe('en');
    $this->assertStringContainsString('New Order Created', $englishContent);
    $this->assertStringContainsString('Status: Ready for Work', $englishContent);
    $this->assertStringContainsString('Priority: High', $englishContent);
    $this->assertStringContainsString('Rectificado Ñ', $englishContent);
    $this->assertStringNotContainsString('Estatus: Lista para trabajo', $englishContent);
});
it('localizes every lifecycle and audit notification', function () {
    $customer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
        'locale' => 'es',
    ]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'lifecycle_status' => OrderLifecycleStatus::ReadyForWork->value,
        'priority' => OrderPriority::HIGH->value,
    ]);

    $notifications = [
        [new OrderReceivedNotification($order), 'notifications.order_received.subject'],
        [new OrderReviewedNotification($order), 'notifications.order_reviewed.subject'],
        [new OrderReadyForWorkNotification($order), 'notifications.order_ready_for_work.subject'],
        [new OrderReadyForDeliveryNotification($order), 'notifications.order_ready_for_delivery.subject'],
        [new OrderDeliveredNotification($order), 'notifications.order_delivered.subject'],
        [new OrderPaidNotification($order), 'notifications.order_paid.subject'],
        [new OrderAuditNotification($order, 'created'), 'notifications.audit.subjects.created'],
    ];

    foreach (['es', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach ($notifications as [$notification, $subjectKey]) {
            $message = $notification->toMail($customer)->toArray();

            expect($message['subject'])->toBe(__($subjectKey));
            expect($message['introLines'])->toContain(__('notifications.status_label', ['status' => $order->lifecycleStatus()->getLabel()]));
        }

        $audit = (new OrderAuditNotification($order, 'created'))->toMail($customer)->toArray();
        expect($audit['introLines'])->toContain(__('notifications.priority_label', ['priority' => $order->priority->getLabel()]));
        expect($audit['introLines'])->not->toContain('created');
    }
});
it('tells customers to visit the store and pay before delivery', function () {
    $customer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
    ]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'lifecycle_status' => OrderLifecycleStatus::ReadyForDelivery->value,
    ]);

    foreach (['es', 'en'] as $locale) {
        app()->setLocale($locale);

        $message = (new OrderReadyForDeliveryNotification($order))->toMail($customer)->toArray();

        expect($message['introLines'])
            ->toContain(__('notifications.order_ready_for_delivery.line'))
            ->toContain(__('notifications.order_ready_for_delivery.payment_line'));
    }
});
it('tells customers their order was completed and delivered', function () {
    $customer = User::factory()->create([
        'role' => UserRole::CUSTOMER->value,
    ]);
    $order = Order::factory()->createQuietly([
        'customer_id' => $customer->id,
        'lifecycle_status' => OrderLifecycleStatus::Delivered->value,
    ]);

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $message = (new OrderDeliveredNotification($order))->toMail($customer)->toArray();

        expect($message['subject'])->toBe(__('notifications.order_delivered.subject'));
        expect($message['introLines'])
            ->toContain(__('notifications.order_delivered.line'));
    }

    app()->setLocale('en');
    $englishContent = (string)(new OrderDeliveredNotification($order))->toMail($customer)->render();
    $this->assertStringContainsString('completed', $englishContent);
    $this->assertStringContainsString('delivered', $englishContent);

    app()->setLocale('es');
    $spanishContent = (string)(new OrderDeliveredNotification($order))->toMail($customer)->render();
    $this->assertStringContainsString('completada', $spanishContent);
    $this->assertStringContainsString('entregada', $spanishContent);
});
it('queues mail and notifications after commit', function () {
    $order = Order::factory()->createQuietly();

    $notificationClasses = [
        OrderAuditNotification::class,
        OrderCreatedNotification::class,
        OrderDeliveredNotification::class,
        OrderPaidNotification::class,
        OrderReadyForDeliveryNotification::class,
        OrderReadyForWorkNotification::class,
        OrderReceivedNotification::class,
        OrderReviewedNotification::class,
    ];

    foreach ($notificationClasses as $notificationClass) {
        $notification = $notificationClass === OrderAuditNotification::class
            ? new $notificationClass($order, 'created')
            : new $notificationClass($order);

        expect($notification)->toBeInstanceOf(ShouldQueueAfterCommit::class);
    }

    expect(new OrderCreatedMail($order))->toBeInstanceOf(ShouldQueueAfterCommit::class);
});
