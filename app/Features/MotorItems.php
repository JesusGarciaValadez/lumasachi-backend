<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

final class MotorItems
{
    /**
     * The stored name of the feature.
     */
    public string $name = 'motor-items';

    /**
     * Run an always-in-memory check before the stored value is retrieved.
     *
     * If globally disabled, only allow internal staff (admins) to access.
     */
    public function before(?User $user): mixed
    {
        if (Config::get('features.motor-items.disabled')) {
            return $user?->isSuperAdministrator() || $user?->isAdministrator();
        }

        $rollout = Config::get('features.motor-items.rollout_date');
        if ($rollout) {
            try {
                if (Carbon::parse($rollout)->isPast()) {
                    return true;
                }
            } catch (InvalidArgumentException) {
                return false;
            }
        }

        return null; // defer to stored value or resolver
    }

    /**
     * Resolve the feature's initial value.
     */
    public function resolve(?User $user): mixed
    {
        // Default rollout: enabled for staff (employee/admin/super_admin), disabled for customers / guests
        if (! $user) {
            return false;
        }

        return $user->isSuperAdministrator() || $user->isAdministrator() || $user->isEmployee();
    }
}
