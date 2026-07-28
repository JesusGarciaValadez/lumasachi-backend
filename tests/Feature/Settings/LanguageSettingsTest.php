<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as InertiaAssert;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('authenticated users can view the language settings page', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->get(route('language.edit'))
        ->assertOk()
        ->assertInertia(fn(InertiaAssert $page) => $page
            ->component('settings/Language')
            ->where('i18n.locale', 'en')
            ->where('i18n.supported_locales', ['es', 'en'])
        );
});
test('guests cannot view the language settings page', function () {
    $this->get(route('language.edit'))->assertRedirect('/login');
});
