<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('administrator cannot use the user creation permission', function (): void {
    $this->assertNotContains('users.create', UserRole::getPermissions(UserRole::ADMINISTRATOR));
    $this->assertContains('users.create', UserRole::getPermissions(UserRole::SUPER_ADMINISTRATOR));
});

test('user activation timestamp column exists', function (): void {
    $this->assertTrue(Schema::hasColumn('users', 'activated_at'));
});
