<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TaskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Task'))
                    ->components([
                        TextEntry::make('task_number')
                            ->label(__('Task #'))
                            ->copyable(),
                        TextEntry::make('title')
                            ->label(__('Title')),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (TaskStatus|string $state): string => ($state instanceof TaskStatus ? $state : TaskStatus::from((string) $state))->label())
                            ->color(fn (TaskStatus|string $state): string => ($state instanceof TaskStatus ? $state : TaskStatus::from((string) $state))->color()),
                        TextEntry::make('priority')
                            ->badge()
                            ->formatStateUsing(fn (TaskPriority|string $state): string => ($state instanceof TaskPriority ? $state : TaskPriority::from((string) $state))->label())
                            ->color(fn (TaskPriority|string $state): string => ($state instanceof TaskPriority ? $state : TaskPriority::from((string) $state))->color()),
                        TextEntry::make('source')
                            ->badge()
                            ->formatStateUsing(fn (TaskSource|string $state): string => ($state instanceof TaskSource ? $state : TaskSource::from((string) $state))->label())
                            ->color(fn (TaskSource|string $state): string => ($state instanceof TaskSource ? $state : TaskSource::from((string) $state))->color()),
                        TextEntry::make('description')
                            ->label(__('Description'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make(__('Assignment & Classification'))
                    ->components([
                        TextEntry::make('department.name')
                            ->label(__('Department'))
                            ->placeholder('-'),
                        TextEntry::make('category.name')
                            ->label(__('Category'))
                            ->placeholder('-'),
                        TextEntry::make('assignedToUser.name')
                            ->label(__('Assigned Employee'))
                            ->placeholder('-'),
                        TextEntry::make('location')
                            ->label(__('Location'))
                            ->placeholder('-'),
                        TextEntry::make('due_at')
                            ->label(__('Due At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('started_at')
                            ->label(__('Started At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('completed_at')
                            ->label(__('Completed At'))
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(3),
                Section::make(__('Audit'))
                    ->components([
                        TextEntry::make('reportedByUser.name')
                            ->label(__('Reported by user'))
                            ->placeholder('-'),
                        TextEntry::make('reported_by_phone')
                            ->label(__('Reported By Phone'))
                            ->placeholder('-'),
                        TextEntry::make('createdByUser.name')
                            ->label(__('Created by'))
                            ->placeholder('-'),
                        TextEntry::make('updatedByUser.name')
                            ->label(__('Updated by'))
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label(__('Created At'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label(__('Updated At'))
                            ->dateTime()
                            ->placeholder('-'),
                        IconEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->boolean()
                            ->state(fn (Task $record): bool => $record->trashed()),
                    ])
                    ->columns(3),
            ]);
    }
}
