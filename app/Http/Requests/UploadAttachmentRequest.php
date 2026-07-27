<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Attachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UploadAttachmentRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedMimes = array_merge(
            Attachment::IMAGE_MIME_TYPES,
            Attachment::DOCUMENT_MIME_TYPES
        );

        return [
            // Single file (backward compatible)
            'file' => [
                'required_without:files',
                'file',
                'max:10240', // 10MB max
                'mimes:'.implode(',', Attachment::getAllowedExtensions()),
                'mimetypes:'.implode(',', $allowedMimes),
            ],
            // Multiple files support
            'files' => ['required_without:file', 'array', 'min:1'],
            'files.*' => [
                'file',
                'max:10240',
                'mimes:'.implode(',', Attachment::getAllowedExtensions()),
                'mimetypes:'.implode(',', $allowedMimes),
            ],
            // Optional naming (single or multiple)
            'name' => 'nullable|string|max:255',
            'names' => 'nullable|array',
            'names.*' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'descriptions' => 'nullable|array',
            'descriptions.*' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required_without' => __('validation.custom.required_without', ['attribute' => __('validation.attributes.file'), 'values' => __('validation.attributes.files')]),
            'file.file' => __('validation.custom.file', ['attribute' => __('validation.attributes.file')]),
            'file.max' => __('validation.custom.max', ['attribute' => __('validation.attributes.file'), 'max' => 10]),
            'file.mimes' => __('validation.custom.mimes', ['attribute' => __('validation.attributes.file'), 'values' => implode(', ', Attachment::getAllowedExtensions())]),
            'file.mimetypes' => __('validation.custom.mimetypes', ['attribute' => __('validation.attributes.file')]),

            'files.required_without' => __('validation.custom.required_without', ['attribute' => __('validation.attributes.files'), 'values' => __('validation.attributes.file')]),
            'files.array' => __('validation.custom.array', ['attribute' => __('validation.attributes.files')]),
            'files.min' => __('validation.custom.min', ['attribute' => __('validation.attributes.files'), 'min' => 1]),
            'files.*.file' => __('validation.custom.file', ['attribute' => __('validation.attributes.files')]),
            'files.*.max' => __('validation.custom.max', ['attribute' => __('validation.attributes.files'), 'max' => 10]),
            'files.*.mimes' => __('validation.custom.mimes', ['attribute' => __('validation.attributes.files'), 'values' => implode(', ', Attachment::getAllowedExtensions())]),
            'files.*.mimetypes' => __('validation.custom.mimetypes', ['attribute' => __('validation.attributes.files')]),

            'name.max' => __('validation.custom.max', ['attribute' => __('validation.attributes.name'), 'max' => 255]),
            'names.array' => __('validation.custom.array', ['attribute' => __('validation.attributes.names')]),
            'names.*.max' => __('validation.custom.max', ['attribute' => __('validation.attributes.names'), 'max' => 255]),
            'description.max' => __('validation.custom.max', ['attribute' => __('validation.attributes.description'), 'max' => 500]),
            'descriptions.array' => __('validation.custom.array', ['attribute' => __('validation.attributes.descriptions'), 'max' => 500]),
            'descriptions.*.max' => __('validation.custom.max', ['attribute' => __('validation.attributes.descriptions'), 'max' => 500]),
        ];
    }
}
