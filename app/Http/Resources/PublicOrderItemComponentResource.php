<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItemComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItemComponent */
final class PublicOrderItemComponentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'component_name' => $this->component_name,
            'is_received' => $this->is_received,
        ];
    }
}
