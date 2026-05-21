<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Enums\TaskAssignmentStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewAssignments', $ownerRecord) ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Assignment History');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('assignedToUser.name')
            ->columns([
                TextColumn::make('assignedToUser.name')
                    ->label(__('Assigned To'))
                    ->placeholder('-'),
                TextColumn::make('assignedByUser.name')
                    ->label(__('Assigned By'))
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (TaskAssignmentStatus|string $state): string => ($state instanceof TaskAssignmentStatus ? $state : TaskAssignmentStatus::from((string) $state))->label())
                    ->color(fn (TaskAssignmentStatus|string $state): string => ($state instanceof TaskAssignmentStatus ? $state : TaskAssignmentStatus::from((string) $state))->color()),
                TextColumn::make('assigned_at')
                    ->dateTime(),
                TextColumn::make('accepted_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextColumn::make('note')
                    ->limit(60)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('assigned_at', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
