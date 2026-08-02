<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('users have a nullable activation timestamp', function (): void {
    $this->assertTrue(Schema::hasColumn('users', 'activated_at'));

    $inactiveUser = User::factory()->inactive()->create();

    $this->assertFalse($inactiveUser->is_active);
    $this->assertNull($inactiveUser->activated_at);
});

test('active factory users have an activation timestamp', function (): void {
    $activeUser = User::factory()->active()->create();

    $this->assertTrue($activeUser->is_active);
    $this->assertInstanceOf(DateTimeInterface::class, $activeUser->activated_at);
});

test('user uuid is unique at the database boundary', function (): void {
    $firstUser = User::factory()->create(['uuid' => '11111111-1111-7111-8111-111111111111']);

    $this->expectException(QueryException::class);

    User::factory()->create(['uuid' => $firstUser->uuid]);
});

test('activation and uuid migrations can be rolled back and reapplied', function (): void {
    $activationMigration = require database_path('migrations/2026_08_01_220705_add_activated_at_to_users_table.php');
    $uuidMigration = require database_path('migrations/2026_08_01_220708_make_users_uuid_unique.php');

    $uuidMigration->down();
    $activationMigration->down();

    $this->assertFalse(Schema::hasColumn('users', 'activated_at'));

    $activationMigration->up();
    $uuidMigration->up();

    $this->assertTrue(Schema::hasColumn('users', 'activated_at'));
});
