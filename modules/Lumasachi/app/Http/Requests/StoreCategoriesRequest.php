<?php

namespace Modules\Lumasachi\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Authorization will be handled by middleware/policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'categories' => 'required|array',
            'categories.*.name' => 'required|string|max:255|unique:categories,name|distinct',
            'categories.*.description' => 'nullable|string',
            'categories.*.is_active' => 'nullable|boolean',
        ];
    }
}
