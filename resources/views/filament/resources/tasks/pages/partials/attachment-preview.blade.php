@php
    $attachments = $task->attachments->sortByDesc('created_at')->values();
    $previewItemIds = collect($previewItems)->pluck('id')->all();
@endphp

<section class="rounded-[1.6rem] border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-[#101827]">
    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-white/10">
        <div>
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">{{ __('معاينة المرفقات') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('استعراض سريع للصور والفيديو وملفات PDF مع قائمة بكل الملفات المرفوعة') }}</p>
        </div>

        @if (auth()->user()?->can('viewAttachments', $task))
            <button
                type="button"
                wire:click="mountAction('uploadAttachment')"
                class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
            >
                <x-filament::icon icon="heroicon-o-paper-clip" class="h-4 w-4" />
                {{ __('رفع مرفق') }}
            </button>
        @endif
    </div>

    <div
        x-data="{
            items: @js($previewItems),
            selected: @js($previewItems[0] ?? null),
            select(id) {
                const found = this.items.find((item) => item.id === id);
                if (found) {
                    this.selected = found;
                }
            }
        }"
        class="space-y-5 p-5"
    >
        @if (count($previewItems))
            <div class="space-y-4">
                <div class="grid gap-4 xl:grid-cols-[1.3fr_.82fr]">
                    <div class="overflow-hidden rounded-[1.4rem] border border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-slate-950/60">
                        <div class="border-b border-slate-200 px-4 py-3 dark:border-white/10">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-950 dark:text-white" x-text="selected?.name"></p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        <span x-text="selected?.uploaded_by"></span>
                                        <span> • </span>
                                        <span x-text="selected?.uploaded_at"></span>
                                        <span> • </span>
                                        <span x-text="selected?.size"></span>
                                    </p>
                                </div>

                                <a
                                    :href="selected?.download_url"
                                    class="inline-flex shrink-0 items-center gap-2 rounded-full bg-slate-950 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                                >
                                    <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                                    {{ __('تحميل') }}
                                </a>
                            </div>
                        </div>

                        <div class="flex min-h-[24rem] items-center justify-center bg-[radial-gradient(circle_at_top,_rgba(245,158,11,.18),_transparent_36%),linear-gradient(180deg,rgba(255,255,255,.7),rgba(248,250,252,.95))] p-4 dark:bg-[radial-gradient(circle_at_top,_rgba(245,158,11,.14),_transparent_32%),linear-gradient(180deg,rgba(15,23,42,.92),rgba(2,6,23,1))]">
                            <template x-if="selected?.preview_type === 'image'">
                                <img :src="selected?.preview_url" :alt="selected?.name" class="max-h-[30rem] w-full rounded-2xl object-contain shadow-sm">
                            </template>

                            <template x-if="selected?.preview_type === 'video'">
                                <video :src="selected?.preview_url" controls class="max-h-[30rem] w-full rounded-2xl bg-black shadow-sm"></video>
                            </template>

                            <template x-if="selected?.preview_type === 'pdf'">
                                <iframe :src="selected?.preview_url" class="h-[30rem] w-full rounded-2xl bg-white"></iframe>
                            </template>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ __('ملفات قابلة للمعاينة') }}</p>

                        <template x-for="item in items" :key="item.id">
                            <button
                                type="button"
                                @click="select(item.id)"
                                :class="selected?.id === item.id ? 'border-amber-300 bg-amber-50 dark:border-amber-500/25 dark:bg-amber-500/10' : 'border-slate-200 bg-white dark:border-white/10 dark:bg-white/5'"
                                class="flex w-full items-start justify-between gap-3 rounded-3xl border p-3 text-start transition hover:border-amber-300 dark:hover:border-amber-500/25"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-white" x-text="item.name"></p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        <span x-text="item.uploaded_by"></span>
                                        <span> • </span>
                                        <span x-text="item.size"></span>
                                    </p>
                                </div>
                                <span
                                    class="inline-flex shrink-0 items-center rounded-full px-2 py-1 text-[10px] font-semibold"
                                    :class="item.preview_type === 'image'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200'
                                        : (item.preview_type === 'video'
                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-200'
                                            : 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-200')"
                                    x-text="item.preview_type === 'image' ? '{{ __('صورة') }}' : (item.preview_type === 'video' ? '{{ __('فيديو') }}' : 'PDF')"
                                ></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center dark:border-white/10 dark:bg-white/5">
                <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ __('لا توجد صور أو فيديوهات أو ملفات PDF قابلة للمعاينة بعد.') }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('ستظل بقية الملفات متاحة من القائمة الكاملة أدناه.') }}</p>
            </div>
        @endif

        <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ __('كل الملفات') }}</p>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ $attachments->count() }} {{ __('ملف') }}</span>
            </div>

            @forelse ($attachments as $attachment)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-slate-200 bg-slate-50/70 p-3 dark:border-white/10 dark:bg-white/5">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white text-slate-500 ring-1 ring-slate-200 dark:bg-slate-950/70 dark:text-slate-300 dark:ring-white/10">
                            <x-filament::icon
                                :icon="match (true) {
                                    $attachment->isImage() => 'heroicon-o-photo',
                                    $attachment->isVideo() => 'heroicon-o-film',
                                    $attachment->mime_type === 'application/pdf' => 'heroicon-o-document-text',
                                    default => 'heroicon-o-paper-clip',
                                }"
                                class="h-5 w-5"
                            />
                        </span>

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $attachment->original_name ?: basename($attachment->path) }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ $attachment->user?->name ?? __('Unknown') }} • {{ $attachment->humanSize() }} • {{ $attachment->created_at?->format('Y-m-d H:i') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if (in_array($attachment->id, $previewItemIds, true))
                            <button
                                type="button"
                                @click="select({{ $attachment->id }})"
                                class="inline-flex items-center gap-2 rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-100 dark:border-amber-500/25 dark:bg-amber-500/10 dark:text-amber-200"
                            >
                                <x-filament::icon icon="heroicon-o-eye" class="h-4 w-4" />
                                {{ __('معاينة') }}
                            </button>
                        @elseif ($attachment->mime_type === 'application/pdf')
                            <a
                                href="{{ route('attachments.preview', $attachment) }}"
                                target="_blank"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                            >
                                <x-filament::icon icon="heroicon-o-eye" class="h-4 w-4" />
                                {{ __('فتح') }}
                            </a>
                        @endif

                        <a
                            href="{{ route('attachments.download', $attachment) }}"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:bg-white/10"
                        >
                            <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                            {{ __('تحميل') }}
                        </a>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد مرفقات مرفوعة لهذه المهمة.') }}</p>
            @endforelse
        </div>
    </div>
</section>
