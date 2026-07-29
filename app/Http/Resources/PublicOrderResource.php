<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class PublicOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'status_label' => $this->status?->getLabel(),
            'lifecycle_status' => $this->lifecycleStatus()?->value,
            'lifecycle_status_label' => $this->lifecycleStatus()?->getLabel(),
            'disposition_status' => $this->dispositionStatus()?->value,
            'disposition_status_label' => $this->dispositionStatus()?->getLabel(),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority
                ? __('orders.priority_labels.' . $this->priority->value)
                : null,
            'estimated_completion' => $this->estimated_completion,
            'actual_completion' => $this->actual_completion,
            'motor_info' => $this->whenLoaded('motorInfo', fn(): array => [
                'brand' => $this->motorInfo?->brand,
                'liters' => $this->motorInfo?->liters,
                'year' => $this->motorInfo?->year,
                'model' => $this->motorInfo?->model,
                'cylinder_count' => $this->motorInfo?->cylinder_count,
            ]),
            'items' => PublicOrderItemResource::collection($this->whenLoaded('items')),
            'services' => PublicOrderServiceResource::collection($this->whenLoaded('services')),
            'financials' => $this->when(
                $this->resource->relationLoaded('services') && $this->resource->relationLoaded('motorInfo'),
                fn(): array => $this->financialTotals(),
            ),
            'history' => PublicOrderHistoryResource::collection($this->whenLoaded('orderHistories')),
            'attachments' => PublicAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
        ];
    }
}
