<?php

namespace Modules\Lumasachi\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Lumasachi\app\Enums\UserRole;
use App\Models\User;

class AssignOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if ($user && !in_array($user->role, [UserRole::EMPLOYEE, UserRole::ADMINISTRATOR, UserRole::SUPER_ADMINISTRATOR])) {
                        $fail('The selected user cannot be assigned to orders.');
                    }
                }
            ],
            'notes' => 'nullable|string|max:500'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'assigned_to.required' => 'Please select an employee to assign the order to.',
            'assigned_to.exists' => 'The selected employee does not exist.',
            'notes.max' => 'The notes cannot exceed 500 characters.'
        ];
    }
}
