<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderHistory */
final class PublicOrderHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'field_changed' => $this->publicFieldName(),
            'description' => $this->publicDescription(),
            'created_at' => $this->created_at,
        ];
    }

    private function publicFieldName(): string
    {
        return match ($this->field_changed) {
            'status' => 'status',
            'priority' => 'priority',
            'item_received', 'item_component_received' => 'items',
            'service_budgeted', 'service_authorized', 'service_completed' => 'services',
            'attachments' => 'attachments',
            default => 'order',
        };
    }

    private function publicDescription(): string
    {
        return in_array($this->field_changed, ['status', 'priority'], true)
            ? $this->description
            : __('orders.public_history_updated');
    }
}
