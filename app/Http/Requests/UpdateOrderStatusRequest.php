<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderStatusStateMachine;
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
     * Configure post-validation transition checks.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(OrderStatusStateMachine $stateMachine): array
    {
        return [
            function (Validator $validator) use ($stateMachine): void {
                /** @var Order|null $order */
                $order = $this->route('order');
                $status = $this->input('status');
                $newStatus = is_string($status) ? OrderStatus::tryFrom($status) : null;

                if ($order && $newStatus) {
                    if (!$stateMachine->canTransition($order->status, $newStatus)) {
                        $validator->errors()->add('status', 'Invalid status transition.');
                    }

                    if ($newStatus === OrderStatus::Completed && empty($this->actual_completion)) {
                        $validator->errors()->add('actual_completion', 'The actual completion date is required when the status is completed.');
                    }
                }
            },
        ];
    }
}
