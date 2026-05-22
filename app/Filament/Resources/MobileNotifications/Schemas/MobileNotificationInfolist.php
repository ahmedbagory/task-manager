<?php

namespace App\Filament\Resources\MobileNotifications\Schemas;

use App\Enums\MobileNotificationStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MobileNotificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Mobile Notification'))
                    ->columns(3)
                    ->components([
                        TextEntry::make('title')
                            ->label(__('Notification Title'))
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (MobileNotificationStatus|string $state): string => ($state instanceof MobileNotificationStatus ? $state : MobileNotificationStatus::from((string) $state))->label())
                            ->color(fn (MobileNotificationStatus|string $state): string => ($state instanceof MobileNotificationStatus ? $state : MobileNotificationStatus::from((string) $state))->color()),
                        TextEntry::make('createdByUser.name')
                            ->label(__('Created by'))
                            ->placeholder('-'),
                        TextEntry::make('queued_at')
                            ->label(__('Queued At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('sent_at')
                            ->label(__('Sent At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('failed_at')
                            ->label(__('Failed At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('targeted_users_count')
                            ->label(__('Resolved audience')),
                        TextEntry::make('targeted_users_with_devices_count')
                            ->label(__('Recipients with app devices')),
                        TextEntry::make('targeted_devices_count')
                            ->label(__('Registered devices')),
                        TextEntry::make('body')
                            ->label(__('Notification Message'))
                            ->columnSpanFull(),
                        TextEntry::make('failure_message')
                            ->label(__('Last error'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Notification Audience'))
                    ->components([
                        RepeatableEntry::make('targets')
                            ->label('')
                            ->schema([
                                TextEntry::make('target_type_label')
                                    ->label(__('Type')),
                                TextEntry::make('target_name')
                                    ->label(__('Target')),
                            ])
                            ->columns(2)
                            ->placeholder(__('No audience selected yet.')),
                    ]),
            ]);
    }
}
