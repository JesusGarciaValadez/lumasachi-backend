<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use Database\Factories\AttachmentFactory;

/**
 * @mixin IdeHelperAttachment
 */
final class Attachment extends Model
{
    use HasFactory, HasUuids;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * Indicates if the primary key is incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array
     */
    public function uniqueIds(): array
    {
        return [$this->getKeyName()];
    }

    /**
     * Generate a new UUID for the model.
     *
     * @return string
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory()
    {
        return AttachmentFactory::new();
    }

    // File type constants
    const TYPE_IMAGE = 'image';
    const TYPE_DOCUMENT = 'document';
    const TYPE_PDF = 'pdf';
    const TYPE_SPREADSHEET = 'spreadsheet';
    const TYPE_PRESENTATION = 'presentation';
    const TYPE_VIDEO = 'video';
    const TYPE_AUDIO = 'audio';
    const TYPE_ARCHIVE = 'archive';
    const TYPE_OTHER = 'other';

    // Common MIME type constants
    const MIME_PDF = 'application/pdf';
    const MIME_DOC = 'application/msword';
    const MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    const MIME_XLS = 'application/vnd.ms-excel';
    const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    const MIME_PPT = 'application/vnd.ms-powerpoint';
    const MIME_PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    const MIME_TXT = 'text/plain';
    const MIME_CSV = 'text/csv';
    const MIME_JPG = 'image/jpeg';
    const MIME_PNG = 'image/png';
    const MIME_GIF = 'image/gif';
    const MIME_SVG = 'image/svg+xml';
    const MIME_WEBP = 'image/webp';
    const MIME_ZIP = 'application/zip';
    const MIME_RAR = 'application/x-rar-compressed';
    const MIME_JSON = 'application/json';
    const MIME_XML = 'application/xml';

    // MIME type groups
    const IMAGE_MIME_TYPES = [
        self::MIME_JPG,
        self::MIME_PNG,
        self::MIME_GIF,
        self::MIME_SVG,
        self::MIME_WEBP,
    ];

    const DOCUMENT_MIME_TYPES = [
        self::MIME_DOC,
        self::MIME_DOCX,
        self::MIME_PDF,
        self::MIME_TXT,
    ];

    const SPREADSHEET_MIME_TYPES = [
        self::MIME_XLS,
        self::MIME_XLSX,
        self::MIME_CSV,
    ];

    const PRESENTATION_MIME_TYPES = [
        self::MIME_PPT,
        self::MIME_PPTX,
    ];

    const ARCHIVE_MIME_TYPES = [
        self::MIME_ZIP,
        self::MIME_RAR,
        'application/x-7z-compressed',
    ];

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by'
    ];

    protected $casts = [
        'file_size' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = $model->newUniqueId();
            }
        });
    }

    /**
     * Get the id attribute (for backward compatibility).
     * Returns the value from the uuid column (primary key).
     *
     * @return string|null
     */
    public function getIdAttribute(): ?string
    {
        // Explicitly get the uuid attribute value
        $uuid = $this->attributes['uuid'] ?? null;
        if ($uuid !== null) {
            return $uuid;
        }
        
        // Fallback to getKey() method
        return $this->getKey();
    }

    // Polymorphic relationships
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Scopes to filter files
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image%');
    }

    public function scopeDocuments($query)
    {
        return $query->where('mime_type', 'like', 'application%');
    }

    // Helper methods

    /**
     * Get the public URL of the file
     *
     * @return string
     */
    public function getUrl(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Get human-readable file size
     *
     * @return string
     */
    public function getHumanReadableSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if the file is an image
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the file is a document
     *
     * @return bool
     */
    public function isDocument(): bool
    {
        $documentMimeTypes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/rtf'
        ];

        return in_array($this->mime_type, $documentMimeTypes);
    }

    /**
     * Check if the file is a PDF
     *
     * @return bool
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Get the file extension
     *
     * @return string
     */
    public function getExtension(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    /**
     * Get allowed file extensions for upload validation
     *
     * @return array<string>
     */
    public static function getAllowedExtensions(): array
    {
        return [
            // Images
            'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp',
            // Documents
            'pdf', 'doc', 'docx', 'txt',
            // Spreadsheets
            'xls', 'xlsx', 'csv',
            // Presentations
            'ppt', 'pptx',
            // Archives
            'zip', 'rar', '7z'
        ];
    }

    /**
     * Delete the model and the physical file
     *
     * @return bool|null
     */
    public function delete(): bool
    {
        // Delete the physical file
        if (Storage::disk('public')->exists($this->file_path)) {
            Storage::disk('public')->delete($this->file_path);
        }

        // Delete the database record
        return parent::delete();
    }
}
