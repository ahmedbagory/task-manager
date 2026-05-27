@php
    $hasActions = filled($headerActions ?? []);
@endphp

<header class="fi-header" data-whatsapp-session-header>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <h1 class="fi-header-heading">
            {{ $heading }}
        </h1>

        <x-filament::badge :color="$statusBadge">
            {{ $statusLabel }}
        </x-filament::badge>
    </div>

    @if ($subheading)
        <p class="fi-header-subheading">
            {{ $subheading }}
        </p>
    @endif

    @if ($hasActions)
        <x-filament::actions
            :actions="$headerActions"
            :alignment="$headerActionsAlignment"
        />
    @endif
</header>
