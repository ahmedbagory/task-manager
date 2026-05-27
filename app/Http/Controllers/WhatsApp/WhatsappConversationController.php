<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsApp\SendWhatsappConversationMessageRequest;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\Services\WhatsApp\WhatsAppMediaService;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WhatsappConversationController extends Controller
{
    public function send(
        SendWhatsappConversationMessageRequest $request,
        WhatsAppConversationService $conversationService
    ): RedirectResponse|JsonResponse {
        Gate::authorize('send', WhatsappMessage::class);

        $result = $conversationService->send(
            data: $request->validated(),
            actor: $request->user(),
            attachment: $request->file('attachment'),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => $result->sent || $result->status === 'queued_bridge',
                'status' => $result->status,
                'message' => $this->buildSendResultMessage($result),
                'whatsapp_message' => $this->formatMessage($result->messageRecord),
            ]);
        }

        if ($result->sent) {
            Notification::make()
                ->title(__('WhatsApp message sent.'))
                ->success()
                ->send();
        } elseif ($result->status === 'queued_bridge') {
            Notification::make()
                ->title(__('WhatsApp message queued.'))
                ->body(__('The message will be delivered when the bridge is available.'))
                ->warning()
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
        WhatsAppConversationService $conversationService,
        Request $request,
    ): RedirectResponse|JsonResponse {
        Gate::authorize('retry', $message);

        $result = $conversationService->retry($message, auth()->user());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => $result->sent || $result->status === 'queued_bridge',
                'status' => $result->status,
                'message' => $this->buildSendResultMessage($result),
                'whatsapp_message' => $this->formatMessage($result->messageRecord),
            ]);
        }

        if ($result->sent) {
            Notification::make()
                ->title(__('WhatsApp message sent.'))
                ->success()
                ->send();
        } elseif ($result->status === 'queued_bridge') {
            Notification::make()
                ->title(__('WhatsApp message queued for retry.'))
                ->body(__('The message will be delivered when the bridge is available.'))
                ->warning()
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

    public function poll(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WhatsappMessage::class);

        $afterId = (int) $request->query('after_id', 0);
        $contactId = (int) $request->query('contact_id', 0);
        $groupId = $request->query('group_id');

        $query = WhatsappMessage::query()
            ->with(['contact', 'task', 'sentByUser'])
            ->where('id', '>', $afterId)
            ->orderBy('id');

        if (filled($groupId)) {
            $query->where('group_id', $groupId);
        } elseif ($contactId > 0) {
            $query->where('contact_id', $contactId)->whereNull('group_id');
        } else {
            return response()->json(['messages' => []]);
        }

        $messages = $query->limit(50)->get();

        $mediaService = app(WhatsAppMediaService::class);

        return response()->json([
            'messages' => $messages->map(fn (WhatsappMessage $m) => $this->formatMessage($m, $mediaService))->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(WhatsappMessage $message, ?WhatsAppMediaService $mediaService = null): array
    {
        $mediaService ??= app(WhatsAppMediaService::class);
        $mediaUrl = $mediaService->resolveRenderableMediaUrl($message->media_path, $message->media_url);
        $mediaAvailable = $mediaService->isMediaAvailable($message->media_path, $message->media_url);
        $date = $message->received_at ?? $message->sent_at ?? $message->created_at;

        return [
            'id' => $message->id,
            'direction' => $message->direction?->value ?? 'inbound',
            'body' => $message->body,
            'status' => $message->status,
            'failed_reason' => $message->failed_reason,
            'media_type' => $message->media_type,
            'media_mime' => $message->media_mime,
            'media_name' => $message->media_name,
            'media_size' => $message->media_size,
            'media_url' => $mediaUrl,
            'media_available' => $mediaAvailable,
            'media_rejected' => (bool) $message->media_rejected,
            'media_reject_reason' => $message->media_reject_reason,
            'task_id' => $message->task_id,
            'task_number' => $message->task?->displayNumber(),
            'task_url' => $message->task_id ? route('filament.admin.resources.tasks.view', $message->task_id) : null,
            'create_task_url' => route('filament.admin.resources.tasks.create', ['whatsapp_message' => $message->id]),
            'retry_url' => route('whatsapp.messages.retry', $message),
            'sender_name' => $message->isOutgoing()
                ? ($message->sentByUser?->name ?? null)
                : ($message->contact?->name ?? $message->from_phone),
            'timestamp' => $date?->format('H:i'),
            'date' => $date?->format('Y-m-d'),
        ];
    }

    private function buildSendResultMessage($result): string
    {
        if ($result->sent) {
            return (string) __('WhatsApp message sent.');
        }

        if ($result->status === 'queued_bridge') {
            return (string) __('WhatsApp message queued.');
        }

        return $result->messageRecord->failed_reason ?: (string) __('Unable to send the WhatsApp message.');
    }
}
