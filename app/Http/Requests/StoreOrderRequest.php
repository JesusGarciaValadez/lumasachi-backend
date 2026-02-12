<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;

final class StoreOrderRequest extends FormRequest
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
            'status' => 'required|string|in:'.implode(',', OrderStatus::getStatuses()),
            'priority' => 'required|string|in:'.implode(',', [
                OrderPriority::LOW->value,
                OrderPriority::NORMAL->value,
                OrderPriority::HIGH->value,
                OrderPriority::URGENT->value,
            ]),
            'estimated_completion' => 'nullable|date|after:today',
            'actual_completion' => 'nullable|date',
            'notes' => 'nullable|string',
            'assigned_to' => 'required|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
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
            'estimated_completion.after' => 'The estimated completion date must be in the future.',
            'assigned_to.exists' => 'The selected employee does not exist.',
        ];
    }
}
