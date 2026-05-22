<?php

namespace App\Filament\Resources\MobileNotifications\Pages;

use App\Filament\Resources\MobileNotifications\MobileNotificationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMobileNotification extends ViewRecord
{
    protected static string $resource = MobileNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
