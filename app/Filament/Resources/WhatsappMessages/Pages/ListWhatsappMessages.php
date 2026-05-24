<?php

namespace App\Filament\Resources\WhatsappMessages\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\BridgeStatus;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Services\Inbound\BridgeStatusService;
use App\Services\Settings\ApiSettingsService;
use App\Services\WhatsApp\BridgeApiClient;
use App\Services\WhatsApp\WhatsAppMediaService;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ListWhatsappMessages extends ListRecords
{
    protected static string $resource = WhatsappMessageResource::class;

    protected string $view = 'filament.resources.whatsapp-messages.pages.list-whatsapp-messages';

    /**
     * @var Collection<int, WhatsappContact>|null
     */
    private ?Collection $conversationCache = null;

    private ?WhatsappContact $activeContactCache = null;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return Collection<int, WhatsappContact>
     */
    public function getConversations(): Collection
    {
        if ($this->conversationCache) {
            return $this->conversationCache;
        }

        $search = $this->getSearchTerm();

        $query = WhatsappContact::query()
            ->with(['latestMessage.task'])
            ->whereHas('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('phone', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('messages', function ($messageQuery) use ($search): void {
                        $messageQuery->where('body', 'like', "%{$search}%");
                    });
            });
        }

        return $this->conversationCache = $query
            ->limit(150)
            ->get();
    }

    public function getActiveContact(): ?WhatsappContact
    {
        if ($this->activeContactCache) {
            return $this->activeContactCache;
        }

        $selectedId = request()->integer('contact');
        $conversations = $this->getConversations();

        $contact = $selectedId > 0
            ? $conversations->firstWhere('id', $selectedId)
            : $conversations->first();

        if (! $contact) {
            return null;
        }

        return $this->activeContactCache = $contact->loadMissing([
            'messages.task',
            'messages.sentByUser',
        ]);
    }

    /**
     * @return Collection<int, array{label:string,messages:Collection<int, WhatsappMessage>}>
     */
    public function getGroupedMessages(): Collection
    {
        $contact = $this->getActiveContact();

        if (! $contact) {
            return collect();
        }

        $messages = $contact->messages
            ->sortBy(fn (WhatsappMessage $message): int => $this->messageDate($message)?->getTimestamp() ?? 0)
            ->values();

        return $messages
            ->groupBy(fn (WhatsappMessage $message): string => ($this->messageDate($message)?->format('Y-m-d')) ?: 'unknown')
            ->map(function (Collection $group, string $dateKey): array {
                return [
                    'label' => $this->dateLabel($dateKey),
                    'messages' => $group->values(),
                ];
            })
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBridgeStatus(): ?array
    {
        $liveStatus = app(BridgeApiClient::class)->getStatus();

        if (is_array($liveStatus)) {
            return $liveStatus;
        }

        $storedStatus = BridgeStatus::query()
            ->where('provider', BridgeStatusService::PROVIDER)
            ->latest('id')
            ->first();

        if (! $storedStatus) {
            return null;
        }

        return [
            'state' => $storedStatus->status ?: 'disconnected',
            'account_id' => $storedStatus->account_id,
            'account_name' => $storedStatus->account_name,
            'group_name' => $storedStatus->group_name,
            'groups_count' => (int) (($storedStatus->meta['groups_count'] ?? 0)),
            'last_heartbeat_at' => $storedStatus->last_heartbeat_at?->toIso8601String(),
            'last_message_at' => $storedStatus->last_message_at?->toIso8601String(),
        ];
    }

    public function canSendMessages(): bool
    {
        return auth()->user()?->can('send', WhatsappMessage::class) ?? false;
    }

    public function shouldSendToSameGroup(): bool
    {
        $settings = app(ApiSettingsService::class)->getWhatsAppSettings();

        return strtolower((string) ($settings['provider'] ?? 'meta')) === BridgeStatusService::PROVIDER
            && strtolower((string) ($settings['bridge_outbound_target'] ?? 'direct_phone')) === 'same_group';
    }

    /**
     * @return array{group_id:?string,group_name:?string}|null
     */
    public function getReplyGroup(): ?array
    {
        $contact = $this->getActiveContact();

        if (! $contact) {
            return null;
        }

        $latestGroupedMessage = $contact->messages
            ->filter(fn (WhatsappMessage $message): bool => filled($message->group_id))
            ->sortByDesc('id')
            ->first();

        if (! $latestGroupedMessage) {
            return null;
        }

        return [
            'group_id' => $latestGroupedMessage->group_id,
            'group_name' => $latestGroupedMessage->group_name,
        ];
    }

    public function mediaSizeLabel(?int $bytes): ?string
    {
        return app(WhatsAppMediaService::class)->humanReadableSize($bytes);
    }

    public function messageMediaUrl(WhatsappMessage $message): ?string
    {
        return app(WhatsAppMediaService::class)->resolveRenderableMediaUrl(
            $message->media_path,
            $message->media_url,
        );
    }

    public function messageMediaIsAvailable(WhatsappMessage $message): bool
    {
        return app(WhatsAppMediaService::class)->isMediaAvailable(
            $message->media_path,
            $message->media_url,
        );
    }

    public function messageTimestampLabel(WhatsappMessage $message): string
    {
        return $this->messageDate($message)?->format('H:i') ?: '--:--';
    }

    public function messageStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending', 'queued_bridge', 'dispatching_bridge' => __('Pending'),
            'sent' => __('Sent'),
            'delivered' => __('Delivered'),
            'read' => __('Read'),
            'failed', 'failed_configuration', 'failed_exception' => __('Failed'),
            default => (string) ($status ?: __('Unknown')),
        };
    }

    public function taskUrlForMessage(WhatsappMessage $message): string
    {
        if ($message->task) {
            return TaskResource::getUrl('view', ['record' => $message->task]);
        }

        return TaskResource::getUrl('create', ['whatsapp_message' => $message->id]);
    }

    public function conversationUrl(WhatsappContact $contact): string
    {
        $params = array_filter([
            'contact' => $contact->id,
            'search' => $this->getSearchTerm(),
        ], fn (mixed $value): bool => filled($value));

        return WhatsappMessageResource::getUrl('index', $params);
    }

    public function indexUrlWithoutContact(): string
    {
        $params = array_filter([
            'search' => $this->getSearchTerm(),
        ], fn (mixed $value): bool => filled($value));

        return WhatsappMessageResource::getUrl('index', $params);
    }

    private function getSearchTerm(): string
    {
        return trim((string) request()->query('search', ''));
    }

    private function dateLabel(string $dateKey): string
    {
        if ($dateKey === 'unknown') {
            return __('Unknown date');
        }

        $date = Carbon::parse($dateKey);

        if ($date->isToday()) {
            return __('Today');
        }

        if ($date->isYesterday()) {
            return __('Yesterday');
        }

        return $date->format('Y-m-d');
    }

    private function messageDate(WhatsappMessage $message): ?\Illuminate\Support\Carbon
    {
        return $message->received_at
            ?? $message->sent_at
            ?? $message->created_at;
    }
}
