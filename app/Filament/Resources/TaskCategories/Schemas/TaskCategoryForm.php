<?php

namespace App\Filament\Resources\TaskCategories\Schemas;

use App\Services\Departments\DepartmentHierarchyService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TaskCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Category Details'))
                    ->components([
                        Select::make('department_id')
                            ->label('القسم / الوحدة')
                            ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions())
                            ->searchable()
                            ->preload(),
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->label(__('Code'))
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true)
                            ->required(),
                        Textarea::make('description')
                            ->label(__('Description'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
