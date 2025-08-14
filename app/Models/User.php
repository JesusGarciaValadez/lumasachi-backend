<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Company;
use App\Models\Order;
use App\Enums\UserRole;
use App\Enums\UserType;

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
        'company_id',
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
        'role' => UserRole::EMPLOYEE->value,
        'is_active' => false,
        'type' => UserType::INDIVIDUAL->value,
        'company_id' => null,
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
            'type' => UserType::class,
        ];
    }

    // Scopes for easy queries
    public function scopeCustomers($query)
    {
        return $query->where('role', UserRole::CUSTOMER->value);
    }

    public function scopeEmployees($query)
    {
        return $query->where('role', UserRole::EMPLOYEE->value);
    }

    // Convenience methods
    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER->value;
    }

    public function isEmployee(): bool
    {
        return $this->role === UserRole::EMPLOYEE->value;
    }

    public function isAdministrator(): bool
    {
        return $this->role === UserRole::ADMINISTRATOR->value;
    }

    public function isSuperAdministrator(): bool
    {
        return $this->role === UserRole::SUPER_ADMINISTRATOR->value;
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return $this->first_name . " " . $this->last_name;
    }

    // Relationships
    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_to');
    }

    public function customerOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'uuid');
    }
}
