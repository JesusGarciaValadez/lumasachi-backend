<?php

declare(strict_types=1);

namespace App\Http\Requests\UserAdministration;

use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $actor = $this->user();
        $isSuperAdministrator = $actor instanceof User && $actor->isSuperAdministrator();
        $roles = $isSuperAdministrator
            ? array_column(UserRole::cases(), 'value')
            : UserRole::administrationValues(UserRole::ADMINISTRATOR);

        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'nullable', Rule::in($roles)],
            'active' => ['required', Rule::in(['1', '0', 'all'])],
            'type' => ['sometimes', 'nullable', Rule::enum(UserType::class)],
            'company_id' => $isSuperAdministrator
                ? ['sometimes', 'nullable', 'integer', 'exists:companies,id']
                : ['prohibited'],
            'per_page' => ['required', Rule::in(['10', '20', '50', 10, 20, 50])],
        ];
    }

    /**
     * Apply the documented defaults before validation.
     */
    protected function prepareForValidation(): void
    {
        $defaults = [];

        if ($this->input('active') === null || $this->input('active') === '') {
            $defaults['active'] = '1';
        }

        if ($this->input('per_page') === null || $this->input('per_page') === '') {
            $defaults['per_page'] = '10';
        }

        if ($defaults !== []) {
            $this->merge($defaults);
        }
    }
}
