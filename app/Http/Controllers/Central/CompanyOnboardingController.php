<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreCompanyOnboardingRequest;
use App\Models\CentralSuperAdministrator;
use App\Models\Company;
use App\Services\Tenancy\CompanyOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class CompanyOnboardingController extends Controller
{
    public function index(): View
    {
        $this->assertActiveCentralAdministrator();

        $centralConnection = (string)config('tenancy.database.central_connection', config('database.default'));
        $onboardings = DB::connection($centralConnection)->table('tenant_onboardings')
            ->join('companies', 'companies.uuid', '=', 'tenant_onboardings.tenant_id')
            ->join('tenants', 'tenants.id', '=', 'tenant_onboardings.tenant_id')
            ->select([
                'companies.id as company_id',
                'companies.uuid as company_uuid',
                'companies.name as company_name',
                'companies.is_active as company_is_active',
                'tenants.provisioning_status',
                'tenant_onboardings.tenant_domain',
                'tenant_onboardings.administrator_first_name',
                'tenant_onboardings.administrator_last_name',
                'tenant_onboardings.administrator_email',
                'tenant_onboardings.attempt_count',
                'tenant_onboardings.last_failed_stage',
                'tenant_onboardings.last_failure_type',
            ])
            ->orderByDesc('tenant_onboardings.created_at')
            ->get();

        $configuredDomains = config('tenancy.central_domains', []);
        $parentDomains = is_array($configuredDomains)
            ? array_values(array_unique(array_filter(
                array_map(static fn(mixed $domain): string => is_string($domain) ? mb_strtolower(mb_trim($domain)) : '', $configuredDomains),
                static fn(string $domain): bool => $domain !== '' && filter_var($domain, FILTER_VALIDATE_IP) === false,
            )))
            : [];

        return view('central.companies.index', [
            'onboardings' => $onboardings,
            'parentDomains' => $parentDomains,
        ]);
    }

    private function assertActiveCentralAdministrator(): CentralSuperAdministrator
    {
        $administrator = Auth::guard('central')->user();
        abort_unless($administrator instanceof CentralSuperAdministrator && $administrator->is_active, 403);

        return $administrator;
    }

    public function store(StoreCompanyOnboardingRequest $request, CompanyOnboardingService $onboarding): RedirectResponse
    {
        $actor = $this->assertActiveCentralAdministrator();

        $company = $onboarding->create($request->validatedOnboardingData(), $actor);
        $company->load('tenant');

        return redirect()->route('central.companies.index', [
            'centralHost' => $request->route('centralHost'),
        ])->with(
            $company->tenant?->provisioning_status === 'ready' ? 'status' : 'error',
            $company->tenant?->provisioning_status === 'ready'
                ? 'Company provisioned and its first Administrator invitation was queued.'
                : 'Company record saved, but provisioning did not finish. Review the recorded stage and retry when ready.',
        );
    }

    public function retry(Request $request, CompanyOnboardingService $onboarding): RedirectResponse
    {
        $this->assertActiveCentralAdministrator();

        $companyUuid = $request->route('company');
        abort_unless(is_string($companyUuid) && $companyUuid !== '', 404);
        $company = Company::query()->where('uuid', $companyUuid)->firstOrFail();

        $centralConnection = (string)config('tenancy.database.central_connection', config('database.default'));
        abort_unless(
            DB::connection($centralConnection)->table('tenant_onboardings')
                ->where('tenant_id', $company->uuid)
                ->exists(),
            404,
        );

        $company = $onboarding->retry($company);
        $company->load('tenant');
        $status = $company->tenant?->provisioning_status;

        return redirect()->route('central.companies.index', [
            'centralHost' => $request->route('centralHost'),
        ])->with(
            $status === 'ready' ? 'status' : ($status === 'failed' ? 'error' : 'info'),
            match ($status) {
                'ready' => 'Company is ready and the first Administrator invitation was queued.',
                'failed' => 'Provisioning failed again. Review the recorded stage and failure type before another retry.',
                default => 'Provisioning is already in progress; no duplicate attempt was started.',
            },
        );
    }
}
