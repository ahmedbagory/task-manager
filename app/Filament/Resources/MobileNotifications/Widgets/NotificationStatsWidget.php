<?php

namespace App\Filament\Resources\MobileNotifications\Widgets;

use App\Enums\MobileNotificationStatus;
use App\Filament\Resources\MobileNotifications\Pages\ListMobileNotifications;
use App\Models\MobileNotification;
use App\Models\MobileNotificationRecipient;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NotificationStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected function getTablePage(): string
    {
        return ListMobileNotifications::class;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $totalNotifications = MobileNotification::query()->count();
        $sentCount = MobileNotification::query()
            ->where('status', MobileNotificationStatus::SENT)->count();
        $queuedCount = MobileNotification::query()
            ->where('status', MobileNotificationStatus::QUEUED)->count();
        $failedCount = MobileNotification::query()
            ->where('status', MobileNotificationStatus::FAILED)->count();

        $totalRecipients = MobileNotificationRecipient::query()->count();
        $readRecipients = MobileNotificationRecipient::query()
            ->whereNotNull('read_at')->count();
        $readRate = $totalRecipients > 0
            ? round(($readRecipients / $totalRecipients) * 100, 1)
            : 0;

        $totalDevices = (int) MobileNotification::query()
            ->where('status', MobileNotificationStatus::SENT)
            ->sum('targeted_devices_count');

        $thisWeekSent = MobileNotification::query()
            ->where('status', MobileNotificationStatus::SENT)
            ->where('sent_at', '>=', now()->startOfWeek())
            ->count();

        return [
            Stat::make(__('Total Notifications'), (string) $totalNotifications)
                ->description(__('All notifications sent'))
                ->descriptionIcon('heroicon-o-bell-alert')
                ->color('gray'),
            Stat::make(__('Sent Successfully'), (string) $sentCount)
                ->description($thisWeekSent > 0 ? __(':count this week', ['count' => $thisWeekSent]) : __('No sends this week'))
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make(__('Queued'), (string) $queuedCount)
                ->description(__('Awaiting delivery'))
                ->descriptionIcon('heroicon-o-clock')
                ->color($queuedCount > 0 ? 'warning' : 'gray'),
            Stat::make(__('Failed'), (string) $failedCount)
                ->description($failedCount > 0 ? __('Needs attention') : __('All clear'))
                ->descriptionIcon($failedCount > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check')
                ->color($failedCount > 0 ? 'danger' : 'gray'),
            Stat::make(__('Read Rate'), $readRate.'%')
                ->description(__(':read / :total recipients read', ['read' => number_format($readRecipients), 'total' => number_format($totalRecipients)]))
                ->descriptionIcon('heroicon-o-eye')
                ->color($readRate >= 70 ? 'success' : ($readRate >= 40 ? 'warning' : 'danger')),
            Stat::make(__('Devices Reached'), number_format($totalDevices))
                ->description(__('Total FCM deliveries'))
                ->descriptionIcon('heroicon-o-device-phone-mobile')
                ->color('info'),
        ];
    }
}
