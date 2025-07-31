<?php

namespace Modules\Lumasachi\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Http\Requests\StoreOrderRequest;
use Modules\Lumasachi\app\Http\Requests\UpdateOrderRequest;
use Modules\Lumasachi\app\Http\Requests\UpdateOrderStatusRequest;
use Modules\Lumasachi\app\Http\Requests\AssignOrderRequest;
use Modules\Lumasachi\app\Http\Resources\OrderResource;
use Modules\Lumasachi\app\Http\Resources\OrderHistoryResource;
use Modules\Lumasachi\app\Models\OrderHistory;
use Illuminate\Support\Facades\DB;
use App\Models\User;

final class OrderController extends Controller
{
    /**
     * Display a listing of all orders.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $orders = Order::with(['customer', 'assignedTo', 'createdBy'])->get();
        
        return response()->json(OrderResource::collection($orders));
    }

    /**
     * Store a newly created order in storage.
     *
     * @param StoreOrderRequest $request
     * @return JsonResponse
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        $order = Order::create(array_merge($validated, [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id
        ]));
        
        return response()->json([
            'message' => 'Order created successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy']))
        ], 201);
    }

    /**
     * Display the specified order.
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function show(Order $order): JsonResponse
    {
        return response()->json(
            new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy']))
        );
    }

    /**
     * Update the specified order in storage.
     *
     * @param UpdateOrderRequest $request
     * @param Order $order
     * @return JsonResponse
     */
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();
        
        $order->update(array_merge($validated, [
            'updated_by' => $request->user()->id
        ]));
        
        return response()->json([
            'message' => 'Order updated successfully.',
            'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy']))
        ]);
    }

    /**
     * Remove the specified order from storage.
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function destroy(Order $order): JsonResponse
    {
        $order->delete();
        
        return response()->json([
            'message' => 'Order deleted successfully.'
        ]);
    }

    /**
     * Update the status of an order.
     *
     * @param UpdateOrderStatusRequest $request
     * @param Order $order
     * @return JsonResponse
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();
        $oldStatus = $order->status;
        
        DB::beginTransaction();
        try {
            // Update order status
            $order->update([
                'status' => $validated['status'],
                'updated_by' => $request->user()->id
            ]);
            
            // Create history record
            OrderHistory::create([
                'order_id' => $order->id,
                'status_from' => $oldStatus,
                'status_to' => $validated['status'],
                'priority_from' => $order->priority,
                'priority_to' => $order->priority,
                'description' => 'Status changed',
                'notes' => $validated['notes'] ?? "Status changed from {$oldStatus} to {$validated['status']}",
                'created_by' => $request->user()->id
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Order status updated successfully.',
                'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy']))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update order status.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign an order to an employee.
     *
     * @param AssignOrderRequest $request
     * @param Order $order
     * @return JsonResponse
     */
    public function assign(AssignOrderRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();
        $oldAssignee = $order->assigned_to;
        
        DB::beginTransaction();
        try {
            // Update order assignment
            $order->update([
                'assigned_to' => $validated['assigned_to'],
                'updated_by' => $request->user()->id
            ]);
            
            // Create history record
            $assignee = User::find($validated['assigned_to']);
            $description = $oldAssignee 
                ? 'Order reassigned' 
                : 'Order assigned';
            
            OrderHistory::create([
                'order_id' => $order->id,
                'status_from' => $order->status,
                'status_to' => $order->status,
                'priority_from' => $order->priority,
                'priority_to' => $order->priority,
                'description' => $description,
                'notes' => $validated['notes'] ?? "Order assigned to {$assignee->full_name}",
                'created_by' => $request->user()->id
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Order assigned successfully.',
                'order' => new OrderResource($order->load(['customer', 'assignedTo', 'createdBy', 'updatedBy']))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to assign order.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the history of an order.
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function history(Order $order): JsonResponse
    {
        $history = $order->orderHistories()
            ->with(['createdBy', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'order_id' => $order->id,
            'history' => OrderHistoryResource::collection($history)
        ]);
    }

}
