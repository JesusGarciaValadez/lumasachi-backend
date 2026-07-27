<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as InertiaAssert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LocaleLifecycleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shares_the_resolved_locale_and_supported_locales_with_inertia(): void
    {
        $this->withHeaders(['Accept-Language' => 'es-MX,en;q=0.8'])
            ->get(route('web.orders.track'))
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('Orders/Track')
                ->where('i18n.locale', 'es')
                ->where('i18n.supported_locales', ['es', 'en'])
            );
    }

    #[Test]
    public function it_prefers_an_authenticated_user_locale_over_session_cookie_and_browser_locale(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->withSession(['locale' => 'es'])
            ->withCookie('locale', 'es')
            ->withHeaders(['Accept-Language' => 'es'])
            ->get('/dashboard')
            ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'en'));
    }

    #[Test]
    public function it_prefers_an_anonymous_session_locale_over_cookie_and_browser_locale(): void
    {
        $this->withSession(['locale' => 'en'])
            ->withCookie('locale', 'es')
            ->withHeaders(['Accept-Language' => 'es'])
            ->get(route('web.orders.track'))
            ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'en'));
    }

    #[Test]
    public function it_uses_the_cookie_before_the_browser_locale_for_anonymous_requests(): void
    {
        $this->withCookie('locale', 'es')
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.8'])
            ->get(route('web.orders.track'))
            ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'es'));
    }

    #[Test]
    public function it_normalizes_supported_browser_language_tags(): void
    {
        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.8'])
            ->get(route('web.orders.track'))
            ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'en'));
    }

    #[Test]
    public function it_respects_browser_language_quality_weights(): void
    {
        $this->withHeaders(['Accept-Language' => 'en;q=0,es;q=1'])
            ->get(route('web.orders.track'))
            ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'es'));
    }

    #[Test]
    public function it_falls_back_from_unsupported_locale_input(): void
    {
        config(['app.locale' => 'es']);

        $this->withCookie('locale', 'fr')
            ->withHeaders(['Accept-Language' => 'de-DE'])
            ->get(route('web.orders.track'))
            ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'es'));
    }

    #[Test]
    public function guests_can_change_and_persist_their_locale(): void
    {
        $response = $this->post(route('locale.update'), ['locale' => 'en']);

        $response
            ->assertRedirect()
            ->assertSessionHas('locale', 'en')
            ->assertCookie('locale', 'en');

        $this->get(route('web.orders.track'))
            ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'en'));
    }

    #[Test]
    public function authenticated_users_can_change_and_persist_their_locale(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect()
            ->assertSessionHas('locale', 'en')
            ->assertCookie('locale', 'en');

        $freshUser = $user->fresh();

        $this->assertNotNull($freshUser);
        $this->assertSame('en', $freshUser->locale);
    }

    #[Test]
    public function unsupported_locale_changes_are_rejected_without_persisting(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->from('/settings/profile')
            ->post(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale')
            ->assertRedirect('/settings/profile');

        $freshUser = $user->fresh();

        $this->assertNotNull($freshUser);
        $this->assertNull($freshUser->locale);
    }

    #[Test]
    public function api_requests_resolve_the_locale_from_accept_language(): void
    {
        $this->withHeaders(['Accept-Language' => 'es-MX'])
            ->getJson('/api/v1/up')
            ->assertOk();

        $this->assertSame('es', app()->getLocale());
    }
}
