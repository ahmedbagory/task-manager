<?php

namespace App\Filament\Resources\WhatsappContacts;

use App\Filament\Resources\WhatsappContacts\Pages\CreateWhatsappContact;
use App\Filament\Resources\WhatsappContacts\Pages\EditWhatsappContact;
use App\Filament\Resources\WhatsappContacts\Pages\ListWhatsappContacts;
use App\Filament\Resources\WhatsappContacts\Pages\ViewWhatsappContact;
use App\Filament\Resources\WhatsappContacts\Schemas\WhatsappContactForm;
use App\Filament\Resources\WhatsappContacts\Schemas\WhatsappContactInfolist;
use App\Filament\Resources\WhatsappContacts\Tables\WhatsappContactsTable;
use App\Models\WhatsappContact;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WhatsappContactResource extends Resource
{
    protected static ?string $model = WhatsappContact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return WhatsappContactForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WhatsappContactInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsappContactsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappContacts::route('/'),
            'create' => CreateWhatsappContact::route('/create'),
            'view' => ViewWhatsappContact::route('/{record}'),
            'edit' => EditWhatsappContact::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('WhatsApp Contact');
    }

    public static function getPluralModelLabel(): string
    {
        return __('WhatsApp Contacts');
    }

    public static function getNavigationLabel(): string
    {
        return __('WhatsApp Contacts');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Task Management');
    }
}
