<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'customer' => new UserResource($this->whenLoaded('customer')),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'estimated_completion' => $this->estimated_completion,
            'actual_completion' => $this->actual_completion,
            'notes' => $this->notes,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'assigned_to' => new UserResource($this->whenLoaded('assignedTo')),
            'motor_info' => $this->whenLoaded('motorInfo'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'item_type' => $item->item_type,
                'is_received' => $item->is_received,
                'components' => $item->relationLoaded('components') ? $item->components->map(fn ($c) => [
                    'id' => $c->id,
                    'uuid' => $c->uuid,
                    'component_name' => $c->component_name,
                    'is_received' => $c->is_received,
                ]) : [],
            ])),
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($s) => [
                'id' => $s->id,
                'uuid' => $s->uuid,
                'service_key' => $s->service_key,
                'measurement' => $s->measurement,
                'is_budgeted' => $s->is_budgeted,
                'is_authorized' => $s->is_authorized,
                'is_completed' => $s->is_completed,
                'base_price' => $s->base_price,
                'net_price' => $s->net_price,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
