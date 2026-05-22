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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

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
                    Select::make('assigned_to_user_id')
                        ->label(__('Assign To Employee'))
                        ->options(fn (): array => User::query()
                            ->role([Rbac::EMPLOYEE])
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload(),
                ])
                ->columns(2),
        ];
    }
}
