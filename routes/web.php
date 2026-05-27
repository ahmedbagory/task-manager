<?php

use App\Http\Controllers\MyTasks\MyTaskWorkspaceController;
use App\Http\Controllers\WhatsApp\WhatsappConversationController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::redirect('/admin/login', '/login');
Route::redirect('/admin', '/');

Route::post('/locale', function (Request $request) {
    $validated = $request->validate([
        'locale' => ['required', 'string', Rule::in(array_keys(config('app.supported_locales', [])))],
    ]);

    session(['locale' => $validated['locale']]);

    return back();
})->name('locale.switch');

Route::middleware('auth')->group(function (): void {
    Route::get('/my-tasks', [MyTaskWorkspaceController::class, 'index'])->name('my-tasks.index');
    Route::get('/my-tasks/{task}', [MyTaskWorkspaceController::class, 'show'])->name('my-tasks.show');

    Route::post('/my-tasks/{task}/accept', [MyTaskWorkspaceController::class, 'accept'])->name('my-tasks.accept');
    Route::post('/my-tasks/{task}/start', [MyTaskWorkspaceController::class, 'start'])->name('my-tasks.start');
    Route::post('/my-tasks/{task}/wait-response', [MyTaskWorkspaceController::class, 'waitResponse'])->name('my-tasks.wait-response');
    Route::post('/my-tasks/{task}/resume', [MyTaskWorkspaceController::class, 'resume'])->name('my-tasks.resume');
    Route::post('/my-tasks/{task}/complete', [MyTaskWorkspaceController::class, 'complete'])->name('my-tasks.complete');
    Route::post('/my-tasks/{task}/reject', [MyTaskWorkspaceController::class, 'reject'])->name('my-tasks.reject');
    Route::post('/my-tasks/{task}/confirm-resolution', [MyTaskWorkspaceController::class, 'confirmResolution'])->name('my-tasks.confirm-resolution');
    Route::post('/my-tasks/{task}/reject-resolution', [MyTaskWorkspaceController::class, 'rejectResolution'])->name('my-tasks.reject-resolution');

    Route::post('/my-tasks/{task}/comments', [MyTaskWorkspaceController::class, 'storeComment'])->name('my-tasks.comments.store');
    Route::post('/my-tasks/{task}/attachments', [MyTaskWorkspaceController::class, 'storeAttachment'])->name('my-tasks.attachments.store');
    Route::get('/my-tasks/{task}/attachments/{attachment}', [MyTaskWorkspaceController::class, 'downloadAttachment'])
        ->name('my-tasks.attachments.download');

    Route::get('/attachments/{attachment}/preview', [MyTaskWorkspaceController::class, 'previewAttachment'])
        ->name('attachments.preview');
    Route::get('/attachments/{attachment}/download', [MyTaskWorkspaceController::class, 'downloadAnyAttachment'])
        ->name('attachments.download');

    Route::get('/whatsapp/bridge-status', function () {
        return response()->json(
            app(\App\Services\WhatsApp\WhatsappBridgeStatusService::class)->current()
        );
    })->name('whatsapp.bridge-status');

    Route::get('/whatsapp/messages/poll', [WhatsappConversationController::class, 'poll'])
        ->name('whatsapp.messages.poll');

    Route::middleware('throttle:30,1')->group(function (): void {
        Route::post('/whatsapp/messages/send', [WhatsappConversationController::class, 'send'])
            ->name('whatsapp.messages.send');
        Route::post('/whatsapp/messages/{message}/retry', [WhatsappConversationController::class, 'retry'])
            ->name('whatsapp.messages.retry');
    });
});

Route::post('/webhooks/inbound-message', [WhatsAppWebhookController::class, 'manual'])->name('webhooks.inbound-message.receive');

Route::prefix('webhooks/meta/whatsapp')->group(function (): void {
    Route::get('/', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.meta.whatsapp.verify');
    Route::post('/', [WhatsAppWebhookController::class, 'receive'])->name('webhooks.meta.whatsapp.receive');
});

Route::post('/webhooks/twilio/whatsapp', [WhatsAppWebhookController::class, 'twilio'])->name('webhooks.twilio.whatsapp.receive');
Route::post('/webhooks/360dialog/whatsapp', [WhatsAppWebhookController::class, 'dialog360'])->name('webhooks.360dialog.whatsapp.receive');
Route::post('/webhooks/bridge/heartbeat', [WhatsAppWebhookController::class, 'bridgeHeartbeat'])->name('webhooks.bridge.heartbeat');
Route::get('/webhooks/bridge/outbound', [WhatsAppWebhookController::class, 'bridgeOutboundPull'])->name('webhooks.bridge.outbound.pull');
Route::post('/webhooks/bridge/outbound/{message}', [WhatsAppWebhookController::class, 'bridgeOutboundAcknowledge'])->name('webhooks.bridge.outbound.ack');

// Backward compatibility for existing Meta webhook path.
Route::prefix('webhooks/whatsapp')->group(function (): void {
    Route::get('/', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
    Route::post('/', [WhatsAppWebhookController::class, 'receive'])->name('webhooks.whatsapp.receive');
});
