<?php

namespace App\Filament\Pages;

use App\Services\Inbound\BridgeStatusService;
use App\Services\Settings\ApiSettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ApiSettings extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $bridgePanelStateCache = null;

    public function mount(ApiSettingsService $apiSettingsService): void
    {
        $settings = $apiSettingsService->getWhatsAppSettings();
        $templates = $apiSettingsService->getMessageTemplates();

        $this->form->fill([
            'provider' => $settings['provider'],
            'enforce_selected_provider' => (bool) ($settings['enforce_selected_provider'] ?? false),
            'outbound_enabled' => (bool) $settings['outbound_enabled'],
            'bridge_outbound_target' => $settings['bridge_outbound_target'] ?? 'direct_phone',
            'verify_token' => null,
            'access_token' => null,
            'bridge_secret' => null,
            'phone_number_id' => $settings['phone_number_id'],
            'api_base_url' => $settings['api_base_url'],
            'graph_version' => $settings['graph_version'],
            'tpl_task_registered' => $templates['task_registered'] ?? '',
            'tpl_task_assigned' => $templates['task_assigned'] ?? '',
            'tpl_task_completed' => $templates['task_completed'] ?? '',
            'enabled_task_registered' => filter_var($templates['enabled_task_registered'] ?? true, FILTER_VALIDATE_BOOL),
            'enabled_task_assigned' => filter_var($templates['enabled_task_assigned'] ?? true, FILTER_VALIDATE_BOOL),
            'enabled_task_completed' => filter_var($templates['enabled_task_completed'] ?? true, FILTER_VALIDATE_BOOL),
        ]);
    }

    public function getTitle(): string | HtmlString
    {
        return __('API Settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('API Settings');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Settings');
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Inbound Messaging Providers'))
                    ->description(__('Manage inbound webhook provider settings used by the WhatsApp inbox.'))
                    ->columns(2)
                    ->components([
                        Select::make('provider')
                            ->options([
                                'manual' => __('Manual Webhook'),
                                'meta' => __('Meta Cloud API'),
                                'twilio' => __('Twilio WhatsApp'),
                                '360dialog' => __('360dialog'),
                                'whatsapp_web_bridge' => __('WhatsApp Web Bridge'),
                            ])
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText(fn (Get $get): string => match ($get('provider')) {
                                'manual' => __('Manual Webhook URL: /webhooks/inbound-message'),
                                'meta' => __('Meta Verify: GET /webhooks/meta/whatsapp | Meta Receive: POST /webhooks/meta/whatsapp'),
                                'twilio' => __('Twilio Inbound URL: /webhooks/twilio/whatsapp'),
                                '360dialog' => __('360dialog Inbound URL: /webhooks/360dialog/whatsapp'),
                                'whatsapp_web_bridge' => __('Use this option to receive messages from a normal WhatsApp group using the local Node.js Baileys bridge. The bridge must be running separately.'),
                                default => __('Choose provider to show required settings.'),
                            }),
                        Toggle::make('enforce_selected_provider')
                            ->label(__('Use selected provider only'))
                            ->helperText(__('When enabled, inbound webhooks from non-selected providers are ignored to prevent provider mix-up.'))
                            ->inline(false),
                        Toggle::make('outbound_enabled')
                            ->label(__('Enable outbound messages'))
                            ->helperText(__('Keep disabled unless outbound sending is intentionally enabled.'))
                            ->inline(false),
                        Select::make('bridge_outbound_target')
                            ->label(__('Bridge outbound target'))
                            ->options([
                                'direct_phone' => __('Send to reporter number directly'),
                                'same_group' => __('Send to the same WhatsApp group'),
                            ])
                            ->helperText(__('When using WhatsApp Web Bridge, choose whether task updates go back to the reporter phone or to the originating group. If no group is linked, direct phone is used as fallback.'))
                            ->visible(fn (Get $get): bool => $get('provider') === 'whatsapp_web_bridge')
                            ->native(false),
                        TextInput::make('verify_token')
                            ->label(__('Webhook verify token'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->autocomplete('new-password')
                            ->helperText(__('Leave empty to keep the current token.'))
                            ->visible(fn (Get $get): bool => $get('provider') === 'meta')
                            ->columnSpanFull(),
                        TextInput::make('access_token')
                            ->label(__('Access token'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->autocomplete('new-password')
                            ->helperText(fn (Get $get): string => match ($get('provider')) {
                                'meta' => __('Used for Meta Cloud API (keep secret). Leave empty to keep current token.'),
                                '360dialog' => __('Used for 360dialog API calls if enabled later. Leave empty to keep current token.'),
                                default => __('Leave empty to keep the current token.'),
                            })
                            ->visible(fn (Get $get): bool => in_array($get('provider'), ['meta', '360dialog'], true))
                            ->columnSpanFull(),
                        TextInput::make('bridge_secret')
                            ->label(__('Bridge secret'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->autocomplete('new-password')
                            ->helperText(__('Leave empty to keep the current bridge secret.'))
                            ->visible(fn (Get $get): bool => $get('provider') === 'whatsapp_web_bridge')
                            ->columnSpanFull(),
                        TextInput::make('phone_number_id')
                            ->label(__('Phone number ID'))
                            ->visible(fn (Get $get): bool => in_array($get('provider'), ['meta', '360dialog'], true))
                            ->maxLength(255),
                        TextInput::make('graph_version')
                            ->label(__('Graph API version'))
                            ->required(fn (Get $get): bool => $get('provider') === 'meta')
                            ->visible(fn (Get $get): bool => $get('provider') === 'meta')
                            ->maxLength(50),
                        TextInput::make('api_base_url')
                            ->label(__('API base URL'))
                            ->required(fn (Get $get): bool => in_array($get('provider'), ['meta', '360dialog'], true))
                            ->url()
                            ->visible(fn (Get $get): bool => in_array($get('provider'), ['meta', '360dialog'], true))
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('WhatsApp Message Templates'))
                    ->description(__('Customize the notification messages sent via WhatsApp. Toggle each event on/off and edit the message text.'))
                    ->columns(1)
                    ->components([
                        Toggle::make('enabled_task_registered')
                            ->label(__('Send on task registration'))
                            ->helperText(__('Sent when a new task is created from a WhatsApp message.'))
                            ->inline(false)
                            ->live(),
                        Textarea::make('tpl_task_registered')
                            ->label(__('Task Registered Message'))
                            ->rows(2)
                            ->maxLength(1000)
                            ->placeholder(ApiSettingsService::defaultMessageTemplates()['task_registered'])
                            ->visible(fn (Get $get): bool => (bool) $get('enabled_task_registered')),
                        Toggle::make('enabled_task_assigned')
                            ->label(__('Send on task assignment'))
                            ->helperText(__('Sent when the task is assigned to an employee.'))
                            ->inline(false)
                            ->live(),
                        Textarea::make('tpl_task_assigned')
                            ->label(__('Task Assigned Message'))
                            ->rows(2)
                            ->maxLength(1000)
                            ->placeholder(ApiSettingsService::defaultMessageTemplates()['task_assigned'])
                            ->visible(fn (Get $get): bool => (bool) $get('enabled_task_assigned')),
                        Toggle::make('enabled_task_completed')
                            ->label(__('Send on task completion'))
                            ->helperText(__('Sent when the task is marked as completed.'))
                            ->inline(false)
                            ->live(),
                        Textarea::make('tpl_task_completed')
                            ->label(__('Task Completed Message'))
                            ->rows(2)
                            ->maxLength(1000)
                            ->placeholder(ApiSettingsService::defaultMessageTemplates()['task_completed'])
                            ->visible(fn (Get $get): bool => (bool) $get('enabled_task_completed')),
                        Placeholder::make('tpl_variables_hint')
                            ->label('')
                            ->content(new HtmlString(
                                '<div class="text-xs text-gray-500 dark:text-gray-400 rounded-lg bg-gray-50 dark:bg-white/5 p-3">'
                                . '<strong>' . __('Available variables') . ':</strong><br>'
                                . '<code class="text-primary-600 dark:text-primary-400">{task_number}</code> — ' . __('Task number (e.g. TASK-25)') . '<br>'
                                . '<code class="text-primary-600 dark:text-primary-400">{task_title}</code> — ' . __('Task title') . '<br>'
                                . '<code class="text-primary-600 dark:text-primary-400">{assignee_name}</code> — ' . __('Assigned employee name') . '<br>'
                                . '<code class="text-primary-600 dark:text-primary-400">{reporter_phone}</code> — ' . __('Reporter phone number') . '</div>'
                            )),
                    ]),
                Section::make(__('WhatsApp Web Bridge Status'))
                    ->description(__('Bridge heartbeat and connection status (read-only).'))
                    ->visible(fn (Get $get): bool => $get('provider') === 'whatsapp_web_bridge')
                    ->columns(2)
                    ->components([
                        Placeholder::make('bridge_webhook_url')
                            ->label(__('Webhook URL'))
                            ->content('/webhooks/inbound-message'),
                        Placeholder::make('bridge_heartbeat_url')
                            ->label(__('Heartbeat URL'))
                            ->content('/webhooks/bridge/heartbeat'),
                        Placeholder::make('bridge_status')
                            ->label(__('Status'))
                            ->content(fn (): HtmlString => $this->renderBridgeStatusBadge())
                            ->columnSpanFull(),
                        Placeholder::make('bridge_last_heartbeat')
                            ->label(__('Last heartbeat time'))
                            ->content(fn (): string => $this->formatBridgeTimestamp('last_heartbeat_at')),
                        Placeholder::make('bridge_last_message')
                            ->label(__('Last message received time'))
                            ->content(fn (): string => $this->formatBridgeTimestamp('last_message_at')),
                        Placeholder::make('bridge_account')
                            ->label(__('Connected WhatsApp account'))
                            ->content(fn (): string => $this->formatBridgeAccount()),
                        Placeholder::make('bridge_group_name')
                            ->label(__('Active group name'))
                            ->content(fn (): string => $this->bridgeStateValue('group_name')),
                        Placeholder::make('bridge_group_id')
                            ->label(__('Active group id'))
                            ->content(fn (): string => $this->bridgeStateValue('group_id'))
                            ->columnSpanFull(),
                        Placeholder::make('bridge_last_error')
                            ->label(__('Last error'))
                            ->content(fn (): string => $this->bridgeStateValue('last_error'))
                            ->visible(fn (): bool => $this->bridgeStateValue('last_error') !== '-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(ApiSettingsService $apiSettingsService): void
    {
        $state = $this->form->getState();
        $current = $apiSettingsService->getWhatsAppSettings();

        $apiSettingsService->updateWhatsAppSettings([
            'provider' => $state['provider'] ?? $current['provider'],
            'enforce_selected_provider' => $state['enforce_selected_provider'] ?? $current['enforce_selected_provider'],
            'outbound_enabled' => $state['outbound_enabled'] ?? $current['outbound_enabled'],
            'bridge_outbound_target' => $state['bridge_outbound_target'] ?? $current['bridge_outbound_target'],
            'verify_token' => $state['verify_token'] ?? $current['verify_token'],
            'access_token' => $state['access_token'] ?? $current['access_token'],
            'bridge_secret' => $state['bridge_secret'] ?? $current['bridge_secret'],
            'phone_number_id' => $state['phone_number_id'] ?? $current['phone_number_id'],
            'api_base_url' => $state['api_base_url'] ?? $current['api_base_url'],
            'graph_version' => $state['graph_version'] ?? $current['graph_version'],
        ]);

        $templateData = [
            'enabled_task_registered' => $state['enabled_task_registered'] ?? true,
            'enabled_task_assigned' => $state['enabled_task_assigned'] ?? true,
            'enabled_task_completed' => $state['enabled_task_completed'] ?? true,
        ];

        if (is_string($state['tpl_task_registered'] ?? null) && trim($state['tpl_task_registered']) !== '') {
            $templateData['task_registered'] = $state['tpl_task_registered'];
        }
        if (is_string($state['tpl_task_assigned'] ?? null) && trim($state['tpl_task_assigned']) !== '') {
            $templateData['task_assigned'] = $state['tpl_task_assigned'];
        }
        if (is_string($state['tpl_task_completed'] ?? null) && trim($state['tpl_task_completed']) !== '') {
            $templateData['task_completed'] = $state['tpl_task_completed'];
        }

        $apiSettingsService->updateMessageTemplates($templateData);

        $this->form->fill([
            ...$state,
            'verify_token' => null,
            'access_token' => null,
            'bridge_secret' => null,
        ]);
        $this->bridgePanelStateCache = null;

        Notification::make()
            ->success()
            ->title(__('API settings updated'))
            ->send();
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('settings.api.manage') ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('Save settings'))
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getBridgePanelState(): array
    {
        if ($this->bridgePanelStateCache === null) {
            $this->bridgePanelStateCache = app(BridgeStatusService::class)->getPanelState();
        }

        return $this->bridgePanelStateCache;
    }

    private function bridgeStateValue(string $key): string
    {
        $value = data_get($this->getBridgePanelState(), $key);

        if (! is_scalar($value)) {
            return '-';
        }

        $value = trim((string) $value);

        return $value === '' ? '-' : $value;
    }

    private function formatBridgeTimestamp(string $key): string
    {
        $value = data_get($this->getBridgePanelState(), $key);

        if (! $value instanceof Carbon) {
            return '-';
        }

        return $value->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    private function formatBridgeAccount(): string
    {
        $name = $this->bridgeStateValue('account_name');
        $id = $this->bridgeStateValue('account_id');

        if ($name === '-' && $id === '-') {
            return '-';
        }

        if ($name !== '-' && $id !== '-') {
            return "{$name} ({$id})";
        }

        return $name !== '-' ? $name : $id;
    }

    private function renderBridgeStatusBadge(): HtmlString
    {
        $status = (string) data_get($this->getBridgePanelState(), 'status', 'unknown');
        $color = match ($status) {
            'connected' => '#16a34a',
            'stale' => '#ca8a04',
            'disconnected' => '#dc2626',
            default => '#6b7280',
        };

        $label = match ($status) {
            'connected' => __('Connected'),
            'stale' => __('Stale'),
            'disconnected' => __('Disconnected'),
            default => __('Unknown'),
        };
        $lastError = $this->bridgeStateValue('last_error');
        $errorHtml = $lastError !== '-'
            ? '<div style="margin-top:8px;color:#dc2626;font-size:12px;">'.e($lastError).'</div>'
            : '';

        return new HtmlString(
            '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:'.$color.';color:#fff;font-weight:600;font-size:12px;">'.$label.'</span>'.$errorHtml
        );
    }
}
