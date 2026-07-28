<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as InertiaAssert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('shares the resolved locale and supported locales with inertia', function () {
    $this->withHeaders(['Accept-Language' => 'es-MX,en;q=0.8'])
        ->get(route('web.orders.track'))
        ->assertInertia(fn(InertiaAssert $page) => $page
            ->component('Orders/Track')
            ->where('i18n.locale', 'es')
            ->where('i18n.supported_locales', ['es', 'en'])
        );
});
it('prefers an authenticated user locale over session cookie and browser locale', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->withSession(['locale' => 'es'])
        ->withCookie('locale', 'es')
        ->withHeaders(['Accept-Language' => 'es'])
        ->get('/dashboard')
        ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'en'));
});
it('prefers an anonymous session locale over cookie and browser locale', function () {
    $this->withSession(['locale' => 'en'])
        ->withCookie('locale', 'es')
        ->withHeaders(['Accept-Language' => 'es'])
        ->get(route('web.orders.track'))
        ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'en'));
});
it('uses the cookie before the browser locale for anonymous requests', function () {
    $this->withCookie('locale', 'es')
        ->withHeaders(['Accept-Language' => 'en-US,en;q=0.8'])
        ->get(route('web.orders.track'))
        ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'es'));
});
it('normalizes supported browser language tags', function () {
    $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.8'])
        ->get(route('web.orders.track'))
        ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'en'));
});
it('respects browser language quality weights', function () {
    $this->withHeaders(['Accept-Language' => 'en;q=0,es;q=1'])
        ->get(route('web.orders.track'))
        ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'es'));
});
it('falls back from unsupported locale input', function () {
    config(['app.locale' => 'es']);

    $this->withCookie('locale', 'fr')
        ->withHeaders(['Accept-Language' => 'de-DE'])
        ->get(route('web.orders.track'))
        ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'es'));
});
test('guests can change and persist their locale', function () {
    $response = $this->post(route('locale.update'), ['locale' => 'en']);

    $response
        ->assertRedirect()
        ->assertSessionHas('locale', 'en')
        ->assertCookie('locale', 'en');

    $this->get(route('web.orders.track'))
        ->assertInertia(fn(InertiaAssert $page) => $page->where('i18n.locale', 'en'));
});
test('authenticated users can change and persist their locale', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->post(route('locale.update'), ['locale' => 'en'])
        ->assertRedirect()
        ->assertSessionHas('locale', 'en')
        ->assertCookie('locale', 'en');

    $freshUser = $user->fresh();

    expect($freshUser)->not->toBeNull();
    expect($freshUser->locale)->toBe('en');
});
test('unsupported locale changes are rejected without persisting', function () {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->from('/settings/profile')
        ->post(route('locale.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale')
        ->assertRedirect('/settings/profile');

    $freshUser = $user->fresh();

    expect($freshUser)->not->toBeNull();
    expect($freshUser->locale)->toBeNull();
});
test('api requests resolve the locale from accept language', function () {
    $this->withHeaders(['Accept-Language' => 'es-MX'])
        ->getJson('/api/v1/up')
        ->assertOk();

    expect(app()->getLocale())->toBe('es');
});
