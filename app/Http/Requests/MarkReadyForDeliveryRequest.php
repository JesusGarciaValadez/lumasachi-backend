<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderLifecycleStatus;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class MarkReadyForDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Order|null $order */
            $order = $this->route('order');

            if ($order && $order->lifecycleStatus() !== OrderLifecycleStatus::ReadyForWork) {
                $validator->errors()->add('lifecycle_status', __('orders.validation.mark_ready_for_delivery_status'));
            }
        });
    }
}
