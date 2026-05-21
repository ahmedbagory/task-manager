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
                Section::make(__('Contact Details'))
                    ->components([
                        TextEntry::make('phone')
                            ->label(__('Phone Number'))
                            ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                            ->html(),
                        TextEntry::make('name')
                            ->label(__('Contact Name'))
                            ->formatStateUsing(fn (?string $state) => BidiText::auto($state))
                            ->html()
                            ->placeholder('-'),
                        TextEntry::make('department.name')
                            ->label(__('Default Department'))
                            ->placeholder('-'),
                        TextEntry::make('default_location')
                            ->label(__('Default Branch / Location'))
                            ->placeholder('-'),
                        TextEntry::make('last_message_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
