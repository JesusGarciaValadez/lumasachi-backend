<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Attachment */
final class PublicAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'human_file_size' => $this->getHumanReadableSize(),
            'is_image' => $this->isImage(),
            'is_document' => $this->isDocument(),
            'is_pdf' => $this->isPdf(),
            'extension' => $this->getExtension(),
            'created_at' => $this->created_at,
        ];
    }
}
