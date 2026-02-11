<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Notifications\Notification;

trait NotifiesAdmins
{
    /**
     * Notify all active admin and super admin users with the given notification.
     */
    private function notifyAdmins(Notification $notification): void
    {
        User::query()
            ->whereIn('role', [UserRole::ADMINISTRATOR->value, UserRole::SUPER_ADMINISTRATOR->value])
            ->where('is_active', true)
            ->chunkById(200, function ($admins) use ($notification) {
                foreach ($admins as $admin) {
                    $admin->notify($notification);
                }
            });
    }
}
