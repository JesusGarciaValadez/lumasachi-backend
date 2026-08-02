<?php

declare(strict_types=1);

namespace Tests\Unit\UserAdministration;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserService;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_sets_a_uuid_and_activation_timestamp_only_for_active_users(): void
    {
        $service = app(UserService::class);

        $active = $service->create($this->attributes('active-service@example.test', true));
        $inactive = $service->create($this->attributes('inactive-service@example.test', false));

        $this->assertNotEmpty($active->uuid);
        $this->assertInstanceOf(DateTimeInterface::class, $active->activated_at);
        $this->assertNull($inactive->activated_at);
    }

    public function test_update_preserves_an_existing_password_and_activation_timestamp(): void
    {
        $user = User::factory()->inactive()->create([
            'password' => 'OriginalPassword123!',
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
    }

    public function test_update_changes_the_password_when_a_new_password_is_supplied(): void
    {
        $user = User::factory()->active()->create([
            'password' => 'OriginalPassword123!',
        ]);

        $updated = app(UserService::class)->update($user, [
            'password' => 'ChangedPassword123!',
        ]);

        $this->assertTrue(Hash::check('ChangedPassword123!', $updated->password));
        $this->assertFalse(Hash::check('OriginalPassword123!', $updated->password));
    }

    public function test_delete_cannot_remove_the_final_active_super_administrator(): void
    {
        $user = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);

        $this->expectException(ValidationException::class);

        app(UserService::class)->delete($user);

        $this->assertModelExists($user);
    }

    public function test_delete_soft_deletes_and_can_restore_a_user(): void
    {
        $user = User::factory()->active()->create();

        app(UserService::class)->delete($user);

        $this->assertSoftDeleted($user);
        $this->assertNull(User::query()->find($user->id));

        $trashedUser = User::withTrashed()->findOrFail($user->id);
        $trashedUser->restore();

        $this->assertNull($trashedUser->refresh()->deleted_at);
        $this->assertModelExists($trashedUser);
    }

    public function test_final_active_super_administrator_cannot_be_demoted_or_deactivated(): void
    {
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
    }

    public function test_update_rolls_back_when_the_database_rejects_a_duplicate_email(): void
    {
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
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(string $email, bool $isActive): array
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
}
