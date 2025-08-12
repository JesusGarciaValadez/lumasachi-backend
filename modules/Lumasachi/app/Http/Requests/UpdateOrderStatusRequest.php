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
                    OrderStatus::CANCELLED->value,
                    OrderStatus::ON_HOLD->value,
                    OrderStatus::COMPLETED->value,
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
            $newStatus = OrderStatus::tryFrom($this->status);

            if ($order && $newStatus) {
                // Check if the status transition is valid
                if (!$this->isValidStatusTransition($order->status, $newStatus)) {
                    $validator->errors()->add('status', 'Invalid status transition.');
                }

                // Check for completion date if status is COMPLETED
                if ($newStatus->value === OrderStatus::COMPLETED->value && empty($this->actual_completion)) {
                    $validator->errors()->add('actual_completion', 'The actual completion date is required when the status is completed.');
                }
            }
        });
    }

    /**
     * Checks if the transition from the current status to the new status is valid.
     *
     * @param OrderStatus $currentStatus
     * @param OrderStatus $newStatus
     * @return bool
     */
    protected function isValidStatusTransition(OrderStatus $currentStatus, OrderStatus $newStatus)
    {
        $allowedTransitions = [
            OrderStatus::OPEN->value => [OrderStatus::IN_PROGRESS, OrderStatus::CANCELLED, OrderStatus::ON_HOLD],
            OrderStatus::IN_PROGRESS->value => [OrderStatus::READY_FOR_DELIVERY, OrderStatus::COMPLETED, OrderStatus::CANCELLED, OrderStatus::ON_HOLD],
            OrderStatus::ON_HOLD->value => [OrderStatus::IN_PROGRESS, OrderStatus::CANCELLED],
            OrderStatus::READY_FOR_DELIVERY->value => [OrderStatus::DELIVERED, OrderStatus::CANCELLED],
            OrderStatus::DELIVERED->value => [OrderStatus::PAID, OrderStatus::RETURNED, OrderStatus::NOT_PAID],
            OrderStatus::PAID->value => [],
            OrderStatus::RETURNED->value => [OrderStatus::CANCELLED],
            OrderStatus::NOT_PAID->value => [OrderStatus::PAID, OrderStatus::CANCELLED],
            OrderStatus::CANCELLED->value => [],
            OrderStatus::COMPLETED->value => [],
        ];

        return in_array($newStatus, $allowedTransitions[$currentStatus->value] ?? []);
    }
}
