<?php

namespace App\Observers;

use App\Traits\CachesCategories;
use App\Models\Category;

class CategoryObserver
{
    use CachesCategories;

    public function created(Category $category): void
    {
        self::bumpVersion();
    }

    public function updated(Category $category): void
    {
        self::bumpVersion();
    }

    public function deleted(Category $category): void
    {
        self::bumpVersion();
    }

    public function restored(Category $category): void
    {
        self::bumpVersion();
    }

    public function forceDeleted(Category $category): void
    {
        self::bumpVersion();
    }
}