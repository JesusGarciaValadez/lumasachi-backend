<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;
use App\Models\OrderHistory;
use App\Models\Order;

class StoreOrderHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', OrderHistory::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'uuid', 'exists:orders,id'],
            'field_changed' => ['required', 'string', 'in:status,priority,title,category_id,assigned_to,estimated_completion,notes'],
            'old_value' => ['nullable', 'string'],
            'new_value' => ['nullable', 'string'],
            'comment' => ['nullable', 'string', 'max:1000'],
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
            'field_changed.required' => 'The field changed is required.',
            'field_changed.in' => 'The field changed must be one of: status, priority, title, category_id, assigned_to, estimated_completion, notes.',
            'comment.max' => 'The comment cannot exceed 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that the user has permission to modify the specific order
            if ($this->order_id) {
                $order = Order::find($this->order_id);
                if ($order && !$this->user()->can('update', $order)) {
                    $validator->errors()->add('order_id', 'You do not have permission to add history to this order.');
                }
            }
        });
    }
}
