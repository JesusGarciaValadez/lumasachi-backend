<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserAdministrationListResource extends JsonResource
{
    /**
     * Transform the resource into the redacted list representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->last_name . ', ' . $this->first_name,
            'role' => $this->role?->value,
            'type' => $this->type?->value,
            'is_active' => (bool)$this->is_active,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'company' => $this->whenLoaded('company', fn(): array => [
                'id' => $this->company->id,
                'uuid' => $this->company->uuid,
                'name' => $this->company->name,
            ]),
        ];
    }
}
