<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateOrderStatusRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                'in:'.implode(',', OrderStatus::getStatuses()),
            ],
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.status')]),
            'status.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.status')]),
            'notes.max' => __('validation.custom.max', ['attribute' => __('validation.attributes.notes'), 'max' => 500]),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Order|null $order */
            $order = $this->route('order');
            $newStatus = OrderStatus::tryFrom($this->status);

            if ($order && $newStatus) {
                // Check if the status transition is valid
                if (! $this->isValidStatusTransition($order->status, $newStatus)) {
                    $validator->errors()->add('status', 'Invalid status transition.');
                }

                // Check for completion date if status is COMPLETED
                if ($newStatus->value === OrderStatus::Completed->value && empty($this->actual_completion)) {
                    $validator->errors()->add('actual_completion', 'The actual completion date is required when the status is completed.');
                }
            }
        });
    }

    /**
     * Checks if the transition from the current status to the new status is valid.
     */
    protected function isValidStatusTransition(OrderStatus $currentStatus, OrderStatus $newStatus): bool
    {
        $allowedTransitions = [
            OrderStatus::Received->value => [OrderStatus::AwaitingReview, OrderStatus::Cancelled],
            OrderStatus::AwaitingReview->value => [OrderStatus::Reviewed, OrderStatus::Cancelled],
            OrderStatus::Reviewed->value => [OrderStatus::AwaitingCustomerApproval, OrderStatus::Cancelled],
            OrderStatus::AwaitingCustomerApproval->value => [OrderStatus::ReadyForWork, OrderStatus::Cancelled],
            OrderStatus::ReadyForWork->value => [OrderStatus::InProgress, OrderStatus::Cancelled],
            OrderStatus::Open->value => [OrderStatus::InProgress, OrderStatus::Cancelled, OrderStatus::OnHold],
            OrderStatus::InProgress->value => [OrderStatus::ReadyForDelivery, OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::OnHold],
            OrderStatus::OnHold->value => [OrderStatus::InProgress, OrderStatus::Cancelled],
            OrderStatus::ReadyForDelivery->value => [OrderStatus::Delivered, OrderStatus::Cancelled],
            OrderStatus::Delivered->value => [OrderStatus::Paid, OrderStatus::Returned, OrderStatus::NotPaid],
            OrderStatus::Paid->value => [],
            OrderStatus::Returned->value => [OrderStatus::Cancelled],
            OrderStatus::NotPaid->value => [OrderStatus::Paid, OrderStatus::Cancelled],
            OrderStatus::Cancelled->value => [],
            OrderStatus::Completed->value => [],
        ];

        return in_array($newStatus, $allowedTransitions[$currentStatus->value] ?? []);
    }
}
