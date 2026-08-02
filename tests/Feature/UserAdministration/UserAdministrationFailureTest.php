<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\User;
use App\Notifications\UserAdministrationIncidentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('unexpected create failure redirects with safe input and notifies super administrators', function (): void {
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
});
