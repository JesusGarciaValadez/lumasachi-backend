<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItemComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItemComponent */
final class OrderItemComponentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, uuid: string, component_name: string, is_received: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'component_name' => $this->component_name,
            'is_received' => $this->is_received,
        ];
    }
}
