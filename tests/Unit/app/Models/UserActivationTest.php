<?php

declare(strict_types=1);

namespace Tests\Unit\app\Models;

use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class UserActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_have_a_nullable_activation_timestamp(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'activated_at'));

        $inactiveUser = User::factory()->inactive()->create();

        $this->assertFalse($inactiveUser->is_active);
        $this->assertNull($inactiveUser->activated_at);
    }

    public function test_active_factory_users_have_an_activation_timestamp(): void
    {
        $activeUser = User::factory()->active()->create();

        $this->assertTrue($activeUser->is_active);
        $this->assertInstanceOf(DateTimeInterface::class, $activeUser->activated_at);
    }

    public function test_user_uuid_is_unique_at_the_database_boundary(): void
    {
        $firstUser = User::factory()->create(['uuid' => '11111111-1111-7111-8111-111111111111']);

        $this->expectException(QueryException::class);

        User::factory()->create(['uuid' => $firstUser->uuid]);
    }

    public function test_activation_and_uuid_migrations_can_be_rolled_back_and_reapplied(): void
    {
        $activationMigration = require database_path('migrations/2026_08_01_220705_add_activated_at_to_users_table.php');
        $uuidMigration = require database_path('migrations/2026_08_01_220708_make_users_uuid_unique.php');

        $uuidMigration->down();
        $activationMigration->down();

        $this->assertFalse(Schema::hasColumn('users', 'activated_at'));

        $activationMigration->up();
        $uuidMigration->up();

        $this->assertTrue(Schema::hasColumn('users', 'activated_at'));
    }
}
