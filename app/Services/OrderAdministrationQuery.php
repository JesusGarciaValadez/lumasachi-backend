<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class OrderAdministrationQuery
{
    /**
     * Return the orders visible to an authenticated actor before filters.
     *
     * @return Builder<Order>
     */
    public function scoped(User $actor): Builder
    {
        $query = Order::query();

        if ($actor->isSuperAdministrator() || $actor->isAdministrator()) {
            return $query;
        }

        if ($actor->isEmployee()) {
            return $query->where(function (Builder $orders) use ($actor): void {
                $orders
                    ->where('assigned_to', $actor->id)
                    ->orWhere('created_by', $actor->id);
            });
        }

        if ($actor->isCustomer()) {
            return $query->where('customer_id', $actor->id);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Build the filtered, deterministic order list.
     *
     * @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $orderTable = (new Order)->getTable();
        $query = $this->scoped($actor)
            ->select([
                "{$orderTable}.id",
                "{$orderTable}.uuid",
                "{$orderTable}.customer_id",
                "{$orderTable}.assigned_to",
                "{$orderTable}.title",
                "{$orderTable}.lifecycle_status",
                "{$orderTable}.disposition_status",
                "{$orderTable}.priority",
                "{$orderTable}.created_at",
            ])
            ->with([
                'customer:id,uuid,company_id,first_name,last_name,role',
                'customer.company:id,uuid,name',
                'assignedTo:id,uuid,first_name,last_name',
                'payments:id,order_id,amount',
                'refunds:id,uuid,order_id,amount,status',
                'services' => function (Relation $services): void {
                    $services->getQuery()->select([
                        'order_services.id',
                        'order_services.order_item_id',
                        'order_services.net_price',
                        'order_services.is_completed',
                    ]);
                },
            ]);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc("{$orderTable}.created_at")
            ->orderByDesc("{$orderTable}.id")
            ->paginate((int)($filters['per_page'] ?? 10))
            ->withQueryString();
    }

    /**
     * Return companies represented by visible order customers.
     *
     * @return Collection<int, Company>
     */
    public function companies(User $actor): Collection
    {
        $customerIds = $this->scoped($actor)
            ->whereNotNull('customer_id')
            ->select('customer_id');

        $companyIds = User::query()
            ->whereIn('id', $customerIds)
            ->where('role', UserRole::CUSTOMER->value)
            ->whereNotNull('company_id')
            ->select('company_id');

        return Company::query()
            ->whereIn('id', $companyIds)
            ->select(['id', 'uuid', 'name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Return assignees represented by visible orders.
     *
     * @return Collection<int, User>
     */
    public function assignees(User $actor): Collection
    {
        $assigneeIds = $this->scoped($actor)
            ->whereNotNull('assigned_to')
            ->select('assigned_to')
            ->distinct();

        return User::query()
            ->whereIn('id', $assigneeIds)
            ->select(['id', 'uuid', 'first_name', 'last_name'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param Builder<Order> $query
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $title = mb_trim((string)($filters['title'] ?? ''));

        if ($title !== '') {
            $column = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn('title'));
            $normalizedTitle = Str::transliterate(mb_strtolower($title));
            $driver = config('database.connections.' . config('database.default') . '.driver');
            $expression = $driver === 'pgsql'
                ? "unaccent(lower({$column}))"
                : "lower({$column})";

            $query->whereRaw("{$expression} LIKE ?", ["%{$normalizedTitle}%"]);
        }

        if (($companyId = $filters['company_id'] ?? null) !== null && $companyId !== '') {
            $customerIds = User::query()
                ->where('company_id', $companyId)
                ->where('role', UserRole::CUSTOMER->value)
                ->select('id');

            $query->whereIn('customer_id', $customerIds);
        }

        if (($assignedTo = $filters['assigned_to'] ?? null) !== null && $assignedTo !== '') {
            $query->where('assigned_to', $assignedTo);
        }

        foreach (['priority', 'lifecycle_status', 'disposition_status'] as $field) {
            if (($value = $filters[$field] ?? null) !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        if (($paymentStatus = $filters['payment_status'] ?? null) !== null && $paymentStatus !== '') {
            $this->applyPaymentFilter($query, (string)$paymentStatus);
        }

        if (($createdFrom = $filters['created_from'] ?? null) !== null && $createdFrom !== '') {
            $query->where('created_at', '>=', CarbonImmutable::parse((string)$createdFrom)->startOfDay());
        }

        if (($createdTo = $filters['created_to'] ?? null) !== null && $createdTo !== '') {
            $query->where('created_at', '<=', CarbonImmutable::parse((string)$createdTo)->endOfDay());
        }
    }

    /**
     * @param Builder<Order> $query
     */
    private function applyPaymentFilter(Builder $query, string $paymentStatus): void
    {
        $paymentTable = (new OrderPayment)->getTable();
        $serviceTable = (new OrderService)->getTable();
        $itemTable = (new OrderItem)->getTable();
        $orderTable = (new Order)->getTable();

        $paymentTotals = OrderPayment::query()
            ->select("{$paymentTable}.order_id")
            ->selectRaw("COALESCE(SUM({$paymentTable}.amount), 0) AS total_paid")
            ->groupBy("{$paymentTable}.order_id");
        $completedTotals = OrderService::query()
            ->join($itemTable, "{$itemTable}.id", '=', "{$serviceTable}.order_item_id")
            ->where("{$serviceTable}.is_completed", true)
            ->select("{$itemTable}.order_id")
            ->selectRaw("COALESCE(SUM({$serviceTable}.net_price), 0) AS completed_total")
            ->groupBy("{$itemTable}.order_id");

        $query
            ->leftJoinSub($paymentTotals, 'order_payment_totals', function (JoinClause $join) use ($orderTable): void {
                $join->on("{$orderTable}.id", '=', 'order_payment_totals.order_id');
            })
            ->leftJoinSub($completedTotals, 'order_completed_totals', function (JoinClause $join) use ($orderTable): void {
                $join->on("{$orderTable}.id", '=', 'order_completed_totals.order_id');
            });

        match ($paymentStatus) {
            OrderPaymentStatus::Unpaid->value => $query->whereRaw('COALESCE(order_payment_totals.total_paid, 0) <= 0'),
            OrderPaymentStatus::Paid->value => $query
                ->whereRaw('COALESCE(order_payment_totals.total_paid, 0) > 0')
                ->whereRaw('COALESCE(order_payment_totals.total_paid, 0) >= COALESCE(order_completed_totals.completed_total, 0)'),
            OrderPaymentStatus::PartiallyPaid->value => $query
                ->whereRaw('COALESCE(order_payment_totals.total_paid, 0) > 0')
                ->whereRaw('COALESCE(order_payment_totals.total_paid, 0) < COALESCE(order_completed_totals.completed_total, 0)'),
            default => null,
        };
    }
}
