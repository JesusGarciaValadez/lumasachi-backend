<?php

declare(strict_types=1);

use App\Services\Tenancy\SessionConnectionContext;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;

test('session connection scope restores the auth guard changed by a request', function (): void {
    $originalGuard = (string)config('auth.defaults.guard');
    $guardDuringRequest = null;

    app(SessionConnectionContext::class)->run('central', function () use (&$guardDuringRequest) {
        $guardDuringRequest = (string)config('auth.defaults.guard');

        return response('central response');
    }, 'central');

    expect($guardDuringRequest)->toBe('central')
        ->and(config('auth.defaults.guard'))->toBe($originalGuard);
});

test('session services are rebuilt inside each connection scope', function (): void {
    $originalSessionManager = app('session');
    $originalStartSession = app(StartSession::class);

    app(SessionConnectionContext::class)->run('tenant', function () use ($originalSessionManager, $originalStartSession) {
        expect(config('session.connection'))->toBe('tenant')
            ->and(app('session'))->not->toBe($originalSessionManager)
            ->and(app(StartSession::class))->not->toBe($originalStartSession);

        return response('tenant response');
    });
});

test('session connection scope restores the auth guard when a request throws', function (): void {
    $originalGuard = (string)config('auth.defaults.guard');

    try {
        app(SessionConnectionContext::class)->run('tenant', function (): never {
            Auth::shouldUse('central');

            throw new RuntimeException('Request failed.');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Request failed.');
    }

    expect(config('auth.defaults.guard'))->toBe($originalGuard);
});
