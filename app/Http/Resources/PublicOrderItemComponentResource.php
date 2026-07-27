<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\OrderItemType;
use App\Models\OrderItemComponent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItemComponent */
final class PublicOrderItemComponentResource extends JsonResource
{
    public function __construct(mixed $resource, private readonly ?OrderItemType $itemType = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'component_name' => $this->component_name,
            'component_key' => $this->component_name,
            'component_label' => $this->itemType?->componentLabel($this->component_name) ?? __('motor.fallback'),
            'is_received' => $this->is_received,
        ];
    }
}
