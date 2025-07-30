<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Attachment Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration settings for file attachments
    | including file size limits, allowed MIME types, and storage settings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | Maximum allowed file size for attachments in bytes.
    | Default: 10MB (10 * 1024 * 1024 bytes)
    |
    */
    'max_file_size' => env('ATTACHMENT_MAX_FILE_SIZE', 10 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Allowed MIME Types
    |--------------------------------------------------------------------------
    |
    | Array of allowed MIME types for attachments.
    | Leave empty to allow all file types.
    |
    */
    'allowed_mime_types' => [
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/svg+xml',
        'image/webp',
        
        // Compressed files
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
        
        // Other common formats
        'application/json',
        'application/xml',
        'text/xml',
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Directory
    |--------------------------------------------------------------------------
    |
    | The directory within the storage disk where attachments will be stored.
    |
    */
    'storage_directory' => env('ATTACHMENT_STORAGE_DIRECTORY', 'attachments'),

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The disk on which to store the attachments. You may specify any of the
    | disks defined in the config/filesystems.php configuration file.
    |
    */
    'storage_disk' => env('ATTACHMENT_STORAGE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Preserve Original Filename
    |--------------------------------------------------------------------------
    |
    | Whether to preserve the original filename or generate a unique one.
    |
    */
    'preserve_original_filename' => env('ATTACHMENT_PRESERVE_FILENAME', false),

    /*
    |--------------------------------------------------------------------------
    | Generate Thumbnails
    |--------------------------------------------------------------------------
    |
    | Whether to generate thumbnails for image attachments.
    |
    */
    'generate_thumbnails' => env('ATTACHMENT_GENERATE_THUMBNAILS', true),

    /*
    |--------------------------------------------------------------------------
    | Thumbnail Dimensions
    |--------------------------------------------------------------------------
    |
    | Default thumbnail dimensions for image attachments.
    |
    */
    'thumbnail_dimensions' => [
        'width' => 150,
        'height' => 150,
    ],
];
