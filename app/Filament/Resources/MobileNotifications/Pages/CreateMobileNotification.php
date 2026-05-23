<?php

namespace App\Filament\Resources\MobileNotifications\Pages;

use App\Filament\Resources\MobileNotifications\MobileNotificationResource;
use App\Models\MobileNotificationAttachment;
use App\Models\User;
use App\Services\Notifications\MobileNotificationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateMobileNotification extends CreateRecord
{
    protected static string $resource = MobileNotificationResource::class;

    private const FILE_SIZE_LIMITS = [
        'image' => 10 * 1024 * 1024,
        'document' => 20 * 1024 * 1024,
        'video' => 50 * 1024 * 1024,
        'other' => 20 * 1024 * 1024,
    ];

    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        $uploadedFiles = collect($data['attachments'] ?? [])
            ->filter(fn ($file) => $file instanceof TemporaryUploadedFile);
        unset($data['attachments']);

        $notification = app(MobileNotificationService::class)->createQueuedNotification($data, $user);

        foreach ($uploadedFiles as $file) {
            $mimeType = (string) $file->getMimeType();
            $type = MobileNotificationAttachment::resolveType($mimeType);
            $sizeLimit = self::FILE_SIZE_LIMITS[$type] ?? self::FILE_SIZE_LIMITS['other'];

            if ($file->getSize() > $sizeLimit) {
                continue;
            }

            $storedPath = $file->store(
                "mobile-notification-attachments/{$notification->id}",
                'public',
            );

            $notification->attachments()->create([
                'disk' => 'public',
                'path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'size' => $file->getSize(),
                'type' => $type,
            ]);
        }

        return $notification;
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
