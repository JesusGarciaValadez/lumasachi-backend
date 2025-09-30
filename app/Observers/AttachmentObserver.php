<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Attachment;
use App\Traits\CachesAttachments;

final class AttachmentObserver
{
    use CachesAttachments;

    public function created(Attachment $attachment): void
    {
        self::bumpVersion();
    }

    public function updated(Attachment $attachment): void
    {
        self::bumpVersion();
    }

    public function deleted(Attachment $attachment): void
    {
        self::bumpVersion();
    }

    public function restored(Attachment $attachment): void
    {
        self::bumpVersion();
    }

    public function forceDeleted(Attachment $attachment): void
    {
        self::bumpVersion();
    }
}
