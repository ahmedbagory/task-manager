@php
    $hasActions = filled($headerActions ?? []);
@endphp

<header class="fi-header" data-whatsapp-session-header>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="fi-header-heading">
                {{ $heading }}
            </h1>

            <div data-whatsapp-session-status-badge>
                <x-filament::badge :color="$statusBadge">
                    {{ $statusLabel }}
                </x-filament::badge>
            </div>
        </div>

        <p class="fi-header-subheading">
            {{ $subheading }}
        </p>
    </div>

    @if ($hasActions)
        <div class="fi-header-actions-ctn" data-whatsapp-session-primary-actions>
            <x-filament::actions
                :actions="$headerActions"
                :alignment="$headerActionsAlignment"
            />
        </div>
    @endif
</header>
