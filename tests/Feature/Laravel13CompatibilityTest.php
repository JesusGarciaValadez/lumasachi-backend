<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

it('uses Laravel 13 request forgery and cache serialization defaults', function () {
    expect(config('sanctum.middleware.validate_csrf_token'))
        ->toBe(PreventRequestForgery::class)
        ->and(config('cache.serializable_classes'))
        ->toBeFalse();
});
