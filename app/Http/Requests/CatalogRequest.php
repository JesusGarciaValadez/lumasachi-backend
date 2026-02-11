<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderItemType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->role !== UserRole::CUSTOMER;
    }

    public function rules(): array
    {
        return [
            'item_type' => ['nullable', Rule::in(OrderItemType::getValues())],
            'locale' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_type.in' => 'The selected item type is invalid.',
            'locale.string' => 'The locale must be a valid string.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasHeader('Accept-Language') && ! $this->has('locale')) {
            $raw = $this->header('Accept-Language');

            // Take the first language tag and drop quality values, normalizing case/underscores
            $first = explode(',', $raw)[0] ?? '';
            $first = explode(';', $first)[0] ?? '';
            $normalized = str_replace('_', '-', mb_strtolower(mb_trim($first)));

            if ($normalized !== '') {
                $this->merge(['locale' => $normalized]);
            }
        }
    }
}
