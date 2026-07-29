<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\ServiceCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

final class OrderIntakeAndReviewTest extends DuskTestCase
{
    use DatabaseTruncation;

    private User $employee;

    private User $customer;

    public function test_staff_can_create_and_review_a_block_order(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->login($browser);

            $this->fillOrder($browser, 'Dusk block review');

            $browser->click('@order-create-submit')
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
    }

    private function login(Browser $browser): void
    {
        $browser->visit('/login')
            ->type('@login-email', $this->employee->email)
            ->type('@login-password', 'password')
            ->click('@login-submit')
            ->waitForLocation('/dashboard');
    }

    private function fillOrder(Browser $browser, string $title): void
    {
        $browser->visit('/orders/create')
            ->waitFor('@order-create-form')
            ->type('@order-title', $title)
            ->type('@order-description', 'Received block for browser review coverage')
            ->select('@order-customer', (string)$this->customer->id)
            ->select('@order-assignee', (string)$this->employee->id)
            ->type('@motor-brand', 'Honda')
            ->type('@motor-liters', '2.0')
            ->type('@motor-year', '2020')
            ->type('@motor-model', 'Civic')
            ->type('@motor-cylinder-count', '4')
            ->check('@order-item-component-0-bearing_caps')
            ->check('@order-item-component-0-cap_bolts')
            ->check('@order-item-component-0-camshaft');
    }

    public function test_staff_sees_a_required_measurement_error_without_leaving_review(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->login($browser);

            $this->fillOrder($browser, 'Dusk measurement error');

            $browser->click('@order-create-submit')
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
    }

    protected function setUp(): void
    {
        parent::setUp();

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
    }
}
