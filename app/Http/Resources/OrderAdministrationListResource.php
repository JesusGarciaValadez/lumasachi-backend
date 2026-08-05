<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
final class OrderAdministrationListResource extends JsonResource
{
    /**
     * Transform the resource into the redacted order list representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'customer' => $this->whenLoaded('customer', fn(): ?array => $this->userSummary($this->customer)),
            'company' => $this->when(
                $this->relationLoaded('customer') && $this->customer?->relationLoaded('company'),
                fn(): ?array => $this->companySummary($this->customer?->company),
            ),
            'assigned_to' => $this->whenLoaded('assignedTo', fn(): ?array => $this->userSummary($this->assignedTo)),
            'lifecycle_status' => $this->lifecycleStatus()?->value,
            'lifecycle_status_label' => $this->lifecycleStatus()?->getLabel(),
            'disposition_status' => $this->dispositionStatus()?->value,
            'disposition_status_label' => $this->dispositionStatus()?->getLabel(),
            'payment_status' => $this->paymentStatusEnum()->value,
            'payment_status_label' => $this->paymentStatusEnum()->getLabel(),
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->getLabel(),
            'refunds' => $this->whenLoaded(
                'refunds',
                fn(): array => $this->refunds
                    ->map(fn(OrderRefund $refund): array => [
                        'id' => $refund->id,
                        'uuid' => $refund->uuid,
                        'order_id' => $refund->order_id,
                        'amount' => $refund->amount,
                        'status' => $refund->status?->value,
                        'status_label' => $refund->status?->getLabel(),
                    ])
                    ->values()
                    ->all(),
            ),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userSummary(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->last_name . ', ' . $user->first_name,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function companySummary(?Company $company): ?array
    {
        if ($company === null) {
            return null;
        }

        return [
            'id' => $company->id,
            'uuid' => $company->uuid,
            'name' => $company->name,
        ];
    }
}
