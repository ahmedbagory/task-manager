<section class="rounded-[1.6rem] border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#101827]">
    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-white/10">
        <div>
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">{{ __('التعليقات') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('عرض المحادثة بشكل أقرب لتجربة التطبيق بدل الجدول التقليدي') }}</p>
        </div>

        @if (auth()->user()?->can('comment', $this->getTask()))
            <button
                type="button"
                wire:click="mountAction('addComment')"
                class="inline-flex items-center gap-2 rounded-full bg-slate-950 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
            >
                <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-4 w-4" />
                {{ __('إضافة تعليق') }}
            </button>
        @endif
    </div>

    <div class="space-y-4 p-5">
        @forelse ($comments as $comment)
            @php
                $isMine = (int) $comment->user_id === (int) auth()->id();
            @endphp

            <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                <article class="max-w-[90%] rounded-[1.6rem] px-4 py-3 shadow-sm {{ $isMine ? 'bg-amber-500 text-slate-950' : 'border border-slate-200 bg-slate-50 text-slate-900 dark:border-white/10 dark:bg-white/5 dark:text-white' }}">
                    <div class="flex flex-wrap items-center gap-2 text-xs {{ $isMine ? 'text-slate-900/75' : 'text-slate-500 dark:text-slate-400' }}">
                        <span class="font-semibold {{ $isMine ? 'text-slate-950' : 'text-slate-900 dark:text-white' }}">
                            {{ $comment->user?->name ?? __('Unknown') }}
                        </span>
                        <span>•</span>
                        <span>{{ $comment->created_at?->format('Y-m-d H:i') }}</span>

                        @if ($comment->is_internal)
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $isMine ? 'bg-slate-950/10 text-slate-950' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-200' }}">
                                {{ __('داخلي') }}
                            </span>
                        @endif
                    </div>

                    @if ($comment->user?->department?->hierarchy_name)
                        <p class="mt-1 text-[11px] {{ $isMine ? 'text-slate-900/70' : 'text-slate-500 dark:text-slate-400' }}">
                            {{ $comment->user->department->hierarchy_name }}
                        </p>
                    @endif

                    <p class="mt-3 whitespace-pre-line text-sm leading-7 {{ $isMine ? 'text-slate-950' : 'text-slate-700 dark:text-slate-200' }}">
                        {{ $comment->comment }}
                    </p>
                </article>
            </div>
        @empty
            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center dark:border-white/10 dark:bg-white/5">
                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('لا توجد تعليقات بعد.') }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('ابدأ أول تعليق ليظهر هنا على هيئة محادثة.') }}</p>
            </div>
        @endforelse
    </div>
</section>
