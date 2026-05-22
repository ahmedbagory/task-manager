<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\User;
use App\Services\Departments\DepartmentHierarchyService;
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
                            ->label('القسم / الوحدة')
                            ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions())
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
                                        $parentId = Department::query()
                                            ->whereKey((int) $departmentId)
                                            ->value('parent_id');

                                        $query->where(fn (Builder $subQuery) => $subQuery
                                            ->where('department_id', $departmentId)
                                            ->when(
                                                filled($parentId),
                                                fn (Builder $builder) => $builder->orWhere('department_id', $parentId),
                                            )
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
                Section::make('التوجيه الإداري')
                    ->components([
                        Select::make('assignment_target_departments')
                            ->label('الأقسام الرئيسية')
                            ->options(fn (): array => app(DepartmentHierarchyService::class)->topLevelOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('assignment_target_units', null))
                            ->columnSpan(1),
                        Select::make('assignment_target_units')
                            ->label('الوحدات / الفروع')
                            ->options(fn (Get $get): array => app(DepartmentHierarchyService::class)->childOptionsGroupedByParent(
                                parentIds: (array) ($get('assignment_target_departments') ?? []),
                            ))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),
                        Select::make('assignment_target_users')
                            ->label(__('موظفين محددين'))
                            ->options(fn () => User::query()
                                ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', [
                                    Rbac::EMPLOYEE,
                                    Rbac::SUPERVISOR,
                                ]))
                                ->with('department.parent')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn ($u) => [
                                    $u->id => $u->name . ($u->department ? ' (' . $u->department->hierarchy_name . ')' : ''),
                                ])
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),
                    ])
                    ->columns(3)
                    ->description('اختر أقساماً رئيسية أو وحدات محددة أو موظفين مباشرين. الموظفون المؤهلون يتم احتسابهم من الوحدات المرتبطة وليس من داخل القالب.'),
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
