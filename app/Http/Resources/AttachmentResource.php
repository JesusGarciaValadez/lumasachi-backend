<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'attachable_type' => $this->attachable_type,
            'attachable_id' => $this->attachable_id,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'human_file_size' => $this->getHumanReadableSize(),
            'uploaded_by' => new UserResource($this->whenLoaded('uploadedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'url' => $this->getUrl(),
            'is_image' => $this->isImage(),
            'is_document' => $this->isDocument(),
            'is_pdf' => $this->isPdf(),
            'extension' => $this->getExtension()
        ];
    }
}
