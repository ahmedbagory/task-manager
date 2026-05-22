<?php

namespace App\Filament\Resources\MobileNotifications\Schemas;

use App\Models\User;
use App\Services\Departments\DepartmentHierarchyService;
use App\Services\Notifications\MobileNotificationAudienceResolver;
use App\Support\Rbac;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class MobileNotificationForm
{
    public static function configure(Schema $schema): Schema
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
                        Select::make('target_department_ids')
                            ->label(__('Top-level Departments'))
                            ->options(fn (): array => app(DepartmentHierarchyService::class)->topLevelOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('target_unit_ids', [])),
                        Select::make('target_unit_ids')
                            ->label(__('Specific Units / Branches'))
                            ->options(fn (Get $get): array => app(DepartmentHierarchyService::class)->childOptionsGroupedByParent(
                                parentIds: (array) ($get('target_department_ids') ?? []),
                            ))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('target_user_ids')
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
                            ->content(fn (Get $get): HtmlString => self::renderAudiencePreview([
                                'target_department_ids' => (array) ($get('target_department_ids') ?? []),
                                'target_unit_ids' => (array) ($get('target_unit_ids') ?? []),
                                'target_user_ids' => (array) ($get('target_user_ids') ?? []),
                            ]))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $targets
     */
    private static function renderAudiencePreview(array $targets): HtmlString
    {
        $preview = app(MobileNotificationAudienceResolver::class)->preview($targets);

        if ($preview['user_count'] === 0) {
            return new HtmlString(
                '<p class="text-sm text-gray-500 dark:text-gray-400">'.e(__('No audience selected yet.')).'</p>'
            );
        }

        $names = self::previewNames($preview['users']);
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
    private static function previewNames(Collection $users): string
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
