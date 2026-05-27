<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('My Tasks') }} | {{ config('app.name', 'Task Manager') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900" x-data="lightbox()">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ __('Employee Workspace') }}</p>
                <h1 class="text-xl font-semibold">{{ $title ?? __('My Tasks') }}</h1>
            </div>

            <div class="flex items-center gap-2 text-sm">
                @include('components.locale-switcher', ['context' => 'public'])
                <a href="{{ route('my-tasks.index') }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50">
                    {{ __('My Tasks') }}
                </a>
                <a href="{{ route('filament.admin.pages.dashboard-page') }}" class="rounded-md border border-slate-300 px-3 py-1.5 font-medium hover:bg-slate-50">
                    {{ __('Admin') }}
                </a>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <ul class="list-disc space-y-1 ps-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>

    {{-- Image Lightbox --}}
    <template x-teleport="body">
        <div x-show="open" x-transition.opacity class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/90 p-4" @click.self="close()" @keydown.escape.window="close()" @keydown.right.window="next()" @keydown.left.window="prev()" style="display:none">
            {{-- Top Bar --}}
            <div class="absolute top-0 inset-x-0 flex items-center justify-between bg-gradient-to-b from-black/60 to-transparent px-4 py-3 z-10">
                <div class="flex items-center gap-3">
                    <span x-show="images.length > 1" class="rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white" x-text="(idx+1) + ' / ' + images.length"></span>
                    <span class="text-sm text-white/80 truncate max-w-xs" x-text="images[idx]?.name"></span>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="images[idx]?.download" class="rounded-full bg-white/10 p-2 text-white hover:bg-white/20 transition" title="{{ __('Download') }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    </a>
                    <button @click="close()" class="rounded-full bg-white/10 p-2 text-white hover:bg-white/20 transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <button x-show="images.length > 1" @click="prev()" class="absolute {{ app()->getLocale() === 'ar' ? 'right-4' : 'left-4' }} rounded-full bg-white/10 p-2.5 text-white hover:bg-white/20 transition">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
            </button>

            <img :src="images[idx]?.src" :alt="images[idx]?.name" class="max-h-[85vh] max-w-[90vw] rounded-lg object-contain shadow-2xl select-none" />

            <button x-show="images.length > 1" @click="next()" class="absolute {{ app()->getLocale() === 'ar' ? 'left-4' : 'right-4' }} rounded-full bg-white/10 p-2.5 text-white hover:bg-white/20 transition">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </button>
        </div>
    </template>

    <script>
    function lightbox() {
        return {
            open: false,
            idx: 0,
            images: [],
            showImage(src, name, download) {
                const gallery = document.querySelectorAll('[data-lightbox="gallery"]');
                this.images = Array.from(gallery).map(el => ({
                    src: el.href,
                    name: el.dataset.title || '',
                    download: el.dataset.download || el.href,
                }));
                this.idx = this.images.findIndex(i => i.src === src);
                if (this.idx < 0) this.idx = 0;
                this.open = true;
            },
            show(src, name) { this.showImage(src, name, src); },
            close() { this.open = false; },
            next() { if (this.open) this.idx = (this.idx + 1) % this.images.length; },
            prev() { if (this.open) this.idx = (this.idx - 1 + this.images.length) % this.images.length; },
        }
    }
    </script>
</body>
</html>
