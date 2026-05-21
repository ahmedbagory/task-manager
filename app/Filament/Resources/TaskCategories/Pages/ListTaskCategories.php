<?php

namespace App\Filament\Resources\TaskCategories\Pages;

use App\Filament\Resources\TaskCategories\TaskCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaskCategories extends ListRecords
{
    protected static string $resource = TaskCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
