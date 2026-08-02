<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Enums\UserRole;
use App\Enums\UserType;
use App\Http\Requests\UserAdministration\IndexUserRequest;
use App\Http\Requests\UserAdministration\StoreUserRequest;
use App\Http\Requests\UserAdministration\UpdateUserRequest;
use App\Http\Resources\UserAdministrationListResource;
use App\Http\Resources\UserAdministrationResource;
use App\Models\User;
use App\Services\UserAdministrationIncidentReporter;
use App\Services\UserAdministrationQuery;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class UserAdministrationController extends Controller
{
    public function index(IndexUserRequest $request, UserAdministrationQuery $query): Response
    {
        $actor = $this->actor($request);
        $filters = $request->validated();
        $users = $query->paginate($actor, $filters);

        return Inertia::render('Users/Index', [
            'users' => $this->paginatedUsers($users, $request),
            'filters' => $this->filters($filters),
            'capabilities' => $this->capabilities($actor),
            'current_user_uuid' => $actor->uuid,
            'options' => $this->options($actor, $query),
        ]);
    }

    public function create(Request $request, UserAdministrationQuery $query): Response
    {
        $actor = $this->actor($request);

        return Inertia::render('Users/Create', [
            'user' => null,
            'capabilities' => $this->capabilities($actor),
            'options' => $this->options($actor, $query),
        ]);
    }

    public function show(Request $request, User $user, UserAdministrationQuery $query): Response
    {
        $actor = $this->actor($request);
        abort_unless($actor->can('viewAdministration', $user), 403);

        $user->load('company:id,uuid,name');

        return Inertia::render('Users/Show', [
            'user' => (new UserAdministrationResource($user))->resolve($request),
            'capabilities' => $this->capabilities($actor, $user),
            'options' => $this->options($actor, $query),
        ]);
    }

    public function store(
        StoreUserRequest                   $request,
        UserService                        $service,
        UserAdministrationIncidentReporter $reporter,
    ): RedirectResponse
    {
        try {
            $service->create($request->validated());

            return to_route('users.index')->with('success', __('users.flash.created'));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->failureRedirect($request, $reporter, $exception, 'create');
        }
    }

    public function update(
        UpdateUserRequest                  $request,
        User                               $user,
        UserService                        $service,
        UserAdministrationIncidentReporter $reporter,
    ): RedirectResponse
    {
        try {
            $service->update($user, $request->validated());

            return to_route('users.index')->with('success', __('users.flash.updated'));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->failureRedirect($request, $reporter, $exception, 'update');
        }
    }

    public function destroy(
        Request                            $request,
        User                               $user,
        UserService                        $service,
        UserAdministrationIncidentReporter $reporter,
    ): RedirectResponse
    {
        try {
            $service->delete($user);

            return to_route('users.index')->with('success', __('users.flash.deleted'));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->failureRedirect($request, $reporter, $exception, 'delete');
        }
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function paginatedUsers(LengthAwarePaginator $paginator, Request $request): array
    {
        return [
            'data' => $paginator->getCollection()
                ->map(fn(User $user): array => (new UserAdministrationListResource($user))->resolve($request))
                ->values()
                ->all(),
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'links' => collect($paginator->linkCollection()->all())
                ->map(static function (array $link): array {
                    $link['label'] = html_entity_decode($link['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    return $link;
                })
                ->all(),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function filters(array $filters): array
    {
        return [
            'first_name' => $filters['first_name'] ?? '',
            'last_name' => $filters['last_name'] ?? '',
            'role' => $filters['role'] ?? '',
            'active' => (string)($filters['active'] ?? '1'),
            'type' => $filters['type'] ?? '',
            'company_id' => $filters['company_id'] ?? '',
            'per_page' => (int)($filters['per_page'] ?? 10),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function capabilities(User $actor, ?User $target = null): array
    {
        $isSuperAdministrator = $actor->isSuperAdministrator();
        $isAdministrator = $actor->isAdministrator();

        return [
            'can_view_users' => $actor->can('viewAny', User::class),
            'can_create_user' => $actor->can('create', User::class),
            'can_open' => $target === null || $actor->can('viewAdministration', $target),
            'can_open_inactive' => $isSuperAdministrator,
            'can_update' => $target === null || $actor->can('updateAdministration', $target),
            'can_update_active' => $isSuperAdministrator,
            'can_delete' => $target === null ? $isSuperAdministrator : $actor->can('delete', $target),
            'can_change_company' => $isSuperAdministrator,
            'can_change_password' => $isSuperAdministrator,
            'allowed_fields' => $isSuperAdministrator
                ? [
                    'first_name',
                    'last_name',
                    'email',
                    'password',
                    'role',
                    'phone_number',
                    'is_active',
                    'notes',
                    'type',
                    'locale',
                    'company_id',
                ]
                : ($isAdministrator ? [
                    'first_name',
                    'last_name',
                    'email',
                    'role',
                    'phone_number',
                    'notes',
                    'type',
                    'locale',
                ] : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(User $actor, UserAdministrationQuery $query): array
    {
        return [
            'roles' => UserRole::administrationValues($actor->role),
            'types' => UserType::getTypes(),
            'locales' => Locale::values(),
            'active' => ['1', '0', 'all'],
            'companies' => $query->companies($actor)
                ->map(fn($company): array => [
                    'id' => $company->id,
                    'uuid' => $company->uuid,
                    'name' => $company->name,
                ])
                ->all(),
            'per_page' => [10, 20, 50],
        ];
    }

    private function failureRedirect(
        Request                            $request,
        UserAdministrationIncidentReporter $reporter,
        Throwable                          $exception,
        string                             $operation,
    ): RedirectResponse
    {
        $incident = $reporter->capture($exception, $request, $operation);

        return back()
            ->withInput($request->except(['password', 'password_confirmation']))
            ->with('error', $incident->getMessage());
    }
}
