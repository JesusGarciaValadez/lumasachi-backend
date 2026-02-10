<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderItemType;
use App\Enums\OrderPriority;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreOrderWithItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware/policy
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => ['required', 'string', Rule::in(array_column(OrderPriority::cases(), 'value'))],
            'assigned_to' => 'required|exists:users,id',
            'categories' => 'sometimes|array',
            'categories.*' => [
                'integer',
                'distinct',
                Rule::exists('categories', 'id')->where(function ($q) {
                    $companyId = optional($this->user())->company_id ?? null;
                    if ($companyId) {
                        $q->whereIn('created_by', User::query()
                            ->where('company_id', $companyId)
                            ->select('id'));
                    }
                }),
            ],
            'estimated_completion' => 'nullable|date|after:today',
            'notes' => 'nullable|string',

            // Motor info (all nullable — order can have partial info)
            'motor_info' => 'sometimes|array',
            'motor_info.brand' => 'nullable|string|max:100',
            'motor_info.liters' => 'nullable|string|max:20',
            'motor_info.year' => 'nullable|string|max:10',
            'motor_info.model' => 'nullable|string|max:100',
            'motor_info.cylinder_count' => 'nullable|string|max:20',
            'motor_info.down_payment' => 'nullable|numeric|min:0',

            // Items (at least one required)
            'items' => 'required|array|min:1',
            'items.*.item_type' => ['required', Rule::in(OrderItemType::getValues())],
            'items.*.components' => 'sometimes|array',
            'items.*.components.*' => 'string|max:100',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'The customer is required.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'title.required' => 'The order title is required.',
            'description.required' => 'The order description is required.',
            'priority.required' => 'The order priority is required.',
            'priority.in' => 'The selected priority is invalid.',
            'assigned_to.required' => 'An assigned employee is required.',
            'assigned_to.exists' => 'The selected employee does not exist.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.item_type.required' => 'Each item must have a type.',
            'items.*.item_type.in' => 'Invalid item type.',
        ];
    }
}
