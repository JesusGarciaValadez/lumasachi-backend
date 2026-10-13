<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\UserRole;
use App\Models\CentralSuperAdministrator;
use App\Models\User;
use Illuminate\Notifications\Notification;

trait NotifiesAdmins
{
    /**
     * Notify active tenant administrators and central super administrators.
     */
    private function notifyAdmins(Notification $notification): void
    {
        $administratorRoles = [UserRole::ADMINISTRATOR->value];

        if (!tenancy()->initialized) {
            $administratorRoles[] = UserRole::SUPER_ADMINISTRATOR->value;
        }

        User::query()
            ->whereIn('role', $administratorRoles)
            ->where('is_active', true)
            ->chunkById(200, function ($admins) use ($notification) {
                foreach ($admins as $admin) {
                    $admin->notify($notification);
                }
            });

        if (!tenancy()->initialized) {
            return;
        }

        CentralSuperAdministrator::query()
            ->where('is_active', true)
            ->chunkById(200, function ($administrators) use ($notification) {
                foreach ($administrators as $administrator) {
                    $administrator->notify($notification);
                }
            });
    }
}
