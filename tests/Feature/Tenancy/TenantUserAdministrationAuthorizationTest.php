<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

test('only a central support actor may administer users in tenant context', function (): void {
    $tenant = Tenant::factory()->make();
    $tenant->database()->makeCredentials();
    tenancy()->initialize($tenant);

    try {
        $tenantSuperAdministrator = User::make([
            'id' => 1,
            'role' => UserRole::SUPER_ADMINISTRATOR,
        ]);
        $tenantEmployee = User::make([
            'id' => 2,
            'is_active' => true,
            'role' => UserRole::EMPLOYEE,
        ]);

        expect($tenantSuperAdministrator->can('viewAny', User::class))->toBeFalse()
            ->and($tenantSuperAdministrator->can('create', User::class))->toBeFalse()
            ->and($tenantSuperAdministrator->can('viewAdministration', $tenantEmployee))->toBeFalse()
            ->and($tenantSuperAdministrator->can('updateAdministration', $tenantEmployee))->toBeFalse()
            ->and($tenantSuperAdministrator->can('delete', $tenantEmployee))->toBeFalse()
            ->and($tenantSuperAdministrator->can('assignRole', [$tenantEmployee, UserRole::CUSTOMER]))->toBeFalse();

        $supportActor = User::make([
            'id' => 3,
            'role' => UserRole::ADMINISTRATOR,
        ])->forceFill(['is_platform_support' => true]);

        expect($supportActor->can('viewAny', User::class))->toBeTrue()
            ->and($supportActor->can('create', User::class))->toBeTrue()
            ->and($supportActor->can('viewAdministration', $tenantEmployee))->toBeTrue()
            ->and($supportActor->can('updateAdministration', $tenantEmployee))->toBeTrue()
            ->and($supportActor->can('delete', $tenantEmployee))->toBeTrue()
            ->and($supportActor->can('assignRole', [$tenantEmployee, UserRole::CUSTOMER]))->toBeTrue();
    } finally {
        tenancy()->end();
    }
});
