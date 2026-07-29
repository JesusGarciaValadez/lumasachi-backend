<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreOrderRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'reason' => ['required', 'string'],
            'source_payment_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('order_payments', 'uuid')->where(function (Builder $query): void {
                    $order = $this->route('order');

                    if ($order instanceof Order) {
                        $query->where('order_id', $order->getKey());
                    }
                }),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Order|null $order */
            $order = $this->route('order');

            if ($order && $order->dispositionStatus() === null) {
                $validator->errors()->add('status', 'Refunds can only be requested for returned or cancelled orders.');
            }
        });
    }
}
