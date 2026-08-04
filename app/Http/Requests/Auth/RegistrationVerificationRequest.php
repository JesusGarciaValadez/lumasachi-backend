<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class RegistrationVerificationRequest extends FormRequest
{
    /**
     * Determine if the signed registration link belongs to an existing user.
     */
    public function authorize(): bool
    {
        $user = $this->verificationUser();

        return $user instanceof User
            && $user->is_active
            && hash_equals(sha1($user->getEmailForVerification()), (string)$this->route('hash'));
    }

    /**
     * Get the user referenced by the signed registration link.
     */
    public function verificationUser(): ?User
    {
        $id = $this->route('id');

        if (is_int($id)) {
            return User::query()->find($id);
        }

        if (!is_string($id) || !ctype_digit($id)) {
            return null;
        }

        return User::query()->find((int)$id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
