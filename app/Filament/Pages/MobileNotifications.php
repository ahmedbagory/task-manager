<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Departments\DepartmentHierarchyService;
use App\Services\Notifications\ManualMobileNotificationService;
use App\Support\Rbac;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class MobileNotifications extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?int $navigationSort = 11;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'title' => '',
            'body' => '',
            'assignment_target_departments' => [],
            'assignment_target_units' => [],
            'assignment_target_users' => [],
        ]);
    }

    public function getTitle(): string|HtmlString
    {
        return __('Mobile Notifications');
    }

    public static function getNavigationLabel(): string
    {
        return __('Mobile Notifications');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Task Management');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('mobile_notifications.send') ?? false;
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->operation('create')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Notification Content'))
                    ->description(__('Send push notifications to selected departments or employees who have the mobile app installed.'))
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->label(__('Notification Title'))
                            ->required()
                            ->maxLength(120)
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->label(__('Notification Message'))
                            ->required()
                            ->rows(4)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Notification Audience'))
                    ->description(__('Choose one or more departments, units, or employees. Matching employees are deduplicated automatically.'))
                    ->columns(3)
                    ->components([
                        Select::make('assignment_target_departments')
                            ->label(__('Top-level Departments'))
                            ->options(fn (): array => app(DepartmentHierarchyService::class)->topLevelOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('assignment_target_units', [])),
                        Select::make('assignment_target_units')
                            ->label(__('Specific Units / Branches'))
                            ->options(fn (Get $get): array => app(DepartmentHierarchyService::class)->childOptionsGroupedByParent(
                                parentIds: (array) ($get('assignment_target_departments') ?? []),
                            ))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('assignment_target_users')
                            ->label(__('Specific Employees'))
                            ->options(fn (): array => User::query()
                                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                                    Rbac::EMPLOYEE,
                                    Rbac::SUPERVISOR,
                                ]))
                                ->with('department.parent')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (User $user) => [
                                    $user->id => $user->name.($user->department ? ' ('.$user->department->hierarchy_name.')' : ''),
                                ])
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Placeholder::make('audience_preview')
                            ->label(__('Audience Preview'))
                            ->content(fn (Get $get): HtmlString => $this->renderAudiencePreview($this->targetsFromGet($get)))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function send(ManualMobileNotificationService $manualMobileNotificationService): void
    {
        $state = $this->form->getState();
        $targets = $this->targetsFromState($state);

        if ($this->isAudienceEmpty($targets)) {
            throw ValidationException::withMessages([
                'data.assignment_target_departments' => __('Select at least one department, unit, or employee.'),
            ]);
        }

        $preview = $manualMobileNotificationService->previewAudience($targets);

        if ($preview['device_count'] === 0) {
            throw ValidationException::withMessages([
                'data.assignment_target_users' => __('No registered mobile devices were found for the selected audience.'),
            ]);
        }

        /** @var User $sender */
        $sender = auth()->user();
        $result = $manualMobileNotificationService->send(
            title: (string) $state['title'],
            body: (string) $state['body'],
            targets: $targets,
            sender: $sender,
        );

        $this->form->fill([
            'title' => '',
            'body' => '',
            'assignment_target_departments' => $state['assignment_target_departments'] ?? [],
            'assignment_target_units' => $state['assignment_target_units'] ?? [],
            'assignment_target_users' => $state['assignment_target_users'] ?? [],
        ]);

        Notification::make()
            ->success()
            ->title(__('Mobile notification sent'))
            ->body(__('Sent to :users users across :devices registered devices.', [
                'users' => $result['targeted_users_with_devices'],
                'devices' => $result['targeted_devices'],
            ]))
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('send')
                    ->footer([
                        Actions::make([
                            Action::make('send')
                                ->label(__('Send Notification'))
                                ->submit('send')
                                ->icon(Heroicon::OutlinedPaperAirplane),
                        ]),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{departments: array<int, int|string>, units: array<int, int|string>, users: array<int, int|string>}
     */
    private function targetsFromState(array $state): array
    {
        return [
            'departments' => (array) ($state['assignment_target_departments'] ?? []),
            'units' => (array) ($state['assignment_target_units'] ?? []),
            'users' => (array) ($state['assignment_target_users'] ?? []),
        ];
    }

    /**
     * @return array{departments: array<int, int|string>, units: array<int, int|string>, users: array<int, int|string>}
     */
    private function targetsFromGet(Get $get): array
    {
        return [
            'departments' => (array) ($get('assignment_target_departments') ?? []),
            'units' => (array) ($get('assignment_target_units') ?? []),
            'users' => (array) ($get('assignment_target_users') ?? []),
        ];
    }

    /**
     * @param  array{departments: array<int, int|string>, units: array<int, int|string>, users: array<int, int|string>}  $targets
     */
    private function isAudienceEmpty(array $targets): bool
    {
        return $targets['departments'] === []
            && $targets['units'] === []
            && $targets['users'] === [];
    }

    /**
     * @param  array{departments: array<int, int|string>, units: array<int, int|string>, users: array<int, int|string>}  $targets
     */
    private function renderAudiencePreview(array $targets): HtmlString
    {
        $preview = app(ManualMobileNotificationService::class)->previewAudience($targets);

        if ($preview['user_count'] === 0) {
            return new HtmlString(
                '<p class="text-sm text-gray-500 dark:text-gray-400">'.e(__('No audience selected yet.')).'</p>'
            );
        }

        $names = $this->previewNames($preview['users']);
        $deviceWarning = $preview['device_count'] === 0
            ? '<div class="text-sm font-medium text-danger-600 dark:text-danger-400">'.e(__('No registered mobile devices were found for the selected audience.')).'</div>'
            : '';

        return new HtmlString(
            '<div class="space-y-2 text-sm">'
            .'<div><strong>'.e(__('Resolved audience')).':</strong> '.e((string) $preview['user_count']).' '.e(__('Employees')).'</div>'
            .'<div><strong>'.e(__('Recipients with app devices')).':</strong> '.e((string) $preview['users_with_devices_count']).'</div>'
            .'<div><strong>'.e(__('Registered devices')).':</strong> '.e((string) $preview['device_count']).'</div>'
            .'<div class="text-gray-500 dark:text-gray-400">'.e($names).'</div>'
            .$deviceWarning
            .'</div>'
        );
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function previewNames(Collection $users): string
    {
        $visibleUsers = $users->take(8);
        $names = $visibleUsers
            ->map(fn (User $user): string => $user->name)
            ->implode(', ');
        $remainingCount = $users->count() - $visibleUsers->count();

        if ($remainingCount > 0) {
            $names .= ' +'.$remainingCount.' '.__('more');
        }

        return $names;
    }
}
