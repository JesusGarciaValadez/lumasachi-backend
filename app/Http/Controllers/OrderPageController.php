<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderCapabilityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrderPageController extends Controller
{
    public function __construct(private OrderCapabilityService $capabilityService)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('Orders/Index', [
            'can_create_order' => $user->can('create', Order::class),
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
            'attachments.uploadedBy',
        ]);

        return Inertia::render('Orders/Show', [
            'order' => (new OrderResource($order))->resolve(),
            'capabilities' => $this->capabilityService->for($user, $order),
        ]);
    }
}
