<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_uuid' => $this->whenNotNull($this->whenLoaded('company', fn($company) => $company->uuid)),
            'company_name' => $this->whenNotNull($this->whenLoaded('company', fn($company) => $company->name)),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'role' => $this->role,
            'type' => $this->type,
            'is_active' => $this->is_active,
            'preferences' => $this->preferences,
            'phone_number' => $this->phone_number,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
