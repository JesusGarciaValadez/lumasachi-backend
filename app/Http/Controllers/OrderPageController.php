<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderDispositionStatus;
use App\Enums\OrderLifecycleStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderPriority;
use App\Http\Requests\IndexOrderRequest;
use App\Http\Resources\OrderAdministrationListResource;
use App\Http\Resources\OrderResource;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderAdministrationQuery;
use App\Services\OrderCapabilityService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class OrderPageController extends Controller
{
    public function __construct(private OrderCapabilityService $capabilityService)
    {
    }

    public function index(IndexOrderRequest $request, OrderAdministrationQuery $query): Response
    {
        $actor = $this->actor($request);
        $filters = $request->validated();

        return Inertia::render('Orders/Index', [
            'orders' => $this->paginatedOrders($query->paginate($actor, $filters), $request),
            'filters' => $this->filters($filters),
            'options' => $this->options($actor, $query),
            'can_create_order' => $actor->can('create', Order::class),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Orders/Create');
    }

    public function show(Request $request, Order $order): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $order->load([
            'customer',
            'assignedTo',
            'createdBy',
            'updatedBy',
            'motorInfo',
            'items.components',
            'services.catalogItem',
            'orderHistories.createdBy',
            'payments.createdBy',
            'refunds.requestedBy',
            'refunds.approvedBy',
            'refunds.rejectedBy',
            'refunds.processedBy',
            'attachments.uploadedBy',
        ]);

        return Inertia::render('Orders/Show', [
            'order' => (new OrderResource($order))->resolve(),
            'capabilities' => $this->capabilityService->for($user, $order),
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * @param LengthAwarePaginator<int, Order> $paginator
     * @return array<string, mixed>
     */
    private function paginatedOrders(LengthAwarePaginator $paginator, Request $request): array
    {
        $links = $paginator instanceof ConcreteLengthAwarePaginator
            ? $paginator->linkCollection()->all()
            : [];

        return [
            'data' => collect($paginator->items())
                ->map(fn(Order $order): array => (new OrderAdministrationListResource($order))->resolve($request))
                ->values()
                ->all(),
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'links' => collect($links)
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
            'title' => $filters['title'] ?? '',
            'company_id' => $filters['company_id'] ?? '',
            'assigned_to' => $filters['assigned_to'] ?? '',
            'priority' => $filters['priority'] ?? '',
            'lifecycle_status' => $filters['lifecycle_status'] ?? '',
            'payment_status' => $filters['payment_status'] ?? '',
            'disposition_status' => $filters['disposition_status'] ?? '',
            'created_from' => $filters['created_from'] ?? '',
            'created_to' => $filters['created_to'] ?? '',
            'per_page' => (int)($filters['per_page'] ?? 10),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(User $actor, OrderAdministrationQuery $query): array
    {
        return [
            'companies' => $query->companies($actor)
                ->map(fn(Company $company): array => [
                    'id' => $company->id,
                    'uuid' => $company->uuid,
                    'name' => $company->name,
                ])
                ->all(),
            'assignees' => $query->assignees($actor)
                ->map(fn(User $assignee): array => [
                    'id' => $assignee->id,
                    'uuid' => $assignee->uuid,
                    'first_name' => $assignee->first_name,
                    'last_name' => $assignee->last_name,
                    'full_name' => $assignee->last_name . ', ' . $assignee->first_name,
                ])
                ->all(),
            'priorities' => OrderPriority::getPriorities(),
            'lifecycle_statuses' => OrderLifecycleStatus::getStatuses(),
            'payment_statuses' => array_column(OrderPaymentStatus::cases(), 'value'),
            'disposition_statuses' => array_column(OrderDispositionStatus::cases(), 'value'),
            'per_page' => [10, 20, 50],
        ];
    }
}
