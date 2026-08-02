<?php

declare(strict_types=1);

namespace Tests\Feature\UserAdministration;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as InertiaAssert;
use Tests\TestCase;

final class UserAdministrationReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_gets_global_ordered_results_and_visible_company_options(): void
    {
        $firstCompany = Company::factory()->active()->create();
        $secondCompany = Company::factory()->active()->create();
        $superAdministrator = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);

        User::factory()->active()->create([
            'company_id' => $firstCompany->id,
            'first_name' => 'Zoe',
            'last_name' => 'Alpha',
            'role' => UserRole::EMPLOYEE->value,
        ]);
        User::factory()->active()->create([
            'company_id' => $secondCompany->id,
            'first_name' => 'Amy',
            'last_name' => 'Alpha',
            'role' => UserRole::CUSTOMER->value,
            'type' => UserType::BUSINESS->value,
        ]);
        User::factory()->inactive()->create([
            'company_id' => $firstCompany->id,
            'first_name' => 'Hidden',
            'last_name' => 'Inactive',
        ]);

        $this->actingAs($superAdministrator)
            ->get('/users?last_name=Al&per_page=20')
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Users/Index')
                ->has('users.data', 2)
                ->where('users.data.0.first_name', 'Amy')
                ->where('users.data.1.first_name', 'Zoe')
                ->where('users.per_page', 20)
                ->has('options.companies', 2)
                ->where('users.data.0.role', UserRole::CUSTOMER->value)
                ->where('users.data.0.type', UserType::BUSINESS->value)
                ->missing('users.data.0.email')
                ->missing('users.data.0.notes')
                ->missing('users.data.0.password'));
    }

    public function test_administrator_scope_includes_only_their_company_and_no_company_options(): void
    {
        $company = Company::factory()->active()->create();
        $otherCompany = Company::factory()->active()->create();
        $administrator = User::factory()->active()->create([
            'company_id' => $company->id,
            'role' => UserRole::ADMINISTRATOR->value,
        ]);
        User::factory()->active()->create(['company_id' => $company->id]);
        User::factory()->inactive()->create(['company_id' => $company->id]);
        $hidden = User::factory()->active()->create([
            'company_id' => $otherCompany->id,
            'first_name' => 'Hidden',
        ]);

        $this->actingAs($administrator)
            ->get('/users?active=all&per_page=20')
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Users/Index')
                ->has('users.data', 3)
                ->where('options.companies', [])
                ->where('capabilities.can_open_inactive', false)
                ->missing('users.data.3'));

        $this->actingAs($administrator)->get('/users?company_id=' . $otherCompany->id)->assertSessionHasErrors('company_id');
        $this->assertDatabaseHas('users', ['id' => $hidden->id]);
    }

    public function test_administrator_without_a_company_has_an_empty_administration_scope(): void
    {
        $administrator = User::factory()->active()->create([
            'company_id' => null,
            'role' => UserRole::ADMINISTRATOR->value,
        ]);
        User::factory()->active()->create();

        $this->actingAs($administrator)
            ->get('/users')
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Users/Index')
                ->has('users.data', 0));
    }

    public function test_detail_payload_contains_edit_fields_but_never_a_password(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = User::factory()->active()->create([
            'company_id' => $company->id,
            'role' => UserRole::ADMINISTRATOR->value,
        ]);
        $target = User::factory()->active()->create([
            'company_id' => $company->id,
            'notes' => 'Private administration note',
        ]);

        $this->actingAs($administrator)
            ->get('/user/' . $target->uuid)
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Users/Show')
                ->where('user.email', $target->email)
                ->where('user.notes', 'Private administration note')
                ->missing('user.password')
                ->missing('user.password_confirmation'));
    }

    public function test_invalid_page_size_is_rejected(): void
    {
        $administrator = User::factory()->active()->create([
            'company_id' => Company::factory()->active()->create()->id,
            'role' => UserRole::ADMINISTRATOR->value,
        ]);

        $this->actingAs($administrator)
            ->from('/users')
            ->get('/users?per_page=15')
            ->assertRedirect('/users')
            ->assertSessionHasErrors('per_page');
    }

    public function test_combined_filters_preserve_the_scope_and_pagination_query_string(): void
    {
        $company = Company::factory()->active()->create();
        $superAdministrator = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);

        for ($index = 0; $index < 11; $index++) {
            User::factory()->active()->create([
                'company_id' => $company->id,
                'first_name' => 'Ana',
                'last_name' => 'Alpha',
                'role' => UserRole::EMPLOYEE->value,
                'type' => UserType::INDIVIDUAL->value,
            ]);
        }

        $response = $this->actingAs($superAdministrator)->get('/users?' . http_build_query([
                'active' => '1',
                'company_id' => $company->id,
                'first_name' => 'Ana',
                'last_name' => 'Al',
                'per_page' => 10,
                'role' => UserRole::EMPLOYEE->value,
                'type' => UserType::INDIVIDUAL->value,
            ]));

        $response->assertOk()->assertInertia(function (InertiaAssert $page): void {
            $page
                ->has('users.data', 10)
                ->where('users.total', 11)
                ->where('users.per_page', 10);

            $links = collect($page->toArray()['props']['users']['links']);
            $nextUrl = $links->first(
                fn(array $link): bool => str_contains((string)($link['url'] ?? ''), 'page=2'),
            )['url'] ?? null;

            self::assertNotNull($nextUrl);

            $labels = $links->pluck('label')->all();
            self::assertContains('« Previous', $labels);
            self::assertContains('Next »', $labels);
            self::assertNotContains('&laquo; Previous', $labels);
            self::assertNotContains('Next &raquo;', $labels);

            foreach (['active=1', 'company_id=', 'first_name=Ana', 'last_name=Al', 'per_page=10', 'role=Employee', 'type=Individual'] as $query) {
                self::assertStringContainsString($query, (string)$nextUrl);
            }
        });
    }
}
