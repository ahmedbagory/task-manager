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
use Illuminate\Database\Eloquent\Builder;

class ConvertWhatsappMessageToTaskForm
{
    /**
     * @return array<int, Section>
     */
    public static function schema(): array
    {
        return [
            Section::make('إنشاء مهمة من واتساب')
                ->components([
                    TextInput::make('title')
                        ->label('عنوان المهمة')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label('الوصف')
                        ->rows(4)
                        ->maxLength(5000)
                        ->columnSpanFull()
                        ->helperText('سيتم الاحتفاظ بنص رسالة واتساب الأصلية داخل وصف المهمة.'),
                    Select::make('reported_by_user_id')
                        ->label('صاحب الطلب')
                        ->options(fn (): array => self::internalUserOptions())
                        ->searchable()
                        ->preload(),
                    Select::make('assignee_ids')
                        ->label('المكلفين')
                        ->options(fn (): array => self::internalUserOptions())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                    Select::make('department_id')
                        ->label('القسم / الفرع / الوحدة')
                        ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions())
                        ->searchable()
                        ->preload(),
                    Select::make('category_id')
                        ->label('التصنيف')
                        ->options(function (Get $get): array {
                            $query = TaskCategory::query()
                                ->where('is_active', true)
                                ->orderBy('name');

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

                            return $query->pluck('name', 'id')->all();
                        })
                        ->searchable()
                        ->preload(),
                    Select::make('priority')
                        ->label('الأولوية')
                        ->options(TaskPriority::options())
                        ->default(TaskPriority::MEDIUM->value)
                        ->required(),
                    TextInput::make('location')
                        ->label('الموقع')
                        ->maxLength(255),
                    DateTimePicker::make('due_at')
                        ->label('تاريخ الاستحقاق'),
                ])
                ->columns(2),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function internalUserOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', Rbac::ROLES))
            ->with('department.parent')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->id => $user->name.($user->department ? ' ('.$user->department->hierarchy_name.')' : ''),
            ])
            ->all();
    }
}
