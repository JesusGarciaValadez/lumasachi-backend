<?php

declare(strict_types=1);

namespace Tests\Feature\UserAdministration;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\User;
use App\Notifications\UserAdministrationIncidentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

final class UserAdministrationFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_unexpected_create_failure_redirects_with_safe_input_and_notifies_super_administrators(): void
    {
        Notification::fake();

        $superAdministrator = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);
        User::creating(function (): never {
            throw new RuntimeException('email=secret@example.test password=secret');
        });

        $response = $this->actingAs($superAdministrator)
            ->from('/user/create')
            ->post('/user', [
                'first_name' => 'Preserved',
                'last_name' => 'Safe value',
                'email' => 'safe@example.test',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'company_id' => null,
                'role' => UserRole::EMPLOYEE->value,
                'phone_number' => null,
                'is_active' => true,
                'notes' => 'Keep this note',
                'type' => UserType::INDIVIDUAL->value,
                'locale' => Locale::ENGLISH->value,
            ]);

        $response
            ->assertRedirect('/user/create')
            ->assertSessionHasInput('first_name', 'Preserved')
            ->assertSessionHasInput('notes', 'Keep this note')
            ->assertSessionMissing('password')
            ->assertSessionHas('error', fn(string $message): bool => str_contains($message, 'Incident: '));

        Notification::assertSentTo(
            $superAdministrator,
            UserAdministrationIncidentNotification::class,
            fn(UserAdministrationIncidentNotification $notification): bool => $notification->context['operation'] === 'create',
        );
    }
}
