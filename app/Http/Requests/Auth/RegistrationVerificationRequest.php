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
        $hash = $this->route('hash');

        return $user instanceof User
            && $user->is_active
            && is_string($hash)
            && hash_equals(sha1($user->getEmailForVerification()), $hash);
    }

    /**
     * Get the user referenced by the signed registration link.
     */
    public function verificationUser(): ?User
    {
        $id = $this->route('id');

        if (!is_int($id) && (!is_string($id) || !ctype_digit($id))) {
            return null;
        }

        return User::query()->whereKey($id)->first();
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
