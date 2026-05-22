<?php

namespace App\Filament\Resources\MobileNotifications\Pages;

use App\Filament\Resources\MobileNotifications\MobileNotificationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMobileNotifications extends ListRecords
{
    protected static string $resource = MobileNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
