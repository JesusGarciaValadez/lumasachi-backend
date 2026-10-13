<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CentralSuperAdministratorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @mixin IdeHelperCentralSuperAdministrator
 */
final class CentralSuperAdministrator extends Authenticatable
{
    /** @use HasFactory<CentralSuperAdministratorFactory> */
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    /** @var string */
    protected $table = 'central_super_administrators';

    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'email_verified_at',
        'password',
        'must_change_password',
        'is_active',
        'activated_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getConnectionName(): string
    {
        return (string)config('tenancy.database.central_connection', config('database.default'));
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }
}
