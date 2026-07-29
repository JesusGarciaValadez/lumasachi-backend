<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPriority;
use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'lifecycle_status' => 'required|string|in:' . implode(',', OrderLifecycleStatus::getStatuses()),
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
            'customer_id.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.customer_id')]),
            'customer_id.exists' => __('validation.custom.exists', ['attribute' => __('validation.attributes.customer_id')]),
            'title.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.title')]),
            'description.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.description')]),
            'lifecycle_status.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.status')]),
            'lifecycle_status.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.status')]),
            'priority.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.priority')]),
            'priority.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.priority')]),
            'estimated_completion.after' => __('validation.custom.after', ['attribute' => __('validation.attributes.estimated_completion'), 'date' => 'now']),
            'assigned_to.exists' => __('validation.custom.exists', ['attribute' => __('validation.attributes.assigned_to')]),
        ];
    }
}
