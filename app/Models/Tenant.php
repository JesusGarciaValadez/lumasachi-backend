<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as StanclTenant;

/**
 * @mixin IdeHelperTenant
 */
final class Tenant extends StanclTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'id',
    ];

    /** @var list<string> */
    protected $hidden = [
        'data',
        'tenancy_db_name',
        'tenancy_db_username',
        'tenancy_db_password',
    ];

    /** @return list<string> */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'provisioning_status',
            'ready_at',
            'created_at',
            'updated_at',
        ];
    }

    /** @return HasOne<Company, $this> */
    public function company(): HasOne
    {
        return $this->hasOne(Company::class, 'uuid', 'id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenancy_db_password' => 'encrypted',
        ];
    }
}
