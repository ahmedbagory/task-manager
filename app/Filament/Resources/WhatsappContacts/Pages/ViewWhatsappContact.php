<?php

namespace App\Filament\Resources\WhatsappContacts\Pages;

use App\Filament\Resources\WhatsappContacts\WhatsappContactResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWhatsappContact extends ViewRecord
{
    protected static string $resource = WhatsappContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
