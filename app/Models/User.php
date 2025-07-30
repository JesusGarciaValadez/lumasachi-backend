<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Enums\UserType;

/**
 * @mixin IdeHelperUser
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'phone_number',
        'address',
        'company',
        'is_active',
        'notes',
        'type',
        'preferences',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array
     */
    protected $attributes = [
        'role' => UserRole::EMPLOYEE,
        'type' => UserType::INDIVIDUAL,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    // Scopes for easy queries
    public function scopeCustomers($query)
    {
        return $query->where('role', UserRole::CUSTOMER);
    }

    public function scopeEmployees($query)
    {
        return $query->where('role', UserRole::EMPLOYEE);
    }

    // Convenience methods
    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER;
    }

    public function isEmployee(): bool
    {
        return $this->role === UserRole::EMPLOYEE;
    }

    public function isAdministrator(): bool
    {
        return $this->role === UserRole::ADMINISTRATOR;
    }

    public function isSuperAdministrator(): bool
    {
        return $this->role === UserRole::SUPER_ADMINISTRATOR;
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // Relationships
    public function createdOrders()
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function assignedOrders()
    {
        return $this->hasMany(Order::class, 'assigned_to');
    }

    public function customerOrders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }
}
