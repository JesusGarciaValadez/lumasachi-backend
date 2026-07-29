<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderLifecycleStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CustomerApprovalRequest extends FormRequest
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
            'authorized_service_ids' => 'required|array|min:1',
            'authorized_service_ids.*' => [
                'distinct',
                'integer',
                Rule::exists('order_services', 'id')->where(function (Builder $query): void {
                    $order = $this->route('order');

                    if ($order instanceof Order) {
                        $query->whereIn(
                            'order_item_id',
                            OrderItem::query()->select('id')->where('order_id', $order->getKey())
                        )->where('is_budgeted', true);
                    }
                }),
            ],
            'down_payment' => 'nullable|numeric|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Order|null $order */
            $order = $this->route('order');

            if ($order && $order->lifecycleStatus() !== OrderLifecycleStatus::AwaitingCustomerApproval) {
                $validator->errors()->add('lifecycle_status', 'Order must be in Awaiting Customer Approval status.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'authorized_service_ids.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.authorized_service_ids')]),
            'authorized_service_ids.min' => __('validation.custom.min', ['attribute' => __('validation.attributes.authorized_service_ids'), 'min' => 1]),
            'authorized_service_ids.*.integer' => __('validation.custom.integer', ['attribute' => __('validation.attributes.authorized_service_ids')]),
            'authorized_service_ids.*.exists' => __('validation.custom.exists', ['attribute' => __('validation.attributes.authorized_service_ids')]),
            'down_payment.numeric' => __('validation.custom.numeric', ['attribute' => __('validation.attributes.down_payment')]),
            'down_payment.min' => __('validation.custom.min', ['attribute' => __('validation.attributes.down_payment'), 'min' => 0]),
        ];
    }
}
