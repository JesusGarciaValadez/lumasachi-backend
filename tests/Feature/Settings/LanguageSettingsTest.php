<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as InertiaAssert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LanguageSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_users_can_view_the_language_settings_page(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->get(route('language.edit'))
            ->assertOk()
            ->assertInertia(fn(InertiaAssert $page) => $page
                ->component('settings/Language')
                ->where('i18n.locale', 'en')
                ->where('i18n.supported_locales', ['es', 'en'])
            );
    }

    #[Test]
    public function guests_cannot_view_the_language_settings_page(): void
    {
        $this->get(route('language.edit'))->assertRedirect('/login');
    }
}
