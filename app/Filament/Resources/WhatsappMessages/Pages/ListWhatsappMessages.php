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
     * Unified conversation list cache (groups + direct contacts).
     *
     * Each item is an array:
     *   - type: 'group' | 'contact'
     *   - id: group_id string or contact int id
     *   - name: display name
     *   - phone: phone or null for groups
     *   - avatar: first letter for avatar
     *   - last_message_at: Carbon|null
     *   - preview: string (last message snippet)
     *   - members_count: int (for groups)
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $conversationCache = null;

    private ?WhatsappContact $activeContactCache = null;

    private ?string $activeGroupIdCache = null;

    private ?Collection $activeGroupMessagesCache = null;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Get unified conversation list: groups + direct contacts.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getConversations(): Collection
    {
        if ($this->conversationCache) {
            return $this->conversationCache;
        }

        $search = $this->getSearchTerm();

        // --- Group conversations ---
        $groupQuery = WhatsappMessage::query()
            ->whereNotNull('group_id')
            ->where('group_id', '!=', '');

        if ($search !== '') {
            $groupQuery->where(function ($q) use ($search): void {
                $q->where('group_name', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhereHas('contact', fn ($cq) => $cq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        $groupConversations = $groupQuery
            ->selectRaw('group_id, MAX(group_name) as group_name, MAX(id) as last_msg_id, MAX(COALESCE(received_at, sent_at, created_at)) as last_message_at, COUNT(*) as msg_count, COUNT(DISTINCT contact_id) as members_count')
            ->groupBy('group_id')
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get()
            ->map(function ($row) {
                $lastMsg = WhatsappMessage::query()->find($row->last_msg_id);
                $preview = trim((string) ($lastMsg?->body ?? ''));

                if ($preview === '' && $lastMsg?->hasMedia()) {
                    $preview = match ($lastMsg->media_type) {
                        'image' => __('Image'),
                        'document' => __('Document'),
                        'audio' => __('Audio'),
                        'video' => __('Video'),
                        default => __('Media message'),
                    };
                } elseif ($preview === '') {
                    $preview = __('No content');
                }

                $senderName = $lastMsg?->contact?->name;
                if ($senderName && $lastMsg->isIncoming()) {
                    $preview = $senderName.': '.$preview;
                }

                return [
                    'type' => 'group',
                    'id' => $row->group_id,
                    'name' => $row->group_name ?: __('Unnamed group'),
                    'phone' => null,
                    'avatar' => mb_substr($row->group_name ?: 'G', 0, 1),
                    'last_message_at' => $row->last_message_at ? Carbon::parse($row->last_message_at) : null,
                    'preview' => str($preview)->limit(80)->toString(),
                    'members_count' => (int) $row->members_count,
                ];
            });

        // --- Direct (non-group) contact conversations ---
        $contactQuery = WhatsappContact::query()
            ->with(['latestMessage.task'])
            ->whereHas('messages', fn ($q) => $q->whereNull('group_id'))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($search !== '') {
            $contactQuery->where(function ($builder) use ($search): void {
                $builder
                    ->where('phone', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('messages', function ($messageQuery) use ($search): void {
                        $messageQuery->whereNull('group_id')->where('body', 'like', "%{$search}%");
                    });
            });
        }

        $contactConversations = $contactQuery
            ->limit(100)
            ->get()
            ->map(function (WhatsappContact $contact) {
                $latestDirect = $contact->messages()
                    ->whereNull('group_id')
                    ->latest('id')
                    ->first();

                $preview = trim((string) ($latestDirect?->body ?? ''));

                if ($preview === '' && $latestDirect?->media_rejected) {
                    $preview = __('Rejected media');
                } elseif ($preview === '' && $latestDirect?->hasMedia()) {
                    $preview = match ($latestDirect->media_type) {
                        'image' => __('Image'),
                        'document' => __('Document'),
                        'audio' => __('Audio'),
                        'video' => __('Video'),
                        'sticker' => __('Sticker'),
                        default => __('Media message'),
                    };
                } elseif ($preview === '') {
                    $preview = __('No content');
                }

                $lastAt = $latestDirect
                    ? ($latestDirect->received_at ?? $latestDirect->sent_at ?? $latestDirect->created_at)
                    : $contact->last_message_at;

                return [
                    'type' => 'contact',
                    'id' => $contact->id,
                    'name' => $contact->name ?: __('Unknown contact'),
                    'phone' => $contact->phone,
                    'avatar' => strtoupper(mb_substr($contact->name ?: $contact->phone, 0, 1)),
                    'last_message_at' => $lastAt,
                    'preview' => str($preview)->limit(80)->toString(),
                    'members_count' => 0,
                    'contact' => $contact,
                ];
            });

        // Merge and sort by last_message_at descending
        $merged = $groupConversations->concat($contactConversations)
            ->sortByDesc(fn (array $item) => $item['last_message_at']?->getTimestamp() ?? 0)
            ->values();

        return $this->conversationCache = $merged;
    }

    /**
     * Determine which type of conversation is active.
     *
     * @return array{type: string, id: string|int}|null
     */
    public function getActiveSelection(): ?array
    {
        $groupId = request()->query('group');
        $contactId = request()->integer('contact');

        if (filled($groupId)) {
            return ['type' => 'group', 'id' => $groupId];
        }

        if ($contactId > 0) {
            return ['type' => 'contact', 'id' => $contactId];
        }

        // Default to first conversation in the list
        $conversations = $this->getConversations();
        $first = $conversations->first();

        if (! $first) {
            return null;
        }

        return ['type' => $first['type'], 'id' => $first['id']];
    }

    public function isGroupActive(): bool
    {
        return ($this->getActiveSelection()['type'] ?? '') === 'group';
    }

    public function getActiveGroupId(): ?string
    {
        $selection = $this->getActiveSelection();

        if (! $selection || $selection['type'] !== 'group') {
            return null;
        }

        return (string) $selection['id'];
    }

    public function getActiveGroupName(): ?string
    {
        $groupId = $this->getActiveGroupId();

        if (! $groupId) {
            return null;
        }

        $conv = $this->getConversations()->first(fn (array $item) => $item['type'] === 'group' && $item['id'] === $groupId);

        return $conv['name'] ?? null;
    }

    public function getActiveGroupMembersCount(): int
    {
        $groupId = $this->getActiveGroupId();

        if (! $groupId) {
            return 0;
        }

        $conv = $this->getConversations()->first(fn (array $item) => $item['type'] === 'group' && $item['id'] === $groupId);

        return (int) ($conv['members_count'] ?? 0);
    }

    public function getActiveContact(): ?WhatsappContact
    {
        if ($this->activeContactCache) {
            return $this->activeContactCache;
        }

        $selection = $this->getActiveSelection();

        if (! $selection || $selection['type'] !== 'contact') {
            return null;
        }

        $contact = WhatsappContact::query()->find($selection['id']);

        if (! $contact) {
            return null;
        }

        return $this->activeContactCache = $contact->loadMissing([
            'messages' => fn ($q) => $q->whereNull('group_id'),
            'messages.task',
            'messages.sentByUser',
        ]);
    }

    /**
     * @return Collection<int, array{label:string,messages:Collection<int, WhatsappMessage>}>
     */
    public function getGroupedMessages(): Collection
    {
        if ($this->isGroupActive()) {
            return $this->getGroupedMessagesForGroup();
        }

        return $this->getGroupedMessagesForContact();
    }

    private function getGroupedMessagesForContact(): Collection
    {
        $contact = $this->getActiveContact();

        if (! $contact) {
            return collect();
        }

        $messages = $contact->messages
            ->filter(fn (WhatsappMessage $m) => blank($m->group_id))
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

    private function getGroupedMessagesForGroup(): Collection
    {
        $groupId = $this->getActiveGroupId();

        if (! $groupId) {
            return collect();
        }

        $messages = WhatsappMessage::query()
            ->where('group_id', $groupId)
            ->with(['contact', 'task', 'sentByUser'])
            ->orderBy('id')
            ->limit(500)
            ->get();

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
        // If already viewing a group conversation, use that group
        if ($this->isGroupActive()) {
            $groupId = $this->getActiveGroupId();
            $groupName = $this->getActiveGroupName();

            return $groupId ? ['group_id' => $groupId, 'group_name' => $groupName] : null;
        }

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

    /**
     * Build URL for a conversation item (group or contact).
     */
    public function conversationItemUrl(array $item): string
    {
        if ($item['type'] === 'group') {
            $params = array_filter([
                'group' => $item['id'],
                'search' => $this->getSearchTerm(),
            ], fn (mixed $value): bool => filled($value));
        } else {
            $params = array_filter([
                'contact' => $item['id'],
                'search' => $this->getSearchTerm(),
            ], fn (mixed $value): bool => filled($value));
        }

        return WhatsappMessageResource::getUrl('index', $params);
    }

    public function indexUrlWithoutContact(): string
    {
        $params = array_filter([
            'search' => $this->getSearchTerm(),
        ], fn (mixed $value): bool => filled($value));

        return WhatsappMessageResource::getUrl('index', $params);
    }

    /**
     * Check if a conversation item is the active one.
     */
    public function isConversationActive(array $item): bool
    {
        $selection = $this->getActiveSelection();

        if (! $selection) {
            return false;
        }

        return $selection['type'] === $item['type'] && (string) $selection['id'] === (string) $item['id'];
    }

    /**
     * Get sender display name for a group message bubble.
     */
    public function messageSenderName(WhatsappMessage $message): ?string
    {
        if (! $this->isGroupActive()) {
            return null;
        }

        if ($message->isOutgoing()) {
            return $message->sentByUser?->name ?? __('You');
        }

        return $message->contact?->name ?? $message->from_phone ?? __('Unknown');
    }

    /**
     * Get a phone number to use as the "phone" hidden field when sending from a group conversation.
     * The bridge routes via group_id, but the form validation requires a phone field.
     */
    public function getGroupSendPhone(): string
    {
        $groupId = $this->getActiveGroupId();

        if (! $groupId) {
            return '';
        }

        // Get any outbound message phone or the first contact's phone
        $msg = WhatsappMessage::query()
            ->where('group_id', $groupId)
            ->whereNotNull('from_phone')
            ->where('from_phone', '!=', '')
            ->latest('id')
            ->first();

        if ($msg?->from_phone) {
            return $msg->from_phone;
        }

        // Fallback: first contact's phone from the group
        $contact = WhatsappMessage::query()
            ->where('group_id', $groupId)
            ->whereNotNull('contact_id')
            ->first()?->contact;

        return $contact?->phone ?? '';
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
