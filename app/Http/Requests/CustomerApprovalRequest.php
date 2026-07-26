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
                Rule::exists('order_services', 'id')->where(function (Builder $query): void {
                    $order = $this->route('order');

                    if ($order instanceof Order) {
                        $query->whereIn(
                            'order_item_id',
                            OrderItem::query()->select('id')->where('order_id', $order->getKey())
                        );
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

            if ($order && $order->status !== OrderStatus::AwaitingCustomerApproval) {
                $validator->errors()->add('status', 'Order must be in Awaiting Customer Approval status.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'authorized_service_ids.required' => 'At least one service must be approved.',
            'authorized_service_ids.min' => 'At least one service must be approved.',
            'authorized_service_ids.*.exists' => 'One or more selected services do not exist.',
            'down_payment.numeric' => 'The down payment must be a number.',
            'down_payment.min' => 'The down payment cannot be negative.',
        ];
    }
}
