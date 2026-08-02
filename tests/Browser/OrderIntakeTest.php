<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

uses(DuskTestCase::class, DatabaseTruncation::class);

beforeEach(function (): void {
    $this->seed(ServiceCatalogSeeder::class);

    $company = Company::factory()->create();
    $password = Hash::make('password');

    $this->employee = User::factory()->create([
        'company_id' => $company->id,
        'email' => 'dusk-employee@example.com',
        'is_active' => true,
        'password' => $password,
        'role' => UserRole::EMPLOYEE->value,
    ]);
    $this->customer = User::factory()->create([
        'company_id' => null,
        'email' => 'dusk-customer@example.com',
        'is_active' => true,
        'password' => $password,
        'role' => UserRole::CUSTOMER->value,
    ]);
});

test('staff can create an order with a received top-level piece', function (): void {
    $this->browse(function (Browser $browser): void {
        orderIntakeLogin($browser, $this->employee);

        $browser->visit('/orders/create')
            ->waitFor('@order-create-form')
            ->type('@order-title', 'Dusk engine block')
            ->type('@order-description', 'Received block for browser coverage')
            ->select('@order-customer', (string)$this->customer->id)
            ->select('@order-assignee', (string)$this->employee->id)
            ->type('@motor-brand', 'Honda')
            ->type('@motor-liters', '2.0')
            ->type('@motor-year', '2020')
            ->type('@motor-model', 'Civic')
            ->type('@motor-cylinder-count', '4')
            ->check('@order-item-component-0-bearing_caps')
            ->check('@order-item-component-0-cap_bolts')
            ->check('@order-item-component-0-camshaft')
            ->click('@order-create-submit')
            ->waitFor('@order-status', 10)
            ->assertSee('Dusk engine block')
            ->assertSee('Awaiting Review');
    });
});

test('staff sees the negative advance payment validation error', function (): void {
    $this->browse(function (Browser $browser): void {
        orderIntakeLogin($browser, $this->employee);

        $browser->visit('/orders/create')
            ->waitFor('@order-create-form')
            ->type('@order-title', 'Invalid advance payment')
            ->type('@order-description', 'The browser should keep the form visible')
            ->select('@order-customer', (string)$this->customer->id)
            ->select('@order-assignee', (string)$this->employee->id)
            ->type('@motor-down-payment', '-1')
            ->click('@order-create-submit')
            ->assertPathIs('/orders/create')
            ->assertInputValue('@motor-down-payment', '-1');
    });
});

function orderIntakeLogin(Browser $browser, User $employee): void
{
    $browser->visit('/login')
        ->type('@login-email', $employee->email)
        ->type('@login-password', 'password')
        ->click('@login-submit')
        ->waitForLocation('/dashboard');
}
