<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Employee Details'))
                    ->components([
                        TextEntry::make('name')
                            ->label(__('Name')),
                        TextEntry::make('email')
                            ->label(__('Email')),
                        TextEntry::make('phone')
                            ->label(__('Phone'))
                            ->placeholder('-'),
                        TextEntry::make('roles_display')
                            ->label(__('Role'))
                            ->state(fn (User $record): string => $record->roles->pluck('name')->join(', ') ?: '-'),
                        TextEntry::make('department.hierarchy_name')
                            ->label('القسم / الوحدة')
                            ->placeholder('-'),
                        TextEntry::make('department.parent.name')
                            ->label('القسم الرئيسي')
                            ->placeholder('-'),
                        TextEntry::make('work_location')
                            ->label(__('Work Location'))
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label(__('Updated At'))
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
