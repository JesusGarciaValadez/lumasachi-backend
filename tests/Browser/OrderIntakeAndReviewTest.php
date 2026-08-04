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
        'email' => 'dusk-review-employee@example.com',
        'is_active' => true,
        'password' => $password,
        'role' => UserRole::EMPLOYEE->value,
    ]);
    $this->customer = User::factory()->create([
        'company_id' => null,
        'email' => 'dusk-review-customer@example.com',
        'is_active' => true,
        'password' => $password,
        'role' => UserRole::CUSTOMER->value,
    ]);
});

test('staff can create and review a block order', function (): void {
    $this->browse(function (Browser $browser): void {
        orderIntakeAndReviewLogin($browser, $this->employee);

        orderIntakeAndReviewFillOrder($browser, $this->customer, $this->employee, 'Dusk block review');

        $browser->click('@order-create-submit')
            ->waitForLocation('/orders', 10)
            ->waitFor('@orders-flash', 10)
            ->assertVisible('@orders-flash')
            ->assertSeeIn('@orders-flash', 'Order created successfully.')
            ->waitForText('Dusk block review', 10)
            ->assertSee('Dusk block review')
            ->clickLink('View')
            ->waitFor('@order-status', 10)
            ->assertSee('Dusk block review')
            ->assertSee('Awaiting Review')
            ->assertSeeIn('@order-motor-information', 'Honda')
            ->assertSeeIn('@order-motor-information', '2.0')
            ->assertSeeIn('@order-motor-information', '2020')
            ->assertSeeIn('@order-motor-information', 'Civic')
            ->assertSeeIn('@order-motor-information', '4')
            ->assertSeeIn('@order-received-items', 'Bearing caps')
            ->assertSeeIn('@order-received-items', 'Cap bolts')
            ->assertSeeIn('@order-received-items', 'Camshaft')
            ->waitFor('@order-review-panel', 10)
            ->check('@order-review-service-wash_block')
            ->check('@order-review-service-weld_between_cylinders_qr25')
            ->check('@order-review-service-deck_assembled_4cyl')
            ->check('@order-review-service-replace_cam_bearings')
            ->check('@order-review-service-polish_camshaft_bars')
            ->assertSeeIn('@order-review-panel', '3,760.00')
            ->assertSeeIn('@order-review-panel', '4,361.60')
            ->scrollIntoView('@order-review-submit')
            ->click('@order-review-submit')
            ->waitFor('@order-confirm-action')
            ->click('@order-confirm-action')
            ->waitUntilMissing('@order-review-panel', 10)
            ->waitFor('@order-history-feed', 10)
            ->assertSeeIn('@order-status', 'Awaiting Customer Approval')
            ->assertSeeIn('@order-financial-summary', '3,760.00')
            ->assertSeeIn('@order-financial-summary', '4,361.60')
            ->assertSeeIn('@order-history-feed', 'Reviewed')
            ->assertSeeIn('@order-history-feed', 'Awaiting Customer Approval');
    });
});

test('staff sees a required measurement error without leaving review', function (): void {
    $this->browse(function (Browser $browser): void {
        orderIntakeAndReviewLogin($browser, $this->employee);

        orderIntakeAndReviewFillOrder($browser, $this->customer, $this->employee, 'Dusk measurement error');

        $browser->click('@order-create-submit')
            ->waitForLocation('/orders', 10)
            ->waitFor('@orders-flash', 10)
            ->clickLink('View')
            ->waitFor('@order-review-panel', 10)
            ->check('@order-review-service-bore_cylinder_pu')
            ->scrollIntoView('@order-review-submit')
            ->click('@order-review-submit')
            ->waitFor('@order-confirm-action')
            ->click('@order-confirm-action')
            ->waitFor('@order-action-error')
            ->assertPresent('@order-review-panel')
            ->assertSeeIn('@order-action-error', 'services.0.measurement');
    });
});

function orderIntakeAndReviewLogin(Browser $browser, User $employee): void
{
    $browser->visit('/login')
        ->type('@login-email', $employee->email)
        ->type('@login-password', 'password')
        ->click('@login-submit')
        ->waitForLocation('/dashboard');
}

function orderIntakeAndReviewFillOrder(
    Browser $browser,
    User   $customer,
    User   $employee,
    string $title,
): void
{
    $browser->visit('/orders/create')
        ->waitFor('@order-create-form')
        ->type('@order-title', $title)
        ->type('@order-description', 'Received block for browser review coverage')
        ->select('@order-customer', (string)$customer->id)
        ->select('@order-assignee', (string)$employee->id)
        ->type('@motor-brand', 'Honda')
        ->type('@motor-liters', '2.0')
        ->type('@motor-year', '2020')
        ->type('@motor-model', 'Civic')
        ->type('@motor-cylinder-count', '4')
        ->check('@order-item-component-0-bearing_caps')
        ->check('@order-item-component-0-cap_bolts')
        ->check('@order-item-component-0-camshaft');
}
