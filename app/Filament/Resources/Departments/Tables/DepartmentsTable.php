<?php

namespace App\Filament\Resources\Departments\Tables;

use App\Models\Department;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('parent')
                ->withCount(['children', 'users', 'tasks'])
                ->orderedHierarchy())
            ->columns([
                TextColumn::make('hierarchy_name')
                    ->label('القسم / الوحدة')
                    ->description(fn (Department $record) => $record->parent?->name ? 'وحدة تابعة' : 'قسم رئيسي'),
                TextColumn::make('level_label')
                    ->label('النوع')
                    ->badge()
                    ->colors([
                        'primary' => 'قسم رئيسي',
                        'warning' => 'وحدة',
                    ]),
                TextColumn::make('code')
                    ->label('الكود')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                IconColumn::make('is_active')
                    ->label('مفعّل')
                    ->boolean(),
                TextColumn::make('children_count')
                    ->label('الوحدات')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('الموظفون')
                    ->sortable(),
                TextColumn::make('tasks_count')
                    ->label('المهام')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('مفعّل'),
            ])
            ->defaultSort('code')
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
