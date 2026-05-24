<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\SendWhatsappConversationMessageRequest;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppConversationService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class WhatsappConversationController extends Controller
{
    public function send(
        SendWhatsappConversationMessageRequest $request,
        WhatsAppConversationService $conversationService
    ): RedirectResponse {
        Gate::authorize('send', WhatsappMessage::class);

        $result = $conversationService->send(
            data: $request->validated(),
            actor: $request->user(),
            attachment: $request->file('attachment'),
        );

        if ($result->sent) {
            Notification::make()
                ->title(__('WhatsApp message sent.'))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('Unable to send the WhatsApp message.'))
                ->body($result->messageRecord->failed_reason ?: __('Check the bridge connection or outbound settings.'))
                ->danger()
                ->send();
        }

        return back();
    }

    public function retry(
        WhatsappMessage $message,
        WhatsAppConversationService $conversationService
    ): RedirectResponse {
        Gate::authorize('retry', $message);

        $result = $conversationService->retry($message, auth()->user());

        if ($result->sent) {
            Notification::make()
                ->title(__('WhatsApp message sent.'))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('Retry failed.'))
                ->body($result->messageRecord->failed_reason ?: __('The bridge did not accept this retry request.'))
                ->danger()
                ->send();
        }

        return back();
    }
}
