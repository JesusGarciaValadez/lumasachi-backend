<?php

declare(strict_types=1);

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        using: static function (): void {
            Route::middleware(App\Http\Middleware\RequireCentralDomain::class)
                ->group(base_path('routes/central.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustHosts(at: static function (): array {
            $patterns = [];

            foreach (config('tenancy.central_hosts', []) as $host) {
                if (!is_string($host) || $host === '') {
                    continue;
                }

                $patterns[] = '^' . preg_quote(mb_strtolower($host), '/') . '$';
            }

            foreach (config('tenancy.central_domains', []) as $domain) {
                if (!is_string($domain) || $domain === '') {
                    continue;
                }

                $escapedDomain = preg_quote(mb_strtolower($domain), '/');
                $patterns[] = '^' . $escapedDomain . '$';

                if (filter_var($domain, FILTER_VALIDATE_IP) === false) {
                    $patterns[] = '^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.' . $escapedDomain . '$';
                }
            }

            return $patterns;
        }, subdomains: false);

        // Enable Sanctum's stateful API so session-authenticated SPA requests to /api work
        $middleware->statefulApi();

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->preventRequestForgery(except: [
            'sanctum/*',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'password.changed' => EnsurePasswordChanged::class,
        ]);

        $middleware->api(append: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn(Request $request): bool => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'code' => 'validation.failed',
                'message' => __('validation.failed'),
                'errors' => $exception->errors(),
            ], 422);
        });
    })->create();
