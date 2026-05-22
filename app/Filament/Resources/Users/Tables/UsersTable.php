<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\Departments\DepartmentHierarchyService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('roles_display')
                    ->label(__('Role'))
                    ->state(fn (User $record): string => $record->roles->pluck('name')->join(', ') ?: '-')
                    ->badge()
                    ->searchable(),
                TextColumn::make('department.hierarchy_name')
                    ->label('القسم / الوحدة')
                    ->placeholder('-'),
                TextColumn::make('department.parent.name')
                    ->label('القسم الرئيسي')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('work_location')
                    ->label(__('Location'))
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('القسم / الوحدة')
                    ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions(childrenOnly: true)),
                SelectFilter::make('role')
                    ->label(__('Role'))
                    ->relationship('roles', 'name'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
