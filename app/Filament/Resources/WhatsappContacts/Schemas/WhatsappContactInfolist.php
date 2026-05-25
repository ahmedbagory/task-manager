<?php

namespace App\Filament\Resources\WhatsappContacts\Schemas;

use App\Support\BidiText;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WhatsappContactInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ملخص جهة الاتصال')
                    ->columns(12)
                    ->components([
                        TextEntry::make('name')
                            ->label('الاسم')
                            ->state(fn ($record): string => $record->displayName())
                            ->formatStateUsing(fn (?string $state) => BidiText::auto($state))
                            ->html()
                            ->columnSpan(4),
                        TextEntry::make('phone')
                            ->label('رقم الهاتف')
                            ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                            ->html()
                            ->columnSpan(4),
                        TextEntry::make('status')
                            ->label('الحالة')
                            ->state(fn ($record): string => $record->statusLabel())
                            ->placeholder('—')
                            ->columnSpan(4),
                        TextEntry::make('department.name')
                            ->label('القسم / الوحدة الافتراضية')
                            ->placeholder('—')
                            ->columnSpan(4),
                        TextEntry::make('default_location')
                            ->label('الموقع الافتراضي')
                            ->placeholder('—')
                            ->columnSpan(4),
                        TextEntry::make('last_message_at')
                            ->label('آخر رسالة')
                            ->dateTime()
                            ->placeholder('—')
                            ->columnSpan(4),
                    ]),
            ]);
    }
}
