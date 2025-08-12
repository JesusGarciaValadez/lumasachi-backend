<?php

namespace Modules\Lumasachi\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Enums\OrderStatus;
use Modules\Lumasachi\app\Enums\OrderPriority;

class StoreOrderRequest extends FormRequest
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
            'customer_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => 'required|string|in:' . implode(',', [
                OrderStatus::OPEN->value,
                OrderStatus::IN_PROGRESS->value,
                OrderStatus::READY_FOR_DELIVERY->value,
                OrderStatus::DELIVERED->value,
                OrderStatus::COMPLETED->value,
                OrderStatus::PAID->value,
                OrderStatus::RETURNED->value,
                OrderStatus::NOT_PAID->value,
                OrderStatus::ON_HOLD->value,
                OrderStatus::CANCELLED->value
            ]),
            'priority' => 'required|string|in:' . implode(',', [
                OrderPriority::LOW->value,
                OrderPriority::NORMAL->value,
                OrderPriority::HIGH->value,
                OrderPriority::URGENT->value
            ]),
            'category_id' => 'required|exists:categories,id',
            'estimated_completion' => 'nullable|date|after:today',
            'actual_completion' => 'nullable|date',
            'notes' => 'nullable|string',
            'assigned_to' => 'required|exists:users,id'
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
            'customer_id.required' => 'The customer is required.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'title.required' => 'The order title is required.',
            'description.required' => 'The order description is required.',
            'status.required' => 'The order status is required.',
            'status.in' => 'The selected status is invalid.',
            'priority.required' => 'The order priority is required.',
            'priority.in' => 'The selected priority is invalid.',
            'category_id.required' => 'The order category is required.',
            'category_id.exists' => 'The selected category does not exist.',
            'estimated_completion.after' => 'The estimated completion date must be in the future.',
            'assigned_to.exists' => 'The selected employee does not exist.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge([
                'status' => OrderStatus::OPEN->value
            ]);
        }

        // Set default priority if not provided
        if (!$this->has('priority')) {
            $this->merge([
                'priority' => OrderPriority::NORMAL->value
            ]);
        }
    }
}
