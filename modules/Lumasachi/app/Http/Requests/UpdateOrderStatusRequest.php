<?php

namespace Modules\Lumasachi\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Lumasachi\app\Enums\OrderStatus;

class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                'in:' . implode(',', [
                    OrderStatus::OPEN->value,
                    OrderStatus::IN_PROGRESS->value,
                    OrderStatus::READY_FOR_DELIVERY->value,
                    OrderStatus::DELIVERED->value,
                    OrderStatus::PAID->value,
                    OrderStatus::RETURNED->value,
                    OrderStatus::NOT_PAID->value,
                    OrderStatus::CANCELLED->value
                ])
            ],
            'notes' => 'nullable|string|max:500'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'status.required' => 'The new status is required.',
            'status.in' => 'The selected status is invalid.',
            'notes.max' => 'The notes cannot exceed 500 characters.'
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $order = $this->route('order');
            $newStatus = $this->status;

            // Validate status transitions
            if ($order && $newStatus) {
                if (!$this->isValidStatusTransition($order->status, $newStatus)) {
                    $validator->errors()->add('status',
                        "Cannot transition from {$order->status} to {$newStatus}."
                    );
                }
            }
        });
    }

    /**
     * Check if the status transition is valid
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    private function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        // Define valid transitions
        $validTransitions = [
            OrderStatus::OPEN->value => [
                OrderStatus::IN_PROGRESS->value,
                OrderStatus::CANCELLED->value
            ],
            OrderStatus::IN_PROGRESS->value => [
                OrderStatus::READY_FOR_DELIVERY->value,
                OrderStatus::CANCELLED->value
            ],
            OrderStatus::READY_FOR_DELIVERY->value => [
                OrderStatus::DELIVERED->value,
                OrderStatus::CANCELLED->value
            ],
            OrderStatus::DELIVERED->value => [
                OrderStatus::PAID->value,
                OrderStatus::RETURNED->value,
                OrderStatus::NOT_PAID->value
            ],
            OrderStatus::RETURNED->value => [
                OrderStatus::CANCELLED->value
            ],
            OrderStatus::NOT_PAID->value => [
                OrderStatus::PAID->value,
                OrderStatus::CANCELLED->value
            ]
        ];

        // Cannot transition from final states
        if (in_array($currentStatus, [OrderStatus::PAID->value, OrderStatus::CANCELLED->value])) {
            return false;
        }

        return isset($validTransitions[$currentStatus]) &&
               in_array($newStatus, $validTransitions[$currentStatus]);
    }
}
