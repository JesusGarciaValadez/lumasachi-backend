<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\LocaleUpdateRequest;
use App\Services\LocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;

final class LocaleController extends Controller
{
    public function update(LocaleUpdateRequest $request): RedirectResponse
    {
        $locale = (string)$request->validated('locale');
        $user = $request->user();

        if ($user !== null) {
            $user->forceFill(['locale' => $locale])->save();
        }

        $request->session()->put(LocaleResolver::SESSION_KEY, $locale);
        App::setLocale($locale);

        return back()->withCookie(cookie(LocaleResolver::COOKIE_NAME, $locale, 60 * 24 * 365));
    }
}
