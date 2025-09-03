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
        Route::delete('/{category:uuid}', [CategoryController::class, 'destroy'])->middleware('can:delete,category');
    });

    // Order History Routes
    Route::scopeBindings()->middleware('auth:sanctum')->prefix('history')->group(function () {
        Route::get('/', [OrderHistoryController::class, 'index'])->middleware('can:viewAny,App\Models\OrderHistory');
        Route::post('/', [OrderHistoryController::class, 'store'])->middleware('can:create,App\Models\OrderHistory');
        Route::get('/{orderHistory:uuid}', [OrderHistoryController::class, 'show'])->middleware('can:view,orderHistory');
        Route::delete('/{orderHistory:uuid}', [OrderHistoryController::class, 'destroy'])->middleware('can:delete,orderHistory');

        Route::get('/{orderHistory:uuid}/order/{order:uuid}', [OrderHistoryController::class, 'order'])->middleware('can:view,orderHistory');
        Route::get('/{orderHistory:uuid}/order/{order:uuid}/attachments', [OrderHistoryController::class, 'orderAttachments'])->middleware('can:view,orderHistory');
    });

    // Order Routes
    Route::scopeBindings()->middleware('auth:sanctum')->prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->middleware('can:viewAny,App\Models\Order')->name('api.orders.index');
        Route::post('/', [OrderController::class, 'store'])->middleware('can:create,App\Models\Order')->name('api.orders.store');
        Route::get('/{order:uuid}', [OrderController::class, 'show'])->middleware('can:view,order')->name('api.orders.show');
        Route::put('/{order:uuid}', [OrderController::class, 'update'])->middleware('can:update,order')->name('api.orders.update');
        Route::delete('/{order:uuid}', [OrderController::class, 'destroy'])->middleware('can:delete,order')->name('api.orders.destroy');

        Route::post('/{order:uuid}/status', [OrderController::class, 'updateStatus'])->middleware('can:update,order')->name('api.orders.status.update');
        Route::post('/{order:uuid}/assign', [OrderController::class, 'assign'])->middleware('can:assign,order')->name('api.orders.assign');
        Route::get('/{order:uuid}/history', [OrderController::class, 'history'])->middleware('can:view,order')->name('api.orders.history');
        Route::get('/{order:uuid}/attachments', [AttachmentController::class, 'index'])->middleware('can:view,order')->name('api.orders.attachments.index');
        Route::post('/{order:uuid}/attachments', [AttachmentController::class, 'store'])->middleware('can:update,order')->name('api.orders.attachments.store');
    });

    // Attachment Routes (outside of orders prefix)
    Route::scopeBindings()->middleware('auth:sanctum')->prefix('attachments')->group(function () {
        Route::get('/{attachment:uuid}/download', [AttachmentController::class, 'download'])
            ->name('api.attachments.download');
        Route::get('/{attachment:uuid}/preview', [AttachmentController::class, 'preview'])
            ->name('api.attachments.preview');
        Route::delete('/{attachment:uuid}', [AttachmentController::class, 'destroy'])
            ->name('api.attachments.destroy');
    });

    // Health Check Routes
    Route::get('/up', [HealthController::class, 'up'])->name('health.up');
    Route::get('/health', [HealthController::class, 'health'])->name('health.check');
});

