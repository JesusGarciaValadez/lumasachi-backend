<?php

declare(strict_types=1);

use App\Models\CentralSuperAdministrator;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

test('operations dashboards require an active central super administrator', function (): void {
    $administrator = (new CentralSuperAdministrator)->forceFill([
        'is_active' => true,
    ]);
    Auth::guard('central')->setUser($administrator);

    expect(Gate::forUser($administrator)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($administrator)->allows('viewTelescope'))->toBeTrue();

    $inactiveAdministrator = (new CentralSuperAdministrator)->forceFill([
        'is_active' => false,
    ]);
    Auth::guard('central')->setUser($inactiveAdministrator);

    expect(Gate::forUser($inactiveAdministrator)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser($inactiveAdministrator)->allows('viewTelescope'))->toBeFalse();

    $tenantUser = (new User)->forceFill([
        'email' => 'jesus.garciav@me.com',
        'is_platform_support' => true,
    ]);
    Auth::guard('central')->setUser($tenantUser);

    expect(Gate::forUser($tenantUser)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser($tenantUser)->allows('viewTelescope'))->toBeFalse();
});
