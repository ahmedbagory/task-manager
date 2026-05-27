<?php

namespace App\Filament\Resources\MobileNotifications\Pages;

use App\Filament\Resources\MobileNotifications\MobileNotificationResource;
use App\Models\ScheduledNotificationLog;
use App\Models\ScheduledNotificationRule;
use App\Models\User;
use App\Services\Departments\DepartmentHierarchyService;
use App\Services\Notifications\ScheduledNotificationRunner;
use App\Support\Rbac;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use BackedEnum;

class ManageScheduledRules extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MobileNotificationResource::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $title = 'جدولة التنبيهات';

    protected static ?string $navigationLabel = 'جدولة التنبيهات';

    protected static ?int $navigationSort = 2;

    public function getView(): string
    {
        return 'filament.resources.mobile-notifications.pages.manage-scheduled-rules';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createRule')
                ->label('إضافة قاعدة تنبيه')
                ->icon('heroicon-o-plus')
                ->form($this->getRuleFormSchema())
                ->action(function (array $data): void {
                    $rule = new ScheduledNotificationRule();
                    $rule->fill($this->prepareRuleData($data));
                    $rule->created_by = auth()->id();
                    $rule->save();

                    Notification::make()
                        ->title('تم إنشاء قاعدة التنبيه')
                        ->success()
                        ->send();
                })
                ->modalHeading('إنشاء قاعدة تنبيه مجدولة')
                ->modalSubmitActionLabel('إنشاء')
                ->modalWidth('3xl'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ScheduledNotificationRule::query()->latest())
            ->columns([
                IconColumn::make('is_active')
                    ->label('')
                    ->width('40px')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-pause-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('name')
                    ->label('اسم القاعدة')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (ScheduledNotificationRule $record): string => str($record->title)->limit(50)->toString()),
                TextColumn::make('frequency_display')
                    ->label('التكرار')
                    ->getStateUsing(fn (ScheduledNotificationRule $record): string => $record->frequencyLabel())
                    ->badge()
                    ->color('info'),
                TextColumn::make('type_display')
                    ->label('النوع')
                    ->getStateUsing(fn (ScheduledNotificationRule $record): string => $record->typeLabel())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('target_display')
                    ->label('المستهدفون')
                    ->getStateUsing(fn (ScheduledNotificationRule $record): string => $record->targetLabel()),
                TextColumn::make('last_run_at')
                    ->label('آخر إرسال')
                    ->since()
                    ->placeholder('—')
                    ->sortable()
                    ->tooltip(fn (ScheduledNotificationRule $record): ?string => $record->last_run_at?->format('Y-m-d H:i')),
                TextColumn::make('next_run_at')
                    ->label('الإرسال القادم')
                    ->since()
                    ->placeholder('—')
                    ->sortable()
                    ->tooltip(fn (ScheduledNotificationRule $record): ?string => $record->next_run_at?->format('Y-m-d H:i'))
                    ->color(fn (ScheduledNotificationRule $record): string => ($record->next_run_at && $record->next_run_at->isPast()) ? 'warning' : 'gray'),
            ])
            ->recordActions([
                ActionGroup::make([
                    \Filament\Actions\Action::make('edit')
                        ->label('تعديل')
                        ->icon('heroicon-o-pencil')
                        ->form(fn (ScheduledNotificationRule $record) => $this->getRuleFormSchema())
                        ->fillForm(fn (ScheduledNotificationRule $record): array => [
                            'name' => $record->name,
                            'title' => $record->title,
                            'body' => $record->body,
                            'type' => $record->type,
                            'target_type' => $record->target_type,
                            'target_user_ids' => $record->target_payload['user_ids'] ?? [],
                            'target_department_ids' => $record->target_payload['department_ids'] ?? [],
                            'target_roles' => $record->target_payload['roles'] ?? [],
                            'frequency' => $record->frequency,
                            'interval_hours' => $record->interval_hours,
                            'start_at' => $record->start_at,
                            'end_at' => $record->end_at,
                            'is_active' => $record->is_active,
                        ])
                        ->action(function (array $data, ScheduledNotificationRule $record): void {
                            $prepared = $this->prepareRuleData($data);
                            $record->fill($prepared);
                            $record->save();

                            Notification::make()
                                ->title('تم تحديث قاعدة التنبيه')
                                ->success()
                                ->send();
                        })
                        ->modalHeading('تعديل قاعدة التنبيه')
                        ->modalSubmitActionLabel('حفظ')
                        ->modalWidth('3xl'),
                    \Filament\Actions\Action::make('toggle')
                        ->label(fn (ScheduledNotificationRule $record): string => $record->is_active ? 'إيقاف' : 'تشغيل')
                        ->icon(fn (ScheduledNotificationRule $record): string => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                        ->color(fn (ScheduledNotificationRule $record): string => $record->is_active ? 'warning' : 'success')
                        ->requiresConfirmation()
                        ->action(function (ScheduledNotificationRule $record): void {
                            $record->forceFill(['is_active' => ! $record->is_active])->save();

                            Notification::make()
                                ->title($record->is_active ? 'تم تشغيل القاعدة' : 'تم إيقاف القاعدة')
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\Action::make('testSend')
                        ->label('إرسال اختبار')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('إرسال اختباري')
                        ->modalDescription('سيتم إرسال التنبيه الآن للمستهدفين دون تغيير الجدول الزمني.')
                        ->action(function (ScheduledNotificationRule $record): void {
                            $runner = app(ScheduledNotificationRunner::class);
                            $log = $runner->executeRule($record);

                            if ($log->status === 'sent') {
                                Notification::make()
                                    ->title('تم إرسال الاختبار — ' . $log->recipients_count . ' مستلم')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('فشل إرسال الاختبار')
                                    ->body($log->error)
                                    ->danger()
                                    ->send();
                            }
                        }),
                    \Filament\Actions\Action::make('delete')
                        ->label('حذف')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('حذف قاعدة التنبيه')
                        ->modalDescription('هل أنت متأكد من حذف هذه القاعدة؟ سيتم حذف جميع سجلات الإرسال المرتبطة.')
                        ->action(function (ScheduledNotificationRule $record): void {
                            $record->delete();

                            Notification::make()
                                ->title('تم حذف القاعدة')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->bulkActions([])
            ->striped()
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading('لا توجد قواعد تنبيه مجدولة')
            ->emptyStateDescription('أنشئ أول قاعدة تنبيه مجدولة لإرسال إشعارات تلقائية.');
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component|Section>
     */
    private function getRuleFormSchema(): array
    {
        return [
            Section::make('محتوى التنبيه')
                ->icon('heroicon-o-pencil-square')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('اسم القاعدة')
                        ->placeholder('مثال: تذكير يومي بالمهام المتأخرة')
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),
                    TextInput::make('title')
                        ->label('عنوان التنبيه')
                        ->placeholder('العنوان الذي سيظهر في الإشعار')
                        ->required()
                        ->maxLength(120),
                    Select::make('type')
                        ->label('نوع التنبيه')
                        ->options([
                            'general' => 'عام',
                            'overdue_tasks' => 'مهام متأخرة',
                            'pending_assignment' => 'بانتظار الإسناد',
                            'pending_confirmation' => 'بانتظار تأكيد صاحب الطلب',
                        ])
                        ->default('general')
                        ->required(),
                    Textarea::make('body')
                        ->label('نص التنبيه')
                        ->placeholder('النص الذي سيظهر في الإشعار')
                        ->required()
                        ->rows(3)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),
            Section::make('الجدول الزمني')
                ->icon('heroicon-o-clock')
                ->columns(2)
                ->schema([
                    Select::make('frequency')
                        ->label('نوع التكرار')
                        ->options([
                            'once' => 'مرة واحدة',
                            'every_hours' => 'كل عدة ساعات',
                            'daily' => 'يومي',
                            'weekly' => 'أسبوعي',
                            'monthly' => 'شهري',
                        ])
                        ->default('daily')
                        ->required()
                        ->live(),
                    TextInput::make('interval_hours')
                        ->label('كل كم ساعة')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(168)
                        ->default(6)
                        ->visible(fn (Get $get): bool => $get('frequency') === 'every_hours')
                        ->required(fn (Get $get): bool => $get('frequency') === 'every_hours'),
                    DateTimePicker::make('start_at')
                        ->label('وقت البداية')
                        ->helperText('متى يبدأ أول إرسال')
                        ->default(now()->addHour()->startOfHour()),
                    DateTimePicker::make('end_at')
                        ->label('تاريخ النهاية')
                        ->helperText('اختياري — اتركه فارغًا لتبقى القاعدة مستمرة'),
                    Toggle::make('is_active')
                        ->label('مفعلة')
                        ->default(true)
                        ->columnSpanFull(),
                ]),
            Section::make('المستهدفون')
                ->icon('heroicon-o-user-group')
                ->columns(2)
                ->schema([
                    Select::make('target_type')
                        ->label('الفئة المستهدفة')
                        ->options([
                            'all' => 'جميع الموظفين',
                            'users' => 'موظفين محددين',
                            'departments' => 'أقسام محددة',
                            'roles' => 'أدوار محددة',
                        ])
                        ->default('all')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('target_user_ids', []);
                            $set('target_department_ids', []);
                            $set('target_roles', []);
                        })
                        ->columnSpanFull(),
                    Select::make('target_user_ids')
                        ->label('الموظفين')
                        ->options(fn (): array => User::query()
                            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', [Rbac::EMPLOYEE, Rbac::SUPERVISOR]))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get('target_type') === 'users')
                        ->columnSpanFull(),
                    Select::make('target_department_ids')
                        ->label('الأقسام')
                        ->options(fn (): array => app(DepartmentHierarchyService::class)->topLevelOptions())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get('target_type') === 'departments')
                        ->columnSpanFull(),
                    Select::make('target_roles')
                        ->label('الأدوار')
                        ->options(fn (): array => Role::query()->pluck('name', 'name')->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get('target_type') === 'roles')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareRuleData(array $data): array
    {
        $targetPayload = match ($data['target_type'] ?? 'all') {
            'users' => ['user_ids' => array_map('intval', (array) ($data['target_user_ids'] ?? []))],
            'departments' => ['department_ids' => array_map('intval', (array) ($data['target_department_ids'] ?? []))],
            'roles' => ['roles' => (array) ($data['target_roles'] ?? [])],
            default => [],
        };

        $startAt = $data['start_at'] ?? now();
        $frequency = $data['frequency'] ?? 'daily';

        return [
            'name' => $data['name'],
            'title' => $data['title'],
            'body' => $data['body'],
            'type' => $data['type'] ?? 'general',
            'target_type' => $data['target_type'] ?? 'all',
            'target_payload' => $targetPayload,
            'frequency' => $frequency,
            'interval_hours' => $frequency === 'every_hours' ? (int) ($data['interval_hours'] ?? 6) : null,
            'start_at' => $startAt,
            'end_at' => $data['end_at'] ?? null,
            'next_run_at' => $startAt,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
