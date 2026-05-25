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
use Filament\Forms\Components\Toggle;
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
                Section::make('تفاصيل المهمة')
                    ->components([
                        TextInput::make('title')
                            ->label('العنوان')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('الوصف')
                            ->rows(4)
                            ->columnSpanFull(),
                        Select::make('priority')
                            ->label('الأولوية')
                            ->options(TaskPriority::options())
                            ->default(TaskPriority::MEDIUM->value)
                            ->required(),
                        Select::make('status')
                            ->label('الحالة')
                            ->options(TaskStatus::formOptions())
                            ->default(TaskStatus::NEW->value)
                            ->required(),
                        Select::make('source')
                            ->label('المصدر')
                            ->options(TaskSource::options())
                            ->default(TaskSource::MANUAL->value)
                            ->required()
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3),
                Section::make('التصنيف')
                    ->components([
                        Select::make('department_id')
                            ->label('القسم / الوحدة')
                            ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('category_id', null)),
                        Select::make('category_id')
                            ->label('التصنيف')
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
                            ->label('الموقع')
                            ->maxLength(255),
                    ])
                    ->columns(3),
                Section::make(__('الإسناد'))
                    ->components([
                        Toggle::make('assign_to_all')
                            ->label(__('إسناد للكل'))
                            ->helperText(__('عند التفعيل سيتم إسناد المهمة لجميع الموظفين.'))
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state): void {
                                if ($state) {
                                    $set('assignment_target_departments', []);
                                    $set('assignment_target_units', []);
                                    $set('assignment_target_users', []);
                                }
                            })
                            ->columnSpanFull(),
                        Select::make('assignment_target_departments')
                            ->label(__('الأقسام'))
                            ->options(fn (): array => app(DepartmentHierarchyService::class)->topLevelOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('assignment_target_units', []))
                            ->disabled(fn (Get $get): bool => (bool) $get('assign_to_all'))
                            ->columnSpan(1),
                        Select::make('assignment_target_units')
                            ->label(__('الفروع'))
                            ->options(fn (Get $get): array => app(DepartmentHierarchyService::class)->childOptionsGroupedByParent(
                                parentIds: (array) ($get('assignment_target_departments') ?? []),
                            ))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get): bool => (bool) $get('assign_to_all'))
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
                            ->disabled(fn (Get $get): bool => (bool) $get('assign_to_all'))
                            ->columnSpan(1),
                    ])
                    ->columns(3)
                    ->description(__('اختر أقسام أو فروع أو موظفين محددين، أو فعّل "إسناد للكل" لإرسالها لجميع الموظفين.')),
                Section::make('صاحب الطلب والمواعيد')
                    ->components([
                        Select::make('reported_by_user_id')
                            ->label('صاحب الطلب')
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
                            ->label('هاتف المبلّغ')
                            ->tel()
                            ->maxLength(255),
                        DateTimePicker::make('due_at')
                            ->label('تاريخ الاستحقاق'),
                        DateTimePicker::make('started_at')
                            ->label('تاريخ البدء'),
                        DateTimePicker::make('completed_at')
                            ->label('تاريخ الإكمال'),
                    ])
                    ->columns(3),
            ]);
    }
}
