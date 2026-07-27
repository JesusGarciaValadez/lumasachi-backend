<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
final class PublicOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'item_type' => $this->item_type?->value,
            'is_received' => $this->is_received,
            'components' => PublicOrderItemComponentResource::collection($this->whenLoaded('components')),
        ];
    }
}
