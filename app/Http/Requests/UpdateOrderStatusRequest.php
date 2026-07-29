<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderLifecycleStatus;
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
            'lifecycle_status' => [
                'required',
                'string',
                'in:' . implode(',', OrderLifecycleStatus::getStatuses()),
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
            'lifecycle_status.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.lifecycle_status')]),
            'lifecycle_status.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.lifecycle_status')]),
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
                $status = $this->input('lifecycle_status');
                $newStatus = is_string($status) ? OrderLifecycleStatus::tryFrom($status) : null;

                if ($order && $newStatus) {
                    if ($order->dispositionStatus() !== null) {
                        $validator->errors()->add('lifecycle_status', 'A terminal disposition cannot resume the lifecycle.');
                    } elseif (!$order->lifecycleStatus() || !$stateMachine->canTransition($order->lifecycleStatus(), $newStatus)) {
                        $validator->errors()->add('lifecycle_status', 'Invalid lifecycle transition.');
                    }
                }
            },
        ];
    }
}
