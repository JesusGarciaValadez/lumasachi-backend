<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

uses(DuskTestCase::class);

beforeEach(function (): void {
    $password = Hash::make('password');
    $sharedEmail = 'dusk-shared-tenant-user@example.test';

    $this->runInDuskTenantContext(function () use ($password, $sharedEmail): void {
        $this->alphaEmployee = User::factory()->create([
            'email' => $sharedEmail,
            'first_name' => 'Alpha',
            'last_name' => 'Employee',
            'is_active' => true,
            'activated_at' => now(),
            'email_verified_at' => now(),
            'password' => $password,
            'role' => UserRole::EMPLOYEE->value,
        ]);
        $this->alphaCustomer = User::factory()->create([
            'first_name' => 'Alpha',
            'last_name' => 'Customer',
            'role' => UserRole::CUSTOMER->value,
        ]);
    });

    $alphaTenant = $this->duskTenant;
    $otherTenant = $this->duskOtherTenant;

    if ($alphaTenant === null || $otherTenant === null) {
        throw new RuntimeException('Both Dusk tenant fixtures must be available.');
    }

    tenancy()->end();
    tenancy()->initialize($otherTenant);

    try {
        $this->betaEmployee = User::factory()->create([
            'email' => $sharedEmail,
            'first_name' => 'Beta',
            'last_name' => 'Employee',
            'is_active' => true,
            'activated_at' => now(),
            'email_verified_at' => now(),
            'password' => $password,
            'role' => UserRole::EMPLOYEE->value,
        ]);
        $this->betaCustomer = User::factory()->create([
            'first_name' => 'Beta',
            'last_name' => 'Customer',
            'role' => UserRole::CUSTOMER->value,
        ]);
    } finally {
        tenancy()->end();
        tenancy()->initialize($alphaTenant);
    }
});

test('order intake stays on the current company host for users and orders', function (): void {
    $this->browse(function (Browser $browser): void {
        tenantOrderIsolationLogin($browser, 'tenant.dusk', $this->alphaEmployee);

        $browser->visit('http://tenant.dusk/orders/create')
            ->waitFor('@order-create-form')
            ->assertMissing('@order-company')
            ->assertSeeIn('@order-customer', 'Alpha Customer')
            ->assertDontSeeIn('@order-customer', 'Beta Customer');

        createTenantOrderThroughDusk(
            $browser,
            'tenant.dusk',
            'Alpha-only order',
            $this->alphaCustomer,
            $this->alphaEmployee,
        );

        tenantOrderIsolationLogin($browser, 'other.dusk', $this->betaEmployee);

        $browser->visit('http://other.dusk/orders/create')
            ->waitFor('@order-create-form')
            ->assertMissing('@order-company')
            ->assertSeeIn('@order-customer', 'Beta Customer')
            ->assertDontSeeIn('@order-customer', 'Alpha Customer');

        createTenantOrderThroughDusk(
            $browser,
            'other.dusk',
            'Beta-only order',
            $this->betaCustomer,
            $this->betaEmployee,
        );

        tenantOrderIsolationLogin($browser, 'tenant.dusk', $this->alphaEmployee, allowExistingSession: true);

        $browser->visit('http://tenant.dusk/orders')
            ->waitForText('Alpha-only order', 10)
            ->assertSee('Alpha-only order')
            ->assertDontSee('Beta-only order');
    });
});

function tenantOrderIsolationLogin(Browser $browser, string $host, User $user, bool $allowExistingSession = false): void
{
    $browser->visit('http://' . $host . '/login')->assertHostIs($host);

    if (!$allowExistingSession) {
        $browser->assertPresent('@login-email');
    }

    if ($browser->element('@login-email') === null) {
        $browser->assertSee($user->first_name . ' ' . $user->last_name);

        return;
    }

    $browser->waitFor('@login-email', 10)
        ->type('@login-email', $user->email)
        ->type('@login-password', 'password')
        ->click('@login-submit')
        ->waitForLocation('/dashboard', 10);
}

function createTenantOrderThroughDusk(
    Browser $browser,
    string  $host,
    string  $title,
    User    $customer,
    User    $employee,
): void
{
    $browser->visit('http://' . $host . '/orders/create')
        ->waitFor('@order-create-form')
        ->type('@order-title', $title)
        ->type('@order-description', 'Company-host isolation browser coverage')
        ->select('@order-customer', (string)$customer->id)
        ->select('@order-assignee', (string)$employee->id)
        ->type('@motor-brand', 'Honda')
        ->type('@motor-liters', '2.0')
        ->type('@motor-year', '2020')
        ->type('@motor-model', 'Civic')
        ->type('@motor-cylinder-count', '4')
        ->check('@order-item-component-0-bearing_caps')
        ->click('@order-create-submit')
        ->waitForLocation('/orders', 10)
        ->waitForText($title, 10);
}
