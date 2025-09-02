<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderHistoryResource extends JsonResource
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
            'order_id' => $this->order_id,
            'field_changed' => $this->field_changed,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'comment' => $this->comment,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'creator' => new UserResource($this->whenLoaded('createdBy')),
            'created_at' => $this->created_at,
            'attachments' => $this->getRelatedAttachments()
        ];
    }

    /**
     * Get attachments related to this history entry.
     * Only returns attachments for attachment-related history entries.
     *
     * @return array
     */
    private function getRelatedAttachments(): array
    {
        // Only return attachments for attachment-related history entries
        if ($this->field_changed !== 'attachments') {
            return [];
        }

        // If this is an attachment-related history entry, find the related attachment
        if ($this->relationLoaded('order') && $this->order) {
            // Look for attachment created within 1 minute of this history entry
            $historyTime = $this->created_at;
            $timeBuffer = 60; // 60 seconds buffer

            // For upload events, new_value contains the filename
            // For deletion events, old_value contains the filename
            $filename = $this->new_value ?: $this->old_value;

            if ($filename && isset($this->order->attachments)) {
                // Filter the already-loaded attachments collection instead of querying database
                $attachment = $this->order->attachments
                    ->where('file_name', $filename)
                    ->filter(function ($attachment) use ($historyTime, $timeBuffer) {
                        $attachmentTime = $attachment->created_at;
                        return $attachmentTime->between(
                            $historyTime->copy()->subSeconds($timeBuffer),
                            $historyTime->copy()->addSeconds($timeBuffer)
                        );
                    })
                    ->first();

                if ($attachment) {
                    return [new AttachmentResource($attachment->load('uploadedBy'))];
                }
            }
        }

        return [];
    }
}
