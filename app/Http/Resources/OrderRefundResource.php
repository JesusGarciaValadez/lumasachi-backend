<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrderRefundResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'order_id' => $this->order_id,
            'source_payment_id' => $this->source_payment_id,
            'amount' => $this->amount,
            'status' => $this->status?->value,
            'reason' => $this->reason,
            'requested_by' => new UserResource($this->whenLoaded('requestedBy')),
            'requested_at' => $this->requested_at,
            'approved_by' => new UserResource($this->whenLoaded('approvedBy')),
            'approved_at' => $this->approved_at,
            'rejected_by' => new UserResource($this->whenLoaded('rejectedBy')),
            'rejected_at' => $this->rejected_at,
            'processed_by' => new UserResource($this->whenLoaded('processedBy')),
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
