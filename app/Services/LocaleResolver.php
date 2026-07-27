<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

final class LocaleResolver
{
    public const SESSION_KEY = 'locale';

    public const COOKIE_NAME = 'locale';

    public function apply(Request $request): string
    {
        $locale = $this->resolve($request);
        App::setLocale($locale);

        return $locale;
    }

    public function resolve(Request $request): string
    {
        $candidates = [
            $request->user()?->getRawOriginal('locale'),
            $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null,
            $request->cookie(self::COOKIE_NAME),
            $this->acceptLanguage($request->header('Accept-Language')),
            config('app.locale'),
            config('app.fallback_locale'),
        ];

        foreach ($candidates as $candidate) {
            $locale = $this->normalize($candidate);

            if ($locale !== null) {
                return $locale;
            }
        }

        return $this->supportedLocales()[0] ?? Locale::SPANISH->value;
    }

    private function acceptLanguage(?string $header): ?string
    {
        if ($header === null) {
            return null;
        }

        $preferences = [];

        foreach (explode(',', $header) as $index => $part) {
            $segments = explode(';', $part);
            $language = array_shift($segments);
            $parameters = $segments;
            $locale = $this->normalize($language ?? '');
            $quality = 1.0;

            foreach ($parameters as $parameter) {
                $parameter = mb_strtolower(mb_trim($parameter));

                if (str_starts_with($parameter, 'q=')) {
                    $quality = (float)mb_substr($parameter, 2);
                }
            }

            if ($locale !== null && $quality > 0) {
                $preferences[] = [
                    'locale' => $locale,
                    'quality' => $quality,
                    'index' => $index,
                ];
            }
        }

        usort($preferences, function (array $left, array $right): int {
            $qualityComparison = $right['quality'] <=> $left['quality'];

            return $qualityComparison !== 0 ? $qualityComparison : $left['index'] <=> $right['index'];
        });

        return $preferences[0]['locale'] ?? null;
    }

    public function normalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $locale = Locale::normalize($value);

        if ($locale === null || !in_array($locale->value, $this->supportedLocales(), true)) {
            return null;
        }

        return $locale->value;
    }

    /**
     * @return list<string>
     */
    public function supportedLocales(): array
    {
        $configured = config('app.supported_locales', Locale::values());

        if (!is_array($configured)) {
            return Locale::values();
        }

        return array_values(array_filter(
            $configured,
            fn(mixed $locale): bool => is_string($locale) && in_array($locale, Locale::values(), true),
        ));
    }
}
