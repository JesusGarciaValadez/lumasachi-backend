<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\Enums\UserRole;

final class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Users can see their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Only admins can view other users
        return in_array($user->role, [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR
        ]);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR
        ]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Users can edit their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Only admins can update other users
        return in_array($user->role, [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR
        ]);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // A user can't delete themselves
        if ($user->id === $model->id) {
            return false;
        }

        return $user->role === UserRole::SUPER_ADMINISTRATOR;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        // Users can restore profiles
        if ($user->id === $model->id) {
            return true;
        }

        // Only admins can restore other users
        return in_array($user->role, [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR
        ]);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Users can delete their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Only admins can delete other users
        return in_array($user->role, [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR
        ]);
    }
}
