<?php

namespace App\Filament\Resources\MobileNotifications\Schemas;

use App\Models\User;
use App\Services\Departments\DepartmentHierarchyService;
use App\Services\Notifications\MobileNotificationAudienceResolver;
use App\Support\Rbac;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                    ->description(__('Compose the push notification that will be sent to employees\' mobile devices.'))
                    ->icon('heroicon-o-pencil-square')
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->label(__('Notification Title'))
                            ->placeholder(__('e.g. Important Update...'))
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->helperText(fn (?string $state): string => strlen((string) $state).'/120 '.__('characters'))
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->label(__('Notification Message'))
                            ->placeholder(__('Write the notification message here...'))
                            ->required()
                            ->rows(4)
                            ->maxLength(500)
                            ->live(onBlur: true)
                            ->helperText(fn (?string $state): string => strlen((string) $state).'/500 '.__('characters'))
                            ->columnSpanFull(),
                        FileUpload::make('attachments')
                            ->label(__('Attachments'))
                            ->multiple()
                            ->storeFiles(false)
                            ->maxSize(50 * 1024)
                            ->maxFiles(10)
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'image/gif',
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'video/mp4',
                                'video/quicktime',
                                'video/webm',
                            ])
                            ->helperText(new HtmlString(
                                '<div class="space-y-1">'
                                .'<div><strong>'.__('Accepted formats').':</strong> JPG, PNG, WEBP, GIF, PDF, DOC, DOCX, XLS, XLSX, MP4, MOV, WEBM</div>'
                                .'<div><strong>'.__('Size limits').':</strong> '.__('Images').' (10MB) • '.__('Documents').' (20MB) • '.__('Videos').' (50MB)</div>'
                                .'</div>'
                            ))
                            ->imagePreviewHeight('120')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Notification Audience'))
                    ->description(__('Select who should receive this notification. Choose departments, units, specific employees, or send to everyone.'))
                    ->icon('heroicon-o-user-group')
                    ->columns(3)
                    ->components([
                        Toggle::make('send_to_all')
                            ->label(__('Send to All Employees'))
                            ->helperText(__('When enabled, the notification will be sent to all employees with registered devices.'))
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state): void {
                                if ($state) {
                                    $set('target_department_ids', []);
                                    $set('target_unit_ids', []);
                                    $set('target_user_ids', []);
                                }
                            })
                            ->columnSpanFull(),
                        Select::make('target_department_ids')
                            ->label(__('Departments'))
                            ->options(fn (): array => app(DepartmentHierarchyService::class)->topLevelOptions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('target_unit_ids', []))
                            ->disabled(fn (Get $get): bool => (bool) $get('send_to_all'))
                            ->placeholder(__('Select departments...')),
                        Select::make('target_unit_ids')
                            ->label(__('Units'))
                            ->options(fn (Get $get): array => app(DepartmentHierarchyService::class)->childOptionsGroupedByParent(
                                parentIds: (array) ($get('target_department_ids') ?? []),
                            ))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->disabled(fn (Get $get): bool => (bool) $get('send_to_all'))
                            ->placeholder(__('Select units...')),
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
                            ->live()
                            ->disabled(fn (Get $get): bool => (bool) $get('send_to_all'))
                            ->placeholder(__('Search employees...')),
                        Placeholder::make('audience_preview')
                            ->label('')
                            ->content(fn (Get $get): HtmlString => self::renderAudiencePreview([
                                'send_to_all' => (bool) $get('send_to_all'),
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
                '<div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-center dark:border-gray-600 dark:bg-gray-800">'
                .'<div class="flex items-center justify-center gap-2 text-sm text-gray-500 dark:text-gray-400">'
                .'<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>'
                .e(__('Select audience above to see preview'))
                .'</div>'
                .'</div>'
            );
        }

        $names = self::previewNames($preview['users']);
        $hasDeviceIssue = $preview['device_count'] === 0;

        $statusColor = $hasDeviceIssue ? 'danger' : 'success';
        $statusBg = $hasDeviceIssue ? 'bg-danger-50 border-danger-200 dark:bg-danger-950 dark:border-danger-800' : 'bg-success-50 border-success-200 dark:bg-success-950 dark:border-success-800';

        $html = '<div class="rounded-lg border '.$statusBg.' p-4 space-y-3">';

        // Stats row
        $html .= '<div class="grid grid-cols-3 gap-4 text-center">';
        $html .= '<div>';
        $html .= '<div class="text-2xl font-bold text-gray-900 dark:text-gray-100">'.e((string) $preview['user_count']).'</div>';
        $html .= '<div class="text-xs text-gray-500 dark:text-gray-400">'.e(__('Employees')).'</div>';
        $html .= '</div>';
        $html .= '<div>';
        $html .= '<div class="text-2xl font-bold text-gray-900 dark:text-gray-100">'.e((string) $preview['users_with_devices_count']).'</div>';
        $html .= '<div class="text-xs text-gray-500 dark:text-gray-400">'.e(__('With App')).'</div>';
        $html .= '</div>';
        $html .= '<div>';
        $html .= '<div class="text-2xl font-bold text-'.($hasDeviceIssue ? 'danger' : 'success').'-600 dark:text-'.($hasDeviceIssue ? 'danger' : 'success').'-400">'.e((string) $preview['device_count']).'</div>';
        $html .= '<div class="text-xs text-gray-500 dark:text-gray-400">'.e(__('Devices')).'</div>';
        $html .= '</div>';
        $html .= '</div>';

        // Names
        $html .= '<div class="text-xs text-gray-500 dark:text-gray-400 truncate">'.e($names).'</div>';

        // Warning
        if ($hasDeviceIssue) {
            $html .= '<div class="flex items-center gap-2 rounded-md bg-danger-100 px-3 py-2 text-sm font-medium text-danger-700 dark:bg-danger-900 dark:text-danger-300">';
            $html .= '<svg class="h-4 w-4 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>';
            $html .= e(__('No registered mobile devices found. The notification cannot be delivered.'));
            $html .= '</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
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
