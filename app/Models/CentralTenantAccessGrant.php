<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCentralTenantAccessGrant
 */
final class CentralTenantAccessGrant extends Model
{
    /** @var list<string> */
    protected $guarded = ['*'];

    public function getConnectionName(): string
    {
        return (string)config('tenancy.database.central_connection', config('database.default'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'string',
            'central_actor_uuid' => 'string',
            'central_origin' => 'string',
            'token_hash' => 'string',
            'expires_at' => 'immutable_datetime',
            'entered_at' => 'immutable_datetime',
            'exited_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
