<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;

final class SubmitBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'services' => 'required|array|min:1',
            'services.*.order_item_id' => 'required|exists:order_items,id',
            'services.*.service_key' => 'required|exists:service_catalog,service_key',
            'services.*.measurement' => 'nullable|string|max:50',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $order = $this->route('order');

            if ($order && $order->status !== OrderStatus::AwaitingReview) {
                $validator->errors()->add('status', 'Order must be in Awaiting Review status.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'services.required' => 'At least one service is required.',
            'services.min' => 'At least one service is required.',
            'services.*.order_item_id.required' => 'Each service must be linked to an order item.',
            'services.*.order_item_id.exists' => 'The selected order item does not exist.',
            'services.*.service_key.required' => 'Each service must have a service key.',
            'services.*.service_key.exists' => 'The selected service does not exist in the catalog.',
        ];
    }
}
