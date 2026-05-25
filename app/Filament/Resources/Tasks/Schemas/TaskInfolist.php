<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskAssignmentTarget;
use App\Services\Tasks\TaskAccessService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TaskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('المهمة')
                    ->components([
                        TextEntry::make('task_number')
                            ->label('رقم المهمة')
                            ->formatStateUsing(fn ($state, Task $record): string => $record->displayNumber())
                            ->copyable(),
                        TextEntry::make('title')
                            ->label('العنوان'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state, Task $record): string => $record->workflowStatusLabel())
                            ->color(fn ($state, Task $record): string => $record->workflowStatus()->color()),
                        TextEntry::make('priority')
                            ->badge()
                            ->formatStateUsing(fn (TaskPriority|string $state): string => ($state instanceof TaskPriority ? $state : TaskPriority::from((string) $state))->label())
                            ->color(fn (TaskPriority|string $state): string => ($state instanceof TaskPriority ? $state : TaskPriority::from((string) $state))->color()),
                        TextEntry::make('source')
                            ->badge()
                            ->formatStateUsing(fn (TaskSource|string $state): string => ($state instanceof TaskSource ? $state : TaskSource::from((string) $state))->label())
                            ->color(fn (TaskSource|string $state): string => ($state instanceof TaskSource ? $state : TaskSource::from((string) $state))->color()),
                        TextEntry::make('description')
                            ->label('الوصف')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('الإسناد والتصنيف')
                    ->components([
                        TextEntry::make('department.hierarchy_name')
                            ->label('القسم / الوحدة')
                            ->placeholder('-'),
                        TextEntry::make('category.name')
                            ->label('التصنيف')
                            ->placeholder('-'),
                        TextEntry::make('assignees_summary')
                            ->label('المكلفين')
                            ->state(fn (Task $record): string => app(TaskAccessService::class)
                                ->resolveAssignees($record)
                                ->pluck('name')
                                ->implode('، ') ?: '—')
                            ->placeholder('-'),
                        TextEntry::make('assigned_by_summary')
                            ->label('تم الإسناد بواسطة')
                            ->state(fn (Task $record): string => $record->latestAssignment?->assignedByUser?->name
                                ?: $record->createdByUser?->name
                                ?: '—')
                            ->placeholder('-'),
                        TextEntry::make('location')
                            ->label('الموقع')
                            ->placeholder('-'),
                        TextEntry::make('due_at')
                            ->label('تاريخ الاستحقاق')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('started_at')
                            ->label('تاريخ البدء')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('completed_at')
                            ->label('تاريخ الإكمال')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(3),
                Section::make(__('أهداف التعيين'))
                    ->components([
                        RepeatableEntry::make('assignmentTargets')
                            ->label('')
                            ->schema([
                                TextEntry::make('target_type_label')
                                    ->label(__('النوع'))
                                    ->badge()
                                    ->color(fn (TaskAssignmentTarget $record) => $record->isUserTarget()
                                        ? 'success'
                                        : ($record->isDepartmentTarget()
                                            ? ($record->target?->parent_id ? 'warning' : 'primary')
                                            : 'gray')),
                                TextEntry::make('target_name')
                                    ->label(__('الهدف')),
                                TextEntry::make('assignedByUser.name')
                                    ->label(__('بواسطة'))
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label(__('التاريخ'))
                                    ->dateTime('Y-m-d H:i'),
                            ])
                            ->columns(4)
                            ->placeholder(__('لا توجد أهداف تعيين')),
                    ])
                    ->visible(fn (Task $record) => $record->hasValidAssignmentTargets()),
                Section::make(__('سجل التعيينات'))
                    ->components([
                        RepeatableEntry::make('assignmentHistories')
                            ->label('')
                            ->schema([
                                TextEntry::make('action_label')
                                    ->label(__('الإجراء'))
                                    ->badge()
                                    ->color(fn ($record) => match ($record->action) {
                                        'assigned' => 'info',
                                        'reassigned' => 'warning',
                                        'accepted' => 'success',
                                        'started' => 'primary',
                                        'completed' => 'success',
                                        'rejected' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('fromUser.name')
                                    ->label(__('من'))
                                    ->placeholder('—'),
                                TextEntry::make('toUser.name')
                                    ->label(__('إلى'))
                                    ->placeholder('—'),
                                TextEntry::make('performedByUser.name')
                                    ->label(__('بواسطة'))
                                    ->placeholder('—'),
                                TextEntry::make('note')
                                    ->label(__('ملاحظة'))
                                    ->placeholder('—')
                                    ->limit(50),
                                TextEntry::make('created_at')
                                    ->label(__('التاريخ'))
                                    ->dateTime('Y-m-d H:i'),
                            ])
                            ->columns(6)
                            ->placeholder(__('لا يوجد سجل')),
                    ])
                    ->visible(fn ($record) => $record->assignmentHistories()->exists())
                    ->collapsible(),
                Section::make('التتبع')
                    ->components([
                        TextEntry::make('reporter_summary')
                            ->label('صاحب الطلب')
                            ->state(function (Task $record): string {
                                $reporter = app(TaskAccessService::class)->resolveReporter($record);

                                if (! $reporter) {
                                    return '—';
                                }

                                $name = $reporter['name'] ?? null;
                                $phone = $reporter['phone'] ?? null;

                                return trim(implode(' - ', array_filter([$name, $phone]))) ?: '—';
                            })
                            ->placeholder('-'),
                        TextEntry::make('reported_by_phone')
                            ->label('هاتف المبلّغ')
                            ->placeholder('-'),
                        TextEntry::make('createdByUser.name')
                            ->label('أنشئت بواسطة')
                            ->placeholder('-'),
                        TextEntry::make('updatedByUser.name')
                            ->label('آخر تعديل بواسطة')
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label('تاريخ الإنشاء')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label('تاريخ التعديل')
                            ->dateTime()
                            ->placeholder('-'),
                        IconEntry::make('deleted_at')
                            ->label('محذوفة')
                            ->boolean()
                            ->state(fn (Task $record): bool => $record->trashed()),
                    ])
                    ->columns(3),
            ]);
    }
}
