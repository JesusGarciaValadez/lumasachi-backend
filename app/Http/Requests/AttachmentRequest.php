<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use App\Models\Attachment;

final class AttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user()->can('create', Attachment::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
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
            'attachable_type' => 'required|string|in:' . implode(',', $this->getAllowedAttachableTypes()),
            'attachable_id' => 'required|integer|min:1',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        $maxSizeInMB = config('attachments.max_file_size') / (1024 * 1024);

        return [
            'file.required' => 'Por favor seleccione un archivo.',
            'file.max' => "El archivo no debe exceder los {$maxSizeInMB}MB.",
            'file.mimes' => 'El tipo de archivo no está permitido.',
            'attachable_type.required' => 'El tipo de entidad es requerido.',
            'attachable_type.in' => 'El tipo de entidad no es válido.',
            'attachable_id.required' => 'El ID de la entidad es requerido.',
            'attachable_id.min' => 'El ID de la entidad debe ser mayor a 0.',
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

