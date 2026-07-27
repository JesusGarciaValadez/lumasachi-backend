<?php

declare(strict_types=1);

namespace Tests\Feature\app\Notifications;

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotificationLocaleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_order_created_mail_in_each_recipient_locale(): void
    {
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
            'status' => OrderStatus::InProgress->value,
            'priority' => OrderPriority::HIGH->value,
            'title' => 'Rectificado Ñ',
        ]);
        $order->load(['customer', 'assignedTo']);

        app()->setLocale('en');
        $spanishMail = (new OrderCreatedNotification($order))->toMail($spanishCustomer);
        $spanishContent = $spanishMail->render();

        $this->assertSame('es', $spanishMail->locale);
        $this->assertStringContainsString('Nueva orden creada', $spanishContent);
        $this->assertStringContainsString('Estatus: En progreso', $spanishContent);
        $this->assertStringContainsString('Prioridad: Alta', $spanishContent);
        $this->assertStringContainsString('Rectificado Ñ', $spanishContent);
        $this->assertStringNotContainsString('Status: In Progress', $spanishContent);

        app()->setLocale('es');
        $englishMail = (new OrderCreatedNotification($order))->toMail($englishCustomer);
        $englishContent = $englishMail->render();

        $this->assertSame('en', $englishMail->locale);
        $this->assertStringContainsString('New Order Created', $englishContent);
        $this->assertStringContainsString('Status: In Progress', $englishContent);
        $this->assertStringContainsString('Priority: High', $englishContent);
        $this->assertStringContainsString('Rectificado Ñ', $englishContent);
        $this->assertStringNotContainsString('Estatus: En progreso', $englishContent);

    }

    #[Test]
    public function it_localizes_every_lifecycle_and_audit_notification(): void
    {
        $customer = User::factory()->create([
            'role' => UserRole::CUSTOMER->value,
            'locale' => 'es',
        ]);
        $order = Order::factory()->createQuietly([
            'customer_id' => $customer->id,
            'status' => OrderStatus::InProgress->value,
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

                $this->assertSame(__($subjectKey), $message['subject']);
                $this->assertContains(
                    __('notifications.status_label', ['status' => $order->status->getLabel()]),
                    $message['introLines']
                );
            }

            $audit = (new OrderAuditNotification($order, 'created'))->toMail($customer)->toArray();
            $this->assertContains(
                __('notifications.priority_label', ['priority' => $order->priority->getLabel()]),
                $audit['introLines']
            );
            $this->assertNotContains('created', $audit['introLines']);
        }
    }

    #[Test]
    public function it_queues_mail_and_notifications_after_commit(): void
    {
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

            $this->assertInstanceOf(ShouldQueueAfterCommit::class, $notification);
        }

        $this->assertInstanceOf(ShouldQueueAfterCommit::class, new OrderCreatedMail($order));
    }
}
