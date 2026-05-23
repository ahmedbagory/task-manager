<x-filament::section
    compact
    :heading="__('التعليقات')"
    :description="__('التعليقات الداخلية والملاحظات المضافة على المهمة')"
>
    <x-slot name="afterHeader">
        @if (auth()->user()?->can('comment', $this->getTask()))
            <x-filament::button
                color="gray"
                icon="heroicon-o-chat-bubble-left-right"
                outlined
                size="sm"
                type="button"
                wire:click="mountAction('addComment')"
            >
                {{ __('إضافة تعليق') }}
            </x-filament::button>
        @endif
    </x-slot>

    <div class="space-y-3">
        @forelse ($comments as $comment)
            @php
                $isMine = (int) $comment->user_id === (int) auth()->id();
            @endphp

            <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $comment->user?->name ?? __('Unknown') }}</p>

                            @if ($isMine)
                                <x-filament::badge color="primary" size="sm">
                                    {{ __('أنت') }}
                                </x-filament::badge>
                            @endif

                            @if ($comment->is_internal)
                                <x-filament::badge color="danger" size="sm">
                                    {{ __('داخلي') }}
                                </x-filament::badge>
                            @endif
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $comment->user?->department?->hierarchy_name ?? '—' }}
                        </p>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $comment->created_at?->format('Y-m-d H:i') }}</p>
                </div>

                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-200">
                    {{ $comment->comment }}
                </p>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('لا توجد تعليقات بعد.') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('ستظهر التعليقات هنا بمجرد إضافتها.') }}</p>
            </div>
        @endforelse
    </div>
</x-filament::section>
