<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderItemType;
use App\Enums\OrderPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreOrderWithItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware/policy
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => ['required', 'string', Rule::in(array_column(OrderPriority::cases(), 'value'))],
            'assigned_to' => 'required|exists:users,id',
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
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $items = $this->input('items', []);

                if (!is_array($items)) {
                    return;
                }

                foreach ($items as $itemIndex => $item) {
                    if (!is_array($item) || !is_string($item['item_type'] ?? null)) {
                        continue;
                    }

                    $itemType = OrderItemType::tryFrom($item['item_type']);
                    $components = $item['components'] ?? [];

                    if ($itemType === null || !is_array($components)) {
                        continue;
                    }

                    foreach ($components as $componentIndex => $component) {
                        if (!is_string($component) || in_array($component, $itemType->getComponents(), true)) {
                            continue;
                        }

                        $validator->errors()->add(
                            "items.{$itemIndex}.components.{$componentIndex}",
                            'The selected component is not valid for the selected item type.'
                        );
                    }
                }
            },
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
            'title.max' => 'The order title must not exceed 255 characters.',
            'description.required' => 'The order description is required.',
            'priority.required' => 'The order priority is required.',
            'priority.in' => 'The selected priority is invalid.',
            'assigned_to.required' => 'An assigned employee is required.',
            'assigned_to.exists' => 'The selected employee does not exist.',
            'estimated_completion.date' => 'The estimated completion must be a valid date.',
            'estimated_completion.after' => 'The estimated completion date must be in the future.',
            'motor_info.brand.max' => 'The motor brand must not exceed 100 characters.',
            'motor_info.liters.max' => 'The motor liters must not exceed 20 characters.',
            'motor_info.year.max' => 'The motor year must not exceed 10 characters.',
            'motor_info.model.max' => 'The motor model must not exceed 100 characters.',
            'motor_info.cylinder_count.max' => 'The cylinder count must not exceed 20 characters.',
            'motor_info.down_payment.numeric' => 'The down payment must be a number.',
            'motor_info.down_payment.min' => 'The down payment cannot be negative.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.item_type.required' => 'Each item must have a type.',
            'items.*.item_type.in' => 'Invalid item type.',
            'items.*.components.*.string' => 'Each component must be a string.',
            'items.*.components.*.max' => 'Each component must not exceed 100 characters.',
        ];
    }
}
