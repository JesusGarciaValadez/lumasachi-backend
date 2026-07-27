<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class TrackOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint — no auth required
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
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
            'uuid.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.uuid')]),
            'uuid.uuid' => __('validation.custom.uuid', ['attribute' => __('validation.attributes.uuid')]),
            'created_date.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.created_date')]),
            'created_date.date' => __('validation.custom.date', ['attribute' => __('validation.attributes.created_date')]),
        ];
    }
}
