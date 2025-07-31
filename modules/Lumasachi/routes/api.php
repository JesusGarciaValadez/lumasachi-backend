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
use Modules\Lumasachi\app\Http\Controllers\OrderController;
use Modules\Lumasachi\app\Http\Controllers\AttachmentController;

Route::group(['prefix' => 'v1'], function () {
    Route::get('/user/{user}', function (Request $request, User $user) {
        return response()->json([
            'user_id' => $user->id,
            'user_found' => true,
            'user_data' => $user->toArray(),
            'user_exists' => $user->exists,
            'all_attributes' => $user->getAttributes()
        ], 200);
    })->middleware('auth:sanctum');

    // Auth Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('register', [RegisteredUserController::class, 'store']);

        Route::post('login', [AuthenticatedSessionController::class, 'store']);

        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->name('password.email');

        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->name('password.store');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('verify-email', EmailVerificationPromptController::class)
            ->name('verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
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

    // Order Routes
    Route::middleware('auth:sanctum')->prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->middleware('can:viewAny,Modules\Lumasachi\app\Models\Order');
        Route::post('/', [OrderController::class, 'store'])->middleware('can:create,Modules\Lumasachi\app\Models\Order');
        Route::get('/{order}', [OrderController::class, 'show'])->middleware('can:view,order');
        Route::put('/{order}', [OrderController::class, 'update'])->middleware('can:update,order');
        Route::delete('/{order}', [OrderController::class, 'destroy'])->middleware('can:delete,order');

        Route::post('/{order}/status', [OrderController::class, 'updateStatus'])->middleware('can:update,order');
        Route::post('/{order}/assign', [OrderController::class, 'assign'])->middleware('can:assign,order');
        Route::get('/{order}/history', [OrderController::class, 'history'])->middleware('can:view,order');
        Route::get('/{order}/attachments', [AttachmentController::class, 'index'])->middleware('can:view,order');
        Route::post('/{order}/attachments', [AttachmentController::class, 'store'])->middleware('can:update,order');

        Route::get('/stats/summary', [OrderController::class, 'stats']);
        Route::get('/stats/by-user/{user}', [OrderController::class, 'userStats']);
    });

    // Attachment Routes (outside of orders prefix)
    Route::middleware('auth:sanctum')->prefix('attachments')->group(function () {
        Route::get('/{attachment}/download', [AttachmentController::class, 'download'])
            ->name('attachments.download');
        Route::get('/{attachment}/preview', [AttachmentController::class, 'preview'])
            ->name('attachments.preview');
        Route::delete('/{attachment}', [AttachmentController::class, 'destroy']);
    });
});

