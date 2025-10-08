<?php

namespace Tests\Feature\app\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as InertiaAssert;
use Tests\TestCase;

final class CatalogRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_access_engine_options_page(): void
    {
        $user = \App\Models\User::factory()->create(['role' => UserRole::EMPLOYEE->value]);
        $this->actingAs($user);

        $response = $this->get('/catalog/engine-options');
        $response->assertOk();
        $response->assertInertia(fn (InertiaAssert $page) =>
$page->component('Orders/EngineOptions')
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/catalog/engine-options');
        $response->assertRedirect('/login');
    }
}
