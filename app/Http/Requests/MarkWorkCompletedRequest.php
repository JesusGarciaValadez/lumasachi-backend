<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class MarkWorkCompletedRequest extends FormRequest
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
            'completed_service_ids' => 'required|array|min:1',
            'completed_service_ids.*' => 'exists:order_services,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $order = $this->route('order');

            if ($order && ! in_array($order->status, [OrderStatus::ReadyForWork, OrderStatus::InProgress], true)) {
                $validator->errors()->add('status', 'Order must be in Ready for Work or In Progress status.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'completed_service_ids.required' => 'At least one service must be marked as completed.',
            'completed_service_ids.min' => 'At least one service must be marked as completed.',
            'completed_service_ids.*.exists' => 'One or more selected services do not exist.',
        ];
    }
}
