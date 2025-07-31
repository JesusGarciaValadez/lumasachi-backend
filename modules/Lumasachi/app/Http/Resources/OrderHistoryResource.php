<?php

namespace Modules\Lumasachi\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderHistoryResource extends JsonResource
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
            'order_id' => $this->order_id,
            'status_from' => $this->status_from,
            'status_to' => $this->status_to,
            'priority_from' => $this->priority_from,
            'priority_to' => $this->priority_to,
            'description' => $this->description,
            'notes' => $this->notes,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'created_at' => $this->created_at,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments'))
        ];
    }
}
