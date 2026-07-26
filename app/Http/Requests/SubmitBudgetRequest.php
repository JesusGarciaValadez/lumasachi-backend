<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Models\Order;
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
                Rule::exists('order_items', 'id')->where(function (Builder $query): void {
                    $order = $this->route('order');

                    if ($order instanceof Order) {
                        $query->where('order_id', $order->getKey());
                    }
                }),
            ],
            'services.*.service_key' => 'required|exists:service_catalog,service_key',
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
            'services.*.order_item_id.exists' => 'The selected order item does not exist.',
            'services.*.service_key.required' => 'Each service must have a service key.',
            'services.*.service_key.exists' => 'The selected service does not exist in the catalog.',
        ];
    }
}
