<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Spatie\Permission\Models\Role;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageRoles')
                ->label(__('Manage Roles'))
                ->icon('heroicon-o-shield-check')
                ->url(RoleResource::getUrl('index'))
                ->visible(fn (): bool => auth()->user()?->can('viewAny', Role::class) ?? false),
            CreateAction::make(),
        ];
    }
}
