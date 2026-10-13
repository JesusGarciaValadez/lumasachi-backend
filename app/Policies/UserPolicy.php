<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

final class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (tenancy()->initialized) {
            return $user->isPlatformSupport();
        }

        return !$user->isPlatformSupport() && in_array($user->role, [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR,
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->isPlatformSupport()) {
            return $this->isTenantSupportManager($user) && !$model->isPlatformSupport();
        }

        if ($user->id === $model->id) {
            return true;
        }

        return $this->canManageTarget($user, $model);
    }

    /**
     * Determine whether the user can view a profile through administration.
     */
    public function viewAdministration(User $user, User $model): bool
    {
        return $this->canManageTarget($user, $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->isTenantSupportManager($user)
            || (!tenancy()->initialized
                && !$user->isPlatformSupport()
                && $user->role === UserRole::SUPER_ADMINISTRATOR);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->isPlatformSupport()) {
            return $this->canManageTarget($user, $model);
        }

        if ($user->id === $model->id) {
            return true;
        }

        return $this->canManageTarget($user, $model);
    }

    /**
     * Determine whether the user can update a profile through administration.
     */
    public function updateAdministration(User $user, User $model): bool
    {
        return $this->canManageTarget($user, $model);
    }

    /**
     * Determine whether an actor may assign a role to an administration target.
     */
    public function assignRole(User $user, User $model, UserRole $role): bool
    {
        if (!$this->canManageTarget($user, $model)) {
            return false;
        }

        if ($user->role === UserRole::SUPER_ADMINISTRATOR) {
            return true;
        }

        return $user->id !== $model->id
            && $role !== UserRole::SUPER_ADMINISTRATOR;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if ($this->isTenantSupportManager($user)) {
            return $user->id !== $model->id && !$model->isPlatformSupport();
        }

        if (tenancy()->initialized) {
            return false;
        }

        if ($user->id === $model->id || $user->role !== UserRole::SUPER_ADMINISTRATOR) {
            return false;
        }

        return !($model->role === UserRole::SUPER_ADMINISTRATOR
            && $model->is_active
            && User::query()
                ->where('role', UserRole::SUPER_ADMINISTRATOR->value)
                ->where('is_active', true)
                ->count() <= 1);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        if (tenancy()->initialized) {
            return $this->isTenantSupportManager($user) && !$model->isPlatformSupport();
        }

        if ($user->isPlatformSupport()) {
            return false;
        }

        // Users can restore profiles
        if ($user->id === $model->id) {
            return true;
        }

        // Only admins can restore other users
        return in_array($user->role, [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR,
        ]);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        if (tenancy()->initialized) {
            return $this->isTenantSupportManager($user) && !$model->isPlatformSupport();
        }

        if ($user->isPlatformSupport()) {
            return false;
        }

        // Users can delete their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Only admins can delete other users
        return in_array($user->role, [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR,
        ]);
    }

    private function canManageTarget(User $user, User $model): bool
    {
        if ($model->isPlatformSupport()) {
            return false;
        }

        if ($this->isTenantSupportManager($user)) {
            return true;
        }

        if (tenancy()->initialized) {
            return false;
        }

        if ($user->isPlatformSupport()) {
            return false;
        }

        if ($user->role === UserRole::SUPER_ADMINISTRATOR) {
            return true;
        }

        return $user->role === UserRole::ADMINISTRATOR
            && $user->company_id !== null
            && $model->company_id === $user->company_id
            && $model->is_active;
    }

    private function isTenantSupportManager(User $user): bool
    {
        return tenancy()->initialized && $user->isPlatformSupport();
    }
}
