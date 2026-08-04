<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('create sets a uuid and activation timestamp only for active users', function (): void {
    $service = app(UserService::class);

    $active = $service->create(userServiceAttributes('active-service@example.test', true));
    $inactive = $service->create(userServiceAttributes('inactive-service@example.test', false));

    $this->assertNotEmpty($active->uuid);
    $this->assertInstanceOf(DateTimeInterface::class, $active->activated_at);
    $this->assertNull($inactive->activated_at);
});

test('update preserves an existing password and activation timestamp', function (): void {
    $user = User::factory()->inactive()->create([
        'password' => 'OriginalPassword123!',
        'role' => UserRole::EMPLOYEE->value,
    ]);
    $originalHash = $user->password;

    $updated = app(UserService::class)->update($user, [
        'first_name' => 'Reactivated',
        'is_active' => true,
    ]);

    $this->assertSame('Reactivated', $updated->first_name);
    $this->assertSame($originalHash, $updated->password);
    $this->assertNotNull($updated->activated_at);

    $updated = app(UserService::class)->update($updated, ['password' => '']);
    $this->assertSame($originalHash, $updated->password);

    $activationTimestamp = $updated->activated_at;

    $updated = app(UserService::class)->update($updated, ['is_active' => false]);
    $this->assertFalse($updated->is_active);
    $this->assertEquals($activationTimestamp, $updated->activated_at);

    $updated = app(UserService::class)->update($updated, ['is_active' => true]);
    $this->assertEquals($activationTimestamp, $updated->activated_at);
    $this->assertTrue(Hash::check('OriginalPassword123!', $updated->password));
});

test('update changes the password when a new password is supplied', function (): void {
    $user = User::factory()->active()->create([
        'password' => 'OriginalPassword123!',
        'role' => UserRole::EMPLOYEE->value,
    ]);

    $updated = app(UserService::class)->update($user, [
        'password' => 'ChangedPassword123!',
    ]);

    $this->assertTrue(Hash::check('ChangedPassword123!', $updated->password));
    $this->assertFalse(Hash::check('OriginalPassword123!', $updated->password));
});

test('delete cannot remove the final active super administrator', function (): void {
    $user = User::factory()->active()->create([
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
    ]);

    $this->expectException(ValidationException::class);

    app(UserService::class)->delete($user);

    $this->assertModelExists($user);
});

test('delete soft deletes and can restore a user', function (): void {
    $user = User::factory()->active()->create([
        'role' => UserRole::EMPLOYEE->value,
    ]);

    app(UserService::class)->delete($user);

    $this->assertSoftDeleted($user);
    $this->assertNull(User::query()->find($user->id));

    $trashedUser = User::withTrashed()->findOrFail($user->id);
    $trashedUser->restore();

    $this->assertNull($trashedUser->refresh()->deleted_at);
    $this->assertModelExists($trashedUser);
});

test('final active super administrator cannot be demoted or deactivated', function (): void {
    $user = User::factory()->active()->create([
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
    ]);

    $this->expectException(ValidationException::class);

    app(UserService::class)->update($user, [
        'role' => UserRole::ADMINISTRATOR->value,
        'is_active' => false,
    ]);

    $this->assertSame(UserRole::SUPER_ADMINISTRATOR, $user->refresh()->role);
    $this->assertTrue($user->is_active);
});

test('update rolls back when the database rejects a duplicate email', function (): void {
    $user = User::factory()->active()->create([
        'email' => 'rollback-target@example.test',
        'first_name' => 'Before',
    ]);
    $otherUser = User::factory()->active()->create([
        'email' => 'rollback-existing@example.test',
    ]);

    $this->expectException(QueryException::class);

    try {
        app(UserService::class)->update($user, [
            'email' => $otherUser->email,
            'first_name' => 'Should not persist',
        ]);
    } finally {
        $this->assertSame('Before', $user->refresh()->first_name);
        $this->assertSame('rollback-target@example.test', $user->email);
    }
});

/**
 * @return array<string, mixed>
 */
function userServiceAttributes(string $email, bool $isActive): array
{
    return [
        'first_name' => 'Service',
        'last_name' => 'User',
        'email' => $email,
        'password' => 'Password123!',
        'company_id' => null,
        'role' => UserRole::EMPLOYEE->value,
        'phone_number' => null,
        'is_active' => $isActive,
        'notes' => null,
        'type' => 'Individual',
        'locale' => 'en',
    ];
}
