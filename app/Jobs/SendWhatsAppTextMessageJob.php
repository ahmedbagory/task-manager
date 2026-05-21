<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppTextMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly ?int $taskId = null,
        public readonly ?string $groupId = null,
        public readonly ?string $groupName = null,
    ) {}

    public function handle(WhatsAppClient $whatsAppClient): void
    {
        $result = $whatsAppClient->sendTextMessage(
            phone: $this->phone,
            message: $this->message,
            groupId: $this->groupId,
            groupName: $this->groupName,
        );

        if (! $this->taskId) {
            return;
        }

        WhatsappMessage::query()
            ->whereKey($result->messageRecord->id)
            ->update(['task_id' => $this->taskId]);
    }
}
