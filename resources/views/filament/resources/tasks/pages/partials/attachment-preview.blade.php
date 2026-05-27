@php
    $attachments = $task->attachments->sortByDesc('created_at')->values();
    $previewItemIds = collect($previewItems)->pluck('id')->all();
@endphp

<x-filament::section
    compact
    :heading="__('المرفقات')"
    :description="__('معاينة وتنزيل الملفات المرفوعة لهذه المهمة')"
>
    <x-slot name="afterHeader">
        @if (auth()->user()?->can('viewAttachments', $task))
            <x-filament::button
                color="gray"
                icon="heroicon-o-paper-clip"
                outlined
                size="sm"
                type="button"
                wire:click="mountAction('uploadAttachment')"
            >
                {{ __('رفع مرفق') }}
            </x-filament::button>
        @endif
    </x-slot>

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
        class="space-y-4"
    >
        @if (count($previewItems))
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,.85fr)]">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/5">
                    <div class="flex items-start justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white" x-text="selected?.name"></p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="selected?.uploaded_by"></span>
                                <span> • </span>
                                <span x-text="selected?.uploaded_at"></span>
                                <span> • </span>
                                <span x-text="selected?.size"></span>
                            </p>
                        </div>

                        <x-filament::button
                            color="gray"
                            outlined
                            size="sm"
                            tag="a"
                            x-bind:href="selected?.download_url"
                        >
                            {{ __('تحميل') }}
                        </x-filament::button>
                    </div>

                    <div class="flex min-h-[20rem] items-center justify-center bg-gray-50 p-4 dark:bg-gray-900/40">
                        <template x-if="selected?.preview_type === 'image'">
                            <img :src="selected?.preview_url" :alt="selected?.name" class="max-h-[28rem] w-full rounded-lg object-contain">
                        </template>

                        <template x-if="selected?.preview_type === 'video'">
                            <video :src="selected?.preview_url" controls class="max-h-[28rem] w-full rounded-lg bg-black"></video>
                        </template>

                        <template x-if="selected?.preview_type === 'pdf'">
                            <iframe :src="selected?.preview_url" class="h-[28rem] w-full rounded-lg bg-white"></iframe>
                        </template>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('ملفات قابلة للمعاينة') }}</p>
                        <x-filament::badge color="gray">{{ count($previewItems) }}</x-filament::badge>
                    </div>

                    <template x-for="item in items" :key="item.id">
                        <button
                            type="button"
                            @click="select(item.id)"
                            :class="selected?.id === item.id
                                ? 'border-gray-300 bg-gray-50 dark:border-white/20 dark:bg-white/10'
                                : 'border-gray-200 bg-white dark:border-white/10 dark:bg-white/5'"
                            class="flex w-full items-start justify-between gap-3 rounded-xl border px-3 py-2 text-start transition"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-950 dark:text-white" x-text="item.name"></p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span x-text="item.uploaded_by"></span>
                                    <span> • </span>
                                    <span x-text="item.size"></span>
                                </p>
                            </div>

                            <span class="inline-flex shrink-0 items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300" x-text="item.preview_type === 'image' ? @js(__('صورة')) : (item.preview_type === 'video' ? @js(__('فيديو')) : 'PDF')"></span>
                        </button>
                    </template>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('لا توجد صور أو فيديوهات أو ملفات PDF قابلة للمعاينة بعد.') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('ستظل بقية الملفات متاحة من القائمة الكاملة أدناه.') }}</p>
            </div>
        @endif

        <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('كل الملفات') }}</p>
                <x-filament::badge color="gray">{{ $attachments->count() }} {{ __('ملف') }}</x-filament::badge>
            </div>

            @forelse ($attachments as $attachment)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-white/5">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 text-gray-500 dark:bg-gray-900/40 dark:text-gray-300">
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
                            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $attachment->original_name ?: basename($attachment->path) }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $attachment->user?->name ?? __('Unknown') }} • {{ $attachment->humanSize() }} • {{ $attachment->created_at?->format('Y-m-d H:i') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if (! in_array($attachment->id, $previewItemIds, true) && $attachment->mime_type === 'application/pdf')
                            <x-filament::button
                                color="gray"
                                outlined
                                size="sm"
                                tag="a"
                                :href="route('attachments.preview', $attachment)"
                                target="_blank"
                            >
                                {{ __('فتح') }}
                            </x-filament::button>
                        @endif

                        <x-filament::button
                            color="gray"
                            outlined
                            size="sm"
                            tag="a"
                            :href="route('attachments.download', $attachment)"
                        >
                            {{ __('تحميل') }}
                        </x-filament::button>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('لا توجد مرفقات مرفوعة لهذه المهمة.') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-filament::section>
