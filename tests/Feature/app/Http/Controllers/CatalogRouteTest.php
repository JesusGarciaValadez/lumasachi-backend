<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Inertia\Testing\AssertableInertia as InertiaAssert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('employee can access engine options page', function () {
    $user = App\Models\User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
    $this->actingAs($user);

    $response = $this->get('/catalog/engine-options');
    $response->assertOk();
    $response->assertInertia(fn(InertiaAssert $page) => $page->component('Orders/EngineOptions')
    );
});
test('guest is redirected to login', function () {
    $response = $this->get('/catalog/engine-options');
    $response->assertRedirect('/login');
});
