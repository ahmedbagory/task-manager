<?php

namespace App\Filament\Resources\WhatsappMessages\Schemas;

use App\Enums\WhatsappMessageDirection;
use App\Support\BidiText;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WhatsappMessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Message Details'))
                    ->components([
                        TextEntry::make('whatsapp_message_id')
                            ->label(__('WhatsApp Message ID'))
                            ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                            ->html()
                            ->placeholder('-'),
                        TextEntry::make('direction')
                            ->label(__('Direction'))
                            ->badge()
                            ->formatStateUsing(fn (WhatsappMessageDirection|string $state): string => ($state instanceof WhatsappMessageDirection ? $state : WhatsappMessageDirection::from((string) $state))->label())
                            ->color(fn (WhatsappMessageDirection|string $state): string => ($state instanceof WhatsappMessageDirection ? $state : WhatsappMessageDirection::from((string) $state))->color()),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('contact.name')
                            ->label(__('Contact'))
                            ->formatStateUsing(fn (?string $state) => BidiText::auto($state))
                            ->html()
                            ->placeholder('-'),
                        TextEntry::make('task.id')
                            ->label(__('Linked Task #'))
                            ->formatStateUsing(fn ($state, $record): string => $record->task?->displayNumber() ?? '-')
                            ->placeholder('-'),
                        TextEntry::make('task.title')
                            ->label(__('Linked Task Title'))
                            ->placeholder('-'),
                        TextEntry::make('from_phone')
                            ->label(__('From Phone'))
                            ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                            ->html()
                            ->placeholder('-'),
                        TextEntry::make('to_phone')
                            ->label(__('To Phone'))
                            ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                            ->html()
                            ->placeholder('-'),
                        TextEntry::make('group_name')
                            ->label(__('Group Name'))
                            ->formatStateUsing(fn (?string $state) => BidiText::auto($state))
                            ->html()
                            ->placeholder('-'),
                        TextEntry::make('group_id')
                            ->label(__('Group ID'))
                            ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                            ->html()
                            ->placeholder('-'),
                        TextEntry::make('message_type')
                            ->label(__('Message Type'))
                            ->placeholder('-'),
                        TextEntry::make('body')
                            ->label(__('Message Body'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('media_url')
                            ->label(__('Media URL'))
                            ->placeholder('-')
                            ->url(fn (?string $state): ?string => $state)
                            ->openUrlInNewTab(),
                        TextEntry::make('raw_payload')
                            ->label(__('Raw Payload'))
                            ->formatStateUsing(fn (array|string|null $state): string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ((string) $state ?: '-'))
                            ->columnSpanFull(),
                        TextEntry::make('received_at')
                            ->label(__('Received At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('sent_at')
                            ->label(__('Sent At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(3),
            ]);
    }
}
