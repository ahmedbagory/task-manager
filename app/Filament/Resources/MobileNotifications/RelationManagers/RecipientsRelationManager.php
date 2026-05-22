<?php

namespace App\Filament\Resources\MobileNotifications\RelationManagers;

use App\Enums\MobileNotificationRecipientStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = null;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Recipients');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('Employee'))
                    ->searchable(),
                TextColumn::make('user.department.hierarchy_name')
                    ->label(__('Department'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (MobileNotificationRecipientStatus|string $state): string => ($state instanceof MobileNotificationRecipientStatus ? $state : MobileNotificationRecipientStatus::from((string) $state))->label())
                    ->color(fn (MobileNotificationRecipientStatus|string $state): string => ($state instanceof MobileNotificationRecipientStatus ? $state : MobileNotificationRecipientStatus::from((string) $state))->color()),
                TextColumn::make('device_count')
                    ->label(__('Registered devices')),
                TextColumn::make('delivered_devices_count')
                    ->label(__('Delivered devices')),
                TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime()
                    ->placeholder('-'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
