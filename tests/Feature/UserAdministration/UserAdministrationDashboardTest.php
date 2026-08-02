<?php

declare(strict_types=1);

namespace Tests\Feature\UserAdministration;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as InertiaAssert;
use Tests\TestCase;

final class UserAdministrationDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dashboard_uses_the_same_company_scope_for_recent_users(): void
    {
        $company = Company::factory()->active()->create();
        $otherCompany = Company::factory()->active()->create();
        $administrator = User::factory()->active()->create([
            'company_id' => $company->id,
            'role' => UserRole::ADMINISTRATOR->value,
        ]);
        User::factory()->active()->create(['company_id' => $company->id]);
        User::factory()->active()->create(['company_id' => $otherCompany->id]);
        User::factory()->inactive()->create(['company_id' => $company->id]);

        $this->actingAs($administrator)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Dashboard')
                ->where('can_view_users', true)
                ->has('recent_users', 2));
    }

    public function test_employee_dashboard_can_use_the_sidebar_without_user_administration_data(): void
    {
        $employee = User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
        ]);
        User::factory()->active()->create();

        $this->actingAs($employee)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Dashboard')
                ->where('can_view_sidebar', true)
                ->where('can_view_users', false)
                ->where('recent_users', []));
    }

    public function test_super_administrators_and_customers_receive_the_correct_sidebar_capability(): void
    {
        foreach ([UserRole::SUPER_ADMINISTRATOR, UserRole::ADMINISTRATOR] as $role) {
            $this->actingAs(User::factory()->active()->create([
                'role' => $role->value,
            ]))
                ->get('/dashboard')
                ->assertOk()
                ->assertInertia(fn(InertiaAssert $page) => $page
                    ->where('can_view_sidebar', true)
                    ->where('is_customer', false));
        }

        $this->actingAs(User::factory()->active()->create([
            'role' => UserRole::CUSTOMER->value,
        ]))
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->where('can_view_sidebar', false)
                ->where('can_view_users', false)
                ->where('is_customer', true));
    }
}
