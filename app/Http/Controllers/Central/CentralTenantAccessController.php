<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralSuperAdministrator;
use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\CentralTenantAccessService;
use App\Services\Tenancy\TenantReadiness;
use App\Services\Tenancy\TenantSupportActorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class CentralTenantAccessController extends Controller
{
    public function store(
        Request                    $request,
        CentralTenantAccessService $access,
        TenantReadiness            $readiness,
    ): RedirectResponse
    {
        $administrator = Auth::guard('central')->user();

        abort_unless($administrator instanceof CentralSuperAdministrator && $administrator->is_active, 403);

        $companyId = $request->route('company');
        abort_unless(is_string($companyId) && ctype_digit($companyId), 404);

        $company = Company::query()->findOrFail((int)$companyId);
        $tenant = $company->tenant;
        abort_unless($tenant instanceof Tenant, 404);
        $readiness->assertReady($tenant);

        $domain = $tenant->domains()->first();
        abort_unless($domain !== null, 404);

        $parentDomains = array_values(array_filter(
            config('tenancy.central_domains', []),
            static fn(mixed $candidate): bool => is_string($candidate)
                && $candidate !== ''
                && filter_var($candidate, FILTER_VALIDATE_IP) === false,
        ));
        abort_unless(count($parentDomains) === 1, 503, 'Central tenant parent domain configuration is ambiguous.');

        $tenantHost = mb_strtolower($domain->domain);
        $parentDomain = mb_strtolower($parentDomains[0]);
        $parentSuffix = '.' . $parentDomain;
        abort_unless(
            str_ends_with($tenantHost, $parentSuffix)
            && preg_match(
                '/\A[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\z/i',
                mb_substr($tenantHost, 0, -mb_strlen($parentSuffix)),
            ) === 1,
            404,
        );

        $issuedGrant = $access->issue($administrator, $tenant, $request->getSchemeAndHttpHost());
        $tenantUrl = $request->getScheme() . '://' . $tenantHost;
        $port = $request->getPort();

        if (!in_array([$request->getScheme(), $port], [['http', 80], ['https', 443]], true)) {
            $tenantUrl .= ':' . $port;
        }

        return redirect()->away($tenantUrl . '/central/entry#token=' . rawurlencode($issuedGrant['token']));
    }

    public function prompt(): Response
    {
        return response()->view('central.tenant-entry')
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function enter(
        Request                    $request,
        CentralTenantAccessService $access,
        TenantSupportActorService  $supportActors,
    ): RedirectResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $tenant = tenancy()->tenant;
        abort_unless($tenant instanceof Tenant, 404);
        $tenantId = (string)$tenant->getTenantKey();
        $grant = $access->consume($validated['token'], $tenantId);
        abort_unless($grant !== null, 404);

        $administrator = CentralSuperAdministrator::query()
            ->where('uuid', $grant->central_actor_uuid)
            ->where('is_active', true)
            ->first();
        abort_unless($administrator !== null, 403);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $supportActor = $supportActors->for($administrator);
        abort_unless($supportActor->isPlatformSupport(), 403);

        Auth::guard('web')->login($supportActor);
        $request->session()->put('central_access_grant_id', (string)$grant->getKey());
        $request->session()->regenerate();

        return redirect('/orders');
    }

    public function exit(Request $request, CentralTenantAccessService $access): SymfonyResponse
    {
        $supportActor = Auth::guard('web')->user();
        abort_unless($supportActor instanceof User && $supportActor->isPlatformSupport(), 403);

        $grantId = $request->session()->pull('central_access_grant_id');
        $centralActorUuid = $supportActor->central_actor_uuid;
        abort_unless(is_string($grantId) && is_string($centralActorUuid), 403);

        $tenant = tenancy()->tenant;
        abort_unless($tenant instanceof Tenant, 403);

        $centralOrigin = $access->finishSupportSession((string)$tenant->getTenantKey(), $supportActor, $grantId);
        abort_unless($centralOrigin !== null, 403);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        abort_unless($access->isAllowedCentralOrigin($centralOrigin), 403);

        $centralUrl = mb_rtrim($centralOrigin, '/') . '/central';

        if ($request->header('X-Inertia') === 'true') {
            return Inertia::location($centralUrl);
        }

        return redirect()->away($centralUrl);
    }
}
