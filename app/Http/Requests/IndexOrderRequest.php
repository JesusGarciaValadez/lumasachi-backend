<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderDispositionStatus;
use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderPriority;
use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Order::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_id' => ['sometimes', 'nullable', 'integer', 'exists:companies,id'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', 'nullable', Rule::enum(OrderPriority::class)],
            'lifecycle_status' => ['sometimes', 'nullable', Rule::enum(OrderLifecycleStatus::class)],
            'payment_status' => ['sometimes', 'nullable', Rule::enum(OrderPaymentStatus::class)],
            'disposition_status' => ['sometimes', 'nullable', Rule::enum(OrderDispositionStatus::class)],
            'created_from' => ['sometimes', 'nullable', 'date'],
            'created_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:created_from'],
            'per_page' => ['required', Rule::in(['10', '20', '50', 10, 20, 50])],
        ];
    }

    /**
     * Apply the documented default page size before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('per_page') === null || $this->input('per_page') === '') {
            $this->merge(['per_page' => '10']);
        }
    }
}
