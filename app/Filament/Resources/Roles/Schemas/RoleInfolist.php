<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class RoleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Role Details'))
                    ->components([
                        TextEntry::make('name')
                            ->label(__('Name')),
                        TextEntry::make('guard_name')
                            ->label(__('Guard Name')),
                        TextEntry::make('permissions_display')
                            ->label(__('Permissions'))
                            ->state(fn (Role $record): string => $record->permissions->pluck('name')->join(', ') ?: '-'),
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
