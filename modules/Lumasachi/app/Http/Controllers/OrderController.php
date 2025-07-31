<?php

namespace Modules\Lumasachi\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Http\Requests\StoreOrderRequest;
use Modules\Lumasachi\app\Http\Requests\UpdateOrderRequest;
use Modules\Lumasachi\app\Http\Requests\UpdateOrderStatusRequest;
use Modules\Lumasachi\app\Http\Requests\AssignOrderRequest;
use Modules\Lumasachi\app\Http\Requests\UploadAttachmentRequest;
use Modules\Lumasachi\app\Http\Resources\OrderResource;
use Modules\Lumasachi\app\Http\Resources\OrderHistoryResource;
use Modules\Lumasachi\app\Http\Resources\AttachmentResource;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Get attachments for an order.
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function attachments(Order $order): JsonResponse
    {
        $attachments = $order->attachments()->with('uploadedBy')->get();
        
        return response()->json([
            'order_id' => $order->id,
            'attachments' => AttachmentResource::collection($attachments),
            'total_size' => $order->getTotalAttachmentsSize(),
            'total_size_formatted' => $order->getTotalAttachmentsSizeFormatted()
        ]);
    }

    /**
     * Upload an attachment to an order.
     *
     * @param UploadAttachmentRequest $request
     * @param Order $order
     * @return JsonResponse
     */
    public function uploadAttachment(UploadAttachmentRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();
        
        DB::beginTransaction();
        try {
            $file = $request->file('file');
            $fileName = $validated['name'] ?? $file->getClientOriginalName();
            
            // Store the file
            $path = $file->store('orders/' . $order->id, 'public');
            
            // Create attachment record
            $attachment = $order->attachments()->create([
                'file_name' => $fileName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id
            ]);
            
            // Create history record
            OrderHistory::create([
                'order_id' => $order->id,
                'status_from' => $order->status,
                'status_to' => $order->status,
                'priority_from' => $order->priority,
                'priority_to' => $order->priority,
                'description' => 'Attachment uploaded',
                'notes' => "File '{$fileName}' was uploaded",
                'created_by' => $request->user()->id
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'File uploaded successfully.',
                'attachment' => new AttachmentResource($attachment->load('uploadedBy'))
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            // Clean up uploaded file if exists
            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            
            return response()->json([
                'message' => 'Failed to upload file.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an attachment
     *
     * @param Attachment $attachment
     * @return JsonResponse
     */
    public function deleteAttachment(Attachment $attachment): JsonResponse
    {
        // Check if attachment belongs to an order
        if ($attachment->attachable_type !== 'order') {
            return response()->json([
                'message' => 'This attachment does not belong to an order.'
            ], 403);
        }
        
        $order = $attachment->attachable;
        
        // Check authorization on the order
        if ($order && !request()->user()->can('update', $order)) {
            return response()->json([
                'message' => 'Unauthorized to delete this attachment.'
            ], 403);
        }
        
        $fileName = $attachment->file_name;
        
        DB::beginTransaction();
        try {
            // Delete the physical file
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            
            // Delete the attachment record
            $attachment->delete();
            
            // Create history record
            if ($order) {
                OrderHistory::create([
                    'order_id' => $order->id,
                    'status_from' => $order->status,
                    'status_to' => $order->status,
                    'priority_from' => $order->priority,
                    'priority_to' => $order->priority,
                    'description' => 'Attachment deleted',
                    'notes' => "File '{$fileName}' was deleted",
                    'created_by' => request()->user()->id
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Attachment deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete attachment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
