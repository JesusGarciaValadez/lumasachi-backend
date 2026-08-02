<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $user = $request->user();
        $canViewUsers = $user instanceof User && $user->can('viewAny', User::class);
        $canViewSidebar = $user instanceof User
            && $user->is_active
            && ($user->isSuperAdministrator() || $user->isAdministrator() || $user->isEmployee());

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'i18n' => [
                'locale' => app()->getLocale(),
                'supported_locales' => config('app.supported_locales', ['es', 'en']),
            ],
            'quote' => ['message' => mb_trim((string)$message), 'author' => mb_trim((string)$author)],
            'auth' => [
                'user' => $request->routeIs('web.orders.track') ? null : $request->user(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'can_create_order' => $request->user()?->can('create', Order::class) ?? false,
            'can_view_sidebar' => $canViewSidebar,
            'is_customer' => $user instanceof User && $user->isCustomer(),
            'can_view_users' => $canViewUsers,
            'can_create_user' => $user?->can('create', User::class) ?? false,
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
