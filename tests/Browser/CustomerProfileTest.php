<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

uses(DuskTestCase::class);

beforeEach(function (): void {
    $this->customer = User::factory()->create([
        'email' => 'dusk-customer-profile@example.com',
        'is_active' => true,
        'password' => Hash::make('password'),
        'role' => UserRole::CUSTOMER->value,
    ]);
});

test('customer can update their profile fields without password controls', function (): void {
    $this->browse(function (Browser $browser): void {
        $this->tenantUserLogsIn($browser, $this->customer);

        $browser->visit('/settings/profile')
            ->waitFor('@customer-profile-form')
            ->clear('@profile-first-name')
            ->type('@profile-first-name', 'Updated')
            ->clear('@profile-last-name')
            ->type('@profile-last-name', 'Customer')
            ->clear('@profile-email')
            ->type('@profile-email', 'updated-dusk-customer@example.com')
            ->type('@profile-phone', '555-111-1111')
            ->assertMissing('@profile-current-password')
            ->assertMissing('@profile-password')
            ->assertMissing('@profile-password-confirmation')
            ->click('@profile-form-submit')
            ->waitFor('@profile-saved')
            ->assertInputValue('@profile-first-name', 'Updated')
            ->assertInputValue('@profile-last-name', 'Customer')
            ->assertInputValue('@profile-email', 'updated-dusk-customer@example.com')
            ->assertInputValue('@profile-phone', '555-111-1111');
    });
});

test('required password changes do not show the current password field', function (): void {
    $this->customer->update([
        'must_change_password' => true,
    ]);

    $this->browse(function (Browser $browser): void {
        $this->tenantUserLogsIn($browser, $this->customer, true);

        $browser->visit('/settings/password?required=1')
            ->waitFor('@password-form')
            ->waitFor('@password-required')
            ->assertMissing('@password-current-password');
    });
});
