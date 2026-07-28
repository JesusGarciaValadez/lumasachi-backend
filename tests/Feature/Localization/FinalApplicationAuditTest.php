<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as InertiaAssert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FinalApplicationAuditTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_public_and_guest_pages_with_the_selected_locale(): void
    {
        $pages = [
            ['/', 'Welcome'],
            ['/login', 'auth/Login'],
            ['/register', 'auth/Register'],
            ['/forgot-password', 'auth/ForgotPassword'],
            ['/reset-password/audit-token?email=audit@example.com', 'auth/ResetPassword'],
            [route('web.orders.track'), 'Orders/Track'],
        ];

        foreach (['es', 'en'] as $locale) {
            foreach ($pages as [$path, $component]) {
                $this->assertLocalizedPage($path, $component, $locale);
            }
        }
    }

    private function assertLocalizedPage(string $path, string $component, string $locale): void
    {
        $this->withCookie('locale', $locale)
            ->get($path)
            ->assertOk()
            ->assertSee('<html lang="' . $locale . '"', false)
            ->assertInertia(fn(InertiaAssert $page) => $page->component($component));
    }

    #[Test]
    public function it_renders_authenticated_pages_with_the_selected_locale(): void
    {
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
                $this->assertLocalizedPage($path, $component, $locale);
            }
        }
    }
}
