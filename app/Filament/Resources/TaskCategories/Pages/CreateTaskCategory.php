<?php

namespace App\Filament\Resources\TaskCategories\Pages;

use App\Filament\Resources\TaskCategories\TaskCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaskCategory extends CreateRecord
{
    protected static string $resource = TaskCategoryResource::class;
}
