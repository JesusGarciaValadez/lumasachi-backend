<?php

declare(strict_types=1);

use App\Services\LocaleResolver;
use Illuminate\Http\Request;

it('uses the central guard for locale lookup only on central hosts', function (string $host, ?string $expectedGuard): void {
    config(['tenancy.central_hosts' => ['localhost']]);

    $request = Request::create('https://' . $host . '/login');
    $resolvedGuard = null;
    $request->setUserResolver(function (?string $guard = null) use (&$resolvedGuard): null {
        $resolvedGuard = $guard;

        return null;
    });

    (new LocaleResolver())->resolve($request);

    expect($resolvedGuard)->toBe($expectedGuard);
})->with([
    'central host' => ['localhost', 'central'],
    'tenant host' => ['company.localhost', null],
]);
