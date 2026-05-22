<?php

namespace App\Filament\Resources\MobileNotifications\Pages;

use App\Filament\Resources\MobileNotifications\MobileNotificationResource;
use App\Models\User;
use App\Services\Notifications\MobileNotificationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMobileNotification extends CreateRecord
{
    protected static string $resource = MobileNotificationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(MobileNotificationService::class)->createQueuedNotification($data, $user);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('Mobile notification queued');
    }
}
