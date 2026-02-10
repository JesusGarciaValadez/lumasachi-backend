<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;

final class CustomerApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'authorized_service_ids' => 'required|array|min:1',
            'authorized_service_ids.*' => 'exists:order_services,id',
            'down_payment' => 'nullable|numeric|min:0',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $order = $this->route('order');

            if ($order && $order->status !== OrderStatus::AWAITING_CUSTOMER_APPROVAL) {
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
