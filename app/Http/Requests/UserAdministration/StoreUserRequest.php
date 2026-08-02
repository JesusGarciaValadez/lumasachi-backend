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

final class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'password_confirmation' => ['required', 'string'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'type' => ['required', Rule::enum(UserType::class)],
            'locale' => ['nullable', 'string', Rule::in(Locale::values())],
            'uuid' => ['prohibited'],
            'activated_at' => ['prohibited'],
            'preferences' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'remember_token' => ['prohibited'],
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
     * Add validation that protects model-managed fields from direct input.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (['id', 'created_at', 'updated_at'] as $field) {
                    if ($this->exists($field)) {
                        $validator->errors()->add($field, __('users.validation.forbidden_field'));
                    }
                }
            },
        ];
    }
}
