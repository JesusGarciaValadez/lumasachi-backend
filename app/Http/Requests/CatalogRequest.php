<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderItemType;
use App\Enums\UserRole;
use App\Services\LocaleResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && $user->role !== UserRole::CUSTOMER;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'item_type' => ['nullable', Rule::in(OrderItemType::getValues())],
            'locale' => ['nullable', 'string', Rule::in(app(LocaleResolver::class)->supportedLocales())],
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
        $hasExplicitLocale = $this->has('locale');
        $raw = $hasExplicitLocale ? $this->input('locale') : $this->header('Accept-Language');

        if (!is_string($raw) || mb_trim($raw) === '') {
            return;
        }

        if (!$hasExplicitLocale) {
            $raw = explode(';', explode(',', $raw)[0] ?? '')[0] ?? '';
        }

        $normalized = app(LocaleResolver::class)->normalize($raw);

        if ($normalized !== null) {
            $this->merge(['locale' => $normalized]);
        } elseif ($hasExplicitLocale) {
            $this->merge(['locale' => $raw]);
        }
    }
}
