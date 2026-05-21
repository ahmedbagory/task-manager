<?php

namespace App\Filament\Resources\TaskCategories\Pages;

use App\Filament\Resources\TaskCategories\TaskCategoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTaskCategory extends ViewRecord
{
    protected static string $resource = TaskCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
