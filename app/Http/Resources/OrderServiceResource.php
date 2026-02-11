<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class OrderServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'service_key' => $this->service_key,
            'measurement' => $this->measurement,
            'is_budgeted' => $this->is_budgeted,
            'is_authorized' => $this->is_authorized,
            'is_completed' => $this->is_completed,
            'base_price' => $this->base_price,
            'net_price' => $this->net_price,
        ];
    }
}
