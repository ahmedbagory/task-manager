<x-filament-panels::page class="fi-height-full">
    @once
        <style>
            body:has([data-whatsapp-inbox-root]) .fi-main,
            body:has([data-whatsapp-inbox-root]) .fi-page,
            body:has([data-whatsapp-inbox-root]) .fi-page-main,
            body:has([data-whatsapp-inbox-root]) .fi-page-content {
                min-height: 0;
            }

            body:has([data-whatsapp-inbox-root]) .fi-main,
            body:has([data-whatsapp-inbox-root]) .fi-page-content {
                overflow: hidden;
            }
        </style>
    @endonce

    @php
        $conversations = $this->getConversations();
        $activeSelection = $this->getActiveSelection();
        $isGroupActive = $this->isGroupActive();
        $activeContact = $isGroupActive ? null : $this->getActiveContact();
        $activeGroupName = $isGroupActive ? $this->getActiveGroupName() : null;
        $activeGroupMembers = $isGroupActive ? $this->getActiveGroupMembersCount() : 0;
        $groupedMessages = $this->getGroupedMessages();
        $bridgeStatus = $this->getBridgeStatus();
        $canSend = $this->canSendMessages();
        $replyGroup = $this->getReplyGroup();
        $sendToSameGroup = $this->shouldSendToSameGroup();
        $sessionUrl = \App\Filament\Pages\WhatsAppSession::getUrl();
        $canManageSession = auth()->user()?->can('settings.api.manage') ?? false;
        $bridgeState = $bridgeStatus['state'] ?? 'disconnected';
        $bridgeStateColor = match ($bridgeState) {
            'connected' => 'success',
            'qr_pending' => 'warning',
            default => 'danger',
        };
        $bridgeStateLabel = match ($bridgeState) {
            'connected' => __('Connected'),
            'qr_pending' => __('QR Pending'),
            default => __('Disconnected'),
        };
        $hasActiveConversation = $activeContact || $isGroupActive;
    @endphp

    <div
        x-data="{
            mobileConversationOpen: {{ $hasActiveConversation ? 'true' : 'false' }},
            sending: false,
            resize(el) {
                el.style.height = '0px';
                el.style.height = Math.min(el.scrollHeight, 180) + 'px';
            },
            submitOnEnter(event) {
                if (event.shiftKey) {
                    return;
                }

                event.preventDefault();
                event.target.form?.requestSubmit();
            },
            scrollToBottom() {
                this.$nextTick(() => {
                    if (this.$refs.timeline) {
                        this.$refs.timeline.scrollTop = this.$refs.timeline.scrollHeight;
                    }
                });
            }
        }"
        x-init="scrollToBottom()"
        data-whatsapp-inbox-root
        class="grid h-[calc(100dvh-clamp(9rem,12vw,12rem))] min-h-0 gap-4 overflow-hidden xl:grid-cols-[340px_minmax(0,1fr)]"
    >
        {{-- === SIDEBAR: Conversation List === --}}
        <section
            class="flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5"
            :class="{ 'hidden xl:flex': mobileConversationOpen }"
        >
            <div class="shrink-0 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ __('Inbox') }}</p>
                        <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ __('WhatsApp Conversations') }}</h2>
                    </div>

                    <x-filament::badge :color="$bridgeStateColor">
                        {{ $bridgeStateLabel }}
                    </x-filament::badge>
                </div>

                <form method="GET" action="{{ $this->indexUrlWithoutContact() }}" class="mt-3">
                    <label for="whatsapp-search" class="sr-only">{{ __('Search conversations') }}</label>
                    <div class="relative">
                        <input
                            id="whatsapp-search"
                            type="search"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="{{ __('Search by name, phone, or message') }}"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-[13px] text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-primary-400 focus:bg-white dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                    </div>
                </form>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                @forelse ($conversations as $convItem)
                    @php
                        $isActive = $this->isConversationActive($convItem);
                        $isGroup = $convItem['type'] === 'group';
                    @endphp

                    <a
                        href="{{ $this->conversationItemUrl($convItem) }}"
                        @click="if (window.innerWidth < 1280) { mobileConversationOpen = true }"
                        class="flex items-start gap-2.5 border-b border-gray-100 px-4 py-3 transition hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5"
                        @class([
                            'bg-primary-50/70 dark:bg-primary-500/10' => $isActive,
                        ])
                    >
                        {{-- Avatar --}}
                        @if ($isGroup)
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200">
                                <x-filament::icon icon="heroicon-o-user-group" class="h-5 w-5" />
                            </div>
                        @else
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-[13px] font-semibold text-gray-700 dark:bg-white/10 dark:text-white">
                                {{ $convItem['avatar'] }}
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <p class="truncate text-[14px] font-semibold leading-5 text-gray-950 dark:text-white">
                                            {{ $convItem['name'] }}
                                        </p>
                                        @if ($isGroup)
                                            <span class="shrink-0 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200">
                                                {{ __('Group') }}
                                            </span>
                                        @endif
                                    </div>
                                    @if (! $isGroup && $convItem['phone'])
                                        <p class="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400" dir="ltr" style="unicode-bidi:isolate;">
                                            {{ $convItem['phone'] }}
                                        </p>
                                    @elseif ($isGroup && $convItem['members_count'] > 0)
                                        <p class="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                                            {{ trans_choice(':count member|:count members', $convItem['members_count'], ['count' => $convItem['members_count']]) }}
                                        </p>
                                    @endif
                                </div>

                                <p class="shrink-0 pt-0.5 text-[10px] text-gray-500 dark:text-gray-400">
                                    {{ $convItem['last_message_at']?->format('H:i') }}
                                </p>
                            </div>

                            <p class="mt-1.5 truncate text-[13px] leading-5 text-gray-600 dark:text-gray-300">
                                {{ $convItem['preview'] }}
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="p-4">
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-[13px] text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            {{ __('No WhatsApp conversations were found for the current search.') }}
                        </div>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- === MAIN PANEL: Chat Thread === --}}
        <section
            class="flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5"
            :class="{ 'hidden xl:flex': !mobileConversationOpen }"
        >
            @if ($hasActiveConversation)
                {{-- Header --}}
                <header class="shrink-0 border-b border-gray-200 bg-gradient-to-r from-white via-primary-50/40 to-white px-4 py-3 dark:border-white/10 dark:from-white/5 dark:via-primary-500/10 dark:to-white/5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-2.5">
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 xl:hidden dark:border-white/10 dark:bg-white/10 dark:text-gray-200"
                                @click="mobileConversationOpen = false"
                            >
                                <x-filament::icon icon="heroicon-o-arrow-left" class="h-4 w-4" />
                            </button>

                            @if ($isGroupActive)
                                {{-- Group header avatar --}}
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200">
                                    <x-filament::icon icon="heroicon-o-user-group" class="h-5 w-5" />
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <h2 class="truncate text-base font-semibold text-gray-950 dark:text-white">
                                            {{ $activeGroupName ?: __('Unnamed group') }}
                                        </h2>

                                        <span class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200">
                                            {{ __('Group') }}
                                        </span>

                                        <x-filament::badge :color="$bridgeStateColor">
                                            {{ $bridgeStateLabel }}
                                        </x-filament::badge>
                                    </div>

                                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[12px] text-gray-500 dark:text-gray-400">
                                        @if ($activeGroupMembers > 0)
                                            <span>{{ trans_choice(':count member|:count members', $activeGroupMembers, ['count' => $activeGroupMembers]) }}</span>
                                        @endif
                                    </div>
                                </div>
                            @else
                                {{-- Contact header avatar --}}
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-[14px] font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-200">
                                    {{ strtoupper(mb_substr($activeContact->name ?: $activeContact->phone, 0, 1)) }}
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <h2 class="truncate text-base font-semibold text-gray-950 dark:text-white">
                                            {{ $activeContact->name ?: __('Unknown contact') }}
                                        </h2>

                                        <x-filament::badge :color="$bridgeStateColor">
                                            {{ $bridgeStateLabel }}
                                        </x-filament::badge>
                                    </div>

                                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[12px] text-gray-500 dark:text-gray-400">
                                        <span dir="ltr" style="unicode-bidi:isolate;">{{ $activeContact->phone }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @php
                                $refreshUrl = $isGroupActive
                                    ? $this->conversationItemUrl(['type' => 'group', 'id' => $this->getActiveGroupId()])
                                    : $this->conversationUrl($activeContact);
                            @endphp
                            <a
                                href="{{ $refreshUrl }}"
                                class="inline-flex h-8 items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-2.5 text-[12px] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/10 dark:text-white"
                            >
                                <x-filament::icon icon="heroicon-o-arrow-path" class="h-3.5 w-3.5" />
                                {{ __('Refresh') }}
                            </a>

                            @if ($canManageSession)
                                <a
                                    href="{{ $sessionUrl }}"
                                    class="inline-flex h-8 items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-2.5 text-[12px] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/10 dark:text-white"
                                >
                                    <x-filament::icon icon="heroicon-o-qr-code" class="h-3.5 w-3.5" />
                                    {{ __('Bridge Session') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </header>

                {{-- Messages timeline --}}
                <div
                    x-ref="timeline"
                    class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain bg-[radial-gradient(circle_at_top,_rgba(251,191,36,0.08),_transparent_45%)] px-3 py-4 sm:px-4"
                >
                    @if ($errors->any())
                        <div class="rounded-xl border border-danger-200 bg-danger-50 px-3 py-2.5 text-[13px] text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    @forelse ($groupedMessages as $group)
                        <div class="space-y-3">
                            <div class="flex justify-center">
                                <span class="rounded-full border border-gray-200 bg-white/90 px-2.5 py-0.5 text-[10px] font-medium text-gray-500 shadow-sm dark:border-white/10 dark:bg-gray-900/80 dark:text-gray-300">
                                    {{ $group['label'] }}
                                </span>
                            </div>

                            @foreach ($group['messages'] as $message)
                                @php
                                    $bubbleClasses = $message->isOutgoing()
                                        ? 'rounded-2xl rounded-tr-md border-primary-200 bg-primary-50 text-gray-900 dark:border-primary-500/30 dark:bg-primary-500/15 dark:text-white'
                                        : 'rounded-2xl rounded-tl-md border-gray-200 bg-white text-gray-900 dark:border-white/10 dark:bg-gray-900/70 dark:text-white';
                                    $mediaUrl = $this->messageMediaUrl($message);
                                    $mediaAvailable = $this->messageMediaIsAvailable($message);
                                    $senderName = $this->messageSenderName($message);
                                @endphp

                                <div class="flex {{ $message->isOutgoing() ? 'justify-end' : 'justify-start' }}">
                                    <article class="group w-full max-w-[92%] border px-3 py-2.5 shadow-sm sm:w-auto sm:max-w-[65%] {{ $bubbleClasses }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                {{-- Sender name for group messages --}}
                                                @if ($senderName)
                                                    <p class="mb-1 text-[11px] font-semibold {{ $message->isOutgoing() ? 'text-primary-600 dark:text-primary-300' : 'text-emerald-600 dark:text-emerald-300' }}">
                                                        {{ $senderName }}
                                                    </p>
                                                @endif

                                                @if ($message->body)
                                                    <p class="whitespace-pre-line text-[13px] leading-5 sm:text-[14px]">
                                                        {{ $message->body }}
                                                    </p>
                                                @endif
                                            </div>

                                            <p class="shrink-0 pt-0.5 text-[10px] text-gray-500 dark:text-gray-400">
                                                {{ $this->messageTimestampLabel($message) }}
                                            </p>
                                        </div>

                                        @if ($message->media_rejected)
                                            <div class="mt-2.5 rounded-xl border border-danger-200 bg-danger-50/80 p-2.5 text-[12px] text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
                                                <p class="font-medium">{{ __('Media was rejected') }}</p>
                                                <p class="mt-1">{{ $message->media_reject_reason ?: __('This file type is not allowed.') }}</p>
                                            </div>
                                        @elseif ($message->hasMedia())
                                            <div class="mt-2.5">
                                                @if ($mediaAvailable && in_array($message->media_type, ['image', 'sticker'], true) && $mediaUrl)
                                                    <a href="{{ $mediaUrl }}" target="_blank" class="block overflow-hidden rounded-xl border border-gray-200/70 dark:border-white/10">
                                                        <img
                                                            src="{{ $mediaUrl }}"
                                                            alt="{{ $message->media_name ?: __('WhatsApp image') }}"
                                                            class="max-h-64 max-w-full rounded-xl object-contain"
                                                            loading="lazy"
                                                        >
                                                    </a>
                                                @elseif ($mediaAvailable && $message->media_type === 'audio' && $mediaUrl)
                                                    <div class="rounded-xl border border-gray-200/70 bg-white/70 p-2.5 dark:border-white/10 dark:bg-white/5">
                                                        <audio controls class="w-full">
                                                            <source src="{{ $mediaUrl }}" type="{{ $message->media_mime }}">
                                                        </audio>
                                                    </div>
                                                @elseif ($mediaAvailable && $message->media_type === 'video' && $mediaUrl)
                                                    <div class="overflow-hidden rounded-xl border border-gray-200/70 dark:border-white/10">
                                                        <video controls class="max-h-72 w-full bg-black">
                                                            <source src="{{ $mediaUrl }}" type="{{ $message->media_mime }}">
                                                        </video>
                                                    </div>
                                                @else
                                                    <div class="rounded-xl border border-gray-200/70 bg-white/70 p-2.5 dark:border-white/10 dark:bg-white/5">
                                                        <div class="flex items-center justify-between gap-3">
                                                            <div class="min-w-0">
                                                                <p class="truncate text-[13px] font-semibold text-gray-950 dark:text-white">
                                                                    {{ $message->media_name ?: __('WhatsApp attachment') }}
                                                                </p>
                                                                <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                                                    {{ $message->media_type ?: __('Document') }}
                                                                    @if ($message->media_size)
                                                                        . {{ $this->mediaSizeLabel($message->media_size) }}
                                                                    @endif
                                                                </p>
                                                            </div>

                                                            @if ($mediaUrl)
                                                                <a
                                                                    href="{{ $mediaUrl }}"
                                                                    target="_blank"
                                                                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 text-[11px] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/10 dark:text-white"
                                                                >
                                                                    <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-3.5 w-3.5" />
                                                                    {{ __('Open') }}
                                                                </a>
                                                            @endif
                                                        </div>

                                                        @if (! $mediaAvailable)
                                                            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                                                                {{ __('This file is not currently available from local storage.') }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2 text-[10px] text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-wrap items-center gap-2">
                                                @if ($message->task)
                                                    <a
                                                        href="{{ $this->taskUrlForMessage($message) }}"
                                                        class="inline-flex items-center rounded-full bg-success-100 px-2 py-0.5 text-[10px] font-medium text-success-700 dark:bg-success-500/15 dark:text-success-200"
                                                    >
                                                        {{ __('Task #:number', ['number' => $message->task->task_number]) }}
                                                    </a>
                                                @else
                                                    <a
                                                        href="{{ $this->taskUrlForMessage($message) }}"
                                                        class="inline-flex items-center rounded-full border border-gray-200 px-2 py-0.5 text-[10px] font-medium text-gray-700 transition hover:border-primary-300 hover:text-primary-700 dark:border-white/10 dark:text-gray-200 dark:hover:text-primary-200"
                                                    >
                                                        {{ __('Convert to Task') }}
                                                    </a>
                                                @endif

                                                @if ($message->isOutgoing() && str_starts_with((string) $message->status, 'failed'))
                                                    <form method="POST" action="{{ route('whatsapp.messages.retry', $message) }}">
                                                        @csrf

                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center rounded-full border border-danger-200 px-2 py-0.5 text-[10px] font-medium text-danger-700 transition hover:bg-danger-50 dark:border-danger-500/30 dark:text-danger-200 dark:hover:bg-danger-500/10"
                                                        >
                                                            {{ __('Retry') }}
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>

                                            @if ($message->isOutgoing())
                                                <span>{{ $this->messageStatusLabel($message->status) }}</span>
                                            @endif
                                        </div>

                                        @if ($message->failed_reason && $message->isOutgoing())
                                            <p class="mt-1.5 text-[11px] text-danger-600 dark:text-danger-300">
                                                {{ $message->failed_reason }}
                                            </p>
                                        @endif
                                    </article>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="flex h-full min-h-[24rem] items-center justify-center">
                            <div class="rounded-2xl border border-dashed border-gray-300 bg-white/80 p-6 text-center shadow-sm dark:border-white/10 dark:bg-white/5">
                                <p class="text-[13px] font-medium text-gray-700 dark:text-gray-200">{{ __('No messages yet in this conversation.') }}</p>
                            </div>
                        </div>
                    @endforelse
                </div>

                {{-- Footer / Composer --}}
                <footer class="shrink-0 border-t border-gray-200 bg-white/90 px-3 py-3 backdrop-blur dark:border-white/10 dark:bg-gray-950/70 sm:px-4">
                    @if ($canSend)
                        <form
                            method="POST"
                            action="{{ route('whatsapp.messages.send') }}"
                            enctype="multipart/form-data"
                            class="space-y-3"
                            @submit="sending = true"
                        >
                            @csrf

                            @if ($isGroupActive)
                                {{-- Group conversation: send to group --}}
                                <input type="hidden" name="group_id" value="{{ $this->getActiveGroupId() }}">
                                <input type="hidden" name="group_name" value="{{ $activeGroupName }}">
                                <input type="hidden" name="phone" value="{{ $this->getGroupSendPhone() }}">

                                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-[11px] text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200">
                                    <x-filament::icon icon="heroicon-o-user-group" class="inline h-3.5 w-3.5" />
                                    {{ __('Sending to group: :group', ['group' => $activeGroupName ?: $this->getActiveGroupId()]) }}
                                </div>
                            @else
                                <input type="hidden" name="phone" value="{{ $activeContact->phone }}">

                                @if ($sendToSameGroup && $replyGroup)
                                    <input type="hidden" name="group_id" value="{{ $replyGroup['group_id'] }}">
                                    <input type="hidden" name="group_name" value="{{ $replyGroup['group_name'] }}">

                                    <div class="rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-[11px] text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-200">
                                        {{ __('Replies from this chat will be sent to the same WhatsApp group: :group', ['group' => $replyGroup['group_name'] ?: $replyGroup['group_id']]) }}
                                    </div>
                                @endif
                            @endif

                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-2.5 dark:border-white/10 dark:bg-white/5">
                                <textarea
                                    x-ref="composer"
                                    name="body"
                                    rows="1"
                                    placeholder="{{ __('Type a message') }}"
                                    class="max-h-40 min-h-[40px] w-full resize-none border-0 bg-transparent px-1.5 py-1.5 text-[13px] text-gray-900 outline-none placeholder:text-gray-400 focus:ring-0 dark:text-white"
                                    @input="resize($event.target)"
                                    @keydown.enter="submitOnEnter($event)"
                                >{{ old('body') }}</textarea>

                                <div
                                    x-data="{
                                        fileName: null,
                                        fileSize: null,
                                        fileType: null,
                                        previewUrl: null,
                                        pickFile() {
                                            this.$refs.fileInput.click();
                                        },
                                        onFileChange(event) {
                                            const file = event.target.files[0];
                                            if (!file) {
                                                this.clearFile();
                                                return;
                                            }
                                            this.fileName = file.name;
                                            this.fileSize = this.formatSize(file.size);
                                            this.fileType = file.type;

                                            if (this.previewUrl) {
                                                URL.revokeObjectURL(this.previewUrl);
                                                this.previewUrl = null;
                                            }
                                            if (file.type.startsWith('image/')) {
                                                this.previewUrl = URL.createObjectURL(file);
                                            }
                                        },
                                        clearFile() {
                                            this.fileName = null;
                                            this.fileSize = null;
                                            this.fileType = null;
                                            if (this.previewUrl) {
                                                URL.revokeObjectURL(this.previewUrl);
                                                this.previewUrl = null;
                                            }
                                            this.$refs.fileInput.value = '';
                                        },
                                        formatSize(bytes) {
                                            if (bytes < 1024) return bytes + ' B';
                                            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                                            return (bytes / 1048576).toFixed(1) + ' MB';
                                        },
                                        fileIcon() {
                                            if (!this.fileType) return 'document';
                                            if (this.fileType.startsWith('image/')) return 'photo';
                                            if (this.fileType.startsWith('video/')) return 'film';
                                            if (this.fileType.startsWith('audio/')) return 'musical-note';
                                            return 'document';
                                        }
                                    }"
                                    class="mt-2.5 space-y-2.5 border-t border-gray-200 pt-2.5 dark:border-white/10"
                                >
                                    {{-- Attachment preview --}}
                                    <div
                                        x-show="fileName"
                                        x-cloak
                                        x-transition:enter="transition ease-out duration-150"
                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        class="flex items-start gap-2.5 rounded-xl border border-primary-200 bg-primary-50/80 p-2.5 dark:border-primary-500/25 dark:bg-primary-500/10"
                                    >
                                        {{-- Image thumbnail --}}
                                        <div
                                            x-show="previewUrl"
                                            class="shrink-0 overflow-hidden rounded-lg border border-primary-200/60 dark:border-primary-500/20"
                                        >
                                            <img :src="previewUrl" alt="" class="h-14 w-14 object-cover">
                                        </div>

                                        {{-- File icon (for non-image files) --}}
                                        <div
                                            x-show="!previewUrl"
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-500/20"
                                        >
                                            <svg x-show="fileIcon() === 'document'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-600 dark:text-primary-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                            <svg x-show="fileIcon() === 'film'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-600 dark:text-primary-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                            <svg x-show="fileIcon() === 'musical-note'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary-600 dark:text-primary-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m9 9 10.5-3m0 6.553v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 1 1-.99-3.467l2.31-.66a2.25 2.25 0 0 0 1.632-2.163Zm0 0V2.25L9 5.25v10.303m0 0v3.75a2.25 2.25 0 0 1-1.632 2.163l-1.32.377a1.803 1.803 0 0 1-.99-3.467l2.31-.66A2.25 2.25 0 0 0 9 15.553Z" /></svg>
                                        </div>

                                        {{-- File info --}}
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[12px] font-semibold text-primary-800 dark:text-primary-100" x-text="fileName"></p>
                                            <p class="mt-0.5 text-[11px] text-primary-600 dark:text-primary-300" x-text="fileSize"></p>
                                        </div>

                                        {{-- Remove button --}}
                                        <button
                                            type="button"
                                            @click="clearFile()"
                                            class="shrink-0 rounded-lg p-1 text-primary-400 transition hover:bg-primary-100 hover:text-primary-700 dark:hover:bg-primary-500/20 dark:hover:text-primary-100"
                                            title="{{ __('Remove attachment') }}"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>

                                    {{-- Actions row --}}
                                    <div class="flex flex-wrap items-center justify-between gap-2.5">
                                        <button
                                            type="button"
                                            @click="pickFile()"
                                            class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-2.5 text-[12px] font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/10 dark:text-white"
                                        >
                                            <x-filament::icon icon="heroicon-o-paper-clip" class="h-3.5 w-3.5" />
                                            <span x-text="fileName ? '{{ __('Change file') }}' : '{{ __('Attach file') }}'"></span>
                                        </button>

                                        <input
                                            x-ref="fileInput"
                                            type="file"
                                            name="attachment"
                                            class="hidden"
                                            accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,audio/mpeg,audio/ogg,audio/webm,audio/mp4,video/mp4,video/webm"
                                            @change="onFileChange($event)"
                                        >

                                        <button
                                            type="submit"
                                            class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-primary-600 px-3 text-[12px] font-semibold text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="sending"
                                        >
                                            <x-filament::icon icon="heroicon-o-paper-airplane" class="h-3.5 w-3.5" />
                                            <span x-show="!sending">{{ __('Send') }}</span>
                                            <span x-show="sending">{{ __('Sending...') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @else
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-2.5 text-[13px] text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            {{ __('You do not have permission to send WhatsApp messages from this inbox.') }}
                        </div>
                    @endif
                </footer>
            @else
                <div class="flex min-h-0 flex-1 items-center justify-center px-6">
                    <div class="max-w-md rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center dark:border-white/10 dark:bg-white/5">
                        <p class="text-[13px] font-semibold text-gray-700 dark:text-gray-200">{{ __('Choose a conversation to view the chat thread.') }}</p>
                        <p class="mt-1.5 text-[13px] text-gray-500 dark:text-gray-400">{{ __('The inbox keeps the existing WhatsApp records and now displays them in a conversation-first layout.') }}</p>
                    </div>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
