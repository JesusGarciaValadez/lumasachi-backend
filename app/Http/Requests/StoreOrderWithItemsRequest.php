<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderItemType;
use App\Enums\OrderPriority;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderParticipantQuery;
use App\Traits\TranslatesValidationAttributes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Stringable;

final class StoreOrderWithItemsRequest extends FormRequest
{
    use TranslatesValidationAttributes;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(OrderParticipantQuery $participantQuery): array
    {
        $actor = $this->user();
        $isSuperAdministrator = $actor instanceof User && $actor->isSuperAdministrator();
        $requestedCompanyId = $isSuperAdministrator && $this->has('company_id')
            ? (int) $this->input('company_id')
            : null;
        $companyId = $actor instanceof User
            ? $participantQuery->effectiveCompanyId($actor, $requestedCompanyId)
            : null;

        return [
            'company_id' => $isSuperAdministrator
                ? ['required', 'integer', Rule::exists(Company::class, 'id')->where('is_active', true)]
                : ['prohibited'],
            'customer_id' => $this->participantRules($companyId, UserRole::CUSTOMER),
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => ['required', 'string', Rule::in(array_column(OrderPriority::cases(), 'value'))],
            'assigned_to' => $this->participantRules($companyId, UserRole::EMPLOYEE),
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

                if (! is_array($items)) {
                    return;
                }

                $itemTypeCounts = array_count_values(array_filter(
                    array_map(
                        static fn (mixed $item): mixed => is_array($item) ? ($item['item_type'] ?? null) : null,
                        $items
                    ),
                    static fn (mixed $itemType): bool => is_string($itemType)
                ));

                foreach ($items as $itemIndex => $item) {
                    if (! is_array($item) || ! is_string($item['item_type'] ?? null)) {
                        continue;
                    }

                    $itemType = OrderItemType::tryFrom($item['item_type']);
                    $components = $item['components'] ?? [];

                    if (($itemTypeCounts[$item['item_type']] ?? 0) > 1) {
                        $validator->errors()->add(
                            "items.{$itemIndex}.item_type",
                            __('validation.custom.distinct', ['attribute' => $this->validationAttribute('items.*.item_type')])
                        );
                    }

                    if ($itemType === null || ! is_array($components)) {
                        continue;
                    }

                    $componentCounts = array_count_values(array_filter(
                        $components,
                        static fn (mixed $component): bool => is_string($component)
                    ));

                    foreach ($components as $componentIndex => $component) {
                        if (! is_string($component) || in_array($component, $itemType->getComponents(), true)) {
                            if (is_string($component) && ($componentCounts[$component] ?? 0) > 1) {
                                $validator->errors()->add(
                                    "items.{$itemIndex}.components.{$componentIndex}",
                                    __('validation.custom.distinct', ['attribute' => $this->validationAttribute('items.*.components.*')])
                                );
                            }

                            continue;
                        }

                        $validator->errors()->add(
                            "items.{$itemIndex}.components.{$componentIndex}",
                            'The selected component is not valid for the selected item type.'
                        );

                        if (($componentCounts[$component] ?? 0) > 1) {
                            $validator->errors()->add(
                                "items.{$itemIndex}.components.{$componentIndex}",
                                __('validation.custom.distinct', ['attribute' => $this->validationAttribute('items.*.components.*')])
                            );
                        }
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
            'company_id.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.company_id')]),
            'company_id.integer' => __('validation.custom.integer', ['attribute' => __('validation.attributes.company_id')]),
            'company_id.exists' => __('validation.custom.exists', ['attribute' => __('validation.attributes.company_id')]),
            'company_id.prohibited' => __('validation.custom.in', ['attribute' => __('validation.attributes.company_id')]),
            'customer_id.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.customer_id')]),
            'customer_id.integer' => __('validation.custom.integer', ['attribute' => __('validation.attributes.customer_id')]),
            'customer_id.exists' => __('validation.custom.exists', ['attribute' => __('validation.attributes.customer_id')]),
            'customer_id.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.customer_id')]),
            'title.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.title')]),
            'title.max' => __('validation.custom.max', ['attribute' => __('validation.attributes.title'), 'max' => 255]),
            'description.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.description')]),
            'priority.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.priority')]),
            'priority.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.priority')]),
            'assigned_to.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.assigned_to')]),
            'assigned_to.integer' => __('validation.custom.integer', ['attribute' => __('validation.attributes.assigned_to')]),
            'assigned_to.exists' => __('validation.custom.exists', ['attribute' => __('validation.attributes.assigned_to')]),
            'assigned_to.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.assigned_to')]),
            'estimated_completion.date' => __('validation.custom.date', ['attribute' => __('validation.attributes.estimated_completion')]),
            'estimated_completion.after' => __('validation.custom.after', ['attribute' => __('validation.attributes.estimated_completion'), 'date' => 'now']),
            'motor_info.brand.max' => __('validation.custom.max', ['attribute' => $this->validationAttribute('motor_info.brand'), 'max' => 100]),
            'motor_info.liters.max' => __('validation.custom.max', ['attribute' => $this->validationAttribute('motor_info.liters'), 'max' => 20]),
            'motor_info.year.max' => __('validation.custom.max', ['attribute' => $this->validationAttribute('motor_info.year'), 'max' => 10]),
            'motor_info.model.max' => __('validation.custom.max', ['attribute' => $this->validationAttribute('motor_info.model'), 'max' => 100]),
            'motor_info.cylinder_count.max' => __('validation.custom.max', ['attribute' => $this->validationAttribute('motor_info.cylinder_count'), 'max' => 20]),
            'motor_info.down_payment.numeric' => __('validation.custom.numeric', ['attribute' => $this->validationAttribute('motor_info.down_payment')]),
            'motor_info.down_payment.min' => __('validation.custom.min', ['attribute' => $this->validationAttribute('motor_info.down_payment'), 'min' => 0]),
            'items.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.items')]),
            'items.min' => __('validation.custom.min', ['attribute' => __('validation.attributes.items'), 'min' => 1]),
            'items.*.item_type.required' => __('validation.custom.required', ['attribute' => $this->validationAttribute('items.*.item_type')]),
            'items.*.item_type.in' => __('validation.custom.in', ['attribute' => $this->validationAttribute('items.*.item_type')]),
            'items.*.components.*.string' => __('validation.custom.string', ['attribute' => $this->validationAttribute('items.*.components.*')]),
            'items.*.components.*.max' => __('validation.custom.max', ['attribute' => $this->validationAttribute('items.*.components.*'), 'max' => 100]),
        ];
    }

    /**
     * @return array<int, Stringable|string>
     */
    private function participantRules(?int $companyId, UserRole $role): array
    {
        if ($companyId === null) {
            return ['required', 'integer', Rule::in([])];
        }

        return [
            'required',
            'integer',
            Rule::exists(User::class, 'id')
                ->where('company_id', $companyId)
                ->where('role', $role->value)
                ->where('is_active', true),
        ];
    }
}
