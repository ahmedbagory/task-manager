<?php

namespace App\Filament\Resources\WhatsappContacts\Tables;

use App\Services\Departments\DepartmentHierarchyService;
use App\Support\BidiText;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsappContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                    ->html()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->formatStateUsing(fn (?string $state) => BidiText::auto($state))
                    ->html()
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('department.hierarchy_name')
                    ->label('القسم / الوحدة')
                    ->placeholder('-'),
                TextColumn::make('default_location')
                    ->label(__('Location'))
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label(__('Messages'))
                    ->sortable(),
                TextColumn::make('last_message_at')
                    ->label(__('Last Message At'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('القسم / الوحدة')
                    ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions()),
            ])
            ->defaultSort('updated_at', 'desc')
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
