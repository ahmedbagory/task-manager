<x-filament-panels::page>
    @php
        $conversations = $this->getConversations();
        $activeContact = $this->getActiveContact();
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
    @endphp

    <div
        x-data="{
            mobileConversationOpen: {{ $activeContact ? 'true' : 'false' }},
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
        class="grid gap-6 xl:grid-cols-[340px_minmax(0,1fr)]"
    >
        <section
            class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5"
            :class="{ 'hidden xl:block': mobileConversationOpen }"
        >
            <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">{{ __('Inbox') }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ __('WhatsApp Conversations') }}</h2>
                    </div>

                    <x-filament::badge :color="$bridgeStateColor">
                        {{ $bridgeStateLabel }}
                    </x-filament::badge>
                </div>

                <form method="GET" action="{{ $this->indexUrlWithoutContact() }}" class="mt-4">
                    <label for="whatsapp-search" class="sr-only">{{ __('Search conversations') }}</label>
                    <div class="relative">
                        <input
                            id="whatsapp-search"
                            type="search"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="{{ __('Search by name, phone, or message') }}"
                            class="w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-primary-400 focus:bg-white dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                    </div>
                </form>
            </div>

            <div class="max-h-[72vh] overflow-y-auto">
                @forelse ($conversations as $contact)
                    @php
                        $latestMessage = $contact->latestMessage;
                        $isActive = $activeContact?->is($contact) ?? false;
                        $previewText = trim((string) ($latestMessage?->body ?? ''));

                        if ($previewText === '' && $latestMessage?->media_rejected) {
                            $previewText = __('Rejected media');
                        } elseif ($previewText === '' && $latestMessage?->hasMedia()) {
                            $previewText = match ($latestMessage->media_type) {
                                'image' => __('Image'),
                                'document' => __('Document'),
                                'audio' => __('Audio'),
                                'video' => __('Video'),
                                'sticker' => __('Sticker'),
                                default => __('Media message'),
                            };
                        } elseif ($previewText === '') {
                            $previewText = __('No content');
                        }
                    @endphp

                    <a
                        href="{{ $this->conversationUrl($contact) }}"
                        @click="if (window.innerWidth < 1280) { mobileConversationOpen = true }"
                        class="flex items-start gap-3 border-b border-gray-100 px-5 py-4 transition hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5"
                        @class([
                            'bg-primary-50/70 dark:bg-primary-500/10' => $isActive,
                        ])
                    >
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gray-100 text-sm font-semibold text-gray-700 dark:bg-white/10 dark:text-white">
                            {{ strtoupper(mb_substr($contact->name ?: $contact->phone, 0, 1)) }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $contact->name ?: __('Unknown contact') }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" dir="ltr" style="unicode-bidi:isolate;">
                                        {{ $contact->phone }}
                                    </p>
                                </div>

                                <p class="shrink-0 text-[11px] text-gray-500 dark:text-gray-400">
                                    {{ $contact->last_message_at?->format('H:i') }}
                                </p>
                            </div>

                            <p class="mt-2 truncate text-sm text-gray-600 dark:text-gray-300">
                                {{ $previewText }}
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="p-6">
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-5 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            {{ __('No WhatsApp conversations were found for the current search.') }}
                        </div>
                    </div>
                @endforelse
            </div>
        </section>

        <section
            class="flex min-h-[72vh] flex-col overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5"
            :class="{ 'hidden xl:flex': !mobileConversationOpen }"
        >
            @if ($activeContact)
                <header class="border-b border-gray-200 bg-gradient-to-r from-white via-primary-50/40 to-white px-5 py-4 dark:border-white/10 dark:from-white/5 dark:via-primary-500/10 dark:to-white/5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-gray-200 bg-white text-gray-600 xl:hidden dark:border-white/10 dark:bg-white/10 dark:text-gray-200"
                                @click="mobileConversationOpen = false"
                            >
                                <x-filament::icon icon="heroicon-o-arrow-left" class="h-5 w-5" />
                            </button>

                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary-100 text-sm font-semibold text-primary-700 dark:bg-primary-500/20 dark:text-primary-200">
                                {{ strtoupper(mb_substr($activeContact->name ?: $activeContact->phone, 0, 1)) }}
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                                        {{ $activeContact->name ?: __('Unknown contact') }}
                                    </h2>

                                    <x-filament::badge :color="$bridgeStateColor">
                                        {{ $bridgeStateLabel }}
                                    </x-filament::badge>
                                </div>

                                <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                                    <span dir="ltr" style="unicode-bidi:isolate;">{{ $activeContact->phone }}</span>

                                    @if ($replyGroup && filled($replyGroup['group_name']))
                                        <span>{{ $replyGroup['group_name'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <a
                                href="{{ $this->conversationUrl($activeContact) }}"
                                class="inline-flex items-center gap-2 rounded-2xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/10 dark:text-white"
                            >
                                <x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4" />
                                {{ __('Refresh') }}
                            </a>

                            @if ($canManageSession)
                                <a
                                    href="{{ $sessionUrl }}"
                                    class="inline-flex items-center gap-2 rounded-2xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/10 dark:text-white"
                                >
                                    <x-filament::icon icon="heroicon-o-qr-code" class="h-4 w-4" />
                                    {{ __('Bridge Session') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </header>

                <div
                    x-ref="timeline"
                    class="flex-1 space-y-6 overflow-y-auto bg-[radial-gradient(circle_at_top,_rgba(251,191,36,0.08),_transparent_45%)] px-4 py-5 sm:px-6"
                >
                    @if ($errors->any())
                        <div class="rounded-2xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    @forelse ($groupedMessages as $group)
                        <div class="space-y-4">
                            <div class="flex justify-center">
                                <span class="rounded-full border border-gray-200 bg-white/90 px-3 py-1 text-xs font-medium text-gray-500 shadow-sm dark:border-white/10 dark:bg-gray-900/80 dark:text-gray-300">
                                    {{ $group['label'] }}
                                </span>
                            </div>

                            @foreach ($group['messages'] as $message)
                                @php
                                    $bubbleClasses = $message->isOutgoing()
                                        ? 'border-primary-200 bg-primary-50 text-gray-900 dark:border-primary-500/30 dark:bg-primary-500/15 dark:text-white'
                                        : 'border-gray-200 bg-white text-gray-900 dark:border-white/10 dark:bg-gray-900/70 dark:text-white';
                                @endphp

                                <div class="flex {{ $message->isOutgoing() ? 'justify-end' : 'justify-start' }}">
                                    <article class="group w-full max-w-3xl rounded-[28px] border px-4 py-3 shadow-sm sm:max-w-[85%] {{ $bubbleClasses }}">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                @if ($message->group_name)
                                                    <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                                        {{ $message->group_name }}
                                                    </p>
                                                @endif

                                                @if ($message->body)
                                                    <p class="mt-1 whitespace-pre-line text-sm leading-6">
                                                        {{ $message->body }}
                                                    </p>
                                                @endif
                                            </div>

                                            <p class="shrink-0 text-[11px] text-gray-500 dark:text-gray-400">
                                                {{ $this->messageTimestampLabel($message) }}
                                            </p>
                                        </div>

                                        @if ($message->media_rejected)
                                            <div class="mt-3 rounded-2xl border border-danger-200 bg-danger-50/80 p-3 text-sm text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
                                                <p class="font-medium">{{ __('Media was rejected') }}</p>
                                                <p class="mt-1">{{ $message->media_reject_reason ?: __('This file type is not allowed.') }}</p>
                                            </div>
                                        @elseif ($message->hasMedia())
                                            <div class="mt-3">
                                                @if (in_array($message->media_type, ['image', 'sticker'], true) && $message->media_url)
                                                    <a href="{{ $message->media_url }}" target="_blank" class="block overflow-hidden rounded-2xl border border-gray-200/70 dark:border-white/10">
                                                        <img
                                                            src="{{ $message->media_url }}"
                                                            alt="{{ $message->media_name ?: __('WhatsApp image') }}"
                                                            class="max-h-72 w-full object-cover"
                                                        >
                                                    </a>
                                                @elseif ($message->media_type === 'audio' && $message->media_url)
                                                    <div class="rounded-2xl border border-gray-200/70 bg-white/70 p-3 dark:border-white/10 dark:bg-white/5">
                                                        <audio controls class="w-full">
                                                            <source src="{{ $message->media_url }}" type="{{ $message->media_mime }}">
                                                        </audio>
                                                    </div>
                                                @elseif ($message->media_type === 'video' && $message->media_url)
                                                    <div class="overflow-hidden rounded-2xl border border-gray-200/70 dark:border-white/10">
                                                        <video controls class="max-h-80 w-full bg-black">
                                                            <source src="{{ $message->media_url }}" type="{{ $message->media_mime }}">
                                                        </video>
                                                    </div>
                                                @else
                                                    <div class="rounded-2xl border border-gray-200/70 bg-white/70 p-3 dark:border-white/10 dark:bg-white/5">
                                                        <div class="flex items-center justify-between gap-3">
                                                            <div class="min-w-0">
                                                                <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                                                    {{ $message->media_name ?: __('WhatsApp attachment') }}
                                                                </p>
                                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                                    {{ $message->media_type ?: __('Document') }}
                                                                    @if ($message->media_size)
                                                                        . {{ $this->mediaSizeLabel($message->media_size) }}
                                                                    @endif
                                                                </p>
                                                            </div>

                                                            @if ($message->media_url)
                                                                <a
                                                                    href="{{ $message->media_url }}"
                                                                    target="_blank"
                                                                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/10 dark:text-white"
                                                                >
                                                                    <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                                                                    {{ __('Open') }}
                                                                </a>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-wrap items-center gap-2">
                                                @if ($message->task)
                                                    <a
                                                        href="{{ $this->taskUrlForMessage($message) }}"
                                                        class="inline-flex items-center rounded-full bg-success-100 px-2.5 py-1 font-medium text-success-700 dark:bg-success-500/15 dark:text-success-200"
                                                    >
                                                        {{ __('Task #:number', ['number' => $message->task->task_number]) }}
                                                    </a>
                                                @else
                                                    <a
                                                        href="{{ $this->taskUrlForMessage($message) }}"
                                                        class="inline-flex items-center rounded-full border border-gray-200 px-2.5 py-1 font-medium text-gray-700 transition hover:border-primary-300 hover:text-primary-700 dark:border-white/10 dark:text-gray-200 dark:hover:text-primary-200"
                                                    >
                                                        {{ __('Convert to Task') }}
                                                    </a>
                                                @endif

                                                @if ($message->isOutgoing() && str_starts_with((string) $message->status, 'failed'))
                                                    <form method="POST" action="{{ route('whatsapp.messages.retry', $message) }}">
                                                        @csrf

                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center rounded-full border border-danger-200 px-2.5 py-1 font-medium text-danger-700 transition hover:bg-danger-50 dark:border-danger-500/30 dark:text-danger-200 dark:hover:bg-danger-500/10"
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
                                            <p class="mt-2 text-xs text-danger-600 dark:text-danger-300">
                                                {{ $message->failed_reason }}
                                            </p>
                                        @endif
                                    </article>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="flex h-full min-h-[24rem] items-center justify-center">
                            <div class="rounded-3xl border border-dashed border-gray-300 bg-white/80 p-8 text-center shadow-sm dark:border-white/10 dark:bg-white/5">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ __('No messages yet in this conversation.') }}</p>
                            </div>
                        </div>
                    @endforelse
                </div>

                <footer class="border-t border-gray-200 bg-white/90 px-4 py-4 backdrop-blur dark:border-white/10 dark:bg-gray-950/70 sm:px-6">
                    @if ($canSend)
                        <form
                            method="POST"
                            action="{{ route('whatsapp.messages.send') }}"
                            enctype="multipart/form-data"
                            class="space-y-3"
                            @submit="sending = true"
                        >
                            @csrf
                            <input type="hidden" name="phone" value="{{ $activeContact->phone }}">

                            @if ($sendToSameGroup && $replyGroup)
                                <input type="hidden" name="group_id" value="{{ $replyGroup['group_id'] }}">
                                <input type="hidden" name="group_name" value="{{ $replyGroup['group_name'] }}">
                            @endif

                            @if ($sendToSameGroup && $replyGroup)
                                <div class="rounded-2xl border border-primary-200 bg-primary-50 px-4 py-2 text-xs text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-200">
                                    {{ __('Replies from this chat will be sent to the same WhatsApp group: :group', ['group' => $replyGroup['group_name'] ?: $replyGroup['group_id']]) }}
                                </div>
                            @endif

                            <div class="rounded-[28px] border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
                                <textarea
                                    x-ref="composer"
                                    name="body"
                                    rows="1"
                                    placeholder="{{ __('Type a message') }}"
                                    class="max-h-44 min-h-[48px] w-full resize-none border-0 bg-transparent px-2 py-2 text-sm text-gray-900 outline-none placeholder:text-gray-400 focus:ring-0 dark:text-white"
                                    @input="resize($event.target)"
                                    @keydown.enter="submitOnEnter($event)"
                                >{{ old('body') }}</textarea>

                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-3 dark:border-white/10">
                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-2xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/10 dark:text-white">
                                        <x-filament::icon icon="heroicon-o-paper-clip" class="h-4 w-4" />
                                        {{ __('Attach file') }}
                                        <input
                                            type="file"
                                            name="attachment"
                                            class="hidden"
                                            accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,audio/mpeg,audio/ogg,audio/webm,audio/mp4,video/mp4,video/webm"
                                        >
                                    </label>

                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-2 rounded-2xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="sending"
                                    >
                                        <x-filament::icon icon="heroicon-o-paper-airplane" class="h-4 w-4" />
                                        <span x-show="!sending">{{ __('Send') }}</span>
                                        <span x-show="sending">{{ __('Sending...') }}</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    @else
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            {{ __('You do not have permission to send WhatsApp messages from this inbox.') }}
                        </div>
                    @endif
                </footer>
            @else
                <div class="flex min-h-[72vh] items-center justify-center px-6">
                    <div class="max-w-md rounded-3xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center dark:border-white/10 dark:bg-white/5">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ __('Choose a conversation to view the chat thread.') }}</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('The inbox keeps the existing WhatsApp records and now displays them in a conversation-first layout.') }}</p>
                    </div>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
