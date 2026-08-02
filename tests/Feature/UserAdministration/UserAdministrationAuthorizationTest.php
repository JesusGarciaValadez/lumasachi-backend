<?php

declare(strict_types=1);

namespace Tests\Feature\UserAdministration;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserAdministrationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_user_index(): void
    {
        $this->get('/users')->assertRedirect('/login');
    }

    public function test_employees_are_forbidden_from_the_user_index(): void
    {
        $user = $this->user(UserRole::EMPLOYEE, null, true);

        $this->actingAs($user)->get('/users')->assertForbidden();
    }

    public function test_customers_are_forbidden_from_the_user_index(): void
    {
        $user = $this->user(UserRole::CUSTOMER, null, true);

        $this->actingAs($user)->get('/users')->assertForbidden();
    }

    public function test_super_administrators_can_access_the_user_index(): void
    {
        $user = $this->user(UserRole::SUPER_ADMINISTRATOR, null, true);

        $this->actingAs($user)->get('/users')->assertOk();
    }

    public function test_administrators_can_access_the_user_index(): void
    {
        $user = $this->user(UserRole::ADMINISTRATOR, null, true);

        $this->actingAs($user)->get('/users')->assertOk();
    }

    public function test_administrators_can_view_an_active_profile_in_their_company(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = $this->user(UserRole::ADMINISTRATOR, $company, true);
        $sameCompanyActiveUser = $this->user(UserRole::EMPLOYEE, $company, true);

        $this->assertTrue($administrator->can('view', $sameCompanyActiveUser));
    }

    public function test_administrators_can_open_a_same_company_active_profile(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = $this->user(UserRole::ADMINISTRATOR, $company, true);
        $sameCompanyActiveUser = $this->user(UserRole::EMPLOYEE, $company, true);

        $this->actingAs($administrator)
            ->get('/user/' . $sameCompanyActiveUser->uuid)
            ->assertOk();
    }

    public function test_administrators_cannot_view_a_cross_company_profile(): void
    {
        $company = Company::factory()->active()->create();
        $otherCompany = Company::factory()->active()->create();
        $administrator = $this->user(UserRole::ADMINISTRATOR, $company, true);
        $crossCompanyUser = $this->user(UserRole::EMPLOYEE, $otherCompany, true);

        $this->assertFalse($administrator->can('view', $crossCompanyUser));
    }

    public function test_administrators_cannot_view_an_inactive_profile(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = $this->user(UserRole::ADMINISTRATOR, $company, true);
        $inactiveUser = $this->user(UserRole::EMPLOYEE, $company, false);

        $this->assertFalse($administrator->can('view', $inactiveUser));
    }

    public function test_administrators_cannot_directly_view_a_cross_company_profile(): void
    {
        $company = Company::factory()->active()->create();
        $otherCompany = Company::factory()->active()->create();
        $administrator = $this->user(UserRole::ADMINISTRATOR, $company, true);
        $crossCompanyUser = $this->user(UserRole::EMPLOYEE, $otherCompany, true);

        $this->actingAs($administrator)
            ->get('/user/' . $crossCompanyUser->uuid)
            ->assertForbidden();
    }

    public function test_administrators_cannot_directly_update_a_cross_company_profile(): void
    {
        $company = Company::factory()->active()->create();
        $otherCompany = Company::factory()->active()->create();
        $administrator = $this->user(UserRole::ADMINISTRATOR, $company, true);
        $crossCompanyUser = $this->user(UserRole::EMPLOYEE, $otherCompany, true);

        $this->actingAs($administrator)
            ->put('/user/' . $crossCompanyUser->uuid, $this->forgedUserPayload($otherCompany))
            ->assertForbidden();
    }

    public function test_administrators_cannot_directly_view_an_inactive_profile(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = $this->user(UserRole::ADMINISTRATOR, $company, true);
        $inactiveUser = $this->user(UserRole::EMPLOYEE, $company, false);

        $this->actingAs($administrator)
            ->get('/user/' . $inactiveUser->uuid)
            ->assertForbidden();
    }

    public function test_administrators_cannot_directly_update_an_inactive_profile(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = $this->user(UserRole::ADMINISTRATOR, $company, true);
        $inactiveUser = $this->user(UserRole::EMPLOYEE, $company, false);

        $this->actingAs($administrator)
            ->put('/user/' . $inactiveUser->uuid, $this->forgedUserPayload($company))
            ->assertForbidden();
    }

    public function test_super_administrators_can_view_an_active_profile_across_companies(): void
    {
        $company = Company::factory()->active()->create();
        $otherCompany = Company::factory()->active()->create();
        $superAdministrator = $this->user(UserRole::SUPER_ADMINISTRATOR, $company, true);
        $activeUser = $this->user(UserRole::EMPLOYEE, $otherCompany, true);

        $this->assertTrue($superAdministrator->can('view', $activeUser));

        $this->actingAs($superAdministrator)
            ->get('/user/' . $activeUser->uuid)
            ->assertOk();
    }

    public function test_super_administrators_can_view_an_inactive_profile(): void
    {
        $company = Company::factory()->active()->create();
        $superAdministrator = $this->user(UserRole::SUPER_ADMINISTRATOR, $company, true);
        $inactiveUser = $this->user(UserRole::EMPLOYEE, $company, false);

        $this->assertTrue($superAdministrator->can('view', $inactiveUser));

        $this->actingAs($superAdministrator)
            ->get('/user/' . $inactiveUser->uuid)
            ->assertOk();
    }

    public function test_administrators_cannot_create_users(): void
    {
        $administrator = $this->user(UserRole::ADMINISTRATOR, null, true);

        $this->assertFalse($administrator->can('create', User::class));
    }

    public function test_super_administrators_can_create_users(): void
    {
        $superAdministrator = $this->user(UserRole::SUPER_ADMINISTRATOR, null, true);

        $this->assertTrue($superAdministrator->can('create', User::class));
    }

    public function test_employees_cannot_create_users(): void
    {
        $employee = $this->user(UserRole::EMPLOYEE, null, true);

        $this->assertFalse($employee->can('create', User::class));
    }

    public function test_super_administrators_can_open_the_user_creation_page(): void
    {
        $superAdministrator = $this->user(UserRole::SUPER_ADMINISTRATOR, null, true);

        $this->actingAs($superAdministrator)->get('/user/create')->assertOk();
    }

    public function test_administrators_cannot_open_the_user_creation_page(): void
    {
        $administrator = $this->user(UserRole::ADMINISTRATOR, null, true);

        $this->actingAs($administrator)->get('/user/create')->assertForbidden();
    }

    public function test_administrators_cannot_forge_the_user_creation_request(): void
    {
        $administrator = $this->user(UserRole::ADMINISTRATOR, null, true);

        $this->actingAs($administrator)
            ->post('/user', $this->validUserPayload())
            ->assertForbidden();
    }

    public function test_inactive_users_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.test',
            'is_active' => false,
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guest_registration_routes_are_retired(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_guest_web_registration_submission_is_retired(): void
    {
        $this->post('/register', $this->validUserPayload())->assertNotFound();
    }

    public function test_guest_api_registration_submission_is_retired(): void
    {
        $this->postJson('/api/v1/register', $this->validUserPayload())->assertNotFound();
    }

    public function test_email_bound_user_lookup_api_route_is_retired(): void
    {
        $requester = User::factory()->create([
            'is_active' => true,
        ]);
        $target = User::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($requester, 'sanctum')
            ->getJson('/api/v1/user/' . $target->email)
            ->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function validUserPayload(): array
    {
        return [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new-user@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::EMPLOYEE->value,
            'is_active' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function forgedUserPayload(Company $company): array
    {
        return [
            'first_name' => 'Forged',
            'company_id' => $company->id,
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ];
    }

    private function user(UserRole $role, ?Company $company, bool $isActive): User
    {
        return User::factory()->create([
            'company_id' => $company?->id,
            'is_active' => $isActive,
            'role' => $role->value,
        ]);
    }
}
