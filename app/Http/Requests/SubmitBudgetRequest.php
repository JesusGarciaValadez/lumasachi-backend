<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderLifecycleStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceCatalog;
use App\Traits\TranslatesValidationAttributes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SubmitBudgetRequest extends FormRequest
{
    use TranslatesValidationAttributes;

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

            if ($order && $order->lifecycleStatus() !== OrderLifecycleStatus::AwaitingReview) {
                $validator->errors()->add('lifecycle_status', 'Order must be in Awaiting Review status.');
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
            'services.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.services')]),
            'services.min' => __('validation.custom.min', ['attribute' => __('validation.attributes.services'), 'min' => 1]),
            'services.*.order_item_id.required' => __('validation.custom.required', ['attribute' => $this->validationAttribute('services.*.order_item_id')]),
            'services.*.order_item_id.integer' => __('validation.custom.integer', ['attribute' => $this->validationAttribute('services.*.order_item_id')]),
            'services.*.order_item_id.exists' => __('validation.custom.exists', ['attribute' => $this->validationAttribute('services.*.order_item_id')]),
            'services.*.service_key.required' => __('validation.custom.required', ['attribute' => $this->validationAttribute('services.*.service_key')]),
            'services.*.service_key.exists' => __('validation.custom.exists', ['attribute' => $this->validationAttribute('services.*.service_key')]),
        ];
    }
}
