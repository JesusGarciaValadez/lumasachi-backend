<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TenantReadiness
{
    public function assertReady(Tenant $tenant): void
    {
        $companyIsActive = Company::query()
            ->where('uuid', $tenant->getTenantKey())
            ->where('is_active', true)
            ->exists();

        if (!$companyIsActive
            || $tenant->getAttribute('provisioning_status') !== 'ready'
            || $tenant->getAttribute('ready_at') === null) {
            throw new NotFoundHttpException();
        }

        $centralConnectionName = config('tenancy.database.central_connection');
        $databaseName = $tenant->database()->getName();

        $databaseExists = is_string($centralConnectionName)
            && config("database.connections.{$centralConnectionName}.driver") === 'pgsql'
            && is_string($databaseName)
            && $databaseName !== ''
            && DB::connection($centralConnectionName)
                ->table('pg_database')
                ->where('datname', $databaseName)
                ->exists();

        if (!$databaseExists) {
            throw new NotFoundHttpException();
        }
    }
}
