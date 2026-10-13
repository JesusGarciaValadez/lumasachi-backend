<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class UserAdministrationQuery
{
    /**
     * Return the users visible to an administration actor before filters.
     *
     * @return Builder<User>
     */
    public function scoped(User $actor): Builder
    {
        $query = User::query();

        if (tenancy()->initialized) {
            if (!$actor->isPlatformSupport()) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('is_platform_support', false);
        }

        if ($actor->isSuperAdministrator()) {
            return $query;
        }

        $companyId = $actor->getRawOriginal('company_id');

        if ($actor->isAdministrator() && $companyId !== null) {
            return $query->where('company_id', $companyId);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Build the filtered, deterministic administration list.
     *
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->scoped($actor)->select([
            'id',
            'uuid',
            'first_name',
            'last_name',
            'role',
            'type',
            'is_active',
            'activated_at',
        ]);
        $this->applyFilters($query, $filters, $actor);

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate((int)($filters['per_page'] ?? 10))
            ->withQueryString();
    }

    /**
     * Return the latest active users visible to the actor for the dashboard.
     *
     * @return Collection<int, User>
     */
    public function recent(User $actor, int $limit = 5): Collection
    {
        return $this->scoped($actor)
            ->select([
                'id',
                'uuid',
                'first_name',
                'last_name',
                'role',
                'type',
                'is_active',
                'activated_at',
            ])
            ->where('is_active', true)
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Return company options represented by users visible to the actor.
     *
     * @return Collection<int, Company>
     */
    public function companies(User $actor): Collection
    {
        if (tenancy()->initialized || !$actor->isSuperAdministrator()) {
            return collect();
        }

        $companyIds = $this->scoped($actor)
            ->whereNotNull('company_id')
            ->select('company_id');

        return Company::query()
            ->whereIn('id', $companyIds)
            ->select(['id', 'uuid', 'name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @param Builder<User> $query
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Builder $query, array $filters, User $actor): void
    {
        foreach (['first_name', 'last_name'] as $field) {
            $value = mb_trim((string)($filters[$field] ?? ''));

            if ($value !== '') {
                $normalizedValue = Str::transliterate(mb_strtolower($value));

                if (config('database.connections.' . config('database.default') . '.driver') === 'pgsql') {
                    $query->whereRaw("unaccent(lower(users.{$field})) LIKE ?", ["%{$normalizedValue}%"]);
                } else {
                    $query->whereRaw("lower(users.{$field}) LIKE ?", ["%{$normalizedValue}%"]);
                }
            }
        }

        if (($role = $filters['role'] ?? null) !== null && $role !== '') {
            $query->where('role', $role);
        }

        if (($type = $filters['type'] ?? null) !== null && $type !== '') {
            $query->where('type', $type);
        }

        match ((string)($filters['active'] ?? '1')) {
            '0' => $query->where('is_active', false),
            'all' => null,
            default => $query->where('is_active', true),
        };

        if (!tenancy()->initialized && $actor->isSuperAdministrator() && array_key_exists('company_id', $filters)) {
            $query->where('company_id', $filters['company_id']);
        }
    }
}
