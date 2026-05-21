<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsappMessageDirection;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BridgeOutboundMessageService
{
    private const QUEUED_STATUS = 'queued_bridge';

    private const DISPATCHING_STATUS = 'dispatching_bridge';

    public function claimPendingMessages(int $limit = 10): Collection
    {
        $limit = max(1, min($limit, 20));
        $staleThreshold = now()->subMinutes(2);

        return DB::transaction(function () use ($limit, $staleThreshold): Collection {
            $messages = WhatsappMessage::query()
                ->where('direction', WhatsappMessageDirection::OUTBOUND->value)
                ->where(function ($query) use ($staleThreshold): void {
                    $query
                        ->where('status', self::QUEUED_STATUS)
                        ->orWhere(function ($staleQuery) use ($staleThreshold): void {
                            $staleQuery
                                ->where('status', self::DISPATCHING_STATUS)
                                ->where('updated_at', '<=', $staleThreshold);
                        });
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($limit)
                ->get();

            foreach ($messages as $message) {
                $message->forceFill([
                    'status' => self::DISPATCHING_STATUS,
                ])->save();
            }

            return $messages->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toBridgePayload(WhatsappMessage $message): array
    {
        $targetType = filled($message->group_id) ? 'group' : 'direct';

        return [
            'id' => $message->id,
            'task_id' => $message->task_id,
            'body' => $message->body,
            'target_type' => $targetType,
            'to' => $targetType === 'group' ? $message->group_id : $message->to_phone,
            'phone' => $message->to_phone,
            'group_id' => $message->group_id,
            'group_name' => $message->group_name,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markSent(
        WhatsappMessage $message,
        ?string $providerMessageId = null,
        ?array $responsePayload = null,
        ?string $sentTo = null
    ): WhatsappMessage {
        $rawPayload = is_array($message->raw_payload) ? $message->raw_payload : [];
        $rawPayload['bridge_delivery'] = array_filter([
            'status' => 'sent',
            'sent_to' => $sentTo,
            'response' => $responsePayload,
        ], fn (mixed $value): bool => $value !== null);

        $message->forceFill([
            'whatsapp_message_id' => $providerMessageId ?: $message->whatsapp_message_id,
            'status' => 'sent',
            'sent_at' => now(),
            'raw_payload' => $rawPayload,
        ])->save();

        return $message->refresh();
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markFailed(
        WhatsappMessage $message,
        ?string $error = null,
        ?array $responsePayload = null
    ): WhatsappMessage {
        $rawPayload = is_array($message->raw_payload) ? $message->raw_payload : [];
        $rawPayload['bridge_delivery'] = array_filter([
            'status' => 'failed',
            'error' => $error,
            'response' => $responsePayload,
        ], fn (mixed $value): bool => $value !== null);

        $message->forceFill([
            'status' => 'failed',
            'raw_payload' => $rawPayload,
        ])->save();

        return $message->refresh();
    }
}
