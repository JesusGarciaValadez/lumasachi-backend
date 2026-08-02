<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class UserAdministrationTest extends DuskTestCase
{
    use DatabaseTruncation;

    private Company $administratorCompany;

    private Company $otherCompany;

    private User $superAdministrator;

    private User $administrator;

    private User $sameCompanyUser;

    private User $inactiveSameCompanyUser;

    private User $otherCompanyUser;

    private User $employee;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administratorCompany = Company::factory()->create();
        $this->otherCompany = Company::factory()->create();

        $password = Hash::make('password');

        $this->superAdministrator = User::factory()->create([
            'email' => 'dusk-user-admin-super@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
            'password' => $password,
            'role' => UserRole::SUPER_ADMINISTRATOR->value,
        ]);

        $this->administrator = User::factory()->create([
            'company_id' => $this->administratorCompany->id,
            'email' => 'dusk-user-admin@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
            'password' => $password,
            'role' => UserRole::ADMINISTRATOR->value,
        ]);

        $this->sameCompanyUser = User::factory()->create([
            'company_id' => $this->administratorCompany->id,
            'email' => 'dusk-user-same-company@example.com',
            'first_name' => 'Same Company',
            'is_active' => true,
            'last_name' => 'User',
            'password' => $password,
            'role' => UserRole::EMPLOYEE->value,
        ]);

        $this->inactiveSameCompanyUser = User::factory()->create([
            'company_id' => $this->administratorCompany->id,
            'email' => 'dusk-user-inactive@example.com',
            'first_name' => 'Inactive',
            'is_active' => false,
            'last_name' => 'User',
            'password' => $password,
            'role' => UserRole::EMPLOYEE->value,
        ]);

        $this->otherCompanyUser = User::factory()->create([
            'company_id' => $this->otherCompany->id,
            'email' => 'dusk-user-other-company@example.com',
            'first_name' => 'Other Company',
            'is_active' => true,
            'last_name' => 'User',
            'password' => $password,
            'role' => UserRole::EMPLOYEE->value,
        ]);

        $this->employee = User::factory()->create([
            'email' => 'dusk-user-employee@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
            'password' => $password,
            'role' => UserRole::EMPLOYEE->value,
        ]);

        $this->customer = User::factory()->create([
            'email' => 'dusk-user-customer@example.com',
            'email_verified_at' => now(),
            'is_active' => true,
            'password' => $password,
            'role' => UserRole::CUSTOMER->value,
        ]);
    }

    public function test_super_administrator_can_create_and_edit_a_user_from_users(): void
    {
        $createdEmail = 'dusk-created-user@example.com';

        $this->browse(function (Browser $browser) use ($createdEmail): void {
            $browser->loginAs($this->superAdministrator)
                ->visit('/users')
                ->waitFor('@users-page')
                ->assertPresent('[data-sidebar="sidebar"]')
                ->assertPresent('[data-sidebar="trigger"]')
                ->assertCount('[data-sidebar="content"] [data-sidebar="menu-item"]', 3)
                ->assertPresent('@users-nav')
                ->assertAttribute('@users-filters-trigger', 'aria-expanded', 'false')
                ->assertMissing('@users-filters-panel')
                ->click('@users-filters-trigger')
                ->waitFor('@users-filters-panel')
                ->assertAttribute('@users-filters-trigger', 'aria-expanded', 'true')
                ->click('@users-filters-trigger')
                ->waitUntilMissing('@users-filters-panel', 5)
                ->assertAttribute('@users-filters-trigger', 'aria-expanded', 'false')
                ->click('@user-create-link')
                ->waitFor('@user-create-form')
                ->type('@user-first-name', 'Created')
                ->type('@user-last-name', 'Browser User')
                ->type('@user-email', $createdEmail)
                ->type('@user-password', 'password')
                ->type('@user-password-confirmation', 'password')
                ->select('@user-company', (string)$this->administratorCompany->id)
                ->select('@user-role', UserRole::EMPLOYEE->value)
                ->select('@user-type', UserType::INDIVIDUAL->value)
                ->select('@user-locale', Locale::ENGLISH->value)
                ->type('@user-phone', '5550000000')
                ->type('@user-notes', 'Created from the browser journey')
                ->check('@user-is-active')
                ->click('@user-form-submit')
                ->waitForLocation('/users')
                ->waitFor('@users-flash')
                ->assertSeeIn('@users-table', 'Browser User, Created')
                ->assertSeeIn('@users-flash', 'created');

            $createdUser = User::query()->where('email', $createdEmail)->firstOrFail();

            $browser->click('@user-row-link-' . $createdUser->uuid)
                ->waitFor('@user-form')
                ->assertInputValue('@user-first-name', 'Created')
                ->type('@user-last-name', 'Updated User')
                ->click('@user-form-submit')
                ->waitForLocation('/users')
                ->waitFor('@users-flash')
                ->assertSeeIn('@users-table', 'Updated User, Created')
                ->assertSeeIn('@users-flash', 'updated');
        });
    }

    public function test_user_pagination_uses_readable_navigation_labels(): void
    {
        User::factory()->active()->count(5)->create();

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->superAdministrator)
                ->visit('/users')
                ->waitFor('@users-page')
                ->waitFor('@users-pagination')
                ->assertSeeIn('@users-pagination', '« Previous')
                ->assertSeeIn('@users-pagination', 'Next »');
        });
    }

    public function test_invalid_user_submission_stays_on_form_with_field_error_and_safe_values(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->superAdministrator)
                ->visit('/user/create')
                ->waitFor('@user-create-form')
                ->type('@user-first-name', 'Preserved')
                ->type('@user-last-name', 'Browser Value')
                ->type('@user-email', 'not-an-email')
                ->type('@user-password', 'password')
                ->type('@user-password-confirmation', 'password')
                ->select('@user-company', (string)$this->administratorCompany->id)
                ->select('@user-role', UserRole::EMPLOYEE->value)
                ->select('@user-type', UserType::INDIVIDUAL->value)
                ->select('@user-locale', Locale::ENGLISH->value)
                ->type('@user-notes', 'Keep this non-password value')
                ->check('@user-is-active')
                ->click('@user-form-submit')
                ->waitFor('@user-error-email')
                ->assertPathIs('/user/create')
                ->assertPresent('@user-create-form')
                ->assertInputValue('@user-first-name', 'Preserved')
                ->assertInputValue('@user-last-name', 'Browser Value')
                ->assertInputValue('@user-email', 'not-an-email')
                ->assertInputValue('@user-notes', 'Keep this non-password value');
        });
    }

    public function test_super_administrator_can_archive_a_user_from_the_list_and_profile(): void
    {
        $listTarget = User::factory()->active()->create([
            'email' => 'dusk-delete-list@example.com',
            'role' => UserRole::EMPLOYEE->value,
        ]);
        $profileTarget = User::factory()->active()->create([
            'email' => 'dusk-delete-profile@example.com',
            'role' => UserRole::CUSTOMER->value,
        ]);

        $this->browse(function (Browser $browser) use ($listTarget, $profileTarget): void {
            $browser->loginAs($this->superAdministrator)
                ->visit('/users')
                ->waitFor('@users-page')
                ->click('@user-delete-trigger-' . $listTarget->uuid)
                ->waitFor('@user-delete-dialog-' . $listTarget->uuid)
                ->assertPresent('@user-delete-confirm-' . $listTarget->uuid)
                ->click('@user-delete-confirm-' . $listTarget->uuid)
                ->waitFor('@users-flash')
                ->assertSeeIn('@users-flash', 'archiv')
                ->assertMissing('@user-row-' . $listTarget->uuid)
                ->visit('/user/' . $profileTarget->uuid)
                ->waitFor('@user-form')
                ->click('@user-delete-trigger')
                ->waitFor('@user-delete-dialog')
                ->assertPresent('@user-delete-confirm')
                ->click('@user-delete-confirm')
                ->waitForLocation('/users')
                ->waitFor('@users-flash')
                ->assertSeeIn('@users-flash', 'archiv');
        });

        $this->assertSoftDeleted($listTarget);
        $this->assertSoftDeleted($profileTarget);
    }

    public function test_administrator_sees_only_same_company_users_and_cannot_open_an_inactive_row(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->administrator)
                ->visit('/users')
                ->waitFor('@users-page')
                ->assertPresent('[data-sidebar="sidebar"]')
                ->assertPresent('[data-sidebar="trigger"]')
                ->assertCount('[data-sidebar="content"] [data-sidebar="menu-item"]', 3)
                ->assertPresent('@user-row-link-' . $this->sameCompanyUser->uuid)
                ->assertMissing('@user-row-' . $this->otherCompanyUser->uuid)
                ->uncheck('@users-active-only')
                ->waitFor('@user-row-' . $this->inactiveSameCompanyUser->uuid)
                ->assertPresent('@user-row-' . $this->inactiveSameCompanyUser->uuid)
                ->assertMissing('@user-row-link-' . $this->inactiveSameCompanyUser->uuid)
                ->visit('/user/' . $this->inactiveSameCompanyUser->uuid)
                ->assertMissing('@user-form');
        });
    }

    public function test_administrator_cannot_reach_user_creation_by_direct_url(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->administrator)
                ->visit('/user/create')
                ->assertMissing('@user-create-form')
                ->assertMissing('@user-form-submit');
        });
    }

    public function test_employee_sees_sidebar_but_customer_does_not_see_sidebar_or_users_administration(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->employee)
                ->visit('/dashboard')
                ->assertPresent('[data-sidebar="sidebar"]')
                ->assertPresent('[data-sidebar="trigger"]')
                ->assertCount('[data-sidebar="content"] [data-sidebar="menu-item"]', 2)
                ->assertMissing('@users-nav')
                ->visit('/users')
                ->assertMissing('@users-page');

            $browser->loginAs($this->customer)
                ->visit('/dashboard')
                ->assertMissing('[data-sidebar="sidebar"]')
                ->assertMissing('[data-sidebar="trigger"]')
                ->assertMissing('@users-nav')
                ->assertSee('My Orders')
                ->assertMissing('@header-search')
                ->assertMissing('@dashboard-summary')
                ->assertPresent('@recent-orders')
                ->visit('/users')
                ->assertMissing('@users-page');
        });
    }
}
