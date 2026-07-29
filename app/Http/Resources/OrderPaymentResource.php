<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderPaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'order_id' => $this->order_id,
            'amount' => $this->amount,
            'received_at' => $this->received_at,
            'created_by' => $this->created_by,
            'creator' => new UserResource($this->whenLoaded('createdBy')),
            'created_at' => $this->created_at,
        ];
    }
}
