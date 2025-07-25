<?php

namespace Modules\Lumasachi\app\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

final class LumasachiServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'lumasachi');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->commands([
            //
        ]);
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'lumasachi');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        $this->registerPolicies();
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerServices();
        $this->configureCommands();
        $this->configureModels();
        $this->configureDates();
        $this->configureUrls();
        $this->configureVite();
    }

    /**
     * Configure the application's commands.
     */
    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands(
            $this->app->isProduction(),
        );
    }

    /**
     * Configure the application's dates.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    /**
     * Configure the application's models.
     */
    private function configureModels(): void
    {
        Model::unguard();

        Model::shouldBeStrict();
    }

    /**
     * Configure the application's URLs.
     */
    private function configureUrls(): void
    {
        URL::forceScheme('http');
    }

    /**
     * Configure the application's Vite instance.
     */
    private function configureVite(): void
    {
        Vite::prefetch(concurrency: 3);
        Vite::useAggressivePrefetching();
    }

    /**
     * Register repositories.
     */
    private function registerRepositories(): void
    {
        // $this->app->singleton(ClassRepositoryInterface::class, ClassRepository::class);
    }

    /**
     * Register the module's policies.
     */
    private function registerPolicies(): void
    {
        // Gate::policy(Class::class, ClassPolicy::class);
    }

    /**
     * Register services.
     */
    private function registerServices(): void
    {
        // Base services
        // $this->app->singleton(ServiceInterface::class, Service::class);
    }
}
