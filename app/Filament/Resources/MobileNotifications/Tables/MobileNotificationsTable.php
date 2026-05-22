<?php

namespace App\Filament\Resources\MobileNotifications\Tables;

use App\Enums\MobileNotificationStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MobileNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('Notification Title'))
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): string => str((string) $record->body)->limit(80)->toString()),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (MobileNotificationStatus|string $state): string => ($state instanceof MobileNotificationStatus ? $state : MobileNotificationStatus::from((string) $state))->label())
                    ->color(fn (MobileNotificationStatus|string $state): string => ($state instanceof MobileNotificationStatus ? $state : MobileNotificationStatus::from((string) $state))->color()),
                TextColumn::make('targeted_users_count')
                    ->label(__('Employees'))
                    ->sortable(),
                TextColumn::make('targeted_devices_count')
                    ->label(__('Registered devices'))
                    ->sortable(),
                TextColumn::make('createdByUser.name')
                    ->label(__('Created by'))
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
