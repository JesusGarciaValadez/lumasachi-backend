<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected from the user index', function (): void {
    $this->get('/users')->assertRedirect('/login');
});

test('employees are forbidden from the user index', function (): void {
    $user = userAdministrationAuthorizationUser(UserRole::EMPLOYEE, null, true);

    $this->actingAs($user)->get('/users')->assertForbidden();
});

test('customers are forbidden from the user index', function (): void {
    $user = userAdministrationAuthorizationUser(UserRole::CUSTOMER, null, true);

    $this->actingAs($user)->get('/users')->assertForbidden();
});

test('super administrators can access the user index', function (): void {
    $user = userAdministrationAuthorizationUser(UserRole::SUPER_ADMINISTRATOR, null, true);

    $this->actingAs($user)->get('/users')->assertOk();
});

test('administrators can access the user index', function (): void {
    $user = userAdministrationAuthorizationUser(UserRole::ADMINISTRATOR, null, true);

    $this->actingAs($user)->get('/users')->assertOk();
});

test('administrators can view an active profile in their company', function (): void {
    $company = Company::factory()->active()->create();
    $administrator = userAdministrationAuthorizationUser(UserRole::ADMINISTRATOR, $company, true);
    $sameCompanyActiveUser = userAdministrationAuthorizationUser(UserRole::EMPLOYEE, $company, true);

    $this->assertTrue($administrator->can('view', $sameCompanyActiveUser));
});

test('administrators can open a same company active profile', function (): void {
    $company = Company::factory()->active()->create();
    $administrator = userAdministrationAuthorizationUser(UserRole::ADMINISTRATOR, $company, true);
    $sameCompanyActiveUser = userAdministrationAuthorizationUser(UserRole::EMPLOYEE, $company, true);

    $this->actingAs($administrator)
        ->get('/user/' . $sameCompanyActiveUser->uuid)
        ->assertOk();
});

test('administrators cannot view a cross company profile', function (): void {
    $company = Company::factory()->active()->create();
    $otherCompany = Company::factory()->active()->create();
    $administrator = userAdministrationAuthorizationUser(UserRole::ADMINISTRATOR, $company, true);
    $crossCompanyUser = userAdministrationAuthorizationUser(UserRole::EMPLOYEE, $otherCompany, true);

    $this->assertFalse($administrator->can('view', $crossCompanyUser));
});

test('administrators cannot view an inactive profile', function (): void {
    $company = Company::factory()->active()->create();
    $administrator = userAdministrationAuthorizationUser(UserRole::ADMINISTRATOR, $company, true);
    $inactiveUser = userAdministrationAuthorizationUser(UserRole::EMPLOYEE, $company, false);

    $this->assertFalse($administrator->can('view', $inactiveUser));
});

test('administrators cannot directly view a cross company profile', function (): void {
    $company = Company::factory()->active()->create();
    $otherCompany = Company::factory()->active()->create();
    $administrator = userAdministrationAuthorizationUser(UserRole::ADMINISTRATOR, $company, true);
    $crossCompanyUser = userAdministrationAuthorizationUser(UserRole::EMPLOYEE, $otherCompany, true);

    $this->actingAs($administrator)
        ->get('/user/' . $crossCompanyUser->uuid)
        ->assertForbidden();
});

test('administrators cannot directly update a cross company profile', function (): void {
    $company = Company::factory()->active()->create();
    $otherCompany = Company::factory()->active()->create();
    $administrator = userAdministrationAuthorizationUser(UserRole::ADMINISTRATOR, $company, true);
    $crossCompanyUser = userAdministrationAuthorizationUser(UserRole::EMPLOYEE, $otherCompany, true);

    $this->actingAs($administrator)
        ->put('/user/' . $crossCompanyUser->uuid, userAdministrationAuthorizationForgedUserPayload($otherCompany))
        ->assertForbidden();
});

test('administrators cannot directly view an inactive profile', function (): void {
    $company = Company::factory()->active()->create();
    $administrator = userAdministrationAuthorizationUser(UserRole::ADMINISTRATOR, $company, true);
    $inactiveUser = userAdministrationAuthorizationUser(UserRole::EMPLOYEE, $company, false);

    $this->actingAs($administrator)
        ->get('/user/' . $inactiveUser->uuid)
        ->assertForbidden();
});

test('administrators cannot directly update an inactive profile', function (): void {
    $company = Company::factory()->active()->create();
    $administrator = userAdministrationAuthorizationUser(UserRole::ADMINISTRATOR, $company, true);
    $inactiveUser = userAdministrationAuthorizationUser(UserRole::EMPLOYEE, $company, false);

    $this->actingAs($administrator)
        ->put('/user/' . $inactiveUser->uuid, userAdministrationAuthorizationForgedUserPayload($company))
        ->assertForbidden();
});

test('super administrators can view an active profile across companies', function (): void {
    $company = Company::factory()->active()->create();
    $otherCompany = Company::factory()->active()->create();
    $superAdministrator = userAdministrationAuthorizationUser(UserRole::SUPER_ADMINISTRATOR, $company, true);
    $activeUser = userAdministrationAuthorizationUser(UserRole::EMPLOYEE, $otherCompany, true);

    $this->assertTrue($superAdministrator->can('view', $activeUser));

    $this->actingAs($superAdministrator)
        ->get('/user/' . $activeUser->uuid)
        ->assertOk();
});

test('super administrators can view an inactive profile', function (): void {
    $company = Company::factory()->active()->create();
    $superAdministrator = userAdministrationAuthorizationUser(UserRole::SUPER_ADMINISTRATOR, $company, true);
    $inactiveUser = userAdministrationAuthorizationUser(UserRole::EMPLOYEE, $company, false);

    $this->assertTrue($superAdministrator->can('view', $inactiveUser));

    $this->actingAs($superAdministrator)
        ->get('/user/' . $inactiveUser->uuid)
        ->assertOk();
});

test('administrators cannot create users', function (): void {
    $administrator = userAdministrationAuthorizationUser(UserRole::ADMINISTRATOR, null, true);

    $this->assertFalse($administrator->can('create', User::class));
});

test('super administrators can create users', function (): void {
    $superAdministrator = userAdministrationAuthorizationUser(UserRole::SUPER_ADMINISTRATOR, null, true);

    $this->assertTrue($superAdministrator->can('create', User::class));
});

test('employees cannot create users', function (): void {
    $employee = userAdministrationAuthorizationUser(UserRole::EMPLOYEE, null, true);

    $this->assertFalse($employee->can('create', User::class));
});

test('super administrators can open the user creation page', function (): void {
    $superAdministrator = userAdministrationAuthorizationUser(UserRole::SUPER_ADMINISTRATOR, null, true);

    $this->actingAs($superAdministrator)->get('/user/create')->assertOk();
});

test('administrators cannot open the user creation page', function (): void {
    $administrator = userAdministrationAuthorizationUser(UserRole::ADMINISTRATOR, null, true);

    $this->actingAs($administrator)->get('/user/create')->assertForbidden();
});

test('administrators cannot forge the user creation request', function (): void {
    $administrator = userAdministrationAuthorizationUser(UserRole::ADMINISTRATOR, null, true);

    $this->actingAs($administrator)
        ->post('/user', userAdministrationAuthorizationValidUserPayload())
        ->assertForbidden();
});

test('inactive users cannot authenticate', function (): void {
    $user = User::factory()->create([
        'email' => 'inactive@example.test',
        'is_active' => false,
        'password' => 'password',
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('guest registration routes are retired', function (): void {
    $this->get('/register')->assertNotFound();
});

test('guest web registration submission is retired', function (): void {
    $this->post('/register', userAdministrationAuthorizationValidUserPayload())->assertNotFound();
});

test('guest api registration submission is retired', function (): void {
    $this->postJson('/api/v1/register', userAdministrationAuthorizationValidUserPayload())->assertNotFound();
});

test('email bound user lookup api route is retired', function (): void {
    $requester = User::factory()->create([
        'is_active' => true,
    ]);
    $target = User::factory()->create([
        'is_active' => true,
    ]);

    $this->actingAs($requester, 'sanctum')
        ->getJson('/api/v1/user/' . $target->email)
        ->assertNotFound();
});

/**
 * @return array<string, mixed>
 */
function userAdministrationAuthorizationValidUserPayload(): array
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
function userAdministrationAuthorizationForgedUserPayload(Company $company): array
{
    return [
        'first_name' => 'Forged',
        'company_id' => $company->id,
        'role' => UserRole::SUPER_ADMINISTRATOR->value,
    ];
}

function userAdministrationAuthorizationUser(UserRole $role, ?Company $company, bool $isActive): User
{
    return User::factory()->create([
        'company_id' => $company?->id,
        'is_active' => $isActive,
        'role' => $role->value,
    ]);
}
