<?php

namespace App\Filament\Resources\MobileNotifications\Pages;

use App\Enums\MobileNotificationStatus;
use App\Filament\Resources\MobileNotifications\MobileNotificationResource;
use App\Filament\Resources\MobileNotifications\Widgets\NotificationStatsWidget;
use App\Models\MobileNotification;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListMobileNotifications extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = MobileNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scheduled')
                ->label(__('جدولة التنبيهات'))
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->url(MobileNotificationResource::getUrl('scheduled')),
            CreateAction::make()
                ->icon('heroicon-o-paper-airplane')
                ->label(__('Send Notification')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            NotificationStatsWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }

    public function getTabs(): array
    {
        $counts = [
            'all' => MobileNotification::query()->count(),
            'sent' => MobileNotification::query()->where('status', MobileNotificationStatus::SENT)->count(),
            'queued' => MobileNotification::query()->where('status', MobileNotificationStatus::QUEUED)->count(),
            'failed' => MobileNotification::query()->where('status', MobileNotificationStatus::FAILED)->count(),
        ];

        return [
            'all' => Tab::make(__('All'))
                ->badge($counts['all'] > 0 ? (string) $counts['all'] : null)
                ->icon('heroicon-o-bell-alert'),
            'sent' => Tab::make(__('Sent'))
                ->badge($counts['sent'] > 0 ? (string) $counts['sent'] : null)
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn ($query) => $query->where('status', MobileNotificationStatus::SENT)),
            'queued' => Tab::make(__('Queued'))
                ->badge($counts['queued'] > 0 ? (string) $counts['queued'] : null)
                ->badgeColor('warning')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn ($query) => $query->where('status', MobileNotificationStatus::QUEUED)),
            'failed' => Tab::make(__('Failed'))
                ->badge($counts['failed'] > 0 ? (string) $counts['failed'] : null)
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn ($query) => $query->where('status', MobileNotificationStatus::FAILED)),
        ];
    }
}
