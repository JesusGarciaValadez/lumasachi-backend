<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class MarkWorkCompletedRequest extends FormRequest
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
            'completed_service_ids' => 'required|array|min:1',
            'completed_service_ids.*' => [
                'distinct',
                'integer',
                Rule::exists('order_services', 'id')->where(function (Builder $query): void {
                    $order = $this->route('order');

                    if ($order instanceof Order) {
                        $query->whereIn(
                            'order_item_id',
                            OrderItem::query()->select('id')->where('order_id', $order->getKey())
                        )->where('is_authorized', true)
                            ->where('is_completed', false);
                    }
                }),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Order|null $order */
            $order = $this->route('order');

            if ($order && ! in_array($order->status, [OrderStatus::ReadyForWork, OrderStatus::InProgress], true)) {
                $validator->errors()->add('status', 'Order must be in Ready for Work or In Progress status.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'completed_service_ids.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.completed_service_ids')]),
            'completed_service_ids.min' => __('validation.custom.min', ['attribute' => __('validation.attributes.completed_service_ids'), 'min' => 1]),
            'completed_service_ids.*.integer' => __('validation.custom.integer', ['attribute' => __('validation.attributes.completed_service_ids')]),
            'completed_service_ids.*.exists' => __('validation.custom.exists', ['attribute' => __('validation.attributes.completed_service_ids')]),
        ];
    }
}
