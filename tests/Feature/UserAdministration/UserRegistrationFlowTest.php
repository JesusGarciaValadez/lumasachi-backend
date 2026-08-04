<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\User;
use App\Notifications\UserRegistrationVerificationNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as InertiaAssert;

uses(RefreshDatabase::class);

test('admin-created users receive a verification notification and must change their password', function (): void {
    $administrator = User::factory()->create([
        'is_active' => true,
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
    ]);

    Notification::fake();

    $response = $this->actingAs($administrator)->post('/user', registrationFlowPayload());

    $response->assertRedirect('/users');

    $createdUser = User::query()->where('email', 'registration-flow@example.test')->firstOrFail();

    expect($createdUser->hasVerifiedEmail())->toBeFalse()
        ->and($createdUser->must_change_password)->toBeTrue()
        ->and(Hash::check('Password123!', $createdUser->password))->toBeTrue();

    Notification::assertSentTo(
        $createdUser,
        UserRegistrationVerificationNotification::class,
        function (UserRegistrationVerificationNotification $notification, array $channels) use ($createdUser): bool {
            $mail = $notification->toMail($createdUser);

            return in_array('mail', $channels, true)
                && collect($mail->introLines)
                    ->contains(fn(string $line): bool => str_contains($line, $createdUser->email))
                && is_string($mail->actionUrl)
                && parse_url($mail->actionUrl, PHP_URL_PATH) === route(
                    'verification.verify',
                    ['id' => $createdUser->id, 'hash' => sha1($createdUser->email)],
                    false,
                )
                && str_contains($mail->actionUrl, 'signature=');
        },
    );
});

test('inactive users receive their invitation when an administrator activates them', function (): void {
    $administrator = User::factory()->create([
        'is_active' => true,
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
    ]);

    Notification::fake();

    $payload = registrationFlowPayload();
    $payload['email'] = 'inactive-registration-flow@example.test';
    $payload['is_active'] = false;

    $this->actingAs($administrator)->post('/user', $payload)->assertRedirect('/users');

    $createdUser = User::query()->where('email', $payload['email'])->firstOrFail();

    Notification::assertNothingSent();

    $this->actingAs($administrator)
        ->put('/user/' . $createdUser->uuid, [
            'email' => $createdUser->email,
            'first_name' => $createdUser->first_name,
            'is_active' => true,
            'last_name' => $createdUser->last_name,
            'locale' => 'en',
            'role' => $createdUser->role->value,
            'type' => $createdUser->type->value,
        ])
        ->assertRedirect('/users');

    Notification::assertSentTo($createdUser, UserRegistrationVerificationNotification::class);
    expect($createdUser->refresh()->activated_at)->not->toBeNull();
});

test('changing an active user email resets verification and sends a new notification', function (): void {
    $administrator = User::factory()->create([
        'is_active' => true,
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
    ]);
    $user = User::factory()->create([
        'email' => 'existing-registration-flow@example.test',
        'email_verified_at' => now(),
        'is_active' => true,
        'role' => UserRole::EMPLOYEE->value,
    ]);

    Notification::fake();

    $this->actingAs($administrator)
        ->put('/user/' . $user->uuid, [
            'email' => 'changed-registration-flow@example.test',
            'first_name' => $user->first_name,
            'is_active' => true,
            'last_name' => $user->last_name,
            'locale' => 'en',
            'role' => $user->role->value,
            'type' => $user->type->value,
        ])
        ->assertRedirect('/users');

    Notification::assertSentTo($user, UserRegistrationVerificationNotification::class);
    expect($user->refresh()->email_verified_at)->toBeNull();
});

test('registration verification redirects users who must change their password to the password settings page', function (): void {
    $user = User::factory()->unverified()->create([
        'must_change_password' => true,
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('password.edit', ['required' => 1]));
});

test('registration verification works from the email link without a current session', function (): void {
    $user = User::factory()->unverified()->create([
        'must_change_password' => true,
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('password.edit', ['required' => 1]));
});

test('registration verification switches from another authenticated user to the recipient', function (): void {
    $administrator = User::factory()->create([
        'is_active' => true,
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
    ]);
    $user = User::factory()->unverified()->create([
        'must_change_password' => true,
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($administrator)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('password.edit', ['required' => 1]));
});

test('verified users who must change their password are redirected before entering the application', function (): void {
    $user = User::factory()->create([
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('password.edit', ['required' => 1]));
});

test('required password change clears the requirement and allows the user to enter the application', function (): void {
    $user = User::factory()->create([
        'must_change_password' => true,
    ]);

    $response = $this->actingAs($user)
        ->from('/settings/password')
        ->put('/settings/password', [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

    $response->assertSessionHasNoErrors()->assertRedirect('/settings/password');
    expect($user->refresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('NewPassword123!', $user->password))->toBeTrue();

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

test('customer can update all requested profile fields in one submission', function (): void {
    $customer = User::factory()->create([
        'email' => 'customer-profile@example.test',
        'role' => UserRole::CUSTOMER->value,
        'phone_number' => '555-000-0000',
    ]);

    $response = $this->actingAs($customer)->patch('/settings/profile', [
        'first_name' => 'Updated',
        'last_name' => 'Customer',
        'email' => 'updated-customer@example.test',
        'phone_number' => '555-111-1111',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect('/settings/profile');

    $customer->refresh();

    expect($customer->first_name)->toBe('Updated')
        ->and($customer->last_name)->toBe('Customer')
        ->and($customer->email)->toBe('updated-customer@example.test')
        ->and($customer->phone_number)->toBe('555-111-1111')
        ->and($customer->email_verified_at)->toBeNull();
});

test('password settings expose the forced password change state', function (): void {
    $user = User::factory()->create([
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->get('/settings/password?required=1')
        ->assertOk()
        ->assertInertia(fn(InertiaAssert $page) => $page
            ->component('settings/Password')
            ->where('mustChangePassword', true));
});

/**
 * @return array<string, mixed>
 */
function registrationFlowPayload(): array
{
    return [
        'company_id' => Company::factory()->create()->id,
        'email' => 'registration-flow@example.test',
        'first_name' => 'Registration',
        'is_active' => true,
        'last_name' => 'Flow',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'phone_number' => '555-000-0000',
        'role' => UserRole::CUSTOMER->value,
        'type' => UserType::INDIVIDUAL->value,
    ];
}
