@props([
    'context' => 'public',
])

@php
    $locales = config('app.supported_locales', ['en' => 'English', 'ar' => 'العربية']);
    $currentLocale = app()->getLocale();
@endphp

@if($context === 'panel')
<div class="px-2 py-1.5">
    <form method="POST" action="{{ route('locale.switch') }}" class="flex items-center justify-center gap-0.5 rounded-lg bg-gray-950/5 p-0.5 dark:bg-white/5">
        @csrf
        @foreach ($locales as $locale => $label)
            <button
                type="submit"
                name="locale"
                value="{{ $locale }}"
                class="flex-1 rounded-md px-3 py-1.5 text-sm font-medium text-center transition {{ $currentLocale === $locale ? 'bg-white text-gray-900 shadow-sm dark:bg-white/10 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </form>
</div>
@elseif($context === 'login')
<div class="mt-4 flex justify-center">
    <form method="POST" action="{{ route('locale.switch') }}" class="inline-flex items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-white/5">
        @csrf
        @foreach ($locales as $locale => $label)
            <button
                type="submit"
                name="locale"
                value="{{ $locale }}"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition {{ $currentLocale === $locale ? 'bg-white text-gray-900 shadow-sm dark:bg-white/10 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </form>
</div>
@else
<div class="flex items-center">
    <form method="POST" action="{{ route('locale.switch') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-1 py-1 shadow-sm">
        @csrf
        @foreach ($locales as $locale => $label)
            <button
                type="submit"
                name="locale"
                value="{{ $locale }}"
                class="rounded-md px-2.5 py-1 text-xs font-medium transition {{ $currentLocale === $locale ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </form>
</div>
@endif
