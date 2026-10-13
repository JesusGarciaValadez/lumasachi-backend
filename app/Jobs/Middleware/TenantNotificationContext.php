<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Models\Tenant;
use App\Services\Tenancy\TenantReadiness;
use Closure;
use Illuminate\Routing\UrlGenerator;
use RuntimeException;

final class TenantNotificationContext
{
    public function __construct(
        private readonly TenantReadiness $tenantReadiness,
        private readonly UrlGenerator    $urlGenerator,
    )
    {
    }

    public function handle(object $job, Closure $next): mixed
    {
        $tenant = tenant();

        if (!$tenant instanceof Tenant) {
            if (app()->environment('testing') && config('tenancy.allow_legacy_test_routes')) {
                return $next($job);
            }

            throw new RuntimeException('A tenant context is required to deliver this notification.');
        }

        $this->tenantReadiness->assertReady($tenant);

        $applicationOrigin = $this->applicationOrigin();
        $tenantOrigin = $this->tenantOrigin($tenant, $applicationOrigin);

        $this->urlGenerator->useOrigin($tenantOrigin);

        try {
            return $next($job);
        } finally {
            $this->urlGenerator->useOrigin(null);
        }
    }

    private function applicationOrigin(): string
    {
        $applicationUrl = parse_url((string)config('app.url'));

        if (!is_array($applicationUrl)) {
            throw new RuntimeException('The application URL must define an HTTP(S) origin.');
        }

        $scheme = $applicationUrl['scheme'] ?? null;
        $host = $applicationUrl['host'] ?? null;

        if (!in_array($scheme, ['http', 'https'], true) || !is_string($host) || $host === '') {
            throw new RuntimeException('The application URL must define an HTTP(S) origin.');
        }

        $port = isset($applicationUrl['port']) ? ':' . $applicationUrl['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }

    private function tenantOrigin(Tenant $tenant, string $applicationOrigin): string
    {
        $domains = $tenant->domains()->limit(2)->pluck('domain');

        if ($domains->count() !== 1) {
            throw new RuntimeException('Exactly one registered tenant domain is required for queued notification links.');
        }

        $domain = $domains->first();
        $domainPattern = '/\A(?=.{1,253}\z)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\z/iD';

        if (!is_string($domain) || preg_match($domainPattern, $domain) !== 1) {
            throw new RuntimeException('The registered tenant domain is invalid.');
        }

        $matchesConfiguredParent = false;

        foreach (config('tenancy.central_domains', []) as $parentDomain) {
            if (!is_string($parentDomain)) {
                continue;
            }

            $suffix = '.' . mb_strtolower($parentDomain);

            if (!str_ends_with($domain, $suffix)) {
                continue;
            }

            $subdomain = mb_substr($domain, 0, -mb_strlen($suffix));

            if (preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/iD', $subdomain) === 1) {
                $matchesConfiguredParent = true;
                break;
            }
        }

        if (!$matchesConfiguredParent) {
            throw new RuntimeException('The registered tenant domain must be one subdomain under a configured tenant parent domain.');
        }

        $applicationUrl = parse_url($applicationOrigin);

        if (!is_array($applicationUrl) || !isset($applicationUrl['scheme'])) {
            throw new RuntimeException('The application URL must define an HTTP(S) origin.');
        }

        $scheme = $applicationUrl['scheme'];
        $port = isset($applicationUrl['port']) ? ':' . $applicationUrl['port'] : '';

        return "{$scheme}://{$domain}{$port}";
    }
}
