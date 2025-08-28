<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderHistoryController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\HealthController;
use App\Http\Resources\UserResource;

Route::group(['prefix' => 'v1'], function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:sanctum')
        ->name('logout');

    Route::get('/user/{user:email}', function (Request $request, User $user) {
        return UserResource::make($user->load('company'));
    })->middleware('auth:sanctum');

    // Auth Routes
    Route::group(['middleware' => ['throttle:5,1']], function () {
        Route::post('register', [RegisteredUserController::class, 'store']);

        Route::post('login', [AuthenticatedSessionController::class, 'store']);

        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->name('password.email');

        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->name('password.store');

        Route::get('verify-email', EmailVerificationPromptController::class)
            ->name('verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);
    });

    Route::post('/sanctum/token', function (Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The provided credentials are incorrect.',
                ], 401);
            }

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $user->createToken($request->device_name)->plainTextToken;
    });

    // Category Routes
    Route::scopeBindings()->middleware('auth:sanctum')->prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/bulk', [CategoryController::class, 'storeBulk']);
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->middleware('can:delete,category');
    });

    // Order History Routes
    Route::scopeBindings()->middleware('auth:sanctum')->prefix('history')->group(function () {
        Route::get('/', [OrderHistoryController::class, 'index'])->middleware('can:viewAny,App\Models\OrderHistory');
        Route::post('/', [OrderHistoryController::class, 'store'])->middleware('can:create,App\Models\OrderHistory');
        Route::get('/{orderHistory}', [OrderHistoryController::class, 'show'])->middleware('can:view,orderHistory');
        Route::delete('/{orderHistory}', [OrderHistoryController::class, 'destroy'])->middleware('can:delete,orderHistory');

        Route::get('/{orderHistory}/order/{order}', [OrderHistoryController::class, 'order'])->middleware('can:view,orderHistory');
        Route::get('/{orderHistory}/order/{order}/attachments', [OrderHistoryController::class, 'orderAttachments'])->middleware('can:view,orderHistory');
    });

    // Order Routes
    Route::scopeBindings()->middleware('auth:sanctum')->prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->middleware('can:viewAny,App\Models\Order');
        Route::post('/', [OrderController::class, 'store'])->middleware('can:create,App\Models\Order');
        Route::get('/{order}', [OrderController::class, 'show'])->middleware('can:view,order');
        Route::put('/{order}', [OrderController::class, 'update'])->middleware('can:update,order');
        Route::delete('/{order}', [OrderController::class, 'destroy'])->middleware('can:delete,order');

        Route::post('/{order}/status', [OrderController::class, 'updateStatus'])->middleware('can:update,order');
        Route::post('/{order}/assign', [OrderController::class, 'assign'])->middleware('can:assign,order');
        Route::get('/{order}/history', [OrderController::class, 'history'])->middleware('can:view,order');
        Route::get('/{order}/attachments', [AttachmentController::class, 'index'])->middleware('can:view,order');
        Route::post('/{order}/attachments', [AttachmentController::class, 'store'])->middleware('can:update,order');
    });

    // Attachment Routes (outside of orders prefix)
    Route::scopeBindings()->middleware('auth:sanctum')->prefix('attachments')->group(function () {
        Route::get('/{attachment}/download', [AttachmentController::class, 'download'])
            ->name('attachments.download');
        Route::get('/{attachment}/preview', [AttachmentController::class, 'preview'])
            ->name('attachments.preview');
        Route::delete('/{attachment}', [AttachmentController::class, 'destroy']);
    });

    // Health Check Routes
    Route::get('/up', [HealthController::class, 'up'])->name('health.up');
    Route::get('/health', [HealthController::class, 'health'])->name('health.check');
});

