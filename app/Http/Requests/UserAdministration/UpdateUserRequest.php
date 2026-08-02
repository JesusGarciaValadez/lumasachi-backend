<?php

declare(strict_types=1);

namespace App\Http\Requests\UserAdministration;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

final class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && ($this->user()?->can('updateAdministration', $target) ?? false);
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

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'role' => [
                'required',
                Rule::in(UserRole::administrationValues($isSuperAdministrator ? UserRole::SUPER_ADMINISTRATOR : UserRole::ADMINISTRATOR)),
            ],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'type' => ['required', Rule::enum(UserType::class)],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(Locale::values())],
            'company_id' => $isSuperAdministrator
                ? ['sometimes', 'nullable', 'integer', 'exists:companies,id']
                : ['prohibited'],
            'is_active' => $isSuperAdministrator
                ? ['required', 'boolean']
                : ['prohibited'],
            'password' => $isSuperAdministrator
                ? ['sometimes', 'nullable', 'confirmed', Password::defaults()]
                : ['prohibited'],
            'password_confirmation' => $isSuperAdministrator
                ? ['sometimes', 'nullable', 'string']
                : ['prohibited'],
            'uuid' => ['prohibited'],
            'activated_at' => ['prohibited'],
            'preferences' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'remember_token' => ['prohibited'],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    /**
     * Get custom validation messages for administration fields.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.prohibited' => __('users.validation.forbidden_field'),
        ];
    }

    /**
     * Reject self-role changes for Administrators after normal field validation.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();
                $target = $this->route('user');

                if (!$actor instanceof User || !$target instanceof User) {
                    return;
                }

                if ($actor->isAdministrator() && $actor->is($target) && $this->filled('role')) {
                    $role = UserRole::tryFrom((string)$this->input('role'));

                    if ($role !== null && $role !== $target->role) {
                        $validator->errors()->add('role', __('users.validation.self_role'));
                    }
                }
            },
        ];
    }
}
