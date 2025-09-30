<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\AttachmentObserver;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin IdeHelperAttachment
 */
#[ObservedBy([AttachmentObserver::class])]
final class Attachment extends Model
{
    use HasFactory, HasUuids;

    // File type constants
    public const TYPE_IMAGE = 'image';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_PDF = 'pdf';

    public const TYPE_SPREADSHEET = 'spreadsheet';

    public const TYPE_PRESENTATION = 'presentation';

    public const TYPE_VIDEO = 'video';

    public const TYPE_AUDIO = 'audio';

    public const TYPE_ARCHIVE = 'archive';

    public const TYPE_OTHER = 'other';

    // Common MIME type constants
    public const MIME_PDF = 'application/pdf';

    public const MIME_DOC = 'application/msword';

    public const MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public const MIME_XLS = 'application/vnd.ms-excel';

    public const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public const MIME_PPT = 'application/vnd.ms-powerpoint';

    public const MIME_PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';

    public const MIME_TXT = 'text/plain';

    public const MIME_CSV = 'text/csv';

    public const MIME_JPG = 'image/jpeg';

    public const MIME_PNG = 'image/png';

    public const MIME_GIF = 'image/gif';

    public const MIME_SVG = 'image/svg+xml';

    public const MIME_WEBP = 'image/webp';

    public const MIME_ZIP = 'application/zip';

    public const MIME_RAR = 'application/x-rar-compressed';

    public const MIME_JSON = 'application/json';

    public const MIME_XML = 'application/xml';

    // MIME type groups
    public const IMAGE_MIME_TYPES = [
        self::MIME_JPG,
        self::MIME_PNG,
        self::MIME_GIF,
        self::MIME_SVG,
        self::MIME_WEBP,
    ];

    public const DOCUMENT_MIME_TYPES = [
        self::MIME_DOC,
        self::MIME_DOCX,
        self::MIME_PDF,
        self::MIME_TXT,
    ];

    public const SPREADSHEET_MIME_TYPES = [
        self::MIME_XLS,
        self::MIME_XLSX,
        self::MIME_CSV,
    ];

    public const PRESENTATION_MIME_TYPES = [
        self::MIME_PPT,
        self::MIME_PPTX,
    ];

    public const ARCHIVE_MIME_TYPES = [
        self::MIME_ZIP,
        self::MIME_RAR,
        'application/x-7z-compressed',
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The name of the primary key.
     *
     * @var string
     */
    protected $keyName = 'id';

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'attachments';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'attachable_type',
        'attachable_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'uuid' => 'string',
        'file_size' => 'integer',
    ];

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
            'zip', 'rar', '7z',
        ];
    }

    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
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
     */
    public function getUrl(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Get human-readable file size
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

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Check if the file is an image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the file is a document
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
            'application/rtf',
        ];

        return in_array($this->mime_type, $documentMimeTypes);
    }

    /**
     * Check if the file is a PDF
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Get the file extension
     */
    public function getExtension(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
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

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory()
    {
        return AttachmentFactory::new();
    }
}
