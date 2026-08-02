<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Inertia\Testing\AssertableInertia as InertiaAssert;
use Tests\TestCase;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('renders public and guest pages with the selected locale', function () {
    $pages = [
        ['/', 'Welcome'],
        ['/login', 'auth/Login'],
        ['/forgot-password', 'auth/ForgotPassword'],
        ['/reset-password/audit-token?email=audit@example.com', 'auth/ResetPassword'],
        [route('web.orders.track'), 'Orders/Track'],
    ];

    foreach (['es', 'en'] as $locale) {
        foreach ($pages as [$path, $component]) {
            assertLocalizedPage($this, $path, $component, $locale);
        }
    }
});
function assertLocalizedPage(TestCase $test, string $path, string $component, string $locale): void
{
    $test->withCookie('locale', $locale)
        ->get($path)
        ->assertOk()
        ->assertSee('<html lang="' . $locale . '"', false)
        ->assertInertia(fn(InertiaAssert $page) => $page->component($component));
}

it('renders authenticated pages with the selected locale', function () {
    $employee = User::factory()->create([
        'locale' => null,
        'is_active' => true,
        'role' => UserRole::EMPLOYEE->value,
    ]);
    $customer = User::factory()->create();
    $order = Order::factory()->createQuietly([
        'created_by' => $employee->id,
        'assigned_to' => $employee->id,
        'customer_id' => $customer->id,
    ]);

    $pages = [
        ['/dashboard', 'Dashboard'],
        ['/settings/profile', 'settings/Profile'],
        ['/settings/password', 'settings/Password'],
        ['/settings/appearance', 'settings/Appearance'],
        ['/settings/language', 'settings/Language'],
        [route('web.catalog.engine-options'), 'Orders/EngineOptions'],
        [route('web.orders.index'), 'Orders/Index'],
        [route('web.orders.create'), 'Orders/Create'],
        [route('web.orders.show', $order), 'Orders/Show'],
    ];

    foreach (['es', 'en'] as $locale) {
        $this->actingAs($employee);

        foreach ($pages as [$path, $component]) {
            assertLocalizedPage($this, $path, $component, $locale);
        }
    }
});
