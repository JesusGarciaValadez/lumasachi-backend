<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SubmitBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'services' => 'required|array|min:1',
            'services.*.order_item_id' => [
                'required',
                'integer',
                Rule::exists('order_items', 'id')->where(function (Builder $query): void {
                    $order = $this->route('order');

                    if ($order instanceof Order) {
                        $query->where('order_id', $order->getKey())
                            ->where('is_received', true);
                    }
                }),
            ],
            'services.*.service_key' => [
                'required',
                Rule::exists('service_catalog', 'service_key')->where(function (Builder $query): void {
                    $query->where('is_active', true);
                }),
            ],
            'services.*.measurement' => 'nullable|string|max:50',
            'services.*.notes' => 'nullable|string|max:500',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Order|null $order */
            $order = $this->route('order');

            if ($order && $order->status !== OrderStatus::AwaitingReview) {
                $validator->errors()->add('status', 'Order must be in Awaiting Review status.');
            }

            $services = $this->input('services', []);

            if (!is_array($services)) {
                return;
            }

            foreach ($services as $index => $service) {
                if (!is_array($service)) {
                    continue;
                }

                $catalog = ServiceCatalog::query()->where('service_key', $service['service_key'] ?? null)->first();
                $itemId = $service['order_item_id'] ?? null;
                $item = is_int($itemId) ? OrderItem::query()->find($itemId) : null;

                if ($catalog && $item && $catalog->item_type !== $item->item_type) {
                    $validator->errors()->add(
                        "services.{$index}.service_key",
                        'The selected service is not available for the selected item type.'
                    );
                }

                if ($catalog?->requires_measurement && blank($service['measurement'] ?? null)) {
                    $validator->errors()->add(
                        "services.{$index}.measurement",
                        'A measurement is required for the selected service.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'services.required' => 'At least one service is required.',
            'services.min' => 'At least one service is required.',
            'services.*.order_item_id.required' => 'Each service must be linked to an order item.',
            'services.*.order_item_id.integer' => 'The selected order item is invalid.',
            'services.*.order_item_id.exists' => 'The selected order item does not exist.',
            'services.*.service_key.required' => 'Each service must have a service key.',
            'services.*.service_key.exists' => 'The selected service does not exist in the active catalog.',
        ];
    }
}
