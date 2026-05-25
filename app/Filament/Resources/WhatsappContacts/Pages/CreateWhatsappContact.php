<?php

namespace App\Filament\Resources\WhatsappContacts\Pages;

use App\Filament\Resources\WhatsappContacts\WhatsappContactResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWhatsappContact extends CreateRecord
{
    protected static string $resource = WhatsappContactResource::class;

    public function getTitle(): string
    {
        return 'إضافة جهة اتصال واتساب';
    }
}
