<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Models\OrderItemComponent;
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
            'item_type_label' => $this->item_type?->label(),
            'is_received' => $this->is_received,
            'components' => $this->whenLoaded('components', fn() => $this->components
                ->map(fn(OrderItemComponent $component): PublicOrderItemComponentResource => new PublicOrderItemComponentResource($component, $this->item_type))
                ->values()),
        ];
    }
}
