<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrderLifecycleStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role->value, [
            UserRole::SUPER_ADMINISTRATOR->value,
            UserRole::ADMINISTRATOR->value,
            UserRole::EMPLOYEE->value,
            UserRole::CUSTOMER->value,
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Order $order): bool
    {
        return match ($user->role->value) {
            UserRole::SUPER_ADMINISTRATOR->value, UserRole::ADMINISTRATOR->value => true,
            UserRole::EMPLOYEE->value => $order->assigned_to === $user->id || $order->created_by === $user->id,
            UserRole::CUSTOMER->value => $order->customer_id === $user->id,
            default => false
        };
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role->value, [
            UserRole::SUPER_ADMINISTRATOR->value,
            UserRole::ADMINISTRATOR->value,
            UserRole::EMPLOYEE->value,
        ]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Order $order): bool
    {
        return match ($user->role->value) {
            UserRole::SUPER_ADMINISTRATOR->value, UserRole::ADMINISTRATOR->value => true,
            UserRole::EMPLOYEE->value => $order->assigned_to === $user->id || $order->created_by === $user->id,
            default => false
        };
    }

    /**
     * Determine whether the user can cancel the order from any lifecycle step.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $order->lifecycleStatus() !== OrderLifecycleStatus::Delivered
            && $order->dispositionStatus() === null
            && in_array($user->role->value, [
                UserRole::SUPER_ADMINISTRATOR->value,
                UserRole::ADMINISTRATOR->value,
            ], true);
    }

    /**
     * Determine whether the user can approve services for the order.
     */
    public function approve(User $user, Order $order): bool
    {
        return $user->role === UserRole::CUSTOMER
            && $order->customer_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): bool
    {
        return match ($user->role->value) {
            UserRole::SUPER_ADMINISTRATOR->value => true,
            UserRole::ADMINISTRATOR->value => false,
            UserRole::EMPLOYEE->value => false,
            default => false
        };
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Order $order): bool
    {
        return match ($user->role->value) {
            UserRole::SUPER_ADMINISTRATOR->value, UserRole::ADMINISTRATOR->value => true,
            UserRole::EMPLOYEE->value => $order->assigned_to === $user->id || $order->created_by === $user->id,
            default => false
        };
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Order $order): bool
    {
        return match ($user->role->value) {
            UserRole::SUPER_ADMINISTRATOR->value, UserRole::ADMINISTRATOR->value => true,
            UserRole::EMPLOYEE->value => $order->assigned_to === $user->id || $order->created_by === $user->id,
            default => false
        };
    }

    /**
     * Determine whether the user can assign the order to another user.
     */
    public function assign(User $user, Order $order): bool
    {
        return in_array($user->role->value, [
            UserRole::SUPER_ADMINISTRATOR->value,
            UserRole::ADMINISTRATOR->value,
        ]);
    }
}
