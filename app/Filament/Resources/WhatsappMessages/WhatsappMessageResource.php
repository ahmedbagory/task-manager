<?php

namespace App\Filament\Resources\WhatsappMessages;

use App\Filament\Resources\WhatsappMessages\Pages\ListWhatsappMessages;
use App\Filament\Resources\WhatsappMessages\Pages\ViewWhatsappMessage;
use App\Filament\Resources\WhatsappMessages\Schemas\WhatsappMessageInfolist;
use App\Filament\Resources\WhatsappMessages\Tables\WhatsappMessagesTable;
use App\Models\WhatsappMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WhatsappMessageResource extends Resource
{
    protected static ?string $model = WhatsappMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 4;

    public static function infolist(Schema $schema): Schema
    {
        return WhatsappMessageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsappMessagesTable::configure($table);
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
            'index' => ListWhatsappMessages::route('/'),
            'view' => ViewWhatsappMessage::route('/{record}'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('WhatsApp Message');
    }

    public static function getPluralModelLabel(): string
    {
        return __('WhatsApp Inbox');
    }

    public static function getNavigationLabel(): string
    {
        return __('WhatsApp Inbox');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Task Management');
    }
}
