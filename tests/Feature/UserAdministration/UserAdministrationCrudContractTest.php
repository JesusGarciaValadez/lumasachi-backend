<?php

declare(strict_types=1);

namespace Tests\Feature\UserAdministration;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as InertiaAssert;
use Tests\TestCase;

final class UserAdministrationCrudContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_create_a_user_with_the_administration_contract(): void
    {
        $superAdministrator = User::factory()->create([
            'is_active' => true,
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);
        $company = Company::factory()->create();

        $response = $this->actingAs($superAdministrator)->post('/user', $this->validPayload($company));

        $response->assertRedirect('/users');
        $response->assertSessionHas('success');

        $createdUser = User::query()->where('email', 'created@example.test')->firstOrFail();

        $this->assertModelExists($createdUser);
        $this->assertTrue(Hash::check('Password123!', $createdUser->password));
        $this->assertNotSame('Password123!', $createdUser->password);
        $this->assertSame(UserRole::EMPLOYEE, $createdUser->role);
        $this->assertSame(UserType::INDIVIDUAL, $createdUser->type);
        $this->assertSame(Locale::ENGLISH->value, $createdUser->locale);
        $this->assertNotNull($createdUser->activated_at);
    }

    public function test_create_validation_preserves_safe_values_and_rejects_duplicate_email(): void
    {
        $superAdministrator = User::factory()->create([
            'is_active' => true,
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);

        $response = $this->actingAs($superAdministrator)->from('/user/create')->post('/user', [
            'first_name' => 'Preserved',
            'last_name' => 'Value',
            'email' => 'not-an-email',
            'password' => 'Password123!',
            'password_confirmation' => 'different',
        ]);

        $response->assertRedirect('/user/create');
        $response->assertSessionHasErrors(['email', 'password']);
        $response->assertSessionHasInput('first_name', 'Preserved');
        $response->assertSessionMissing('password');
    }

    public function test_administrator_cannot_submit_creation_or_privileged_fields(): void
    {
        $administrator = User::factory()->create([
            'is_active' => true,
            'role' => UserRole::ADMINISTRATOR->value,
        ]);

        $this->actingAs($administrator)
            ->post('/user', $this->validPayload())
            ->assertForbidden();
    }

    public function test_index_returns_scoped_dedicated_paginated_payload(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $administrator = User::factory()->create([
            'company_id' => $company->id,
            'is_active' => true,
            'role' => UserRole::ADMINISTRATOR->value,
        ]);

        User::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Visible',
            'is_active' => true,
            'last_name' => 'User',
            'role' => UserRole::EMPLOYEE->value,
        ]);
        User::factory()->create([
            'company_id' => $otherCompany->id,
            'first_name' => 'Hidden',
            'is_active' => true,
            'last_name' => 'User',
            'role' => UserRole::EMPLOYEE->value,
        ]);

        $response = $this->actingAs($administrator)->get('/users?first_name=Visible&per_page=20');

        $response->assertOk()->assertInertia(fn(InertiaAssert $page) => $page
            ->component('Users/Index')
            ->has('users.data', 1)
            ->where('users.per_page', 20)
            ->where('filters.first_name', 'Visible')
            ->missing('users.data.0.password')
            ->missing('users.data.0.notes')
        );
    }

    public function test_update_preserves_password_when_omitted_and_changes_it_when_supplied(): void
    {
        $superAdministrator = User::factory()->create([
            'is_active' => true,
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);
        $target = User::factory()->create([
            'is_active' => true,
            'password' => 'OriginalPassword123!',
        ]);
        $originalHash = $target->password;

        $response = $this->actingAs($superAdministrator)->put('/user/' . $target->uuid, [
            'first_name' => 'Updated',
            'last_name' => $target->last_name,
            'email' => $target->email,
            'role' => $target->role->value,
            'is_active' => true,
            'type' => $target->type->value,
            'locale' => Locale::SPANISH->value,
        ]);

        $response->assertRedirect('/users');
        $target->refresh();
        $this->assertSame('Updated', $target->first_name);
        $this->assertSame($originalHash, $target->password);

        $this->actingAs($superAdministrator)->put('/user/' . $target->uuid, [
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => $target->email,
            'password' => 'ChangedPassword123!',
            'password_confirmation' => 'ChangedPassword123!',
            'role' => $target->role->value,
            'is_active' => true,
            'type' => $target->type->value,
            'locale' => Locale::SPANISH->value,
        ])->assertRedirect('/users');

        $this->assertTrue(Hash::check('ChangedPassword123!', $target->refresh()->password));
    }

    public function test_forbidden_administrator_update_fields_are_not_accepted(): void
    {
        $company = Company::factory()->create();
        $administrator = User::factory()->create([
            'company_id' => $company->id,
            'is_active' => true,
            'role' => UserRole::ADMINISTRATOR->value,
        ]);
        $target = User::factory()->create([
            'company_id' => $company->id,
            'is_active' => true,
            'role' => UserRole::EMPLOYEE->value,
        ]);

        $this->actingAs($administrator)
            ->from('/user/' . $target->uuid)
            ->put('/user/' . $target->uuid, [
                'first_name' => 'Attempted',
                'last_name' => $target->last_name,
                'email' => $target->email,
                'role' => UserRole::SUPER_ADMINISTRATOR->value,
                'is_active' => false,
                'company_id' => Company::factory()->create()->id,
            ])
            ->assertSessionHasErrors(['role', 'is_active', 'company_id']);

        $this->assertSame(UserRole::EMPLOYEE, $target->refresh()->role);
        $this->assertTrue($target->is_active);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(?Company $company = null): array
    {
        return [
            'company_id' => $company?->id,
            'email' => 'created@example.test',
            'first_name' => 'Created',
            'is_active' => true,
            'last_name' => 'User',
            'locale' => Locale::ENGLISH->value,
            'notes' => 'Safe note',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone_number' => '555-000-0000',
            'role' => UserRole::EMPLOYEE->value,
            'type' => UserType::INDIVIDUAL->value,
        ];
    }
}
