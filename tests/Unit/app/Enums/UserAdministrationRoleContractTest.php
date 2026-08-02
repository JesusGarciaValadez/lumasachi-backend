<?php

declare(strict_types=1);

namespace Tests\Unit\app\Enums;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class UserAdministrationRoleContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_cannot_use_the_user_creation_permission(): void
    {
        $this->assertNotContains('users.create', UserRole::getPermissions(UserRole::ADMINISTRATOR));
        $this->assertContains('users.create', UserRole::getPermissions(UserRole::SUPER_ADMINISTRATOR));
    }

    public function test_user_activation_timestamp_column_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'activated_at'));
    }
}
