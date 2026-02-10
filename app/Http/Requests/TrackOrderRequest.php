<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TrackOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint — no auth required
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'uuid' => 'required|uuid',
            'created_date' => 'required|date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'uuid.required' => 'The order UUID is required.',
            'uuid.uuid' => 'The order UUID must be a valid UUID.',
            'created_date.required' => 'The creation date is required.',
            'created_date.date' => 'The creation date must be a valid date.',
        ];
    }
}
