<?php

namespace App\Filament\Resources\TaskCategories;

use App\Filament\Resources\TaskCategories\Pages\CreateTaskCategory;
use App\Filament\Resources\TaskCategories\Pages\EditTaskCategory;
use App\Filament\Resources\TaskCategories\Pages\ListTaskCategories;
use App\Filament\Resources\TaskCategories\Pages\ViewTaskCategory;
use App\Filament\Resources\TaskCategories\Schemas\TaskCategoryForm;
use App\Filament\Resources\TaskCategories\Schemas\TaskCategoryInfolist;
use App\Filament\Resources\TaskCategories\Tables\TaskCategoriesTable;
use App\Models\TaskCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TaskCategoryResource extends Resource
{
    protected static ?string $model = TaskCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return TaskCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TaskCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaskCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaskCategories::route('/'),
            'create' => CreateTaskCategory::route('/create'),
            'view' => ViewTaskCategory::route('/{record}'),
            'edit' => EditTaskCategory::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('Task Category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Task Categories');
    }

    public static function getNavigationLabel(): string
    {
        return __('Task Categories');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Configuration');
    }
}
