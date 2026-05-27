<x-filament-panels::page class="fi-height-full">
    @once
        <style>
            body:has([data-wa-root]) .fi-main,
            body:has([data-wa-root]) .fi-page,
            body:has([data-wa-root]) .fi-page-main,
            body:has([data-wa-root]) .fi-page-content {
                min-height: 0;
            }

            body:has([data-wa-root]) .fi-main,
            body:has([data-wa-root]) .fi-page-content {
                overflow: hidden;
            }

            body:has([data-wa-root]) .fi-header {
                display: none !important;
            }

            [data-wa-root] .wa-chat-surface {
                background:
                    radial-gradient(circle at top right, rgba(16, 185, 129, 0.08), transparent 28%),
                    radial-gradient(circle at bottom left, rgba(59, 130, 246, 0.08), transparent 24%),
                    linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(248, 250, 252, 0.96));
            }

            .dark [data-wa-root] .wa-chat-surface {
                background:
                    radial-gradient(circle at top right, rgba(16, 185, 129, 0.09), transparent 28%),
                    radial-gradient(circle at bottom left, rgba(59, 130, 246, 0.10), transparent 24%),
                    linear-gradient(180deg, rgba(10, 17, 28, 0.98), rgba(12, 21, 33, 0.98));
            }

            [data-wa-root] .wa-thread-bg {
                background-image: url("data:image/svg+xml,%3Csvg width='84' height='84' viewBox='0 0 84 84' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23cbd5e1' fill-opacity='.22'%3E%3Cpath d='M42 6a4 4 0 0 1 4 4v6h6a4 4 0 1 1 0 8h-6v6a4 4 0 1 1-8 0v-6h-6a4 4 0 1 1 0-8h6v-6a4 4 0 0 1 4-4Zm-24 48a4 4 0 0 1 4 4v6h6a4 4 0 1 1 0 8h-6v6a4 4 0 1 1-8 0v-6H8a4 4 0 1 1 0-8h6v-6a4 4 0 0 1 4-4Zm48 0a4 4 0 0 1 4 4v6h6a4 4 0 1 1 0 8h-6v6a4 4 0 1 1-8 0v-6h-6a4 4 0 1 1 0-8h6v-6a4 4 0 0 1 4-4Z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            }

            .dark [data-wa-root] .wa-thread-bg {
                background-image: url("data:image/svg+xml,%3Csvg width='84' height='84' viewBox='0 0 84 84' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23334155' fill-opacity='.16'%3E%3Cpath d='M42 6a4 4 0 0 1 4 4v6h6a4 4 0 1 1 0 8h-6v6a4 4 0 1 1-8 0v-6h-6a4 4 0 1 1 0-8h6v-6a4 4 0 0 1 4-4Zm-24 48a4 4 0 0 1 4 4v6h6a4 4 0 1 1 0 8h-6v6a4 4 0 1 1-8 0v-6H8a4 4 0 1 1 0-8h6v-6a4 4 0 0 1 4-4Zm48 0a4 4 0 0 1 4 4v6h6a4 4 0 1 1 0 8h-6v6a4 4 0 1 1-8 0v-6h-6a4 4 0 1 1 0-8h6v-6a4 4 0 0 1 4-4Z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            }

            @keyframes waMsgEnter {
                from { opacity: 0; transform: translateY(12px) scale(0.97); }
                to   { opacity: 1; transform: translateY(0) scale(1); }
            }

            .wa-msg-enter {
                animation: waMsgEnter 0.28s ease-out both;
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
        $bridgeStatus = $this->getBridgeStatus() ?? [];
        $canSend = $this->canSendMessages();
        $canUseComposer = $this->canUseComposer();
        $replyGroup = $this->getReplyGroup();
        $sendToSameGroup = $this->shouldSendToSameGroup();
        $sessionUrl = \App\Filament\Pages\WhatsAppSession::getUrl();
        $contactsUrl = \App\Filament\Resources\WhatsappContacts\WhatsappContactResource::getUrl('index');
        $activeContactUrl = $this->activeContactUrl();
        $canManageSession = auth()->user()?->can('settings.api.manage') ?? false;
        $bridgeState = $bridgeStatus['state'] ?? 'disconnected';
        $hasActiveConversation = $activeContact || $isGroupActive;
        $sidebarCount = $conversations->count();
        $isRtl = __('filament-panels::layout.direction') === 'rtl';
        $localeDirection = $isRtl ? 'rtl' : 'ltr';
        $desktopLayoutClass = $isRtl ? 'xl:flex-row-reverse' : 'xl:flex-row';
        $asideBorderClass = $isRtl ? 'border-e' : 'border-s';
        $mobileBackIcon = $isRtl ? 'heroicon-o-arrow-right' : 'heroicon-o-arrow-left';
        $sendIconClass = $isRtl ? 'rotate-180' : null;
        $outgoingAlignmentClass = $isRtl ? 'justify-start' : 'justify-end';
        $incomingAlignmentClass = $isRtl ? 'justify-end' : 'justify-start';
        $contentAlignClass = $isRtl ? 'text-right' : 'text-left';
        $metaAlignClass = $isRtl ? 'text-left' : 'text-right';
        $headerBadgesAlignClass = $isRtl ? 'justify-end' : 'justify-start';
        $bidiAuto = static fn (?string $value) => \App\Support\BidiText::auto($value);
        $bidiLtr = static fn (?string $value) => \App\Support\BidiText::ltr($value);
    @endphp

    <div
        x-data="{
            mobileView: {{ $hasActiveConversation ? "'chat'" : "'list'" }},
            sending: false,
            uploadProgress: 0,
            uploading: false,
            lightboxOpen: false,
            lightboxUrl: '',
            lightboxType: 'image',
            lightboxName: '',
            bridgeState: @js($bridgeState),
            bridgeLabel: @js($bridgeStatus['label'] ?? 'غير متصل'),
            bridgeHex: @js($bridgeStatus['hex'] ?? '#ef4444'),
            bridgeHint: @js($bridgeStatus['status_hint'] ?? ''),
            bridgeCanSend: {{ ($bridgeStatus['can_send'] ?? false) ? 'true' : 'false' }},
            bridgeCanQueue: {{ ($bridgeStatus['can_queue'] ?? false) ? 'true' : 'false' }},
            bridgeComposerEnabled: {{ (($bridgeStatus['supports_bridge'] ?? false) && ($bridgeStatus['outbound_enabled'] ?? false)) ? 'true' : 'false' }},
            knownIds: new Set(),
            lastMessageId: 0,
            init() {
                window.waRetry = (url) => this.retryMessage(url);
                window.waOpenMedia = (url, type, name) => this.openMedia(url, type, name);
                this._msgCfg = {
                    outgoingAlign: @js($outgoingAlignmentClass),
                    incomingAlign: @js($incomingAlignmentClass),
                    isGroup: {{ $isGroupActive ? 'true' : 'false' }},
                    labels: {
                        fileRejected: @js(__('تم رفض الملف')),
                        task: @js(__('المهمة')),
                        convertToTask: @js(__('تحويل لمهمة')),
                        retry: @js(__('إعادة الإرسال')),
                    }
                };
                this.$refs.thread?.querySelectorAll('[data-msg-id]').forEach(el => {
                    const id = parseInt(el.dataset.msgId);
                    this.knownIds.add(id);
                    if (id > this.lastMessageId) this.lastMessageId = id;
                });
                this.scrollToBottom();
                this.pollBridge();
                this.pollMessages();
            },
            setBridge(data) {
                this.bridgeState = data.state || 'disconnected';
                this.bridgeLabel = data.label || 'غير متصل';
                this.bridgeHex = data.hex || '#ef4444';
                this.bridgeHint = data.status_hint || '';
                this.bridgeCanSend = Boolean(data.can_send);
                this.bridgeCanQueue = Boolean(data.can_queue);
                this.bridgeComposerEnabled = Boolean(data.supports_bridge) && Boolean(data.outbound_enabled);
            },
            async pollBridge() {
                try {
                    const response = await fetch('{{ route('whatsapp.bridge-status') }}', {
                        headers: { Accept: 'application/json' },
                    });

                    if (response.ok) {
                        this.setBridge(await response.json());
                    }
                } catch (_) {
                    this.setBridge({
                        state: 'disconnected',
                        label: 'غير متصل',
                        hex: '#ef4444',
                        status_hint: 'تعذر الوصول إلى حالة البريدج حاليًا.',
                    });
                }

                setTimeout(() => this.pollBridge(), 10000);
            },
            async pollMessages() {
                @if ($hasActiveConversation)
                try {
                    const params = new URLSearchParams({
                        after_id: this.lastMessageId,
                        @if ($isGroupActive)
                            group_id: @js($this->getActiveGroupId()),
                        @else
                            contact_id: @js($activeContact?->id ?? 0),
                        @endif
                    });
                    const response = await fetch('{{ route('whatsapp.messages.poll') }}?' + params, {
                        headers: { Accept: 'application/json' },
                    });
                    if (response.ok) {
                        const data = await response.json();
                        const thread = this.$refs.thread;
                        const isNearBottom = thread && (thread.scrollHeight - thread.scrollTop - thread.clientHeight < 120);
                        data.messages.forEach(msg => {
                            if (!this.knownIds.has(msg.id)) {
                                this.knownIds.add(msg.id);
                                if (msg.id > this.lastMessageId) this.lastMessageId = msg.id;
                                this.appendMessage(msg);
                            } else {
                                this.updateMessageStatus(msg);
                            }
                        });
                        if (isNearBottom && data.messages.length > 0) {
                            this.$nextTick(() => { if (thread) thread.scrollTop = thread.scrollHeight; });
                        }
                    }
                } catch (_) {}
                @endif
                setTimeout(() => this.pollMessages(), 5000);
            },
            appendMessage(msg) {
                var thread = this.$refs.thread;
                if (!thread) return;
                thread.insertAdjacentHTML('beforeend', waBuildBubble(msg, this._msgCfg));
            },
            updateMessageStatus(msg) {
                var el = this.$refs.thread ? this.$refs.thread.querySelector('[data-msg-id="' + msg.id + '"]') : null;
                if (!el) return;
                if (msg.task_id && msg.task_url) {
                    var taskLink = el.querySelector('a[href*="tasks/create"]');
                    if (taskLink) {
                        taskLink.href = msg.task_url;
                        taskLink.className = 'inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[10px] font-semibold text-emerald-700 transition hover:bg-emerald-500/20 dark:text-emerald-200';
                        taskLink.textContent = this._msgCfg.labels.task + ' ' + (msg.task_number || '');
                    }
                }
            },
            async sendMessage() {
                if (this.sending) return;
                const composerEl = this.$refs.composer;
                const fileInput = this.$refs.fileInput;
                const body = (composerEl?.value || '').trim();
                const file = fileInput?.files[0] || null;
                if (!body && !file) return;

                this.sending = true;
                this.uploading = !!file;
                this.uploadProgress = 0;

                const formData = new FormData();
                formData.append('_token', @js(csrf_token()));
                if (body) formData.append('body', body);
                if (file) formData.append('attachment', file);
                @if ($isGroupActive)
                    formData.append('group_id', @js($this->getActiveGroupId()));
                    formData.append('group_name', @js($activeGroupName));
                    formData.append('phone', @js($this->getGroupSendPhone()));
                @else
                    formData.append('phone', @js($activeContact?->phone ?? ''));
                    @if ($sendToSameGroup && $replyGroup)
                        formData.append('group_id', @js($replyGroup['group_id']));
                        formData.append('group_name', @js($replyGroup['group_name']));
                    @endif
                @endif

                try {
                    const xhr = new XMLHttpRequest();
                    const result = await new Promise((resolve, reject) => {
                        xhr.open('POST', '{{ route('whatsapp.messages.send') }}');
                        xhr.setRequestHeader('Accept', 'application/json');
                        xhr.upload.addEventListener('progress', (e) => {
                            if (e.lengthComputable) {
                                this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                            }
                        });
                        xhr.addEventListener('load', () => {
                            try { resolve(JSON.parse(xhr.responseText)); }
                            catch (_) { reject(new Error('Invalid response')); }
                        });
                        xhr.addEventListener('error', () => reject(new Error('Network error')));
                        xhr.send(formData);
                    });

                    if (result.whatsapp_message) {
                        const msg = result.whatsapp_message;
                        if (!this.knownIds.has(msg.id)) {
                            this.knownIds.add(msg.id);
                            if (msg.id > this.lastMessageId) this.lastMessageId = msg.id;
                            this.appendMessage(msg);
                        }
                    }

                    if (composerEl) { composerEl.value = ''; composerEl.style.height = ''; }
                    if (fileInput) fileInput.value = '';
                    this.fileName = null; this.fileSize = null;
                    if (this.previewUrl) { URL.revokeObjectURL(this.previewUrl); this.previewUrl = null; }
                    this.scrollToBottom();
                } catch (err) {
                    console.error('Send failed:', err);
                }

                this.sending = false;
                this.uploading = false;
                this.uploadProgress = 0;
            },
            async retryMessage(url) {
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                    });
                    if (response.ok) {
                        const result = await response.json();
                        if (result.whatsapp_message) {
                            this.updateMessageStatus(result.whatsapp_message);
                        }
                    }
                } catch (_) {}
            },
            resize(el) {
                el.style.height = '0px';
                el.style.height = Math.min(el.scrollHeight, 140) + 'px';
            },
            submitOnEnter(event) {
                if (event.shiftKey) return;
                event.preventDefault();
                this.sendMessage();
            },
            scrollToBottom() {
                this.$nextTick(() => {
                    if (this.$refs.thread) {
                        this.$refs.thread.scrollTop = this.$refs.thread.scrollHeight;
                    }
                });
            },
            openMedia(url, type, name) {
                this.lightboxUrl = url;
                this.lightboxType = type;
                this.lightboxName = name || '';
                this.lightboxOpen = true;
            },
            closeMedia() {
                this.lightboxOpen = false;
                this.lightboxUrl = '';
                this.lightboxType = 'image';
                this.lightboxName = '';
            }
        }"
        data-wa-root
        dir="{{ $localeDirection }}"
        class="wa-chat-surface flex h-[calc(100dvh-7rem)] min-h-0 overflow-hidden rounded-[1.75rem] border border-gray-200 shadow-sm dark:border-white/10 {{ $desktopLayoutClass }}"
    >
        <aside
            class="flex h-full w-full flex-col {{ $asideBorderClass }} border-gray-200/80 bg-white/90 backdrop-blur dark:border-white/10 dark:bg-[#07111d]/90 xl:w-[360px] xl:shrink-0"
            :class="{ 'hidden xl:flex': mobileView === 'chat' }"
        >
            <div class="shrink-0 border-b border-gray-200/80 px-4 py-4 dark:border-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-1 {{ $contentAlignClass }}">
                        <p class="text-[11px] font-medium tracking-[0.22em] text-emerald-600 dark:text-emerald-300">{{ __('واتساب') }}</p>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('محادثات واتساب') }}</h2>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($canManageSession)
                            <a
                                href="{{ $sessionUrl }}"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-600 dark:border-white/10 dark:text-gray-300 dark:hover:border-emerald-500/30 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                                title="{{ __('جلسة واتساب') }}"
                            >
                                <x-filament::icon icon="heroicon-o-qr-code" class="h-4 w-4" />
                            </a>
                        @endif

                        <a
                            href="{{ $contactsUrl }}"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-600 dark:border-white/10 dark:text-gray-300 dark:hover:border-emerald-500/30 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                            title="{{ __('جهات الاتصال') }}"
                        >
                            <x-filament::icon icon="heroicon-o-user-group" class="h-4 w-4" />
                        </a>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2 {{ $headerBadgesAlignClass }}">
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                        {{ $sidebarCount }} {{ __('محادثة') }}
                    </span>
                </div>

                <form method="GET" action="{{ $this->indexUrlWithoutContact() }}" class="mt-3">
                    <label class="relative block">
                        <span class="pointer-events-none absolute inset-y-0 start-3 flex items-center text-gray-400">
                            <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-4 w-4" />
                        </span>
                        <input
                            type="search"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="{{ __('ابحث بالاسم أو الرقم أو الرسالة') }}"
                            class="w-full rounded-2xl border border-gray-200 bg-gray-50 py-2 pe-3 ps-9 text-sm text-gray-900 outline-none transition focus:border-emerald-300 focus:bg-white focus:ring-0 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:border-emerald-500/40"
                        >
                    </label>
                </form>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-2 py-2">
                @forelse ($conversations as $convItem)
                    @php
                        $isActive = $this->isConversationActive($convItem);
                        $isGroup = $convItem['type'] === 'group';
                    @endphp

                    <a
                        href="{{ $this->conversationItemUrl($convItem) }}"
                        @click="if (window.innerWidth < 1280) mobileView = 'chat'"
                        class="mb-1.5 flex items-center gap-3 rounded-2xl border px-3 py-3 transition {{ $isActive ? 'border-emerald-200 bg-emerald-50 shadow-sm dark:border-emerald-500/25 dark:bg-emerald-500/10' : 'border-transparent hover:border-gray-200 hover:bg-gray-50 dark:hover:border-white/10 dark:hover:bg-white/5' }}"
                    >
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $isGroup ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-white' }}">
                            @if ($isGroup)
                                <x-filament::icon icon="heroicon-s-user-group" class="h-5 w-5" />
                            @else
                                <span class="text-sm font-bold">{{ $convItem['avatar'] }}</span>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1 {{ $contentAlignClass }}">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $bidiAuto($convItem['name']) }}</p>
                                    <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400" @if (! $isGroup && filled($convItem['phone'])) dir="ltr" style="unicode-bidi:isolate" @endif>
                                        {{ $isGroup ? __('مجموعة') : ($convItem['phone'] ?: __('بدون رقم')) }}
                                    </p>
                                </div>

                                <span class="shrink-0 text-[11px] font-medium text-gray-400 dark:text-gray-500 {{ $metaAlignClass }}">
                                    {{ $convItem['last_message_at']?->format('H:i') ?? '—' }}
                                </span>
                            </div>

                            <p class="mt-2 truncate text-[12px] leading-5 text-gray-600 dark:text-gray-300">
                                {{ $bidiAuto($convItem['preview']) }}
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="flex h-full items-center justify-center px-5 py-10 text-center">
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-5 py-6 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            {{ __('لا توجد محادثات لعرضها حاليًا.') }}
                        </div>
                    </div>
                @endforelse
            </div>
        </aside>

        <main class="flex min-w-0 flex-1 flex-col" :class="{ 'hidden xl:flex': mobileView === 'list' }">
            @if ($hasActiveConversation)
                <header class="shrink-0 border-b border-gray-200/80 bg-white/85 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-[#081320]/90">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-600 dark:border-white/10 dark:text-gray-300 dark:hover:border-emerald-500/30 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200 xl:hidden"
                            @click="mobileView = 'list'"
                        >
                            <x-filament::icon :icon="$mobileBackIcon" class="h-4 w-4" />
                        </button>

                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $isGroupActive ? 'bg-emerald-500 text-white' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-white' }}">
                            @if ($isGroupActive)
                                <x-filament::icon icon="heroicon-s-user-group" class="h-5 w-5" />
                            @else
                                <span class="text-sm font-bold">{{ strtoupper(mb_substr($activeContact->name ?: $activeContact->phone, 0, 1)) }}</span>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1 {{ $contentAlignClass }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $bidiAuto($isGroupActive ? ($activeGroupName ?: __('مجموعة بدون اسم')) : ($activeContact->name ?: __('جهة اتصال غير معروفة'))) }}
                                </h3>

                                @if ($isGroupActive)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200">
                                        {{ $activeGroupMembers > 0 ? $activeGroupMembers . ' ' . __('عضو') : __('مجموعة') }}
                                    </span>
                                @endif
                            </div>

                            <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400" @if (! $isGroupActive) dir="ltr" style="unicode-bidi:isolate" @endif>
                                @if ($isGroupActive)
                                    @if (filled($this->getActiveGroupId()))
                                        {{ $bidiLtr($this->getActiveGroupId()) }}
                                    @else
                                        {{ __('بدون معرف') }}
                                    @endif
                                @else
                                    {{ $bidiLtr($activeContact->phone) }}
                                @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                class="hidden items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold text-white shadow-sm sm:inline-flex"
                                :style="'background-color:' + bridgeHex"
                            >
                                <span class="inline-block h-1.5 w-1.5 rounded-full bg-white/85" :class="bridgeCanSend && 'animate-pulse'"></span>
                                <span x-text="bridgeLabel"></span>
                            </span>

                            @if ($activeContactUrl)
                                <a
                                    href="{{ $activeContactUrl }}"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-600 dark:border-white/10 dark:text-gray-300 dark:hover:border-emerald-500/30 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                                    title="{{ __('عرض جهة الاتصال') }}"
                                >
                                    <x-filament::icon icon="heroicon-o-user" class="h-4 w-4" />
                                </a>
                            @endif

                            @if ($canManageSession)
                                <a
                                    href="{{ $sessionUrl }}"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-600 dark:border-white/10 dark:text-gray-300 dark:hover:border-emerald-500/30 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                                    title="{{ __('جلسة واتساب') }}"
                                >
                                    <x-filament::icon icon="heroicon-o-qr-code" class="h-4 w-4" />
                                </a>
                            @endif

                            @php
                                $refreshUrl = $isGroupActive
                                    ? $this->conversationItemUrl(['type' => 'group', 'id' => $this->getActiveGroupId()])
                                    : $this->conversationUrl($activeContact);
                            @endphp
                            <a
                                href="{{ $refreshUrl }}"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-600 dark:border-white/10 dark:text-gray-300 dark:hover:border-emerald-500/30 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                                title="{{ __('تحديث المحادثة') }}"
                            >
                                <x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </header>

                <div
                    x-show="!bridgeCanSend"
                    x-cloak
                    class="shrink-0 border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100"
                >
                    <div class="flex items-start gap-2">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                        <p x-text="bridgeHint"></p>
                    </div>
                </div>

                <div
                    x-ref="thread"
                    class="wa-thread-bg min-h-0 flex-1 overflow-y-auto px-3 py-4 sm:px-5"
                >
                    @if ($errors->any())
                        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-100">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    @forelse ($groupedMessages as $group)
                        <div class="mb-4 flex justify-center">
                            <span class="rounded-full bg-white/90 px-3 py-1 text-[11px] font-medium text-gray-500 shadow-sm dark:bg-[#132133] dark:text-gray-300">
                                {{ $group['label'] }}
                            </span>
                        </div>

                        @foreach ($group['messages'] as $message)
                            @php
                                $isOutgoing = $message->isOutgoing();
                                $mediaUrl = $this->messageMediaUrl($message);
                                $mediaAvailable = $this->messageMediaIsAvailable($message);
                                $senderName = $this->messageSenderName($message);
                                $bubbleClasses = $isOutgoing
                                    ? 'bg-emerald-100 text-gray-950 dark:bg-emerald-500/15 dark:text-white'
                                    : 'bg-white text-gray-950 dark:bg-[#132133] dark:text-white';
                                $timestampClasses = $isOutgoing
                                    ? 'text-emerald-700/80 dark:text-emerald-200/80'
                                    : 'text-gray-500 dark:text-gray-400';
                            @endphp

                            <div class="mb-3 flex {{ $isOutgoing ? $outgoingAlignmentClass : $incomingAlignmentClass }}" data-msg-id="{{ $message->id }}">
                                <article class="max-w-[88%] rounded-[1.4rem] border border-black/5 px-3 py-2 shadow-sm sm:max-w-[72%] {{ $bubbleClasses }}">
                                    @if ($senderName)
                                        <p class="mb-1 text-[11px] font-bold text-amber-600 dark:text-amber-300">
                                            {{ $bidiAuto($senderName) }}
                                        </p>
                                    @endif

                                    @if ($message->media_rejected)
                                        <div class="mb-2 rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-[12px] text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-100">
                                            {{ __('تم رفض الملف') }}:
                                            <span dir="auto" style="unicode-bidi:isolate">
                                                {{ $message->media_reject_reason ?: __('نوع غير مسموح') }}
                                            </span>
                                        </div>
                                    @elseif ($message->hasMedia())
                                        <div class="mb-2">
                                            @if ($mediaAvailable && in_array($message->media_type, ['image', 'sticker'], true) && $mediaUrl)
                                                <button
                                                    type="button"
                                                    class="block overflow-hidden rounded-[1.1rem] border border-black/5"
                                                    @click="openMedia(@js($mediaUrl), 'image', @js($message->media_name ?: 'صورة واتساب'))"
                                                >
                                                    <img
                                                        src="{{ $mediaUrl }}"
                                                        alt="{{ $message->media_name ?: __('صورة واتساب') }}"
                                                        class="max-h-72 w-full object-cover"
                                                        loading="lazy"
                                                    >
                                                </button>
                                            @elseif ($mediaAvailable && $message->media_type === 'video' && $mediaUrl)
                                                <button
                                                    type="button"
                                                    class="relative block overflow-hidden rounded-[1.1rem] border border-black/5 bg-slate-950"
                                                    @click="openMedia(@js($mediaUrl), 'video', @js($message->media_name ?: 'فيديو واتساب'))"
                                                >
                                                    <video class="max-h-72 w-full object-cover opacity-80" preload="metadata">
                                                        <source src="{{ $mediaUrl }}" type="{{ $message->media_mime }}">
                                                    </video>
                                                    <span class="absolute inset-0 flex items-center justify-center">
                                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-white/90 text-gray-900 shadow-lg">
                                                            <x-filament::icon icon="heroicon-s-play" class="h-5 w-5" />
                                                        </span>
                                                    </span>
                                                </button>
                                            @elseif ($mediaAvailable && $message->media_type === 'audio' && $mediaUrl)
                                                <div class="rounded-[1.1rem] bg-black/5 px-3 py-3 dark:bg-white/5">
                                                    <audio controls class="h-10 w-full min-w-[240px]">
                                                        <source src="{{ $mediaUrl }}" type="{{ $message->media_mime }}">
                                                    </audio>
                                                </div>
                                            @else
                                                <div class="rounded-[1.1rem] border border-black/5 bg-black/5 px-3 py-3 dark:bg-white/5">
                                                    <div class="flex items-center gap-3">
                                                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-gray-600 shadow-sm dark:bg-white/10 dark:text-white">
                                                            <x-filament::icon icon="heroicon-o-document" class="h-5 w-5" />
                                                        </div>

                                                        <div class="min-w-0 flex-1">
                                                            <p class="truncate text-sm font-semibold">{{ $bidiAuto($message->media_name ?: __('مرفق واتساب')) }}</p>
                                                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                                                <span dir="auto" style="unicode-bidi:isolate">{{ $message->media_type ?: __('ملف') }}</span>
                                                                @if ($message->media_size)
                                                                    • {{ $this->mediaSizeLabel($message->media_size) }}
                                                                @endif
                                                            </p>
                                                        </div>

                                                        @if ($mediaUrl)
                                                            <a
                                                                href="{{ $mediaUrl }}"
                                                                target="_blank"
                                                                class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-black/5 bg-white text-gray-600 transition hover:text-emerald-600 dark:border-white/10 dark:bg-white/10 dark:text-white dark:hover:text-emerald-200"
                                                                title="{{ __('تحميل') }}"
                                                            >
                                                                <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                                                            </a>
                                                        @endif
                                                    </div>

                                                    @if (! $mediaAvailable)
                                                        <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">{{ __('الملف غير متوفر حاليًا.') }}</p>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @if (filled($message->body))
                                        <p class="whitespace-pre-line text-[13px] leading-6" dir="auto" style="unicode-bidi:isolate">
                                            {{ $message->body }}
                                        </p>
                                    @endif

                                    <div class="mt-2 flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            @if ($message->task)
                                                <a
                                                    href="{{ $this->taskUrlForMessage($message) }}"
                                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[10px] font-semibold text-emerald-700 transition hover:bg-emerald-500/20 dark:text-emerald-200"
                                                >
                                                    <x-filament::icon icon="heroicon-o-clipboard-document-check" class="h-3.5 w-3.5" />
                                                    <span>{{ __('المهمة') }} {{ $message->task->displayNumber() }}</span>
                                                </a>
                                            @else
                                                <a
                                                    href="{{ $this->taskUrlForMessage($message) }}"
                                                    class="inline-flex items-center gap-1 rounded-full bg-black/5 px-2.5 py-1 text-[10px] font-medium text-gray-600 transition hover:bg-emerald-500/10 hover:text-emerald-700 dark:bg-white/10 dark:text-gray-300 dark:hover:text-emerald-200"
                                                >
                                                    <x-filament::icon icon="heroicon-o-arrow-path-rounded-square" class="h-3.5 w-3.5" />
                                                    <span>{{ __('تحويل لمهمة') }}</span>
                                                </a>
                                            @endif

                                            @if ($isOutgoing && str_starts_with((string) $message->status, 'failed'))
                                                <button
                                                    type="button"
                                                    @click="retryMessage('{{ route('whatsapp.messages.retry', $message) }}')"
                                                    class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2.5 py-1 text-[10px] font-semibold text-red-700 transition hover:bg-red-500/20 dark:text-red-200"
                                                >
                                                    <x-filament::icon icon="heroicon-o-arrow-path" class="h-3.5 w-3.5" />
                                                    <span>{{ __('إعادة الإرسال') }}</span>
                                                </button>
                                            @endif
                                        </div>

                                        <div class="flex items-center gap-1.5 text-[11px] {{ $timestampClasses }}">
                                            <span>{{ $this->messageTimestampLabel($message) }}</span>
                                            @if ($isOutgoing)
                                                @php
                                                    $statusIcon = match ($message->status) {
                                                        'read' => 'text-sky-400',
                                                        'delivered' => 'text-gray-500 dark:text-gray-300',
                                                        'sent' => 'text-gray-400 dark:text-gray-300',
                                                        default => str_starts_with((string) $message->status, 'failed') ? 'text-red-400' : 'text-gray-400 dark:text-gray-300',
                                                    };
                                                @endphp
                                                <svg class="h-3.5 w-3.5 {{ $statusIcon }}" viewBox="0 0 16 15" fill="currentColor" title="{{ $this->messageStatusLabel($message->status) }}">
                                                    @if (in_array($message->status, ['delivered', 'read']))
                                                        <path d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.88a.32.32 0 0 1-.484.033l-.358-.325a.32.32 0 0 0-.484.033l-.378.456a.32.32 0 0 0 .04.456l1.297 1.178a.32.32 0 0 0 .484-.033l6.272-7.94a.366.366 0 0 0-.063-.51z"/>
                                                        <path d="M10.91 3.316l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.88a.32.32 0 0 1-.484.033L1.89 7.77a.366.366 0 0 0-.516.005l-.423.433a.364.364 0 0 0 .006.514l3.255 3.185a.32.32 0 0 0 .484-.033l6.272-7.94a.366.366 0 0 0-.063-.51z"/>
                                                    @elseif ($message->status === 'sent')
                                                        <path d="M10.91 3.316l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.88a.32.32 0 0 1-.484.033L1.89 7.77a.366.366 0 0 0-.516.005l-.423.433a.364.364 0 0 0 .006.514l3.255 3.185a.32.32 0 0 0 .484-.033l6.272-7.94a.366.366 0 0 0-.063-.51z"/>
                                                    @else
                                                        <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm.5 10.5h-1v-1h1v1zm0-2h-1v-5h1v5z"/>
                                                    @endif
                                                </svg>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($message->failed_reason && $isOutgoing)
                                        <p class="mt-2 text-[11px] text-red-600 dark:text-red-200" dir="auto" style="unicode-bidi:isolate">{{ $message->failed_reason }}</p>
                                    @endif
                                </article>
                            </div>
                        @endforeach
                    @empty
                        <div class="flex h-full items-center justify-center px-6">
                            <div class="rounded-2xl border border-dashed border-gray-300 bg-white/90 px-5 py-6 text-center text-sm text-gray-500 shadow-sm dark:border-white/10 dark:bg-[#132133] dark:text-gray-300">
                                {{ __('لا توجد رسائل بعد في هذه المحادثة.') }}
                            </div>
                        </div>
                    @endforelse
                </div>

                <footer class="shrink-0 border-t border-gray-200/80 bg-white/90 px-3 py-3 backdrop-blur dark:border-white/10 dark:bg-[#081320]/92 sm:px-4">
                    @if ($canSend && $canUseComposer)
                        <div>
                            @if ($isGroupActive)
                                <div class="mb-2 rounded-2xl bg-emerald-50 px-3 py-2 text-[11px] text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200">
                                    {{ __('الإرسال سيتم إلى المجموعة الحالية:') }}
                                    <span dir="auto" style="unicode-bidi:isolate">
                                        {{ $activeGroupName ?: $this->getActiveGroupId() }}
                                    </span>
                                </div>
                            @elseif ($sendToSameGroup && $replyGroup)
                                <div class="mb-2 rounded-2xl bg-sky-50 px-3 py-2 text-[11px] text-sky-700 dark:bg-sky-500/10 dark:text-sky-200">
                                    {{ __('الرد سيعود إلى المجموعة:') }}
                                    <span dir="auto" style="unicode-bidi:isolate">
                                        {{ $replyGroup['group_name'] ?: $replyGroup['group_id'] }}
                                    </span>
                                </div>
                            @endif

                            <div
                                x-data="{
                                    fileName: null,
                                    fileSize: null,
                                    previewUrl: null,
                                    pickFile() { this.$refs.fileInput.click(); },
                                    onFileChange(event) {
                                        const file = event.target.files[0];
                                        if (!file) {
                                            this.clearFile();
                                            return;
                                        }

                                        this.fileName = file.name;
                                        this.fileSize = file.size < 1048576
                                            ? (file.size / 1024).toFixed(0) + ' KB'
                                            : (file.size / 1048576).toFixed(1) + ' MB';

                                        if (this.previewUrl) {
                                            URL.revokeObjectURL(this.previewUrl);
                                        }

                                        this.previewUrl = file.type.startsWith('image/')
                                            ? URL.createObjectURL(file)
                                            : null;
                                    },
                                    clearFile() {
                                        this.fileName = null;
                                        this.fileSize = null;

                                        if (this.previewUrl) {
                                            URL.revokeObjectURL(this.previewUrl);
                                            this.previewUrl = null;
                                        }

                                        this.$refs.fileInput.value = '';
                                    }
                                }"
                            >
                                <div
                                    x-show="fileName"
                                    x-cloak
                                    x-transition
                                    class="mb-2 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/80 px-3 py-2 dark:border-emerald-500/20 dark:bg-emerald-500/10"
                                >
                                    <div x-show="previewUrl" class="shrink-0 overflow-hidden rounded-2xl">
                                        <img :src="previewUrl" alt="" class="h-12 w-12 object-cover">
                                    </div>

                                    <div x-show="!previewUrl" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white text-emerald-600 shadow-sm dark:bg-white/10 dark:text-emerald-200">
                                        <x-filament::icon icon="heroicon-o-document" class="h-5 w-5" />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white" x-text="fileName"></p>
                                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400" x-text="fileSize"></p>
                                    </div>

                                    <button
                                        type="button"
                                        @click="clearFile()"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-red-200 text-red-500 transition hover:bg-red-50 dark:border-red-500/20 dark:hover:bg-red-500/10"
                                    >
                                        <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                                    </button>
                                </div>

                                <div
                                    x-show="uploading"
                                    x-cloak
                                    x-transition
                                    class="mb-2 overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50/80 dark:border-emerald-500/20 dark:bg-emerald-500/10"
                                >
                                    <div class="flex items-center gap-2 px-3 py-2">
                                        <svg class="h-4 w-4 animate-spin text-emerald-600 dark:text-emerald-300" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        <span class="text-[11px] font-medium text-emerald-700 dark:text-emerald-200">{{ __('جاري الرفع...') }}</span>
                                        <span class="text-[11px] text-emerald-600 dark:text-emerald-300" x-text="uploadProgress + '%'"></span>
                                    </div>
                                    <div class="h-1 bg-emerald-100 dark:bg-emerald-900/40">
                                        <div class="h-full bg-emerald-500 transition-all duration-300" :style="'width:' + uploadProgress + '%'"></div>
                                    </div>
                                </div>

                                <div class="flex items-end gap-2">
                                    <button
                                        type="button"
                                        @click="pickFile()"
                                        :disabled="sending"
                                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-600 disabled:opacity-50 dark:border-white/10 dark:bg-white/10 dark:text-gray-300 dark:hover:border-emerald-500/30 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200"
                                        title="{{ __('رفع مرفق') }}"
                                    >
                                        <x-filament::icon icon="heroicon-o-paper-clip" class="h-5 w-5" />
                                    </button>

                                    <input
                                        x-ref="fileInput"
                                        type="file"
                                        class="hidden"
                                        accept="image/jpeg,image/png,image/webp,image/gif,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,audio/mpeg,audio/ogg,audio/webm,audio/mp4,video/mp4,video/webm"
                                        @change="onFileChange($event)"
                                    >

                                    <div class="min-w-0 flex-1 rounded-[1.4rem] border border-gray-200 bg-gray-50 px-3 py-2 dark:border-white/10 dark:bg-white/5">
                                        <textarea
                                            x-ref="composer"
                                            rows="1"
                                            placeholder="{{ __('اكتب رسالتك أو أرفق صورة / ملف') }}"
                                            dir="auto"
                                            style="unicode-bidi:isolate"
                                            class="max-h-[130px] min-h-[38px] w-full resize-none border-0 bg-transparent p-0 text-[13px] text-gray-900 outline-none focus:ring-0 dark:text-white"
                                            @input="resize($event.target)"
                                            @keydown.enter="submitOnEnter($event)"
                                        ></textarea>
                                    </div>

                                    <button
                                        type="button"
                                        @click="sendMessage()"
                                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="sending"
                                        title="{{ __('إرسال') }}"
                                    >
                                        <svg x-show="!sending" class="h-5 w-5 {{ $sendIconClass }}" fill="currentColor" viewBox="0 0 24 24"><path d="M1.101 21.757 23.8 12.028 1.101 2.3l.011 7.912 13.623 1.816-13.623 1.817-.011 7.912z"/></svg>
                                        <svg x-show="sending" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @elseif (! $canSend)
                        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            {{ __('ليس لديك صلاحية إرسال رسائل واتساب من هذه الصفحة.') }}
                        </div>
                    @else
                        <div class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                            {{ $bridgeStatus['status_hint'] ?? __('إرسال واتساب غير متاح من خلال البريدج الحالي.') }}
                        </div>
                    @endif
                </footer>
            @else
                <div class="flex h-full items-center justify-center px-6">
                    <div class="max-w-md rounded-[1.75rem] border border-dashed border-gray-300 bg-white/90 px-6 py-8 text-center shadow-sm dark:border-white/10 dark:bg-[#101d2d]">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-200">
                            <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-8 w-8" />
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-gray-950 dark:text-white">{{ __('اختر محادثة لبدء المتابعة') }}</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ __('ستظهر الرسائل والوسائط وسجل التحويل إلى مهام داخل نفس الشاشة بدون الانتقال بين صفحات متعددة.') }}</p>
                    </div>
                </div>
            @endif
        </main>

        <div
            x-show="lightboxOpen"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 px-4 py-6"
            @click.self="closeMedia()"
            @keydown.escape.window="closeMedia()"
        >
            <div class="w-full max-w-5xl overflow-hidden rounded-[1.75rem] bg-white shadow-2xl dark:bg-[#081320]">
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-gray-950 dark:text-white" x-text="lightboxName || '{{ __('معاينة الوسيط') }}'"></p>
                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400" x-text="lightboxType === 'video' ? '{{ __('فيديو') }}' : '{{ __('صورة') }}'"></p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600 dark:border-white/10 dark:text-gray-300 dark:hover:border-red-500/20 dark:hover:bg-red-500/10 dark:hover:text-red-200"
                        @click="closeMedia()"
                    >
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <div class="max-h-[80vh] overflow-auto bg-slate-950 p-3">
                    <template x-if="lightboxType === 'video'">
                        <video controls class="mx-auto max-h-[74vh] w-full rounded-2xl bg-black">
                            <source :src="lightboxUrl">
                        </video>
                    </template>

                    <template x-if="lightboxType !== 'video'">
                        <img :src="lightboxUrl" alt="" class="mx-auto max-h-[74vh] rounded-2xl object-contain">
                    </template>
                </div>
            </div>
        </div>
    </div>

    @once
        <script>
            function waEscape(str) {
                var d = document.createElement('div');
                d.textContent = str;
                return d.innerHTML;
            }

            function waBuildBubble(msg, cfg) {
                var isOut = msg.direction === 'outbound';
                var bg = isOut
                    ? 'bg-emerald-100 text-gray-950 dark:bg-emerald-500/15 dark:text-white'
                    : 'bg-white text-gray-950 dark:bg-[#132133] dark:text-white';
                var align = isOut ? cfg.outgoingAlign : cfg.incomingAlign;
                var ts = isOut
                    ? 'text-emerald-700/80 dark:text-emerald-200/80'
                    : 'text-gray-500 dark:text-gray-400';

                var media = '';
                if (msg.media_rejected) {
                    media = '<div class="mb-2 rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-[12px] text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-100">'
                        + waEscape(cfg.labels.fileRejected) + '</div>';
                } else if (msg.media_type && msg.media_available && msg.media_url) {
                    var safeUrl = waEscape(msg.media_url);
                    var safeName = waEscape(msg.media_name || '');
                    if (msg.media_type === 'image' || msg.media_type === 'sticker') {
                        media = '<div class="mb-2"><img src="' + safeUrl
                            + '" class="max-h-72 w-full rounded-[1.1rem] border border-black/5 object-cover cursor-pointer" loading="lazy"'
                            + ' onclick="waOpenMedia(\'' + safeUrl.replace(/'/g, "\\'") + "','image','" + safeName.replace(/'/g, "\\'") + '\')" /></div>';
                    } else if (msg.media_type === 'audio') {
                        media = '<div class="mb-2 rounded-[1.1rem] bg-black/5 px-3 py-3 dark:bg-white/5">'
                            + '<audio controls class="h-10 w-full min-w-[240px]"><source src="' + safeUrl + '"></audio></div>';
                    }
                }

                var body = '';
                if (msg.body) {
                    body = '<p class="whitespace-pre-line text-[13px] leading-6" dir="auto" style="unicode-bidi:isolate">'
                        + waEscape(msg.body) + '</p>';
                }

                var sender = '';
                if (cfg.isGroup && msg.sender_name) {
                    sender = '<p class="mb-1 text-[11px] font-bold text-amber-600 dark:text-amber-300">'
                        + waEscape(msg.sender_name) + '</p>';
                }

                var task = '';
                if (msg.task_id && msg.task_url) {
                    task = '<a href="' + waEscape(msg.task_url)
                        + '" class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[10px] font-semibold text-emerald-700 transition hover:bg-emerald-500/20 dark:text-emerald-200">'
                        + waEscape(cfg.labels.task) + ' ' + waEscape(msg.task_number || '') + '</a>';
                } else if (msg.create_task_url) {
                    task = '<a href="' + waEscape(msg.create_task_url)
                        + '" class="inline-flex items-center gap-1 rounded-full bg-black/5 px-2.5 py-1 text-[10px] font-medium text-gray-600 transition hover:bg-emerald-500/10 hover:text-emerald-700 dark:bg-white/10 dark:text-gray-300 dark:hover:text-emerald-200">'
                        + waEscape(cfg.labels.convertToTask) + '</a>';
                }

                var retry = '';
                if (isOut && msg.status && msg.status.indexOf('failed') === 0) {
                    retry = '<button type="button" onclick="waRetry(\'' + waEscape(msg.retry_url).replace(/'/g, "\\'")
                        + '\')" class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2.5 py-1 text-[10px] font-semibold text-red-700 transition hover:bg-red-500/20 dark:text-red-200">'
                        + waEscape(cfg.labels.retry) + '</button>';
                }

                var fail = '';
                if (msg.failed_reason && isOut) {
                    fail = '<p class="mt-2 text-[11px] text-red-600 dark:text-red-200" dir="auto" style="unicode-bidi:isolate">'
                        + waEscape(msg.failed_reason) + '</p>';
                }

                return '<div class="mb-3 flex ' + align + ' wa-msg-enter" data-msg-id="' + msg.id + '">'
                    + '<article class="max-w-[88%] rounded-[1.4rem] border border-black/5 px-3 py-2 shadow-sm sm:max-w-[72%] ' + bg + '">'
                    + sender + media + body
                    + '<div class="mt-2 flex items-center justify-between gap-3">'
                    + '<div class="flex items-center gap-2">' + task + retry + '</div>'
                    + '<div class="flex items-center gap-1.5 text-[11px] ' + ts + '">'
                    + '<span>' + waEscape(msg.timestamp || '') + '</span>'
                    + '</div></div>' + fail
                    + '</article></div>';
            }
        </script>
    @endonce
</x-filament-panels::page>
