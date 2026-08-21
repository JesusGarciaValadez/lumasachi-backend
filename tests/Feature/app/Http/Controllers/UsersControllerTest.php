<?php

declare(strict_types=1);

namespace Tests\Feature\App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class UsersControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_list_only_active_companies(): void
    {
        $activeCompany = Company::factory()->active()->create(['name' => 'Active Company']);
        $secondActiveCompany = Company::factory()->active()->create(['name' => 'Second Active Company']);
        $inactiveCompany = Company::factory()->inactive()->create(['name' => 'Inactive Company']);
        $superAdministrator = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
            'company_id' => null,
        ]);

        $response = $this->actingAs($superAdministrator)
            ->getJson('/api/v1/users/companies');

        $response->assertOk()
            ->assertJsonStructure([
                ['id', 'uuid', 'name'],
            ])
            ->assertJsonMissingPath('0.email')
            ->assertJsonMissingPath('0.phone');

        $companyIds = collect($response->json())
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        self::assertSame(
            collect([$activeCompany->id, $secondActiveCompany->id])->sort()->values()->all(),
            $companyIds
        );
        self::assertNotContains($inactiveCompany->id, $companyIds);
    }

    public function test_super_administrator_gets_only_active_role_correct_participants_for_selected_company(): void
    {
        $selectedCompany = Company::factory()->active()->create();
        $otherCompany = Company::factory()->active()->create();
        $superAdministrator = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
            'company_id' => null,
        ]);

        $selectedEmployee = User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $selectedCompany->id,
        ]);
        $inactiveSelectedEmployee = User::factory()->inactive()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $selectedCompany->id,
        ]);
        $selectedAdministrator = User::factory()->active()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'company_id' => $selectedCompany->id,
        ]);
        $deletedSelectedEmployee = User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $selectedCompany->id,
        ]);
        $deletedSelectedEmployee->delete();

        $selectedCustomer = User::factory()->active()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => $selectedCompany->id,
        ]);
        $inactiveSelectedCustomer = User::factory()->inactive()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => $selectedCompany->id,
        ]);
        $otherCompanyEmployee = User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $otherCompany->id,
        ]);
        $otherCompanyCustomer = User::factory()->active()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => $otherCompany->id,
        ]);

        $employees = $this->actingAs($superAdministrator)
            ->getJson("/api/v1/users/employees?company_id={$selectedCompany->id}");
        $customers = $this->actingAs($superAdministrator)
            ->getJson("/api/v1/users/customers?company_id={$selectedCompany->id}");

        $this->assertParticipantIds($employees, [$selectedEmployee->id]);
        $this->assertParticipantIds($customers, [$selectedCustomer->id]);

        $employeeIds = collect($employees->json())->pluck('id')->all();
        $customerIds = collect($customers->json())->pluck('id')->all();

        self::assertNotContains($inactiveSelectedEmployee->id, $employeeIds);
        self::assertNotContains($selectedAdministrator->id, $employeeIds);
        self::assertNotContains($deletedSelectedEmployee->id, $employeeIds);
        self::assertNotContains($inactiveSelectedCustomer->id, $customerIds);
        self::assertNotContains($otherCompanyEmployee->id, $employeeIds);
        self::assertNotContains($otherCompanyCustomer->id, $customerIds);
    }

    public function test_super_administrator_has_no_participants_before_selecting_a_company(): void
    {
        $company = Company::factory()->active()->create();
        $superAdministrator = User::factory()->active()->create([
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
            'company_id' => null,
        ]);
        User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => null,
        ]);
        User::factory()->active()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => $company->id,
        ]);

        $employees = $this->actingAs($superAdministrator)
            ->getJson('/api/v1/users/employees');
        $customers = $this->actingAs($superAdministrator)
            ->getJson('/api/v1/users/customers');

        $employees->assertOk()->assertExactJson([]);
        $customers->assertOk()->assertExactJson([]);
    }

    public function test_administrator_gets_only_active_participants_from_their_company(): void
    {
        $company = Company::factory()->active()->create();
        $otherCompany = Company::factory()->active()->create();
        $administrator = User::factory()->active()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'company_id' => $company->id,
        ]);
        $sameCompanyEmployee = User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $company->id,
        ]);
        $sameCompanyCustomer = User::factory()->active()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => $company->id,
        ]);
        User::factory()->inactive()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $company->id,
        ]);
        User::factory()->inactive()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => $company->id,
        ]);
        $otherCompanyEmployee = User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $otherCompany->id,
        ]);
        $otherCompanyCustomer = User::factory()->active()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => $otherCompany->id,
        ]);

        $employees = $this->actingAs($administrator)
            ->getJson('/api/v1/users/employees');
        $customers = $this->actingAs($administrator)
            ->getJson('/api/v1/users/customers');

        $this->assertParticipantIds($employees, [$sameCompanyEmployee->id]);
        $this->assertParticipantIds($customers, [$sameCompanyCustomer->id]);

        self::assertNotContains($otherCompanyEmployee->id, collect($employees->json())->pluck('id')->all());
        self::assertNotContains($otherCompanyCustomer->id, collect($customers->json())->pluck('id')->all());
    }

    public function test_employee_gets_only_active_participants_from_their_company(): void
    {
        $company = Company::factory()->active()->create();
        $otherCompany = Company::factory()->active()->create();
        $employee = User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $company->id,
        ]);
        $sameCompanyEmployee = User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $company->id,
        ]);
        $sameCompanyCustomer = User::factory()->active()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => $company->id,
        ]);
        $otherCompanyEmployee = User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => $otherCompany->id,
        ]);
        $otherCompanyCustomer = User::factory()->active()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => $otherCompany->id,
        ]);

        $employees = $this->actingAs($employee)
            ->getJson('/api/v1/users/employees');
        $customers = $this->actingAs($employee)
            ->getJson('/api/v1/users/customers');

        $this->assertParticipantIds($employees, [$employee->id, $sameCompanyEmployee->id]);
        $this->assertParticipantIds($customers, [$sameCompanyCustomer->id]);

        self::assertNotContains($otherCompanyEmployee->id, collect($employees->json())->pluck('id')->all());
        self::assertNotContains($otherCompanyCustomer->id, collect($customers->json())->pluck('id')->all());
    }

    public function test_administrator_and_employee_without_a_company_have_no_participant_scope(): void
    {
        $company = Company::factory()->active()->create();
        $administrator = User::factory()->active()->create([
            'role' => UserRole::ADMINISTRATOR->value,
            'company_id' => null,
        ]);
        $employee = User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => null,
        ]);
        User::factory()->active()->create([
            'role' => UserRole::EMPLOYEE->value,
            'company_id' => null,
        ]);
        User::factory()->active()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => null,
        ]);
        User::factory()->active()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => $company->id,
        ]);

        foreach ([$administrator, $employee] as $actor) {
            $employees = $this->actingAs($actor)->getJson('/api/v1/users/employees');
            $customers = $this->actingAs($actor)->getJson('/api/v1/users/customers');

            $employees->assertOk()->assertExactJson([]);
            $customers->assertOk()->assertExactJson([]);
        }
    }

    public function test_every_participant_lookup_requires_order_create_authorization(): void
    {
        $paths = [
            '/api/v1/users/companies',
            '/api/v1/users/employees',
            '/api/v1/users/customers',
        ];

        foreach ($paths as $path) {
            $this->getJson($path)->assertUnauthorized();
        }

        $company = Company::factory()->active()->create();
        $customer = User::factory()->active()->create([
            'role' => UserRole::CUSTOMER->value,
            'company_id' => $company->id,
        ]);

        foreach ($paths as $path) {
            $this->actingAs($customer)->getJson($path)->assertForbidden();
        }
    }

    private function assertParticipantIds(TestResponse $response, array $expectedIds): void
    {
        $response->assertOk()
            ->assertJsonMissingPath('0.email')
            ->assertJsonMissingPath('0.phone_number')
            ->assertJsonMissingPath('0.notes');

        $actualIds = collect($response->json())
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
        $expectedIds = collect($expectedIds)->sort()->values()->all();

        self::assertSame($expectedIds, $actualIds);
    }
}
