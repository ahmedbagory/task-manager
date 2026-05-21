<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Role Details'))
                    ->components([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Hidden::make('guard_name')
                            ->label(__('Guard Name'))
                            ->default('web')
                            ->dehydrated(),
                        Select::make('permissions')
                            ->label(__('Permissions'))
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('guard_name', 'web')->orderBy('name'),
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
