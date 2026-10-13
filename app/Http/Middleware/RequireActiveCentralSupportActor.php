<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\CentralTenantAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RequireActiveCentralSupportActor
{
    public function __construct(private CentralTenantAccessService $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!tenancy()->initialized) {
            return $next($request);
        }

        $actor = Auth::guard('web')->user();

        if (!$actor instanceof User || !$actor->isPlatformSupport()) {
            return $next($request);
        }

        $grantId = $request->session()->get('central_access_grant_id');
        $centralActorUuid = $actor->central_actor_uuid;
        $tenant = tenancy()->tenant;
        abort_unless($tenant instanceof Tenant, 404);
        $tenantId = (string)$tenant->getTenantKey();

        if (!is_string($grantId)
            || !is_string($centralActorUuid)
            || !$this->access->isActive($grantId, $tenantId, $centralActorUuid)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403);
        }

        return $next($request);
    }
}
