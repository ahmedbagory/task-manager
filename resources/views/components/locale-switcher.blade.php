@props([
    'context' => 'public',
])

@php
    $locales = config('app.supported_locales', ['en' => 'English', 'ar' => 'العربية']);
    $currentLocale = app()->getLocale();
@endphp

@if($context === 'panel')
<x-filament::dropdown.list>
    <form method="POST" action="{{ route('locale.switch') }}" style="width: 100%;">
        @csrf
        <x-filament::button.group style="width: 100%;">
            @foreach ($locales as $locale => $label)
                <x-filament::button
                    type="submit"
                    name="locale"
                    value="{{ $locale }}"
                    size="sm"
                    :color="$currentLocale === $locale ? 'primary' : 'gray'"
                >
                    {{ $label }}
                </x-filament::button>
            @endforeach
        </x-filament::button.group>
    </form>
</x-filament::dropdown.list>
@elseif($context === 'login')
<div style="margin-top: 1rem; display: flex; justify-content: center;">
    <form method="POST" action="{{ route('locale.switch') }}">
        @csrf
        <x-filament::button.group>
            @foreach ($locales as $locale => $label)
                <x-filament::button
                    type="submit"
                    name="locale"
                    value="{{ $locale }}"
                    size="sm"
                    :color="$currentLocale === $locale ? 'primary' : 'gray'"
                >
                    {{ $label }}
                </x-filament::button>
            @endforeach
        </x-filament::button.group>
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
