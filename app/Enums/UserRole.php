<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMINISTRATOR = 'Super Administrator';
    case ADMINISTRATOR = 'Administrator';
    case EMPLOYEE = 'Employee';
    case CUSTOMER = 'Customer';

    public static function getPermissions(UserRole $role): array
    {
        return match ($role) {
            self::SUPER_ADMINISTRATOR => [
                'users.create',
                'users.read',
                'users.update',
                'users.delete',
                'customers.create',
                'customers.read',
                'customers.update',
                'customers.delete',
                'orders.create',
                'orders.read',
                'orders.update',
                'orders.delete',
                'orders.assign',
                'orders.status_change',
                'reports.view',
                'reports.export',
                'system.settings',
                'system.logs'
            ],
            self::ADMINISTRATOR => [
                'users.create',
                'users.read',
                'users.update',
                'customers.create',
                'customers.read',
                'customers.update',
                'orders.create',
                'orders.read',
                'orders.update',
                'orders.assign',
                'orders.status_change',
                'reports.view',
                'reports.export'
            ],
            self::EMPLOYEE => [
                'customers.read',
                'orders.create',
                'orders.read',
                'orders.update',
                'orders.status_change' // Only their assigned orders
            ],
            self::CUSTOMER => [
                'orders.read' // Only their own orders
            ]
        };
    }

    public static function getLabel(UserRole $role): string
    {
        return match ($role) {
            self::SUPER_ADMINISTRATOR => 'Super Administrator',
            self::ADMINISTRATOR => 'Administrator',
            self::EMPLOYEE => 'Employee',
            self::CUSTOMER => 'Customer'
        };
    }
}
