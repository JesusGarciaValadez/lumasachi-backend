<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\CentralSuperAdministrator;
use App\Models\CentralTenantAccessGrant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CentralTenantAccessService
{
    /** @return array{grant: CentralTenantAccessGrant, token: string} */
    public function issue(CentralSuperAdministrator $administrator, Tenant $tenant, string $centralOrigin): array
    {
        $token = Str::random(64);

        $grant = new CentralTenantAccessGrant;
        $grant->forceFill([
            'token_hash' => hash('sha256', $token),
            'tenant_id' => $tenant->getTenantKey(),
            'central_actor_uuid' => $administrator->uuid,
            'central_origin' => $centralOrigin,
            'expires_at' => now()->addMinute(),
        ])->save();

        return ['grant' => $grant, 'token' => $token];
    }

    public function consume(string $token, string $tenantId): ?CentralTenantAccessGrant
    {
        $connectionName = $this->centralConnectionName();

        return DB::connection($connectionName)->transaction(function () use ($token, $tenantId): ?CentralTenantAccessGrant {
            $grant = CentralTenantAccessGrant::query()
                ->where('token_hash', hash('sha256', $token))
                ->where('tenant_id', $tenantId)
                ->whereNull('entered_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($grant === null) {
                return null;
            }

            $administrator = CentralSuperAdministrator::query()
                ->where('uuid', $grant->central_actor_uuid)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($administrator === null) {
                return null;
            }

            $grant->forceFill(['entered_at' => now()])->save();

            return $grant;
        });
    }

    private function centralConnectionName(): string
    {
        return (string)config('tenancy.database.central_connection', config('database.default'));
    }

    public function isActive(string $grantId, string $tenantId, string $centralActorUuid): bool
    {
        $grantIsActive = CentralTenantAccessGrant::query()
            ->whereKey($grantId)
            ->where('tenant_id', $tenantId)
            ->where('central_actor_uuid', $centralActorUuid)
            ->whereNotNull('entered_at')
            ->whereNull('exited_at')
            ->whereNull('revoked_at')
            ->exists();

        return $grantIsActive && $this->administratorIsActive($centralActorUuid);
    }

    private function administratorIsActive(string $centralActorUuid): bool
    {
        return CentralSuperAdministrator::query()
            ->where('uuid', $centralActorUuid)
            ->where('is_active', true)
            ->exists();
    }

    public function finishSupportSession(string $tenantId, User $supportActor, ?string $grantId): ?string
    {
        if (!$supportActor->isPlatformSupport() || !is_string($supportActor->central_actor_uuid) || $grantId === null) {
            return null;
        }

        return $this->exit($grantId, $tenantId, $supportActor->central_actor_uuid);
    }

    public function exit(string $grantId, string $tenantId, string $centralActorUuid): ?string
    {
        $grant = CentralTenantAccessGrant::query()
            ->whereKey($grantId)
            ->where('tenant_id', $tenantId)
            ->where('central_actor_uuid', $centralActorUuid)
            ->whereNotNull('entered_at')
            ->first();

        if ($grant === null) {
            return null;
        }

        if ($grant->exited_at === null) {
            $grant->forceFill(['exited_at' => now()])->save();
        }

        return $grant->central_origin;
    }

    public function revokeOutstandingFor(CentralSuperAdministrator $administrator): int
    {
        return CentralTenantAccessGrant::query()
            ->where('central_actor_uuid', $administrator->uuid)
            ->whereNull('exited_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function isAllowedCentralOrigin(string $origin): bool
    {
        $url = parse_url($origin);
        $centralHosts = config('tenancy.central_hosts', []);
        $allowedHosts = is_array($centralHosts)
            ? array_map(
                'mb_strtolower',
                array_values(array_filter($centralHosts, 'is_string')),
            )
            : [];

        return is_array($url)
            && in_array($url['scheme'] ?? null, ['https', 'http'], true)
            && is_string($url['host'] ?? null)
            && in_array(mb_strtolower($url['host']), $allowedHosts, true)
            && array_intersect(['user', 'pass', 'path', 'query', 'fragment'], array_keys($url)) === [];
    }
}
