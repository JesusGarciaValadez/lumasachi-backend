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
use App\Models\User;
use Modules\Lumasachi\app\Enums\UserRole;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Observers\OrderObserver;
use Modules\Lumasachi\app\Policies\OrderPolicy;
use Modules\Lumasachi\app\Policies\OrderHistoryPolicy;
use Modules\Lumasachi\app\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;

final class LumasachiServiceProvider extends BaseServiceProvider
{
    protected $policies = [
        Order::class => OrderPolicy::class,
        OrderHistory::class => OrderHistoryPolicy::class,
        User::class => UserPolicy::class,
    ];

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

        \Illuminate\Support\Facades\Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__ . '/../../routes/api.php');

        $this->registerPolicies();
        $this->registerRelations();
        $this->registerObservers();
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

    /**
     * Register the module's observers.
     */
    private function registerObservers(): void
    {
        Order::observe(OrderObserver::class);
    }
}
