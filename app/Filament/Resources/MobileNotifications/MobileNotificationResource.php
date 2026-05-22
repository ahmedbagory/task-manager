<?php

namespace App\Filament\Resources\MobileNotifications;

use App\Filament\Resources\MobileNotifications\Pages\CreateMobileNotification;
use App\Filament\Resources\MobileNotifications\Pages\ListMobileNotifications;
use App\Filament\Resources\MobileNotifications\Pages\ViewMobileNotification;
use App\Filament\Resources\MobileNotifications\RelationManagers\RecipientsRelationManager;
use App\Filament\Resources\MobileNotifications\Schemas\MobileNotificationForm;
use App\Filament\Resources\MobileNotifications\Schemas\MobileNotificationInfolist;
use App\Filament\Resources\MobileNotifications\Tables\MobileNotificationsTable;
use App\Models\MobileNotification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MobileNotificationResource extends Resource
{
    protected static ?string $model = MobileNotification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return MobileNotificationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MobileNotificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MobileNotificationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMobileNotifications::route('/'),
            'create' => CreateMobileNotification::route('/create'),
            'view' => ViewMobileNotification::route('/{record}'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('Mobile Notification');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Mobile Notifications');
    }

    public static function getNavigationLabel(): string
    {
        return __('Mobile Notifications');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Task Management');
    }
}
