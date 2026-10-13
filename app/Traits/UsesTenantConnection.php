<?php

declare(strict_types=1);

namespace App\Traits;

use Stancl\Tenancy\Exceptions\TenancyNotInitializedException;

trait UsesTenantConnection
{
    public function getConnectionName(): ?string
    {
        if (!tenancy()->initialized) {
            if (app()->environment('testing') && config('tenancy.allow_legacy_model_connection')) {
                return parent::getConnectionName();
            }

            throw new TenancyNotInitializedException('Tenant-owned models require an initialized company context.');
        }

        return 'tenant';
    }
}
