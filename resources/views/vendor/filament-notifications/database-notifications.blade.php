@php
    use Filament\Support\Enums\Alignment;
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\View\Components\BadgeComponent;
    use Illuminate\View\ComponentAttributeBag;

    $notifications = $this->getNotifications();
    $unreadNotificationsCount = $this->getUnreadNotificationsCount();
    $hasNotifications = $notifications->count();
    $isPaginated = $notifications instanceof \Illuminate\Contracts\Pagination\Paginator && $notifications->hasPages();
    $pollingInterval = $this->getPollingInterval();
    $markAllAsReadLabel = __('filament-notifications::database.modal.actions.mark_all_as_read.label');
    $clearNotificationsLabel = __('filament-notifications::database.modal.actions.clear.label');
@endphp

<div class="fi-no-database">
    <x-filament::modal
        :alignment="$hasNotifications ? null : Alignment::Center"
        close-button
        :description="$hasNotifications ? null : __('filament-notifications::database.modal.empty.description')"
        :heading="$hasNotifications ? null : __('filament-notifications::database.modal.empty.heading')"
        :icon="$hasNotifications ? null : Heroicon::OutlinedBellSlash"
        :icon-alias="
            $hasNotifications
            ? null
            : \Filament\Notifications\View\NotificationsIconAlias::DATABASE_MODAL_EMPTY_STATE
        "
        :icon-color="$hasNotifications ? null : 'gray'"
        id="database-notifications"
        slide-over
        :sticky-header="$hasNotifications"
        teleport="body"
        width="md"
        class="fi-no-database"
        :attributes="
            new \Illuminate\View\ComponentAttributeBag([
                'wire:poll.' . $pollingInterval => $pollingInterval ? '' : false,
            ])
        "
    >
        @if ($trigger = $this->getTrigger())
            <x-slot name="trigger">
                {{ $trigger->with(['unreadNotificationsCount' => $unreadNotificationsCount]) }}
            </x-slot>
        @endif

        @if ($hasNotifications)
            <x-slot name="header">
                <div class="fi-no-header">
                    <div class="fi-no-header-main">
                        <h2 class="fi-modal-heading">
                            {{ __('filament-notifications::database.modal.heading') }}

                            @if ($unreadNotificationsCount)
                                <span
                                    {{
                                        (new ComponentAttributeBag)->color(BadgeComponent::class, 'primary')->class([
                                            'fi-badge fi-size-xs',
                                        ])
                                    }}
                                >
                                    {{ $unreadNotificationsCount }}
                                </span>
                            @endif
                        </h2>
                    </div>

                    <div class="fi-no-toolbar" aria-label="{{ __('filament-notifications::database.modal.heading') }}">
                        @if ($unreadNotificationsCount && $this->markAllNotificationsAsReadAction()->isVisible())
                            <x-filament::icon-button
                                color="warning"
                                :icon="Heroicon::OutlinedEnvelopeOpen"
                                :label="$markAllAsReadLabel"
                                :tooltip="$markAllAsReadLabel"
                                size="sm"
                                class="fi-no-toolbar-btn fi-no-toolbar-btn-read"
                                wire:click="markAllNotificationsAsRead"
                            />
                        @endif

                        @if ($this->clearNotificationsAction()->isVisible())
                            <x-filament::icon-button
                                color="danger"
                                :icon="Heroicon::OutlinedTrash"
                                :label="$clearNotificationsLabel"
                                :tooltip="$clearNotificationsLabel"
                                size="sm"
                                class="fi-no-toolbar-btn fi-no-toolbar-btn-clear"
                                wire:click="clearNotifications"
                            />
                        @endif
                    </div>
                </div>
            </x-slot>

            @foreach ($notifications as $notification)
                <div
                    @class([
                        'fi-no-notification-read-ctn' => ! $notification->unread(),
                        'fi-no-notification-unread-ctn' => $notification->unread(),
                    ])
                >
                    {{ $this->getNotification($notification)->inline() }}
                </div>
            @endforeach

            @if ($broadcastChannel = $this->getBroadcastChannel())
                @script
                    <script>
                        window.addEventListener('EchoLoaded', () => {
                            window.Echo.private(@js($broadcastChannel)).listen(
                                '.database-notifications.sent',
                                () => {
                                    setTimeout(
                                        () => $wire.call('$refresh'),
                                        500,
                                    )
                                },
                            )
                        })

                        if (window.Echo) {
                            window.dispatchEvent(new CustomEvent('EchoLoaded'))
                        }
                    </script>
                @endscript
            @endif

            @if ($isPaginated)
                <x-slot name="footer">
                    <x-filament::pagination :paginator="$notifications" />
                </x-slot>
            @endif
        @endif
    </x-filament::modal>
</div>
