<x-layouts.my-tasks :title="$task->task_number">
    @php
        $workflowStatus = $task->workflowStatus();

        $statusClass = match ($workflowStatus->value) {
            'new' => 'bg-slate-100 text-slate-700',
            'pending_assignment' => 'bg-amber-100 text-amber-800',
            'assigned' => 'bg-sky-100 text-sky-800',
            'accepted' => 'bg-cyan-100 text-cyan-800',
            'in_progress' => 'bg-indigo-100 text-indigo-800',
            'wait_response' => 'bg-orange-100 text-orange-800',
            'completed' => 'bg-emerald-100 text-emerald-800',
            'cancelled', 'rejected' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };

        $currentStatus = $workflowStatus->value;
        $currentAssignmentStatus = $currentAssignment?->status->value;
        $canAcceptAction = $currentAssignmentStatus === 'assigned' && $currentStatus === 'assigned';
        $canStartAction = $currentAssignmentStatus === 'accepted' && $currentStatus === 'accepted';
        $canWaitResponseAction = $currentAssignmentStatus === 'accepted' && $currentStatus === 'in_progress';
        $canResumeAction = $currentAssignmentStatus === 'accepted' && $currentStatus === 'wait_response';
        $canCompleteAction = $currentAssignmentStatus === 'accepted' && in_array($currentStatus, ['in_progress', 'wait_response'], true);
        $canRejectAction = $currentAssignmentStatus !== null && ! in_array($currentStatus, ['completed', 'rejected', 'cancelled'], true);

        $priorityClass = match ($task->priority->value) {
            'low' => 'bg-slate-100 text-slate-700',
            'medium' => 'bg-sky-100 text-sky-800',
            'high' => 'bg-amber-100 text-amber-800',
            'urgent' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    @endphp

    <a href="{{ route('my-tasks.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-slate-600 hover:text-slate-900">
        <span aria-hidden="true">{{ app()->getLocale() === 'ar' ? '→' : '←' }}</span> {{ __('Back to My Tasks') }}
    </a>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $task->task_number }}</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">{{ $task->title }}</h2>
            </div>

            <div class="flex flex-wrap gap-2 text-xs">
                <span class="rounded-full px-2.5 py-1 font-semibold {{ $statusClass }}">{{ $task->workflowStatusLabel() }}</span>
                <span class="rounded-full px-2.5 py-1 font-semibold {{ $priorityClass }}">{{ $task->priority->label() }}</span>
            </div>
        </div>

        <dl class="mt-4 grid gap-3 text-sm text-slate-700 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="font-semibold text-slate-500">{{ __('Department') }}</dt>
                <dd>{{ $task->department?->hierarchy_name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500">{{ __('Category') }}</dt>
                <dd>{{ $task->category?->name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500">{{ __('Due At') }}</dt>
                <dd>{{ $task->due_at?->format('Y-m-d H:i') ?? '-' }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-slate-500">{{ __('Location') }}</dt>
                <dd>{{ $task->location ?: '-' }}</dd>
            </div>
        </dl>

        <div class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
            <p class="font-semibold text-slate-500">{{ __('Description') }}</p>
            <p class="mt-1 whitespace-pre-line">{{ $task->description ?: __('No description provided.') }}</p>
        </div>
    </section>

    @if ($canRespond)
        <section class="mt-5 grid gap-4 lg:grid-cols-2">
            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Task Actions') }}</h3>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($canAcceptAction)
                        <form method="POST" action="{{ route('my-tasks.accept', $task) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-sky-600 px-3 py-2 text-sm font-semibold text-white hover:bg-sky-500">
                                {{ __('Accept Task') }}
                            </button>
                        </form>
                    @endif

                    @if ($canStartAction)
                        <form method="POST" action="{{ route('my-tasks.start', $task) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                                {{ __('Start Work') }}
                            </button>
                        </form>
                    @endif

                    @if ($canWaitResponseAction)
                        <form method="POST" action="{{ route('my-tasks.wait-response', $task) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-500">
                                {{ __('Wait Response') }}
                            </button>
                        </form>
                    @endif

                    @if ($canResumeAction)
                        <form method="POST" action="{{ route('my-tasks.resume', $task) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-cyan-600 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-500">
                                {{ __('Resume Work') }}
                            </button>
                        </form>
                    @endif

                    @if ($canCompleteAction)
                        <form method="POST" action="{{ route('my-tasks.complete', $task) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                {{ __('Mark Completed') }}
                            </button>
                        </form>
                    @endif
                </div>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Reject Task') }}</h3>
                @if ($canRejectAction)
                    <form method="POST" action="{{ route('my-tasks.reject', $task) }}" class="mt-3 space-y-2">
                        @csrf
                        <textarea
                            name="reason"
                            rows="3"
                            required
                            maxlength="2000"
                            class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                            placeholder="{{ __('Provide rejection reason...') }}"
                        >{{ old('reason') }}</textarea>
                        <button type="submit" class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-500">
                            {{ __('Reject With Reason') }}
                        </button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-slate-500">{{ __('This task can no longer be rejected.') }}</p>
                @endif
            </article>
        </section>
    @endif

    <section class="mt-5 grid gap-4 lg:grid-cols-2">
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Comments') }}</h3>

            @if ($canComment)
                <form method="POST" action="{{ route('my-tasks.comments.store', $task) }}" class="mt-3 space-y-2">
                    @csrf
                    <textarea
                        name="comment"
                        rows="3"
                        required
                        maxlength="5000"
                        class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200"
                        placeholder="{{ __('Add a progress comment...') }}"
                    >{{ old('comment') }}</textarea>
                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        {{ __('Add Comment') }}
                    </button>
                </form>
            @endif

            <div class="mt-4 space-y-3">
                @forelse ($task->comments->sortByDesc('created_at') as $comment)
                    <div class="rounded-lg border border-slate-200 p-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-semibold text-slate-800">{{ $comment->user?->name ?? __('Unknown') }}</p>
                            <p class="text-xs text-slate-500">{{ $comment->created_at?->format('Y-m-d H:i') }}</p>
                        </div>
                        <p class="mt-2 whitespace-pre-line text-slate-700">{{ $comment->comment }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('No comments yet.') }}</p>
                @endforelse
            </div>
        </article>

        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Attachments') }}</h3>

            @if ($canUploadAttachment)
                <form method="POST" action="{{ route('my-tasks.attachments.store', $task) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                    @csrf
                    <input
                        type="file"
                        name="attachment"
                        required
                        class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 file:me-3 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-slate-700"
                    >
                    <p class="text-xs text-slate-500">{{ __('Allowed: jpg, jpeg, png, webp, pdf, doc, docx, xls, xlsx. Max 10MB.') }}</p>
                    <button type="submit" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        {{ __('Upload File') }}
                    </button>
                </form>
            @endif

            @php
                $images = $task->attachments->filter(fn ($a) => $a->isImage())->sortByDesc('created_at');
                $videos = $task->attachments->filter(fn ($a) => $a->isVideo())->sortByDesc('created_at');
                $excels = $task->attachments->filter(fn ($a) => $a->isExcel())->sortByDesc('created_at');
                $files  = $task->attachments->filter(fn ($a) => !$a->isImage() && !$a->isVideo() && !$a->isExcel())->sortByDesc('created_at');
            @endphp

            {{-- ========== IMAGE GALLERY ========== --}}
            @if ($images->isNotEmpty())
                <div class="mt-4">
                    <div class="mb-2 flex items-center gap-2">
                        <svg class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Images') }} <span class="text-slate-300">({{ $images->count() }})</span></p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4" id="image-gallery">
                        @foreach ($images as $img)
                            <a href="{{ route('attachments.preview', $img) }}"
                               data-lightbox="gallery"
                               data-title="{{ $img->original_name }}"
                               data-download="{{ route('attachments.download', $img) }}"
                               @click.prevent="showImage('{{ route('attachments.preview', $img) }}', '{{ $img->original_name }}', '{{ route('attachments.download', $img) }}')"
                               class="group relative block aspect-square overflow-hidden rounded-lg border border-slate-200 bg-slate-50 cursor-pointer">
                                <img
                                    src="{{ route('attachments.preview', $img) }}"
                                    alt="{{ $img->original_name }}"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-110"
                                    loading="lazy"
                                >
                                <div class="absolute inset-0 flex items-end bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 transition duration-300 group-hover:opacity-100">
                                    <div class="flex w-full items-center justify-between px-2 py-1.5">
                                        <span class="truncate text-xs font-medium text-white">{{ $img->original_name }}</span>
                                        <span class="text-[10px] text-white/70">{{ $img->humanSize() }}</span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ========== VIDEO GALLERY ========== --}}
            @if ($videos->isNotEmpty())
                <div class="mt-4" x-data="videoGallery()">
                    <div class="mb-2 flex items-center gap-2">
                        <svg class="h-4 w-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Videos') }} <span class="text-slate-300">({{ $videos->count() }})</span></p>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($videos as $idx => $vid)
                            <div @click="openVideo({{ $idx }})"
                                 class="group relative cursor-pointer overflow-hidden rounded-lg border border-slate-200 bg-black aspect-video">
                                <video preload="metadata" muted class="h-full w-full object-cover pointer-events-none">
                                    <source src="{{ route('attachments.preview', $vid) }}#t=1" type="{{ $vid->mime_type }}">
                                </video>
                                <div class="absolute inset-0 flex items-center justify-center bg-black/30 transition duration-300 group-hover:bg-black/50">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/90 shadow-lg transition duration-300 group-hover:scale-110">
                                        <svg class="h-5 w-5 text-slate-900 {{ app()->getLocale() === 'ar' ? '' : 'ms-0.5' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                </div>
                                <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/70 to-transparent px-2 py-1.5">
                                    <p class="truncate text-xs font-medium text-white">{{ $vid->original_name }}</p>
                                    <p class="text-[10px] text-white/60">{{ $vid->humanSize() }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Video Modal --}}
                    <template x-teleport="body">
                        <div x-show="videoOpen" x-transition.opacity
                             class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/90 p-4"
                             @click.self="closeVideo()" @keydown.escape.window="closeVideo()" style="display:none">

                            <button @click="closeVideo()" class="absolute top-4 {{ app()->getLocale() === 'ar' ? 'left-4' : 'right-4' }} z-10 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 transition">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>

                            <button x-show="videoList.length > 1" @click="prevVideo()" class="absolute {{ app()->getLocale() === 'ar' ? 'right-4' : 'left-4' }} z-10 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 transition">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                            </button>

                            <div class="w-full max-w-4xl">
                                <video x-ref="videoPlayer" controls autoplay class="w-full max-h-[80vh] rounded-lg shadow-2xl">
                                    <source :src="videoList[videoIdx]?.src" :type="videoList[videoIdx]?.mime">
                                </video>
                                <div class="mt-3 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm text-white" x-text="videoList[videoIdx]?.name"></span>
                                        <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs text-white/60" x-text="(videoIdx+1) + ' / ' + videoList.length"></span>
                                    </div>
                                    <a :href="videoList[videoIdx]?.download" class="rounded-lg bg-white/10 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/20 transition">{{ __('Download') }}</a>
                                </div>
                            </div>

                            <button x-show="videoList.length > 1" @click="nextVideo()" class="absolute {{ app()->getLocale() === 'ar' ? 'left-4' : 'right-4' }} z-10 rounded-full bg-white/10 p-2 text-white hover:bg-white/20 transition">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                            </button>
                        </div>
                    </template>

                    <script>
                    function videoGallery() {
                        return {
                            videoOpen: false,
                            videoIdx: 0,
                            videoList: @json($videos->values()->map(fn ($v) => [
                                'src'      => route('attachments.preview', $v),
                                'mime'     => $v->mime_type,
                                'name'     => $v->original_name,
                                'download' => route('attachments.download', $v),
                            ])),
                            openVideo(idx) {
                                this.videoIdx = idx;
                                this.videoOpen = true;
                                this.$nextTick(() => {
                                    const player = this.$refs.videoPlayer;
                                    if (player) { player.load(); player.play().catch(() => {}); }
                                });
                            },
                            closeVideo() {
                                this.videoOpen = false;
                                const player = this.$refs.videoPlayer;
                                if (player) player.pause();
                            },
                            nextVideo() {
                                this.videoIdx = (this.videoIdx + 1) % this.videoList.length;
                                this.$nextTick(() => { const p = this.$refs.videoPlayer; if (p) { p.load(); p.play().catch(() => {}); } });
                            },
                            prevVideo() {
                                this.videoIdx = (this.videoIdx - 1 + this.videoList.length) % this.videoList.length;
                                this.$nextTick(() => { const p = this.$refs.videoPlayer; if (p) { p.load(); p.play().catch(() => {}); } });
                            },
                        }
                    }
                    </script>
                </div>
            @endif

            {{-- ========== EXCEL GALLERY ========== --}}
            @if ($excels->isNotEmpty())
                <div class="mt-4" x-data="excelViewer()">
                    <div class="mb-2 flex items-center gap-2">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M12 12v-1.5c0 .621.504 1.125 1.125 1.125M12 12c0 .621-.504 1.125-1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m-1.125-1.125c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m8.625-3.75c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125"/></svg>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Spreadsheets') }} <span class="text-slate-300">({{ $excels->count() }})</span></p>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($excels as $idx => $xl)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-3 transition hover:border-emerald-300 hover:shadow-sm">
                                <div class="flex items-center gap-2.5 overflow-hidden">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50">
                                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M12 12v-1.5c0 .621.504 1.125 1.125 1.125M12 12c0 .621-.504 1.125-1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m-1.125-1.125c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m8.625-3.75c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125"/></svg>
                                    </span>
                                    <div class="overflow-hidden">
                                        <p class="truncate text-sm font-medium text-slate-800">{{ $xl->original_name ?: basename($xl->path) }}</p>
                                        <p class="text-xs text-slate-500">{{ $xl->humanSize() }} · {{ $xl->user?->name ?? __('Unknown') }}</p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 gap-1.5">
                                    <button @click="openExcel({{ $idx }})" class="rounded border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition">{{ __('Preview') }}</button>
                                    <a href="{{ route('attachments.download', $xl) }}" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100">{{ __('Download') }}</a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Excel Modal --}}
                    <template x-teleport="body">
                        <div x-show="excelOpen" x-transition.opacity
                             class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-4"
                             @click.self="closeExcel()" @keydown.escape.window="closeExcel()" style="display:none">

                            <div class="relative flex h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl" @click.stop>
                                {{-- Header --}}
                                <div class="flex items-center justify-between border-b border-slate-200 bg-emerald-50 px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625"/></svg>
                                        <span class="text-sm font-semibold text-emerald-800" x-text="excelFiles[excelIdx]?.name"></span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a :href="excelFiles[excelIdx]?.download" class="rounded-lg border border-emerald-300 bg-white px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 transition">{{ __('Download') }}</a>
                                        <button @click="closeExcel()" class="rounded-lg bg-slate-100 p-1.5 text-slate-500 hover:bg-slate-200 transition">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>

                                {{-- Sheet Tabs --}}
                                <div x-show="sheetNames.length > 1" class="flex gap-0 overflow-x-auto border-b border-slate-200 bg-slate-50 px-2">
                                    <template x-for="(sn, si) in sheetNames" :key="si">
                                        <button @click="switchSheet(si)"
                                                :class="si === activeSheet ? 'bg-white border-b-2 border-emerald-500 text-emerald-700 font-semibold' : 'text-slate-500 hover:text-slate-700 hover:bg-white/50'"
                                                class="whitespace-nowrap px-4 py-2 text-xs transition" x-text="sn"></button>
                                    </template>
                                </div>

                                {{-- Loading --}}
                                <div x-show="excelLoading" class="flex flex-1 items-center justify-center">
                                    <div class="text-center">
                                        <svg class="mx-auto h-8 w-8 animate-spin text-emerald-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        <p class="mt-2 text-sm text-slate-500">{{ __('Loading spreadsheet...') }}</p>
                                    </div>
                                </div>

                                {{-- Error --}}
                                <div x-show="excelError" class="flex flex-1 items-center justify-center">
                                    <div class="text-center">
                                        <svg class="mx-auto h-10 w-10 text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                                        <p class="mt-2 text-sm text-rose-600" x-text="excelError"></p>
                                    </div>
                                </div>

                                {{-- Table --}}
                                <div x-show="!excelLoading && !excelError && sheetData.length" class="flex-1 overflow-auto">
                                    <table class="w-full border-collapse text-xs">
                                        <thead class="sticky top-0 z-10">
                                            <tr class="bg-slate-100">
                                                <th class="border border-slate-200 bg-slate-200 px-2 py-1.5 text-center text-[10px] font-bold text-slate-500 w-10">#</th>
                                                <template x-for="(cell, ci) in (sheetData[0] || [])" :key="ci">
                                                    <th class="border border-slate-200 px-3 py-1.5 text-start font-semibold text-slate-700 whitespace-nowrap" x-text="cell"></th>
                                                </template>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="(row, ri) in sheetData.slice(1)" :key="ri">
                                                <tr :class="ri % 2 === 0 ? 'bg-white' : 'bg-slate-50'" class="hover:bg-emerald-50/50 transition-colors">
                                                    <td class="border border-slate-200 bg-slate-100 px-2 py-1 text-center text-[10px] font-medium text-slate-400" x-text="ri + 1"></td>
                                                    <template x-for="(cell, ci) in row" :key="ci">
                                                        <td class="border border-slate-200 px-3 py-1 text-slate-700 whitespace-nowrap" x-text="cell"></td>
                                                    </template>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Footer --}}
                                <div x-show="!excelLoading && !excelError && sheetData.length" class="border-t border-slate-200 bg-slate-50 px-4 py-2">
                                    <p class="text-xs text-slate-500">
                                        <span x-text="Math.max(0, sheetData.length - 1)"></span> {{ __('rows') }} ·
                                        <span x-text="(sheetData[0] || []).length"></span> {{ __('columns') }}
                                        <template x-if="sheetNames.length > 1">
                                            <span> · <span x-text="sheetNames.length"></span> {{ __('sheets') }}</span>
                                        </template>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <script>
                    function excelViewer() {
                        return {
                            excelOpen: false,
                            excelIdx: 0,
                            excelLoading: false,
                            excelError: '',
                            sheetNames: [],
                            sheetData: [],
                            activeSheet: 0,
                            workbook: null,
                            excelFiles: @json($excels->values()->map(fn ($x) => [
                                'name'     => $x->original_name ?: basename($x->path),
                                'src'      => route('attachments.preview', $x),
                                'download' => route('attachments.download', $x),
                            ])),
                            async openExcel(idx) {
                                this.excelIdx = idx;
                                this.excelOpen = true;
                                this.excelLoading = true;
                                this.excelError = '';
                                this.sheetNames = [];
                                this.sheetData = [];
                                this.activeSheet = 0;
                                this.workbook = null;

                                if (!window.XLSX) {
                                    try {
                                        await this.loadScript('https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js');
                                    } catch {
                                        this.excelLoading = false;
                                        this.excelError = '{{ __("Failed to load spreadsheet library.") }}';
                                        return;
                                    }
                                }

                                try {
                                    const resp = await fetch(this.excelFiles[idx].src);
                                    if (!resp.ok) throw new Error('HTTP ' + resp.status);
                                    const buf = await resp.arrayBuffer();
                                    this.workbook = XLSX.read(buf, { type: 'array' });
                                    this.sheetNames = this.workbook.SheetNames;
                                    this.renderSheet(0);
                                } catch (e) {
                                    this.excelError = '{{ __("Failed to load file.") }}';
                                }
                                this.excelLoading = false;
                            },
                            renderSheet(idx) {
                                this.activeSheet = idx;
                                const ws = this.workbook.Sheets[this.sheetNames[idx]];
                                this.sheetData = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
                            },
                            switchSheet(idx) {
                                if (this.workbook) this.renderSheet(idx);
                            },
                            closeExcel() {
                                this.excelOpen = false;
                                this.workbook = null;
                                this.sheetData = [];
                            },
                            loadScript(src) {
                                return new Promise((resolve, reject) => {
                                    const s = document.createElement('script');
                                    s.src = src;
                                    s.onload = resolve;
                                    s.onerror = reject;
                                    document.head.appendChild(s);
                                });
                            },
                        }
                    }
                    </script>
                </div>
            @endif

            {{-- ========== OTHER FILES ========== --}}
            @if ($files->isNotEmpty())
                <div class="mt-4">
                    <div class="mb-2 flex items-center gap-2">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Files') }} <span class="text-slate-300">({{ $files->count() }})</span></p>
                    </div>
                    <ul class="space-y-2 text-sm">
                        @foreach ($files as $file)
                            <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3 transition hover:border-slate-300 hover:shadow-sm">
                                <div class="flex items-center gap-2.5 overflow-hidden">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg
                                        {{ $file->mime_type === 'application/pdf' ? 'bg-rose-50 text-rose-600' :
                                           (str_contains($file->mime_type ?? '', 'word') || str_contains($file->mime_type ?? '', 'document') ? 'bg-sky-50 text-sky-600' : 'bg-slate-100 text-slate-500') }}">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </span>
                                    <div class="overflow-hidden">
                                        <p class="truncate font-medium text-slate-800">{{ $file->original_name ?: basename($file->path) }}</p>
                                        <p class="text-xs text-slate-500">{{ $file->humanSize() }} · {{ $file->user?->name ?? __('Unknown') }}</p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 gap-1.5">
                                    @if ($file->mime_type === 'application/pdf')
                                        <a href="{{ route('attachments.preview', $file) }}" target="_blank" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100">{{ __('Preview') }}</a>
                                    @endif
                                    <a href="{{ route('attachments.download', $file) }}" class="rounded border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100">{{ __('Download') }}</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($task->attachments->isEmpty())
                <p class="mt-4 text-sm text-slate-500">{{ __('No attachments uploaded.') }}</p>
            @endif
        </article>
    </section>

    <section class="mt-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Timeline') }}</h3>
        <div class="mt-4 space-y-3">
            @forelse ($timeline as $event)
                <article class="rounded-lg border border-slate-200 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-800">{{ $event['title'] }}</p>
                        <p class="text-xs text-slate-500">{{ optional($event['at'])->format('Y-m-d H:i') }}</p>
                    </div>
                    @if (! empty($event['meta']))
                        <p class="mt-1 text-xs text-slate-500">{{ $event['meta'] }}</p>
                    @endif
                    @if (! empty($event['body']))
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $event['body'] }}</p>
                    @endif
                </article>
            @empty
                <p class="text-sm text-slate-500">{{ __('No timeline events available.') }}</p>
            @endforelse
        </div>
    </section>
</x-layouts.my-tasks>
