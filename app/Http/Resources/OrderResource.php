<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'customer' => new UserResource($this->whenLoaded('customer')),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->getLabel(),
            'lifecycle_status' => $this->lifecycleStatus()?->value,
            'lifecycle_status_label' => $this->lifecycleStatus()?->getLabel(),
            'disposition_status' => $this->dispositionStatus()?->value,
            'disposition_status_label' => $this->dispositionStatus()?->getLabel(),
            'payment_status' => $this->paymentStatusEnum()->value,
            'payment_status_label' => $this->paymentStatusEnum()->getLabel(),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->getLabel(),
            'estimated_completion' => $this->estimated_completion,
            'actual_completion' => $this->actual_completion,
            'notes' => $this->notes,
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'assigned_to' => new UserResource($this->whenLoaded('assignedTo')),
            'motor_info' => $this->whenLoaded('motorInfo'),
            'payments' => OrderPaymentResource::collection($this->whenLoaded('payments')),
            'refunds' => OrderRefundResource::collection($this->whenLoaded('refunds')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'services' => OrderServiceResource::collection($this->whenLoaded('services')),
            'history' => OrderHistoryResource::collection($this->whenLoaded('orderHistories')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'financials' => $this->when(
                $this->resource->relationLoaded('services') && $this->resource->relationLoaded('motorInfo'),
                fn(): array => $this->financialTotals(),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
