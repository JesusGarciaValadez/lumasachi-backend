<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LocaleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(Locale::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locale.required' => __('locale.validation.locale'),
            'locale.in' => __('locale.validation.locale'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'locale' => $this->normalizedLocale(),
        ]);
    }

    private function normalizedLocale(): ?string
    {
        $locale = Locale::normalize($this->input('locale'));

        if ($locale === null || !in_array($locale->value, Locale::values(), true)) {
            return null;
        }

        return $locale->value;
    }
}
