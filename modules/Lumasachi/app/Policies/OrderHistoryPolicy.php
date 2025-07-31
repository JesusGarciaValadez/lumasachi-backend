<?php

namespace Modules\Lumasachi\app\Policies;

use App\Models\User;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Enums\UserRole;

class OrderHistoryPolicy
{
    /**
     * Determine whether the user can view any order histories.
     */
    public function viewAny(User $user): bool
    {
        // Administrators can view all order histories
        if (in_array($user->role, [UserRole::ADMINISTRATOR, UserRole::SUPER_ADMINISTRATOR])) {
            return true;
        }

        // Employees can view histories of their assigned orders
        if ($user->role === UserRole::EMPLOYEE) {
            return true;
        }

        // Customers can view histories of their own orders
        if ($user->type === 'customer') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the order history.
     */
    public function view(User $user, OrderHistory $orderHistory): bool
    {
        // Administrators can view all order histories
        if (in_array($user->role, [UserRole::ADMINISTRATOR, UserRole::SUPER_ADMINISTRATOR])) {
            return true;
        }

        // Load the order relationship if not loaded
        $order = $orderHistory->order;

        // Employees can view histories of orders assigned to them
        if ($user->role === UserRole::EMPLOYEE && $order->assigned_to === $user->id) {
            return true;
        }

        // Customers can view histories of their own orders
        if ($user->type === 'customer' && $order->customer_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create order histories.
     */
    public function create(User $user): bool
    {
        // Only administrators and employees can create order histories
        // Customers cannot manually create histories
        return in_array($user->role, [
            UserRole::ADMINISTRATOR,
            UserRole::SUPER_ADMINISTRATOR,
            UserRole::EMPLOYEE
        ]);
    }

    /**
     * Determine whether the user can delete the order history.
     */
    public function delete(User $user, OrderHistory $orderHistory): bool
    {
        // Only super administrators can delete order histories
        // This maintains audit trail integrity
        return $user->role === UserRole::SUPER_ADMINISTRATOR;
    }
}
