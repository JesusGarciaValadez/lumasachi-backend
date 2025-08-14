<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\OrderStatus;
use App\Enums\OrderPriority;

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
                OrderStatus::OPEN->value,
                OrderStatus::IN_PROGRESS->value,
                OrderStatus::READY_FOR_DELIVERY->value,
                OrderStatus::DELIVERED->value,
                OrderStatus::PAID->value,
                OrderStatus::RETURNED->value,
                OrderStatus::NOT_PAID->value,
                OrderStatus::CANCELLED->value
            ]),
            'priority' => 'sometimes|required|string|in:' . implode(',', [
                OrderPriority::LOW->value,
                OrderPriority::NORMAL->value,
                OrderPriority::HIGH->value,
                OrderPriority::URGENT->value
            ]),
            'category_id' => 'sometimes|required|exists:categories,id',
            'estimated_completion' => 'nullable|date',
            'actual_completion' => 'nullable|date',
            'notes' => 'nullable|string',
            'assigned_to' => 'sometimes|exists:users,id'
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
            'category_id.required' => 'The order category is required.',
            'category_id.exists' => 'The selected category does not exist.',
            'assigned_to.exists' => 'The selected employee does not exist.'
        ];
    }
}
