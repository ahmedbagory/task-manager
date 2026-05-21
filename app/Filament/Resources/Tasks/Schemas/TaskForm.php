<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Support\Rbac;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Task Details'))
                    ->components([
                        TextInput::make('title')
                            ->label(__('Title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label(__('Description'))
                            ->rows(4)
                            ->columnSpanFull(),
                        Select::make('priority')
                            ->label(__('Priority'))
                            ->options(TaskPriority::options())
                            ->default(TaskPriority::MEDIUM->value)
                            ->required(),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(TaskStatus::options())
                            ->default(TaskStatus::PENDING_ASSIGNMENT->value)
                            ->required(),
                        Select::make('source')
                            ->label(__('Source'))
                            ->options(TaskSource::options())
                            ->default(TaskSource::MANUAL->value)
                            ->required()
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3),
                Section::make(__('Classification'))
                    ->components([
                        Select::make('department_id')
                            ->label(__('Department'))
                            ->relationship(
                                name: 'department',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('category_id', null)),
                        Select::make('category_id')
                            ->label(__('Category'))
                            ->relationship(
                                name: 'category',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, Get $get): void {
                                    $query->where('is_active', true);

                                    $departmentId = $get('department_id');

                                    if (filled($departmentId)) {
                                        $query->where(fn (Builder $subQuery) => $subQuery
                                            ->where('department_id', $departmentId)
                                            ->orWhereNull('department_id'));
                                    }
                                },
                            )
                            ->searchable()
                            ->preload(),
                        TextInput::make('location')
                            ->label(__('Location'))
                            ->maxLength(255),
                    ])
                    ->columns(3),
                Section::make(__('Reporter & Timing'))
                    ->components([
                        Select::make('reported_by_user_id')
                            ->label(__('Reported By User'))
                            ->relationship(
                                name: 'reportedByUser',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->whereIn('name', [
                                    Rbac::ADMIN,
                                    Rbac::DISPATCHER,
                                    Rbac::SUPERVISOR,
                                    Rbac::EMPLOYEE,
                                ])),
                            )
                            ->searchable()
                            ->preload(),
                        TextInput::make('reported_by_phone')
                            ->label(__('Reported By Phone'))
                            ->tel()
                            ->maxLength(255),
                        DateTimePicker::make('due_at')
                            ->label(__('Due At')),
                        DateTimePicker::make('started_at')
                            ->label(__('Started At')),
                        DateTimePicker::make('completed_at')
                            ->label(__('Completed At')),
                    ])
                    ->columns(3),
            ]);
    }
}
