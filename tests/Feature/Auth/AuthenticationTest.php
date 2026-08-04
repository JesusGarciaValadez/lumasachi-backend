<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});
test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});
test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});
test('soft-deleted users cannot authenticate', function () {
    $user = User::factory()->create([
        'email' => 'archived@example.test',
        'password' => 'password',
    ]);
    $user->delete();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('api token issuance requires verified email and a changed password', function (): void {
    $unverified = User::factory()->create([
        'email' => 'api-unverified@example.test',
        'email_verified_at' => null,
    ]);

    $this->postJson('/api/v1/sanctum/token', [
        'device_name' => 'test-device',
        'email' => $unverified->email,
        'password' => 'password',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'auth.email_verification_required');

    $pendingPasswordChange = User::factory()->create([
        'email' => 'api-pending-password@example.test',
        'must_change_password' => true,
    ]);

    $this->postJson('/api/v1/sanctum/token', [
        'device_name' => 'test-device',
        'email' => $pendingPasswordChange->email,
        'password' => 'password',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'auth.password_change_required');

    $eligibleUser = User::factory()->create([
        'email' => 'api-eligible@example.test',
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);

    $response = $this->postJson('/api/v1/sanctum/token', [
        'device_name' => 'test-device',
        'email' => $eligibleUser->email,
        'password' => 'password',
    ]);

    $response->assertOk();
    expect($eligibleUser->tokens()->count())->toBe(1)
        ->and(Hash::check('password', $eligibleUser->password))->toBeTrue();
});
