<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderService */
final class PublicOrderServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'service_key' => $this->service_key,
            'service_name' => $this->catalogItem?->service_name,
            'measurement' => $this->measurement,
            'is_budgeted' => $this->is_budgeted,
            'is_authorized' => $this->is_authorized,
            'is_completed' => $this->is_completed,
            'base_price' => $this->base_price,
            'net_price' => $this->net_price,
        ];
    }
}
