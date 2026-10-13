<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Enums\UserRole;
use App\Models\CentralSuperAdministrator;
use App\Models\User;
use Illuminate\Support\Str;

final class TenantSupportActorService
{
    public function for(CentralSuperAdministrator $administrator): User
    {
        $centralAdministratorUuid = (string)$administrator->uuid;

        return User::unguarded(static fn(): User => User::query()->firstOrCreate(
            ['central_actor_uuid' => $centralAdministratorUuid],
            [
                'uuid' => Str::uuid7()->toString(),
                'first_name' => 'Platform',
                'last_name' => 'Support',
                'email' => 'platform-support+' . mb_strtolower($centralAdministratorUuid) . '@invalid.lumasachi',
                'password' => Str::random(64),
                'must_change_password' => false,
                'role' => UserRole::ADMINISTRATOR,
                'is_active' => false,
                'activated_at' => null,
                'email_verified_at' => now(),
                'is_platform_support' => true,
            ],
        ));
    }
}
