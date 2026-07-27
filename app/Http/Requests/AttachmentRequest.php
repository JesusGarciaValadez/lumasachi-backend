<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Attachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

final class AttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', Attachment::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxSizeInKB = config('attachments.max_file_size') / 1024;
        $allowedMimes = config('attachments.allowed_mime_types');

        return [
            'file' => [
                'required',
                File::types($this->getAllowedExtensions())
                    ->max($maxSizeInKB),
            ],
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'attachable_type' => 'required|string|in:'.implode(',', $this->getAllowedAttachableTypes()),
            'attachable_id' => 'required|integer|min:1',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxSizeInMB = config('attachments.max_file_size') / (1024 * 1024);

        return [
            'file.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.file')]),
            'file.max' => __('validation.custom.max', ['attribute' => __('validation.attributes.file'), 'max' => $maxSizeInMB . ' MB']),
            'file.mimes' => __('validation.custom.mimes', ['attribute' => __('validation.attributes.file'), 'values' => implode(', ', $this->getAllowedExtensions())]),
            'attachable_type.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.attachable_type')]),
            'attachable_type.in' => __('validation.custom.in', ['attribute' => __('validation.attributes.attachable_type')]),
            'attachable_id.required' => __('validation.custom.required', ['attribute' => __('validation.attributes.attachable_id')]),
            'attachable_id.min' => __('validation.custom.min', ['attribute' => __('validation.attributes.attachable_id'), 'min' => 1]),
        ];
    }

    /**
     * Get allowed file extensions based on MIME types.
     *
     * @return array<string>
     */
    protected function getAllowedExtensions(): array
    {
        $mimeToExtension = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/webp' => 'webp',
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'application/x-7z-compressed' => '7z',
            'application/json' => 'json',
            'application/xml' => 'xml',
            'text/xml' => 'xml',
        ];

        $extensions = [];
        $allowedMimes = config('attachments.allowed_mime_types', []);

        foreach ($allowedMimes as $mime) {
            if (isset($mimeToExtension[$mime])) {
                $extensions[] = $mimeToExtension[$mime];
            }
        }

        return array_unique($extensions);
    }

    /**
     * Get allowed attachable types.
     *
     * @return array<string>
     */
    protected function getAllowedAttachableTypes(): array
    {
        // Add your model class names here
        return [
            'App\\Models\\Order',
            'App\\Models\\OrderHistory',
            // Add more models as needed
        ];
    }
}
