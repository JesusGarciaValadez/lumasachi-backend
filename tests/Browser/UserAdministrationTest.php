<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

uses(DuskTestCase::class);

beforeEach(function (): void {
    $password = Hash::make('password');

    $this->administrator = User::factory()->create([
        'email' => 'dusk-user-admin@example.com',
        'email_verified_at' => now(),
        'is_active' => true,
        'password' => $password,
        'role' => UserRole::ADMINISTRATOR->value,
    ]);

    $this->sameCompanyUser = User::factory()->create([
        'email' => 'dusk-user-same-company@example.com',
        'first_name' => 'Same Company',
        'is_active' => true,
        'last_name' => 'User',
        'password' => $password,
        'role' => UserRole::EMPLOYEE->value,
    ]);

    $this->inactiveSameCompanyUser = User::factory()->create([
        'email' => 'dusk-user-inactive@example.com',
        'first_name' => 'Inactive',
        'is_active' => false,
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
});

test('super administrator can create and edit a user from users', function (): void {
    $createdEmail = 'dusk-created-user@example.com';

    $this->browse(function (Browser $browser) use ($createdEmail): void {
        $this->centralAdministratorEntersDuskTenant($browser);

        $browser->visit('/users')
            ->waitFor('@users-page')
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
});

test('user pagination uses readable navigation labels', function (): void {
    User::factory()->active()->count(12)->create(['role' => UserRole::EMPLOYEE->value]);

    $this->browse(function (Browser $browser): void {
        $this->centralAdministratorEntersDuskTenant($browser);

        $browser->visit('/users')
            ->waitFor('@users-page')
            ->waitFor('@users-pagination')
            ->assertSeeIn('@users-pagination', '« Previous')
            ->assertSeeIn('@users-pagination', 'Next »');
    });
});

test('super administrator can open a recent user from the dashboard card', function (): void {
    $target = User::factory()->active()->create([
        'first_name' => 'Dashboard',
        'last_name' => 'User',
        'role' => UserRole::EMPLOYEE->value,
    ]);

    $this->browse(function (Browser $browser) use ($target): void {
        $this->centralAdministratorEntersDuskTenant($browser);

        $browser->visit('/dashboard')
            ->waitFor('@dashboard-summary')
            ->click('@dashboard-user-' . $target->uuid)
            ->waitForLocation('/user/' . $target->uuid)
            ->waitFor('@user-form')
            ->assertInputValue('@user-first-name', 'Dashboard')
            ->assertInputValue('@user-last-name', 'User');
    });
});

test('tenant administrator cannot open another user from the dashboard', function (): void {
    $this->browse(function (Browser $browser): void {
        $this->tenantUserLogsIn($browser, $this->administrator);

        $browser->visit('/dashboard')
            ->assertMissing('@users-nav')
            ->visit('/users')
            ->assertMissing('@users-page')
            ->visit('/user/' . $this->sameCompanyUser->uuid)
            ->assertMissing('@user-form');
    });
});

test('super administrator can find accented names without accents', function (): void {
    $target = User::factory()->active()->create([
        'first_name' => 'Jesús',
        'last_name' => 'García',
        'role' => UserRole::EMPLOYEE->value,
    ]);

    $this->browse(function (Browser $browser) use ($target): void {
        $this->centralAdministratorEntersDuskTenant($browser);

        $browser->visit('/users')
            ->waitFor('@users-page')
            ->click('@users-filters-trigger')
            ->waitFor('@users-filters-panel')
            ->type('#users-first-name', 'Jesus')
            ->waitFor('@user-row-link-' . $target->uuid)
            ->assertSeeIn('@users-table', 'García, Jesús');
    });
});

test('invalid user submission stays on form with field error and safe values', function (): void {
    $this->browse(function (Browser $browser): void {
        $this->centralAdministratorEntersDuskTenant($browser);

        $browser->visit('/user/create')
            ->waitFor('@user-create-form')
            ->type('@user-first-name', 'Preserved')
            ->type('@user-last-name', 'Browser Value')
            ->type('@user-email', 'not-an-email')
            ->type('@user-password', 'password')
            ->type('@user-password-confirmation', 'password')
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
});

test('super administrator can archive a user from the list and profile', function (): void {
    $listTarget = User::factory()->active()->create([
        'email' => 'dusk-delete-list@example.com',
        'role' => UserRole::EMPLOYEE->value,
    ]);
    $profileTarget = User::factory()->active()->create([
        'email' => 'dusk-delete-profile@example.com',
        'role' => UserRole::CUSTOMER->value,
    ]);

    $this->browse(function (Browser $browser) use ($listTarget, $profileTarget): void {
        $this->centralAdministratorEntersDuskTenant($browser);

        $browser->visit('/users')
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
});

test('tenant administrators cannot list or manage users', function (): void {
    $this->browse(function (Browser $browser): void {
        $this->tenantUserLogsIn($browser, $this->administrator);

        $browser->visit('/users')
            ->assertMissing('@users-page')
            ->visit('/user/' . $this->inactiveSameCompanyUser->uuid)
            ->assertMissing('@user-form');
    });
});

test('administrator cannot reach user creation by direct url', function (): void {
    $this->browse(function (Browser $browser): void {
        $this->tenantUserLogsIn($browser, $this->administrator);

        $browser->visit('/user/create')
            ->assertMissing('@user-create-form')
            ->assertMissing('@user-form-submit');
    });
});

test('employee sees sidebar but customer does not see sidebar or users administration', function (): void {
    $this->browse(function (Browser $browser): void {
        $this->tenantUserLogsIn($browser, $this->employee);

        $browser->visit('/dashboard')
            ->assertPresent('[data-sidebar="sidebar"]')
            ->assertPresent('[data-sidebar="trigger"]')
            ->assertCount('[data-sidebar="content"] [data-sidebar="menu-item"]', 2)
            ->assertMissing('@users-nav')
            ->visit('/users')
            ->assertMissing('@users-page');

        $this->tenantUserLogsIn($browser, $this->customer);

        $browser->visit('/dashboard')
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
});
