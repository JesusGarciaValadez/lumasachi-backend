<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Dedoc\Scramble\Scramble;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Observers\OrderObserver;
use App\Policies\OrderPolicy;
use App\Policies\OrderHistoryPolicy;
use App\Policies\UserPolicy;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Order::class => OrderPolicy::class,
        OrderHistory::class => OrderHistoryPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerServices();
        $this->registerViews();
        $this->configureCommands();
        $this->configureModels();
        $this->configureDates();
        $this->configureUrls();
        $this->configureVite();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(base_path('resources/views/mail'), 'mail');
        $this->registerPolicies();
        $this->registerRelations();
        $this->configureDocumentation();

        // Discover class-based features in app/Features
        Feature::discover();
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
        URL::forceScheme('https');
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
        // Register the policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Gates for general permissions
        Gate::define(
            'users.create',
            fn(User $user) =>
            in_array($user->role, [UserRole::SUPER_ADMINISTRATOR, UserRole::ADMINISTRATOR])
        );

        Gate::define(
            'users.delete',
            fn(User $user) =>
            $user->role === UserRole::SUPER_ADMINISTRATOR
        );

        Gate::define(
            'system.settings',
            fn(User $user) =>
            $user->role === UserRole::SUPER_ADMINISTRATOR
        );

        Gate::define(
            'reports.export',
            fn(User $user) =>
            in_array($user->role, [UserRole::SUPER_ADMINISTRATOR, UserRole::ADMINISTRATOR])
        );

        Gate::define(
            'orders.assign',
            fn(User $user) =>
            in_array($user->role, [UserRole::SUPER_ADMINISTRATOR, UserRole::ADMINISTRATOR])
        );

        // Gate to verify if the user can perform a specific action
        Gate::define('has-permission', function (User $user, string $permission) {
            return in_array($permission, $user->role->getPermissions());
        });
    }

    /**
     * Register services.
     */
    private function registerServices(): void
    {
        // Base services
        // $this->app->singleton(ServiceInterface::class, Service::class);
    }

    private function registerRelations(): void
    {
        Relation::morphMap([
            'order' => Order::class,
            'order_history' => OrderHistory::class,
        ]);
    }

    private function registerViews(): void
    {
        // Load the views
        $this->loadViewsFrom(resource_path('views/vendor/mail'), 'mail');
        $this->loadViewsFrom(resource_path('views/vendor/mail/html'), 'mail');
        $this->loadViewsFrom(resource_path('views/vendor/mail/text'), 'mail');
    }

    private function configureDocumentation(): void
    {
        Scramble::configure()
            ->routes(function (Route $route) {
                return Str::startsWith($route->uri, 'api/');
            });

        Gate::define('viewApiDocs', function (User $user) {
            return in_array($user->email, [env('APP_MAINTAINER_EMAIL')]);
        });
    }
}
