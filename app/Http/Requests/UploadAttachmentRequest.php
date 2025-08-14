<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Attachment;

class UploadAttachmentRequest extends FormRequest
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
        $allowedMimes = array_merge(
            Attachment::IMAGE_MIME_TYPES,
            Attachment::DOCUMENT_MIME_TYPES
        );

        return [
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB max
                'mimes:' . implode(',', Attachment::getAllowedExtensions()),
                'mimetypes:' . implode(',', $allowedMimes)
            ],
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500'
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
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The upload must be a valid file.',
            'file.max' => 'The file size cannot exceed 10MB.',
            'file.mimes' => 'The file type is not allowed. Allowed types: ' . implode(', ', Attachment::getAllowedExtensions()),
            'file.mimetypes' => 'The file format is not supported.',
            'name.max' => 'The file name cannot exceed 255 characters.',
            'description.max' => 'The description cannot exceed 500 characters.'
        ];
    }
}
