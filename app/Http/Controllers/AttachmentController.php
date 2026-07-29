<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PublicAttachmentRequest;
use App\Http\Requests\UploadAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\User;
use App\Traits\CachesAttachments;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class AttachmentController extends Controller
{
    use CachesAttachments;

    /**
     * Get attachments for an order.
     */
    public function index(Order $order): JsonResponse
    {
        // Authorization is handled by middleware
        $filters = ['order_id' => $order->id];
        $key = self::indexKeyFor($filters);
        $hit = Cache::has($key);

        $payload = Cache::remember($key, now()->addSeconds(self::ttlIndex()), function () use ($order) {
            $attachments = $order->attachments()->with('uploadedBy')->get();

            return [
                'order_id' => $order->id,
                'attachments' => AttachmentResource::collection($attachments),
                'total_size' => $order->getTotalAttachmentsSize(),
                'total_size_formatted' => $order->getTotalAttachmentsSizeFormatted(),
            ];
        });

        return response()->json($payload)
            ->header('X-Cache', $hit ? 'HIT' : 'MISS');
    }

    /**
     * Upload an attachment to an order.
     */
    public function store(UploadAttachmentRequest $request, Order $order): JsonResponse
    {
        // Authorization is handled by middleware
        $user = $this->authenticatedUser();
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $attachments = [];

            // Determine if multiple files were provided
            $files = [];
            if ($request->hasFile('files')) {
                $uploadedFiles = $request->file('files');
                $files = is_array($uploadedFiles) ? $uploadedFiles : [$uploadedFiles];
            } elseif ($request->hasFile('file')) {
                $uploadedFile = $request->file('file');
                $files = $uploadedFile instanceof UploadedFile ? [$uploadedFile] : [];
            }

            foreach ($files as $index => $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }

                // Determine name/description per file
                $fileName = $validated['names'][$index] ?? ($validated['name'] ?? $file->getClientOriginalName());

                // Store the file
                $path = $file->store('orders/'.$order->uuid.'/'.$file->hashName(), 'public');

                // Create attachment record
                $attachment = $order->attachments()->create([
                    'uuid' => Str::uuid7()->toString(),
                    'file_name' => $fileName,
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => $user->id,
                ]);

                $attachments[] = $attachment;

                // Create history record (one per file)
                OrderHistory::create([
                    'uuid' => Str::uuid7()->toString(),
                    'order_id' => $order->id,
                    'field_changed' => 'attachments',
                    'old_value' => null,
                    'new_value' => $attachment->file_name,
                    'comment' => "File '{$fileName}' was uploaded",
                    'created_by' => $user->id,
                ]);
            }

            DB::commit();

            if (count($attachments) === 1) {
                return response()->json([
                    'code' => 'attachments.uploaded',
                    'message' => __('attachments.uploaded'),
                    'attachment' => new AttachmentResource($attachments[0]->load('uploadedBy')),
                ], 201);
            }

            // Ensure we use an Eloquent Collection to support ->load()
            $eloquentCollection = new Collection($attachments);

            return response()->json([
                'code' => 'attachments.uploaded_many',
                'message' => __('attachments.uploaded_many'),
                'attachments' => AttachmentResource::collection($eloquentCollection->load('uploadedBy')),
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'code' => 'attachments.upload_failed',
                'message' => __('attachments.upload_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download an attachment.
     */
    public function download(Attachment $attachment): JsonResponse|BinaryFileResponse
    {
        // Check if user has permission to download this attachment
        if (! $this->canAccessAttachment($attachment)) {
            return response()->json([
                'code' => 'attachments.download_unauthorized',
                'message' => __('attachments.download_unauthorized'),
            ], 403);
        }

        // Check if file exists
        if (! Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json([
                'code' => 'attachments.not_found',
                'message' => __('attachments.not_found'),
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
                'Content-Disposition' => 'attachment; filename="'.$attachment->file_name.'"',
            ]
        );
    }

    /**
     * Preview an attachment (for images and PDFs).
     */
    public function preview(Attachment $attachment): JsonResponse|BinaryFileResponse
    {
        // Check if user has permission to preview this attachment
        if (! $this->canAccessAttachment($attachment)) {
            return response()->json([
                'code' => 'attachments.preview_unauthorized',
                'message' => __('attachments.preview_unauthorized'),
            ], 403);
        }

        // Check if file can be previewed
        $previewableMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/svg+xml',
            'image/webp',
            'application/pdf',
        ];

        if (! in_array($attachment->mime_type, $previewableMimeTypes)) {
            return response()->json([
                'code' => 'attachments.not_previewable',
                'message' => __('attachments.not_previewable'),
            ], 400);
        }

        // Check if file exists
        if (! Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json([
                'code' => 'attachments.not_found',
                'message' => __('attachments.not_found'),
            ], 404);
        }

        // Get the file path
        $filePath = Storage::disk('public')->path($attachment->file_path);

        return response()->file(
            $filePath,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="'.$attachment->file_name.'"',
            ]
        );
    }

    /**
     * Download an attachment through the public order-tracking contract.
     */
    public function publicDownload(PublicAttachmentRequest $request, Order $order, Attachment $attachment): JsonResponse|BinaryFileResponse
    {
        if (!$this->publicAttachmentMatchesOrder($request, $order, $attachment)) {
            return $this->publicAttachmentNotFound();
        }

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return $this->publicAttachmentNotFound();
        }

        return response()->download(
            Storage::disk('public')->path($attachment->file_path),
            $attachment->file_name,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'attachment; filename="' . $attachment->file_name . '"',
            ],
        );
    }

    /**
     * Preview an image or PDF through the public order-tracking contract.
     */
    public function publicPreview(PublicAttachmentRequest $request, Order $order, Attachment $attachment): JsonResponse|BinaryFileResponse
    {
        if (!$this->publicAttachmentMatchesOrder($request, $order, $attachment)) {
            return $this->publicAttachmentNotFound();
        }

        if (!$attachment->isImage() && !$attachment->isPdf()) {
            return response()->json([
                'code' => 'attachments.not_previewable',
                'message' => __('attachments.not_previewable'),
            ], 400);
        }

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return $this->publicAttachmentNotFound();
        }

        return response()->file(
            Storage::disk('public')->path($attachment->file_path),
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="' . $attachment->file_name . '"',
            ],
        );
    }

    /**
     * Delete an attachment.
     */
    public function destroy(Attachment $attachment): JsonResponse
    {
        // Check if attachment belongs to an order
        if ($attachment->attachable_type !== 'order') {
            return response()->json([
                'code' => 'attachments.not_belonging',
                'message' => __('attachments.not_belonging'),
            ], 403);
        }

        $order = $attachment->attachable;

        // Check authorization on the order
        $user = $this->authenticatedUser();
        if ($order instanceof Order && !$user->can('update', $order)) {
            return response()->json([
                'code' => 'attachments.delete_unauthorized',
                'message' => __('attachments.delete_unauthorized'),
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
            if ($order instanceof Order) {
                OrderHistory::create([
                    'uuid' => Str::uuid7()->toString(),
                    'order_id' => $order->id,
                    'field_changed' => 'attachments',
                    'old_value' => $attachment->file_name,
                    'new_value' => null,
                    'comment' => "File '{$fileName}' was deleted",
                    'created_by' => $user->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'code' => 'attachments.deleted',
                'message' => __('attachments.deleted'),
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'code' => 'attachments.delete_failed',
                'message' => __('attachments.delete_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if the current user can access the attachment.
     */
    private function canAccessAttachment(Attachment $attachment): bool
    {
        $user = request()->user();

        if (!$user instanceof User) {
            return false;
        }

        // If attachment doesn't belong to an order, deny access
        if ($attachment->attachable_type !== 'order') {
            return false;
        }

        $order = $attachment->attachable;

        // If order doesn't exist, deny access
        if (!$order instanceof Order) {
            return false;
        }

        // Use the order policy to check if user can view the order
        return $user->can('view', $order);
    }

    private function publicAttachmentMatchesOrder(PublicAttachmentRequest $request, Order $order, Attachment $attachment): bool
    {
        return $order->created_at?->toDateString() === $request->validated('created_date')
            && $attachment->attachable_type === (new Order)->getMorphClass()
            && $attachment->attachable_id === $order->getKey();
    }

    private function publicAttachmentNotFound(): JsonResponse
    {
        return response()->json([
            'code' => 'orders.track_not_found',
            'message' => __('orders.track_not_found'),
        ], 404);
    }

    private function authenticatedUser(): User
    {
        $user = request()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
