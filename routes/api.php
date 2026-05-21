<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\MyTaskController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('api.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');

        Route::prefix('my-tasks')->group(function (): void {
            Route::get('/', [MyTaskController::class, 'index'])->name('api.my-tasks.index');
            Route::get('/{task}', [MyTaskController::class, 'show'])->whereNumber('task')->name('api.my-tasks.show');

            Route::post('/{task}/accept', [MyTaskController::class, 'accept'])->whereNumber('task')->name('api.my-tasks.accept');
            Route::post('/{task}/start', [MyTaskController::class, 'start'])->whereNumber('task')->name('api.my-tasks.start');
            Route::post('/{task}/wait-response', [MyTaskController::class, 'waitResponse'])->whereNumber('task')->name('api.my-tasks.wait-response');
            Route::post('/{task}/resume', [MyTaskController::class, 'resume'])->whereNumber('task')->name('api.my-tasks.resume');
            Route::match(['post', 'patch'], '/{task}/status', [MyTaskController::class, 'updateStatus'])->whereNumber('task')->name('api.my-tasks.status');
            Route::post('/{task}/comment', [MyTaskController::class, 'comment'])->whereNumber('task')->name('api.my-tasks.comment');
            Route::post('/{task}/complete', [MyTaskController::class, 'complete'])->whereNumber('task')->name('api.my-tasks.complete');
            Route::post('/{task}/reject', [MyTaskController::class, 'reject'])->whereNumber('task')->name('api.my-tasks.reject');
            Route::post('/{task}/attachments', [MyTaskController::class, 'attachment'])->whereNumber('task')->name('api.my-tasks.attachments');
        });
});

Route::prefix('mobile')->name('api.mobile.')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        // Device token (FCM)
        Route::post('/device-token', [DeviceTokenController::class, 'store'])->name('device-token.store');
        Route::delete('/device-token', [DeviceTokenController::class, 'destroy'])->name('device-token.destroy');

        Route::prefix('my-tasks')->name('my-tasks.')->group(function (): void {
            Route::get('/', [MyTaskController::class, 'index'])->name('index');
            Route::get('/{task}', [MyTaskController::class, 'show'])->whereNumber('task')->name('show');

            Route::post('/{task}/accept', [MyTaskController::class, 'accept'])->whereNumber('task')->name('accept');
            Route::post('/{task}/start', [MyTaskController::class, 'start'])->whereNumber('task')->name('start');
            Route::post('/{task}/wait-response', [MyTaskController::class, 'waitResponse'])->whereNumber('task')->name('wait-response');
            Route::post('/{task}/resume', [MyTaskController::class, 'resume'])->whereNumber('task')->name('resume');
            Route::match(['post', 'patch'], '/{task}/status', [MyTaskController::class, 'updateStatus'])->whereNumber('task')->name('status');
            Route::post('/{task}/comment', [MyTaskController::class, 'comment'])->whereNumber('task')->name('comment');
            Route::post('/{task}/complete', [MyTaskController::class, 'complete'])->whereNumber('task')->name('complete');
            Route::post('/{task}/reject', [MyTaskController::class, 'reject'])->whereNumber('task')->name('reject');
            Route::post('/{task}/attachments', [MyTaskController::class, 'attachment'])->whereNumber('task')->name('attachments');
            Route::get('/{task}/attachments/{attachment}/download', [MyTaskController::class, 'downloadAttachment'])->whereNumber(['task', 'attachment'])->name('attachments.download');
        });
    });
});
