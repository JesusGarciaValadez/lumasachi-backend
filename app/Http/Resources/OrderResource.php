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
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'estimated_completion' => $this->estimated_completion,
            'actual_completion' => $this->actual_completion,
            'notes' => $this->notes,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'assigned_to' => new UserResource($this->whenLoaded('assignedTo')),
            'motor_info' => $this->whenLoaded('motorInfo'),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'services' => OrderServiceResource::collection($this->whenLoaded('services')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
