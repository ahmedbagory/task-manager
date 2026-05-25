<?php

namespace App\Filament\Resources\MobileNotifications\RelationManagers;

use App\Enums\MobileNotificationRecipientStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = null;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Recipients').' ('.$ownerRecord->recipients()->count().')';
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('delivery_status')
                    ->label('')
                    ->width('40px')
                    ->getStateUsing(fn ($record): string => $record->status->value ?? 'pending')
                    ->icon(fn (string $state): string => match ($state) {
                        'sent' => 'heroicon-o-check-circle',
                        'pending' => 'heroicon-o-clock',
                        'skipped_no_device' => 'heroicon-o-device-phone-mobile',
                        'failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-minus-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'pending' => 'warning',
                        'skipped_no_device' => 'gray',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')
                    ->label(__('Employee'))
                    ->searchable()
                    ->icon('heroicon-o-user')
                    ->weight('bold'),
                TextColumn::make('user.department.hierarchy_name')
                    ->label(__('Department'))
                    ->icon('heroicon-o-building-office')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (MobileNotificationRecipientStatus|string $state): string => ($state instanceof MobileNotificationRecipientStatus ? $state : MobileNotificationRecipientStatus::from((string) $state))->label())
                    ->color(fn (MobileNotificationRecipientStatus|string $state): string => ($state instanceof MobileNotificationRecipientStatus ? $state : MobileNotificationRecipientStatus::from((string) $state))->color()),
                TextColumn::make('device_count')
                    ->label(__('Devices'))
                    ->icon('heroicon-o-device-phone-mobile')
                    ->numeric()
                    ->description(fn ($record): ?string => $record->delivered_devices_count > 0
                        ? $record->delivered_devices_count.'/'.$record->device_count.' '.__('delivered')
                        : null
                    ),
                TextColumn::make('read_at')
                    ->label(__('Read'))
                    ->placeholder(__('Not read'))
                    ->since()
                    ->tooltip(fn ($record): ?string => $record->read_at?->format('Y-m-d H:i:s'))
                    ->icon(fn ($record): string => $record->read_at ? 'heroicon-o-eye' : 'heroicon-o-eye-slash')
                    ->color(fn ($record): string => $record->read_at ? 'success' : 'gray'),
                TextColumn::make('sent_at')
                    ->label(__('Sent'))
                    ->since()
                    ->placeholder('-')
                    ->tooltip(fn ($record): ?string => $record->sent_at?->format('Y-m-d H:i:s'))
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Delivery Status'))
                    ->options([
                        'sent' => __('Sent'),
                        'pending' => __('Pending'),
                        'skipped_no_device' => __('No Device'),
                        'failed' => __('Failed'),
                    ]),
                SelectFilter::make('read')
                    ->label(__('Read Status'))
                    ->options([
                        'read' => __('Read'),
                        'unread' => __('Unread'),
                    ])
                    ->query(function ($query, array $data) {
                        if (($data['value'] ?? null) === 'read') {
                            $query->whereNotNull('read_at');
                        } elseif (($data['value'] ?? null) === 'unread') {
                            $query->whereNull('read_at');
                        }
                    }),
            ])
            ->headerActions([])
            ->recordActions([])
            ->striped()
            ->poll('30s')
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading(__('No recipients'))
            ->emptyStateDescription(__('Recipients will appear here after the notification is dispatched.'));
    }
}
