<?php

namespace Modules\Lumasachi\app\Traits;

use Modules\Lumasachi\app\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasAttachments
{
    /**
     * Get all of the attachments for the model.
     *
     * @return MorphMany
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Attach a file to the model.
     *
     * @param UploadedFile $file
     * @param int $uploadedBy User ID who uploaded the file
     * @param string|null $disk Storage disk to use (default: 'public')
     * @return Attachment
     */
    public function attach(UploadedFile $file, int $uploadedBy, ?string $disk = 'public'): Attachment
    {
        // Generate a unique file name to avoid conflicts
        $fileName = $file->getClientOriginalName();
        $uniqueFileName = Str::uuid() . '_' . $fileName;
        
        // Determine the storage path based on the model type and ID
        $modelType = class_basename($this);
        $modelId = $this->getKey();
        $storagePath = "attachments/{$modelType}/{$modelId}";
        
        // Store the file
        $filePath = $file->storeAs($storagePath, $uniqueFileName, $disk);
        
        // Create the attachment record
        return $this->attachments()->create([
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => $uploadedBy
        ]);
    }

    /**
     * Detach (delete) an attachment by ID.
     *
     * @param string $attachmentId
     * @return bool
     */
    public function detach(string $attachmentId): bool
    {
        $attachment = $this->attachments()->find($attachmentId);
        
        if (!$attachment) {
            return false;
        }
        
        // The delete method in Attachment model handles file deletion
        return $attachment->delete();
    }

    /**
     * Check if the model has any attachments.
     *
     * @return bool
     */
    public function hasAttachments(): bool
    {
        return $this->attachments()->exists();
    }

    /**
     * Get attachments filtered by MIME type.
     *
     * @param string $mimeType Can be exact type (e.g., 'application/pdf') or partial (e.g., 'image')
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAttachmentsByType(string $mimeType): \Illuminate\Database\Eloquent\Collection
    {
        // Check if it's a partial type (e.g., 'image', 'application')
        if (!str_contains($mimeType, '/')) {
            return $this->attachments()
                ->where('mime_type', 'like', $mimeType . '%')
                ->get();
        }
        
        // Exact MIME type match
        return $this->attachments()
            ->where('mime_type', $mimeType)
            ->get();
    }

    /**
     * Get all image attachments.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getImageAttachments(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->attachments()->images()->get();
    }

    /**
     * Get all document attachments.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDocumentAttachments(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->attachments()->documents()->get();
    }

    /**
     * Get the total size of all attachments in bytes.
     *
     * @return int
     */
    public function getTotalAttachmentsSize(): int
    {
        return $this->attachments()->sum('file_size');
    }

    /**
     * Get the total size of all attachments in human-readable format.
     *
     * @return string
     */
    public function getTotalAttachmentsSizeFormatted(): string
    {
        $bytes = $this->getTotalAttachmentsSize();
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Detach all attachments from the model.
     *
     * @return int Number of attachments deleted
     */
    public function detachAll(): int
    {
        $count = 0;
        
        foreach ($this->attachments as $attachment) {
            if ($attachment->delete()) {
                $count++;
            }
        }
        
        return $count;
    }
}
