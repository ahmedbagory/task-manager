<?php

namespace App\Filament\Resources\MobileNotifications\Tables;

use App\Enums\MobileNotificationStatus;
use App\Jobs\SendMobileNotificationJob;
use App\Models\MobileNotification;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MobileNotificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('status_icon')
                    ->label('')
                    ->width('40px')
                    ->getStateUsing(fn ($record): string => $record->status->value ?? 'queued')
                    ->icon(fn (string $state): string => match ($state) {
                        'sent' => 'heroicon-o-check-circle',
                        'queued' => 'heroicon-o-clock',
                        'failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-bell',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'queued' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('title')
                    ->label(__('Notification'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record): string => str((string) $record->body)->limit(60)->toString())
                    ->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (MobileNotificationStatus|string $state): string => ($state instanceof MobileNotificationStatus ? $state : MobileNotificationStatus::from((string) $state))->label())
                    ->color(fn (MobileNotificationStatus|string $state): string => ($state instanceof MobileNotificationStatus ? $state : MobileNotificationStatus::from((string) $state))->color()),
                TextColumn::make('delivery_info')
                    ->label(__('Delivery'))
                    ->getStateUsing(function ($record): string {
                        $users = (int) $record->targeted_users_count;
                        $devices = (int) $record->targeted_devices_count;

                        if ($users === 0 && $devices === 0) {
                            return '-';
                        }

                        return $users.' '.__('users').' / '.$devices.' '.__('devices');
                    })
                    ->description(function ($record): ?string {
                        $withDevices = (int) $record->targeted_users_with_devices_count;
                        $total = (int) $record->targeted_users_count;

                        if ($total === 0) {
                            return null;
                        }

                        $rate = round(($withDevices / $total) * 100);

                        return $rate.'% '.__('have devices');
                    })
                    ->color(fn ($record): string => ((int) $record->targeted_devices_count) > 0 ? 'success' : 'gray'),
                TextColumn::make('attachments_count')
                    ->label(__('Files'))
                    ->counts('attachments')
                    ->icon('heroicon-o-paper-clip')
                    ->placeholder('-')
                    ->color(fn ($state): string => $state > 0 ? 'info' : 'gray'),
                TextColumn::make('createdByUser.name')
                    ->label(__('Sender'))
                    ->icon('heroicon-o-user')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sent_at')
                    ->label(__('Sent'))
                    ->since()
                    ->placeholder('-')
                    ->sortable()
                    ->tooltip(fn ($record): ?string => $record->sent_at?->format('Y-m-d H:i:s'))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->since()
                    ->sortable()
                    ->tooltip(fn ($record): ?string => $record->created_at?->format('Y-m-d H:i:s'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'sent' => __('Sent'),
                        'queued' => __('Queued'),
                        'failed' => __('Failed'),
                    ]),
                SelectFilter::make('created_by')
                    ->label(__('Created By'))
                    ->relationship('createdByUser', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('resend')
                        ->label(__('Resend'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading(__('Resend Notification'))
                        ->modalDescription(__('This will re-dispatch the notification to all original recipients. Are you sure?'))
                        ->visible(fn (MobileNotification $record): bool => $record->status === MobileNotificationStatus::FAILED)
                        ->action(function (MobileNotification $record): void {
                            $record->update([
                                'status' => MobileNotificationStatus::QUEUED,
                                'queued_at' => now(),
                                'failed_at' => null,
                                'failure_message' => null,
                            ]);
                            SendMobileNotificationJob::dispatch($record->id);
                            Notification::make()
                                ->title(__('Notification re-queued for delivery.'))
                                ->success()
                                ->send();
                        }),
                    Action::make('duplicate')
                        ->label(__('Duplicate'))
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->url(fn (MobileNotification $record): string => route('filament.admin.resources.mobile-notifications.create', [
                            'title' => $record->title,
                            'body' => $record->body,
                        ])),
                ]),
            ])
            ->bulkActions([])
            ->striped()
            ->poll('30s')
            ->emptyStateIcon('heroicon-o-bell-slash')
            ->emptyStateHeading(__('No notifications yet'))
            ->emptyStateDescription(__('Send your first mobile notification to your team.'));
    }
}
