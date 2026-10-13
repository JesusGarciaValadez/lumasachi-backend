<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class MigrationPathServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $path = $this->app->environment('testing')
            ? database_path('migrations/legacy')
            : database_path('migrations/central');

        $this->loadMigrationsFrom($path);
    }
}
