<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderPriority;
use App\Enums\OrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'status' => 'sometimes|required|string|in:'.implode(',', OrderStatus::getStatuses()),
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
            'status.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.status')]),
            'priority.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.priority')]),
            'assigned_to.exists' => __('validation.custom.exists', ['attribute' => __('validation.attributes.assigned_to')]),
        ];
    }
}
