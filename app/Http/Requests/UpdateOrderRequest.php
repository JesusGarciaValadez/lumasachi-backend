<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderDispositionStatus;
use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPriority;
use App\Models\Order;
use App\Services\OrderStatusStateMachine;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateOrderRequest extends FormRequest
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
            'customer_id' => 'sometimes|required|exists:users,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'lifecycle_status' => 'sometimes|required|string|in:' . implode(',', OrderLifecycleStatus::getStatuses()),
            'disposition_status' => 'sometimes|required|string|in:' . implode(',', [
                    OrderDispositionStatus::Returned->value,
                    OrderDispositionStatus::Cancelled->value,
                ]),
            'priority' => 'sometimes|required|string|in:'.implode(',', [
                OrderPriority::LOW->value,
                OrderPriority::NORMAL->value,
                OrderPriority::HIGH->value,
                OrderPriority::URGENT->value,
            ]),
            'estimated_completion' => 'nullable|date',
            'actual_completion' => 'nullable|date',
            'notes' => 'nullable|string',
            'assigned_to' => 'sometimes|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'customer_id.exists' => __('validation.custom.exists', ['attribute' => __('validation.attributes.customer_id')]),
            'title.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.title')]),
            'description.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.description')]),
            'lifecycle_status.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.lifecycle_status')]),
            'priority.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.priority')]),
            'assigned_to.exists' => __('validation.custom.exists', ['attribute' => __('validation.attributes.assigned_to')]),
        ];
    }

    /**
     * Validate a status change through the shared state machine when this
     * general update endpoint includes a status.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(OrderStatusStateMachine $stateMachine): array
    {
        return [
            function (Validator $validator) use ($stateMachine): void {
                /** @var Order|null $order */
                $order = $this->route('order');
                $status = $this->input('lifecycle_status');
                $newStatus = is_string($status) ? OrderLifecycleStatus::tryFrom($status) : null;

                if ($this->filled('lifecycle_status')
                    && $order
                    && $newStatus
                    && (!$order->lifecycleStatus() || !$stateMachine->canTransition($order->lifecycleStatus(), $newStatus))) {
                    $validator->errors()->add('lifecycle_status', 'Invalid lifecycle transition.');
                }

                if ($this->filled('disposition_status') && blank($this->input('notes'))) {
                    $validator->errors()->add('notes', 'A note is required when setting a terminal disposition.');
                }

                if ($this->filled('lifecycle_status') && $this->filled('disposition_status')) {
                    $validator->errors()->add('disposition_status', 'Update lifecycle and disposition separately.');
                }

                if ($order?->dispositionStatus() !== null && $this->filled('disposition_status')) {
                    $validator->errors()->add('disposition_status', 'A terminal disposition cannot be changed.');
                }
            },
        ];
    }
}
