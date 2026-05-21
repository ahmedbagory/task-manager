<?php

namespace App\Filament\Resources\WhatsappContacts\Pages;

use App\Filament\Resources\WhatsappContacts\WhatsappContactResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWhatsappContact extends EditRecord
{
    protected static string $resource = WhatsappContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
