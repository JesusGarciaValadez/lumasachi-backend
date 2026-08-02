<?php

declare(strict_types=1);

namespace Tests\Unit\UserAdministration;

use App\Enums\UserRole;
use App\Exceptions\UserAdministrationException;
use App\Models\User;
use App\Notifications\UserAdministrationIncidentNotification;
use App\Services\UserAdministrationIncidentReporter;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

final class UserAdministrationIncidentReporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_unexpected_failures_are_sanitized_and_sent_only_to_active_super_administrators(): void
    {
        Notification::fake();
        Log::shouldReceive('error')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'User administration operation failed.'
                && !array_key_exists('userId', $context)
                && !array_key_exists('email', $context)
                && !array_key_exists('password', $context);
        });

        $activeSuperAdministrator = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);
        $inactiveSuperAdministrator = User::factory()->inactive()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);
        $administrator = User::factory()->active()->create([
            'role' => UserRole::ADMINISTRATOR->value,
        ]);
        $request = Request::create('/user/' . $activeSuperAdministrator->uuid, 'PUT', [
            'email' => 'secret@example.test',
            'password' => 'SecretPassword123!',
            'notes' => 'private notes',
        ]);
        $request->setUserResolver(fn(): User => $administrator);

        $incident = app(UserAdministrationIncidentReporter::class)->capture(
            new RuntimeException('email=secret@example.test password=SecretPassword123!'),
            $request,
            'update',
        );

        $this->assertInstanceOf(UserAdministrationException::class, $incident);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $incident->incidentId);
        $this->assertSame([
            'incident_id',
            'operation',
            'actor_role',
            'route',
            'method',
            'exception_class',
        ], array_keys($incident->context));
        $this->assertSame('update', $incident->context['operation']);
        $this->assertSame(UserRole::ADMINISTRATOR->value, $incident->context['actor_role']);
        $this->assertStringNotContainsString('secret@example.test', serialize($incident->context));
        $this->assertStringNotContainsString('SecretPassword123!', serialize($incident->context));

        Notification::assertSentTo(
            $activeSuperAdministrator,
            UserAdministrationIncidentNotification::class,
            function (UserAdministrationIncidentNotification $notification) use ($incident): bool {
                return $notification->context === $incident->context;
            },
        );
        Notification::assertNotSentTo($inactiveSuperAdministrator, UserAdministrationIncidentNotification::class);
        Notification::assertNotSentTo($administrator, UserAdministrationIncidentNotification::class);
    }

    public function test_incident_notification_is_queued_after_commit_and_contains_only_safe_context(): void
    {
        $notification = new UserAdministrationIncidentNotification([
            'incident_id' => '11111111-1111-7111-8111-111111111111',
            'operation' => 'create',
            'actor_role' => UserRole::SUPER_ADMINISTRATOR->value,
            'route' => 'web.users.store',
            'method' => 'POST',
            'exception_class' => RuntimeException::class,
        ]);

        $mail = (string)$notification->toMail(User::factory()->make())->render();

        $this->assertInstanceOf(ShouldQueueAfterCommit::class, $notification);
        $this->assertStringContainsString('11111111-1111-7111-8111-111111111111', $mail);
        $this->assertStringContainsString('create', $mail);
        $this->assertStringNotContainsString('secret@example.test', $mail);
        $this->assertStringNotContainsString('SecretPassword123!', $mail);
    }

    public function test_incident_operation_is_reduced_to_the_allowlisted_context(): void
    {
        Notification::fake();

        $actor = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);
        $request = Request::create('/users', 'GET');
        $request->setUserResolver(fn(): User => $actor);

        $incident = app(UserAdministrationIncidentReporter::class)->capture(
            new RuntimeException('unexpected'),
            $request,
            'email=secret@example.test',
        );

        $this->assertSame('unknown', $incident->context['operation']);
    }
}
