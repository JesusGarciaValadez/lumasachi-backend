<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderLifecycleStatus;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class DeliverOrderRequest extends FormRequest
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
        $validator->after(function (Validator $validator) {
            /** @var Order|null $order */
            $order = $this->route('order');

            if ($order && $order->lifecycleStatus() !== OrderLifecycleStatus::ReadyForDelivery) {
                $validator->errors()->add('lifecycle_status', 'Order must be in Ready for Delivery status.');
            }

            if ($order && $order->hasPendingPayment()) {
                $validator->errors()->add('payment', 'Order must be fully paid before delivery.');
            }
        });
    }
}
