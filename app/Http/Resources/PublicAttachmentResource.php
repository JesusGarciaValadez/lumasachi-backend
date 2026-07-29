<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attachment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Attachment */
final class PublicAttachmentResource extends JsonResource
{
    private ?Order $publicOrder = null;

    public function forOrder(Order $order): self
    {
        $this->publicOrder = $order;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'human_file_size' => $this->getHumanReadableSize(),
            'is_image' => $this->isImage(),
            'is_document' => $this->isDocument(),
            'is_pdf' => $this->isPdf(),
            'extension' => $this->getExtension(),
            'preview_url' => $this->publicOrder !== null && ($this->isImage() || $this->isPdf())
                ? route('api.orders.public.attachments.preview', [
                    'order' => $this->publicOrder->uuid,
                    'attachment' => $this->uuid,
                    'created_date' => $this->publicOrder->created_at->toDateString(),
                ])
                : null,
            'download_url' => $this->publicOrder !== null
                ? route('api.orders.public.attachments.download', [
                    'order' => $this->publicOrder->uuid,
                    'attachment' => $this->uuid,
                    'created_date' => $this->publicOrder->created_at->toDateString(),
                ])
                : null,
            'created_at' => $this->created_at,
        ];
    }
}
