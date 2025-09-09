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
            // Single file (backward compatible)
            'file' => [
                'required_without:files',
                'file',
                'max:10240', // 10MB max
                'mimes:' . implode(',', Attachment::getAllowedExtensions()),
                'mimetypes:' . implode(',', $allowedMimes)
            ],
            // Multiple files support
            'files' => ['required_without:file', 'array', 'min:1'],
            'files.*' => [
                'file',
                'max:10240',
                'mimes:' . implode(',', Attachment::getAllowedExtensions()),
                'mimetypes:' . implode(',', $allowedMimes)
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
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'file.required_without' => 'Please provide a file or files to upload.',
            'file.file' => 'The upload must be a valid file.',
            'file.max' => 'The file size cannot exceed 10MB.',
            'file.mimes' => 'The file type is not allowed. Allowed types: ' . implode(', ', Attachment::getAllowedExtensions()),
            'file.mimetypes' => 'The file format is not supported.',

            'files.required_without' => 'Please provide a file or files to upload.',
            'files.array' => 'Files must be an array.',
            'files.min' => 'Please upload at least one file.',
            'files.*.file' => 'Each uploaded item must be a valid file.',
            'files.*.max' => 'Each file size cannot exceed 10MB.',
            'files.*.mimes' => 'One or more files have a disallowed type.',
            'files.*.mimetypes' => 'One or more files have an unsupported format.',

            'name.max' => 'The file name cannot exceed 255 characters.',
            'names.array' => 'Names must be provided as an array.',
            'names.*.max' => 'Each file name cannot exceed 255 characters.',
            'description.max' => 'The description cannot exceed 500 characters.',
            'descriptions.array' => 'Descriptions must be provided as an array.',
            'descriptions.*.max' => 'Each description cannot exceed 500 characters.',
        ];
    }
}
