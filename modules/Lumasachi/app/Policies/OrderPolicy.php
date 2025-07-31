<?php

namespace Modules\Lumasachi\app\Policies;

use Modules\Lumasachi\app\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Lumasachi\app\Enums\UserRole;

final class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR,
            UserRole::EMPLOYEE,
            UserRole::CUSTOMER
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        return match ($user->role) {
            UserRole::SUPER_ADMINISTRATOR, UserRole::ADMINISTRATOR => true,
            UserRole::EMPLOYEE => $order->assigned_to === $user->id || $order->created_by === $user->id,
            UserRole::CUSTOMER => $order->customer_id === $user->id,
            default => false
        };
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR,
            UserRole::EMPLOYEE
        ]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        return match ($user->role) {
            UserRole::SUPER_ADMINISTRATOR, UserRole::ADMINISTRATOR => true,
            UserRole::EMPLOYEE => $order->assigned_to === $user->id || $order->created_by === $user->id,
            default => false
        };
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->role === UserRole::SUPER_ADMINISTRATOR;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Order $order): bool
    {
        return match ($user->role) {
            UserRole::SUPER_ADMINISTRATOR, UserRole::ADMINISTRATOR => true,
            UserRole::EMPLOYEE => $order->assigned_to === $user->id || $order->created_by === $user->id,
            default => false
        };
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return match ($user->role) {
            UserRole::SUPER_ADMINISTRATOR, UserRole::ADMINISTRATOR => true,
            UserRole::EMPLOYEE => $order->assigned_to === $user->id || $order->created_by === $user->id,
            default => false
        };
    }

    /**
     * Determine whether the user can assign the order to another user.
     */
    public function assign(User $user, Order $order): bool
    {
        return in_array($user->role, [
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::ADMINISTRATOR
        ]);
    }
}
