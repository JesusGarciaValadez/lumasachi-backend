<?php

namespace Modules\Lumasachi\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Lumasachi\app\Models\Order;

class UpdateOrderRequest extends FormRequest
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
            'customer_id' => 'sometimes|required|exists:users,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'status' => 'sometimes|required|string|in:' . implode(',', [
                Order::STATUS_OPEN,
                Order::STATUS_IN_PROGRESS,
                Order::STATUS_READY_FOR_DELIVERY,
                Order::STATUS_DELIVERED,
                Order::STATUS_PAID,
                Order::STATUS_RETURNED,
                Order::STATUS_NOT_PAID,
                Order::STATUS_CANCELLED
            ]),
            'priority' => 'sometimes|required|string|in:' . implode(',', [
                Order::PRIORITY_LOW,
                Order::PRIORITY_NORMAL,
                Order::PRIORITY_HIGH,
                Order::PRIORITY_URGENT
            ]),
            'category' => 'sometimes|required|string|max:100',
            'estimated_completion' => 'nullable|date',
            'actual_completion' => 'nullable|date',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id'
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
            'customer_id.exists' => 'The selected customer does not exist.',
            'title.required' => 'The order title is required.',
            'description.required' => 'The order description is required.',
            'status.in' => 'The selected status is invalid.',
            'priority.in' => 'The selected priority is invalid.',
            'category.required' => 'The order category is required.',
            'assigned_to.exists' => 'The selected employee does not exist.'
        ];
    }
}
