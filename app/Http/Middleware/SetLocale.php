<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function __construct(private readonly LocaleResolver $localeResolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->localeResolver->apply($request);

        return $next($request);
    }
}
