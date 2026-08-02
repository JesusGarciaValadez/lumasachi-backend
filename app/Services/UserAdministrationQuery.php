<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

        if ($actor->isSuperAdministrator()) {
            return $query;
        }

        if ($actor->isAdministrator() && $actor->company_id !== null) {
            return $query->where('company_id', $actor->company_id);
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
        $query = $this->scoped($actor)
            ->select([
                'id',
                'uuid',
                'company_id',
                'first_name',
                'last_name',
                'role',
                'type',
                'is_active',
                'activated_at',
            ])
            ->with('company:id,uuid,name');

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
                'company_id',
                'first_name',
                'last_name',
                'role',
                'type',
                'is_active',
                'activated_at',
            ])
            ->with('company:id,uuid,name')
            ->where('is_active', true)
            ->latest('created_at')
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
        if (!$actor->isSuperAdministrator()) {
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
                $query->whereRaw('LOWER(' . $field . ') LIKE ?', ['%' . mb_strtolower($value) . '%']);
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

        if ($actor->isSuperAdministrator() && array_key_exists('company_id', $filters)) {
            $query->where('company_id', $filters['company_id']);
        }
    }
}
