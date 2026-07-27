<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\OrderHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class StoreOrderHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', OrderHistory::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'exists:orders,id'],
            'field_changed' => ['required', 'string', 'in:status,priority,title,assigned_to,estimated_completion,notes'],
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
            'order_id.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.order_id')]),
            'order_id.exists' => __('validation.custom.exists', ['attribute' => __('validation.attributes.order_id')]),
            'field_changed.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.field_changed')]),
            'field_changed.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.field_changed')]),
            'comment.max' => __('validation.custom.max', ['attribute' => __('validation.attributes.comment'), 'max' => 1000]),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Validate that the user has permission to modify the specific order
            if ($this->order_id) {
                $order = Order::find($this->order_id);
                $user = $this->user();
                if ($order && $user !== null && !$user->can('update', $order)) {
                    $validator->errors()->add('order_id', 'You do not have permission to add history to this order.');
                }
            }
        });
    }
}
