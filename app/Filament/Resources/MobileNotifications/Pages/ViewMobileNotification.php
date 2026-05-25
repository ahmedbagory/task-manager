<?php

namespace App\Filament\Resources\MobileNotifications\Pages;

use App\Enums\MobileNotificationStatus;
use App\Filament\Resources\MobileNotifications\MobileNotificationResource;
use App\Jobs\SendMobileNotificationJob;
use App\Models\MobileNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewMobileNotification extends ViewRecord
{
    protected static string $resource = MobileNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resend')
                ->label(__('Resend'))
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Resend Notification'))
                ->modalDescription(__('This will re-dispatch the notification to all original recipients. Are you sure?'))
                ->modalSubmitActionLabel(__('Yes, Resend'))
                ->visible(fn (): bool => $this->record->status === MobileNotificationStatus::FAILED)
                ->action(function (): void {
                    /** @var MobileNotification $record */
                    $record = $this->record;
                    $record->update([
                        'status' => MobileNotificationStatus::QUEUED,
                        'queued_at' => now(),
                        'failed_at' => null,
                        'failure_message' => null,
                    ]);
                    SendMobileNotificationJob::dispatch($record->id);
                    Notification::make()
                        ->title(__('Notification re-queued for delivery.'))
                        ->success()
                        ->send();
                }),
            Action::make('duplicate')
                ->label(__('Send Similar'))
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->url(fn (): string => route('filament.admin.resources.mobile-notifications.create', [
                    'title' => $this->record->title,
                    'body' => $this->record->body,
                ])),
        ];
    }
}
