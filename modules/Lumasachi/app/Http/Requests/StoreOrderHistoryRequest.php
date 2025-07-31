<?php

namespace Modules\Lumasachi\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;

class StoreOrderHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \Modules\Lumasachi\app\Models\OrderHistory::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'uuid', 'exists:orders,id'],
            'status_from' => ['nullable', Rule::enum(OrderStatus::class)],
            'status_to' => ['nullable', Rule::enum(OrderStatus::class)],
            'priority_from' => ['nullable', Rule::enum(OrderPriority::class)],
            'priority_to' => ['nullable', Rule::enum(OrderPriority::class)],
            'description' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'The order ID is required.',
            'order_id.uuid' => 'The order ID must be a valid UUID.',
            'order_id.exists' => 'The specified order does not exist.',
            'status_from.enum' => 'The status from value is invalid.',
            'status_to.enum' => 'The status to value is invalid.',
            'priority_from.enum' => 'The priority from value is invalid.',
            'priority_to.enum' => 'The priority to value is invalid.',
            'description.required' => 'A description is required for the history entry.',
            'description.max' => 'The description cannot exceed 255 characters.',
            'notes.max' => 'The notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure at least one change is being recorded
            if (!$this->status_from && !$this->status_to && !$this->priority_from && !$this->priority_to) {
                $validator->errors()->add('general', 'At least one status or priority change must be specified.');
            }

            // Validate that the user has permission to modify the specific order
            if ($this->order_id) {
                $order = \Modules\Lumasachi\app\Models\Order::find($this->order_id);
                if ($order && !$this->user()->can('update', $order)) {
                    $validator->errors()->add('order_id', 'You do not have permission to add history to this order.');
                }
            }
        });
    }
}
