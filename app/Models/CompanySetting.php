<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

final class CompanySetting extends Model
{
    use UsesTenantConnection;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'website',
        'logo',
        'tax_id',
        'contact_person',
        'contact_email',
        'contact_phone',
        'locale',
        'email_from_address',
        'email_from_name',
        'description',
        'notes',
        'settings',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
