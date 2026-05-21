<?php

namespace App\Filament\Resources\WhatsappMessages\Pages;

use App\Filament\Resources\WhatsappMessages\Actions\ConvertWhatsappMessageToTaskAction;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use Filament\Resources\Pages\ViewRecord;

class ViewWhatsappMessage extends ViewRecord
{
    protected static string $resource = WhatsappMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConvertWhatsappMessageToTaskAction::makeViewLinkedTask(),
            ConvertWhatsappMessageToTaskAction::make(),
        ];
    }
}
