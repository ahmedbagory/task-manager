<?php

namespace App\Filament\Resources\WhatsappMessages\Schemas;

use App\Enums\TaskPriority;
use App\Models\Department;
use App\Models\TaskCategory;
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
use Illuminate\Database\Eloquent\Builder;

class ConvertWhatsappMessageToTaskForm
{
    /**
     * @return array<int, Section>
     */
    public static function schema(): array
    {
        return [
            Section::make(__('Task Details'))
                ->components([
                    TextInput::make('title')
                        ->label(__('Title'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label(__('Additional Description'))
                        ->rows(4)
                        ->maxLength(5000)
                        ->columnSpanFull()
                        ->helperText(__('Original WhatsApp message text is always preserved in the task description.')),
                    Select::make('department_id')
                        ->label('القسم / الوحدة')
                        ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('category_id', null)),
                    Select::make('category_id')
                        ->label(__('Category'))
                        ->options(function (Get $get): array {
                            $query = TaskCategory::query()
                                ->where('is_active', true)
                                ->orderBy('name');

                            $departmentId = $get('department_id');

                            if (filled($departmentId)) {
                                $parentId = Department::query()
                                    ->whereKey((int) $departmentId)
                                    ->value('parent_id');

                                $query->where(fn ($subQuery) => $subQuery
                                    ->where('department_id', $departmentId)
                                    ->when(
                                        filled($parentId),
                                        fn ($builder) => $builder->orWhere('department_id', $parentId),
                                    )
                                    ->orWhereNull('department_id'));
                            }

                            return $query->pluck('name', 'id')->all();
                        })
                        ->searchable()
                        ->preload(),
                    Select::make('priority')
                        ->label(__('Priority'))
                        ->options(TaskPriority::options())
                        ->default(TaskPriority::MEDIUM->value)
                        ->required(),
                    TextInput::make('location')
                        ->label(__('Location'))
                        ->maxLength(255),
                    DateTimePicker::make('due_at')
                        ->label(__('Due At')),
                ])
                ->columns(2),
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
                        ->disabled(fn (Get $get): bool => (bool) $get('assign_to_all')),
                    Select::make('assignment_target_units')
                        ->label(__('الفروع'))
                        ->options(fn (Get $get): array => app(DepartmentHierarchyService::class)->childOptionsGroupedByParent(
                            parentIds: (array) ($get('assignment_target_departments') ?? []),
                        ))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->disabled(fn (Get $get): bool => (bool) $get('assign_to_all')),
                    Select::make('assignment_target_users')
                        ->label(__('موظفين محددين'))
                        ->options(fn (): array => User::query()
                            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', [
                                Rbac::EMPLOYEE,
                                Rbac::SUPERVISOR,
                            ]))
                            ->with('department.parent')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (User $u): array => [
                                $u->id => $u->name . ($u->department ? ' (' . $u->department->hierarchy_name . ')' : ''),
                            ])
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->disabled(fn (Get $get): bool => (bool) $get('assign_to_all')),
                ])
                ->columns(3)
                ->description(__('اختر أقسام أو فروع أو موظفين محددين، أو فعّل "إسناد للكل" لإرسالها لجميع الموظفين.')),
        ];
    }
}
