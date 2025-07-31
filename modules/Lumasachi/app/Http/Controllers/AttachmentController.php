<?php

namespace Modules\Lumasachi\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\Lumasachi\app\Models\Attachment;
use Modules\Lumasachi\app\Models\Order;
use Modules\Lumasachi\app\Models\OrderHistory;
use Modules\Lumasachi\app\Http\Requests\UploadAttachmentRequest;
use Modules\Lumasachi\app\Http\Resources\AttachmentResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class AttachmentController extends Controller
{
    /**
     * Get attachments for an order.
     *
     * @param Order $order
     * @return JsonResponse
     */
    public function index(Order $order): JsonResponse
    {
        // Authorization is handled by middleware
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
    public function store(UploadAttachmentRequest $request, Order $order): JsonResponse
    {
        // Authorization is handled by middleware
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
     * Download an attachment.
     *
     * @param Attachment $attachment
     * @return Response|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function download(Attachment $attachment)
    {
        // Check if user has permission to download this attachment
        if (!$this->canAccessAttachment($attachment)) {
            return response()->json([
                'message' => 'Unauthorized to download this attachment.'
            ], 403);
        }
        
        // Check if file exists
        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json([
                'message' => 'File not found.'
            ], 404);
        }
        
        // Get the file path
        $filePath = Storage::disk('public')->path($attachment->file_path);
        
        // Return file download response
        return response()->download(
            $filePath,
            $attachment->file_name,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'attachment; filename="' . $attachment->file_name . '"'
            ]
        );
    }

    /**
     * Preview an attachment (for images and PDFs).
     *
     * @param Attachment $attachment
     * @return Response|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function preview(Attachment $attachment)
    {
        // Check if user has permission to preview this attachment
        if (!$this->canAccessAttachment($attachment)) {
            return response()->json([
                'message' => 'Unauthorized to preview this attachment.'
            ], 403);
        }
        
        // Check if file can be previewed
        $previewableMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/svg+xml',
            'image/webp',
            'application/pdf'
        ];
        
        if (!in_array($attachment->mime_type, $previewableMimeTypes)) {
            return response()->json([
                'message' => 'This file type cannot be previewed.'
            ], 400);
        }
        
        // Check if file exists
        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json([
                'message' => 'File not found.'
            ], 404);
        }
        
        // Get the file path
        $filePath = Storage::disk('public')->path($attachment->file_path);
        
        // Return file response for preview
        return response()->file(
            $filePath,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="' . $attachment->file_name . '"'
            ]
        );
    }

    /**
     * Delete an attachment.
     *
     * @param Attachment $attachment
     * @return JsonResponse
     */
    public function destroy(Attachment $attachment): JsonResponse
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

    /**
     * Check if the current user can access the attachment.
     *
     * @param Attachment $attachment
     * @return bool
     */
    private function canAccessAttachment(Attachment $attachment): bool
    {
        $user = request()->user();
        
        // If attachment doesn't belong to an order, deny access
        if ($attachment->attachable_type !== 'order') {
            return false;
        }
        
        $order = $attachment->attachable;
        
        // If order doesn't exist, deny access
        if (!$order) {
            return false;
        }
        
        // Use the order policy to check if user can view the order
        return $user->can('view', $order);
    }
}
