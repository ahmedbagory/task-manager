<?php

namespace App\Filament\Resources\Departments\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DepartmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Department'))
                    ->components([
                        TextEntry::make('name')
                            ->label(__('Name')),
                        TextEntry::make('code')
                            ->label(__('Code'))
                            ->placeholder('-'),
                        IconEntry::make('is_active')
                            ->label(__('Active'))
                            ->boolean(),
                        TextEntry::make('description')
                            ->label(__('Description'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label(__('Updated At'))
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(2),
            ]);
    }
}
