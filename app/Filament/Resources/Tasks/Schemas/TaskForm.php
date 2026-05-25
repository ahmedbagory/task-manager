<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Enums\TaskSource;
use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Services\Departments\DepartmentHierarchyService;
use App\Support\Rbac;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('إنشاء مهمة')
                    ->description('أدخل بيانات المهمة، ثم حدّد صاحب الطلب والمكلفين بشكل منفصل.')
                    ->components([
                        Hidden::make('whatsapp_message_id')
                            ->dehydrated(),
                        TextInput::make('title')
                            ->label('عنوان المهمة')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('الوصف')
                            ->rows(5)
                            ->readOnly(fn (Get $get): bool => filled($get('whatsapp_message_id')))
                            ->helperText(fn (Get $get): ?string => filled($get('whatsapp_message_id'))
                                ? 'يؤخذ الوصف تلقائيًا من نص رسالة واتساب أو الكابشن فقط.'
                                : null)
                            ->columnSpanFull(),
                        Select::make('reported_by_user_id')
                            ->label('صاحب الطلب')
                            ->options(fn (): array => self::internalUserOptions())
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => blank($get('reported_by_phone'))),
                        TextInput::make('reported_by_phone')
                            ->label('هاتف صاحب الطلب')
                            ->tel()
                            ->maxLength(255)
                            ->placeholder('اختياري عند وجود حساب موظف مرتبط'),
                        Select::make('assignee_ids')
                            ->label('المكلفين')
                            ->options(fn (): array => self::internalUserOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('صاحب الطلب لا يُضاف إلى المكلفين إلا إذا تم اختياره هنا صراحةً.')
                            ->columnSpanFull(),
                        Select::make('priority')
                            ->label('الأولوية')
                            ->options(TaskPriority::options())
                            ->default(TaskPriority::MEDIUM->value)
                            ->required(),
                        Select::make('status')
                            ->label('الحالة')
                            ->options(TaskStatus::formOptions())
                            ->required(fn (?Task $record): bool => $record !== null)
                            ->visible(fn (?Task $record): bool => $record !== null),
                        Select::make('source')
                            ->label('المصدر')
                            ->options(TaskSource::options())
                            ->default(TaskSource::MANUAL->value)
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3),
                Section::make('التصنيف والموقع')
                    ->components([
                        Select::make('department_id')
                            ->label('القسم / الفرع / الوحدة')
                            ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions())
                            ->searchable()
                            ->preload()
                            ->live(),
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
                        DateTimePicker::make('due_at')
                            ->label('تاريخ الاستحقاق'),
                    ])
                    ->columns(3),
                Section::make('المرفقات')
                    ->components([
                        FileUpload::make('attachment_uploads')
                            ->label('رفع مرفقات')
                            ->multiple()
                            ->storeFiles(false)
                            ->maxSize(10 * 1024)
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/plain',
                            ])
                            ->helperText('الحد الأقصى 10MB لكل ملف. الصيغ المدعومة: JPG, JPEG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX, TXT.')
                            ->columnSpanFull(),
                        CheckboxList::make('remove_attachment_ids')
                            ->label('إزالة مرفقات حالية')
                            ->options(fn (?Task $record): array => $record?->attachments()
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn ($attachment): array => [
                                    $attachment->id => ($attachment->original_name ?: basename($attachment->path))
                                        .' ('.$attachment->humanSize().')',
                                ])
                                ->all() ?? [])
                            ->visible(fn (?Task $record): bool => $record?->attachments()->exists() ?? false)
                            ->columnSpanFull(),
                    ]),
            ]);
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
