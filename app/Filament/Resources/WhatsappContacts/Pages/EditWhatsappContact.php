<?php

namespace App\Filament\Resources\WhatsappContacts\Pages;

use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Filament\Resources\WhatsappContacts\WhatsappContactResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatsappContact extends EditRecord
{
    protected static string $resource = WhatsappContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openConversation')
                ->label('فتح المحادثة')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->url(fn (): string => WhatsappMessageResource::getUrl('index', ['contact' => $this->getRecord()->getKey()])),
            ViewAction::make()->label('عرض'),
            DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'تعديل جهة اتصال واتساب';
    }
}
