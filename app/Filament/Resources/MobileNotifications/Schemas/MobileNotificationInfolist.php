<?php

namespace App\Filament\Resources\MobileNotifications\Schemas;

use App\Enums\MobileNotificationStatus;
use App\Models\MobileNotificationAttachment;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class MobileNotificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ─── Notification Content ─────────────────────────────
                Section::make(__('Notification Content'))
                    ->icon('heroicon-o-bell-alert')
                    ->columns(2)
                    ->components([
                        TextEntry::make('title')
                            ->label(__('Title'))
                            ->weight('bold')
                            ->size('lg')
                            ->columnSpanFull(),
                        TextEntry::make('body')
                            ->label(__('Message'))
                            ->columnSpanFull()
                            ->markdown(),
                    ]),

                // ─── Delivery Status ──────────────────────────────────
                Section::make(__('Delivery Status'))
                    ->icon('heroicon-o-signal')
                    ->columns(4)
                    ->components([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (MobileNotificationStatus|string $state): string => ($state instanceof MobileNotificationStatus ? $state : MobileNotificationStatus::from((string) $state))->label())
                            ->color(fn (MobileNotificationStatus|string $state): string => ($state instanceof MobileNotificationStatus ? $state : MobileNotificationStatus::from((string) $state))->color())
                            ->size('lg'),
                        TextEntry::make('createdByUser.name')
                            ->label(__('Created by'))
                            ->icon('heroicon-o-user')
                            ->placeholder('-'),
                        TextEntry::make('queued_at')
                            ->label(__('Queued'))
                            ->icon('heroicon-o-clock')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('sent_at')
                            ->label(__('Delivered'))
                            ->icon('heroicon-o-check-circle')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('failed_at')
                            ->label(__('Failed'))
                            ->icon('heroicon-o-x-circle')
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn ($record): bool => $record->failed_at !== null),
                        TextEntry::make('failure_message')
                            ->label(__('Error Details'))
                            ->icon('heroicon-o-exclamation-triangle')
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->visible(fn ($record): bool => filled($record->failure_message))
                            ->color('danger'),
                    ]),

                // ─── Audience Metrics ─────────────────────────────────
                Section::make(__('Audience Metrics'))
                    ->icon('heroicon-o-chart-bar')
                    ->columns(4)
                    ->components([
                        TextEntry::make('targeted_users_count')
                            ->label(__('Total Recipients'))
                            ->icon('heroicon-o-users')
                            ->numeric()
                            ->placeholder('0'),
                        TextEntry::make('targeted_users_with_devices_count')
                            ->label(__('With Mobile App'))
                            ->icon('heroicon-o-device-phone-mobile')
                            ->numeric()
                            ->placeholder('0'),
                        TextEntry::make('targeted_devices_count')
                            ->label(__('Devices Reached'))
                            ->icon('heroicon-o-signal')
                            ->numeric()
                            ->placeholder('0'),
                        TextEntry::make('read_rate')
                            ->label(__('Read Rate'))
                            ->icon('heroicon-o-eye')
                            ->getStateUsing(function ($record): string {
                                $total = $record->recipients()->count();
                                $read = $record->recipients()->whereNotNull('read_at')->count();

                                if ($total === 0) {
                                    return '-';
                                }

                                return round(($read / $total) * 100).'% ('.$read.'/'.$total.')';
                            })
                            ->color(function ($record): string {
                                $total = $record->recipients()->count();
                                $read = $record->recipients()->whereNotNull('read_at')->count();
                                $rate = $total > 0 ? ($read / $total) * 100 : 0;

                                return $rate >= 70 ? 'success' : ($rate >= 40 ? 'warning' : 'danger');
                            }),
                        TextEntry::make('delivery_breakdown')
                            ->label(__('Delivery Breakdown'))
                            ->columnSpanFull()
                            ->getStateUsing(function ($record): HtmlString {
                                $recipients = $record->recipients;
                                $total = $recipients->count();

                                if ($total === 0) {
                                    return new HtmlString('<span class="text-sm text-gray-500">'.__('No delivery data available.').'</span>');
                                }

                                $sent = $recipients->where('status', 'sent')->count();
                                $failed = $recipients->where('status', 'failed')->count();
                                $skipped = $recipients->where('status', 'skipped_no_device')->count();
                                $pending = $recipients->where('status', 'pending')->count();

                                $sentPct = round(($sent / $total) * 100);
                                $failedPct = round(($failed / $total) * 100);
                                $skippedPct = round(($skipped / $total) * 100);

                                $html = '<div class="space-y-3">';

                                // Progress bar
                                $html .= '<div class="flex h-3 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">';
                                if ($sentPct > 0) {
                                    $html .= '<div class="h-full bg-success-500 transition-all duration-500" style="width: '.$sentPct.'%"></div>';
                                }
                                if ($failedPct > 0) {
                                    $html .= '<div class="h-full bg-danger-500 transition-all duration-500" style="width: '.$failedPct.'%"></div>';
                                }
                                if ($skippedPct > 0) {
                                    $html .= '<div class="h-full bg-gray-400 transition-all duration-500" style="width: '.$skippedPct.'%"></div>';
                                }
                                $html .= '</div>';

                                // Legend
                                $html .= '<div class="flex flex-wrap gap-4 text-sm">';
                                $html .= '<span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-success-500"></span> '.e(__('Delivered')).': '.$sent.'</span>';
                                if ($failed > 0) {
                                    $html .= '<span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-danger-500"></span> '.e(__('Failed')).': '.$failed.'</span>';
                                }
                                if ($skipped > 0) {
                                    $html .= '<span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-gray-400"></span> '.e(__('No Device')).': '.$skipped.'</span>';
                                }
                                if ($pending > 0) {
                                    $html .= '<span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-warning-500"></span> '.e(__('Pending')).': '.$pending.'</span>';
                                }
                                $html .= '</div>';
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),

                // ─── Attachments ──────────────────────────────────────
                Section::make(__('Attachments'))
                    ->icon('heroicon-o-paper-clip')
                    ->visible(fn ($record): bool => $record->attachments->isNotEmpty())
                    ->collapsible()
                    ->components([
                        RepeatableEntry::make('attachments')
                            ->label('')
                            ->schema([
                                TextEntry::make('original_name')
                                    ->label(__('File'))
                                    ->icon(fn (MobileNotificationAttachment $record): string => match ($record->type) {
                                        'image' => 'heroicon-o-photo',
                                        'video' => 'heroicon-o-film',
                                        'document' => 'heroicon-o-document-text',
                                        default => 'heroicon-o-paper-clip',
                                    }),
                                TextEntry::make('type')
                                    ->label(__('Type'))
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'image' => 'info',
                                        'video' => 'warning',
                                        'document' => 'primary',
                                        default => 'gray',
                                    }),
                                TextEntry::make('human_size')
                                    ->label(__('Size'))
                                    ->getStateUsing(fn (MobileNotificationAttachment $record): string => $record->humanSize()),
                                TextEntry::make('url')
                                    ->label(__('Download'))
                                    ->getStateUsing(fn (): string => __('Open'))
                                    ->url(fn (MobileNotificationAttachment $record): string => $record->url())
                                    ->openUrlInNewTab()
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('primary'),
                            ])
                            ->columns(4),
                    ]),

                // ─── Audience Targets ─────────────────────────────────
                Section::make(__('Audience Targets'))
                    ->icon('heroicon-o-user-group')
                    ->collapsible()
                    ->collapsed()
                    ->components([
                        RepeatableEntry::make('targets')
                            ->label('')
                            ->schema([
                                TextEntry::make('target_type_label')
                                    ->label(__('Type'))
                                    ->icon(fn ($record): string => match ($record->target_type) {
                                        'department' => 'heroicon-o-building-office',
                                        'user' => 'heroicon-o-user',
                                        'all' => 'heroicon-o-users',
                                        default => 'heroicon-o-tag',
                                    })
                                    ->badge()
                                    ->color(fn ($record): string => match ($record->target_type) {
                                        'department' => 'primary',
                                        'user' => 'info',
                                        'all' => 'success',
                                        default => 'gray',
                                    }),
                                TextEntry::make('target_name')
                                    ->label(__('Target')),
                            ])
                            ->columns(2)
                            ->placeholder(__('No audience selected.')),
                    ]),
            ]);
    }
}
