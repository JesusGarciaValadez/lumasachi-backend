<?php

namespace App\Observers;

use App\Models\ServiceCatalog;
use App\Traits\CachesServiceCatalog;

final class ServiceCatalogObserver
{
    use CachesServiceCatalog;

    public function created(ServiceCatalog $catalog): void
    {
        self::bumpVersion();
    }

    public function updated(ServiceCatalog $catalog): void
    {
        self::bumpVersion();
    }

    public function deleted(ServiceCatalog $catalog): void
    {
        self::bumpVersion();
    }

    public function restored(ServiceCatalog $catalog): void
    {
        self::bumpVersion();
    }

    public function forceDeleted(ServiceCatalog $catalog): void
    {
        self::bumpVersion();
    }
}
