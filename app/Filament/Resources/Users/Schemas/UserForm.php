<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Services\Departments\DepartmentHierarchyService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Account Details'))
                    ->components([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->label(__('Phone (Optional)'))
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('roles')
                            ->label(__('Role'))
                            ->relationship(
                                name: 'roles',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('guard_name', 'web')->orderBy('name'),
                            )
                            ->multiple()
                            ->minItems(1)
                            ->maxItems(1)
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->minLength(8)
                            ->maxLength(255),
                        TextInput::make('password_confirmation')
                            ->label(__('Password Confirmation'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->same('password')
                            ->dehydrated(false)
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make(__('Work Profile'))
                    ->components([
                        Select::make('department_id')
                            ->label('القسم / الوحدة')
                            ->options(fn (): array => app(DepartmentHierarchyService::class)->employeeDepartmentOptions())
                            ->searchable()
                            ->preload(),
                        TextInput::make('work_location')
                            ->label(__('Work Location'))
                            ->maxLength(255)
                            ->placeholder(__('Example: Mega 6')),
                    ])
                    ->columns(2),
            ]);
    }
}
