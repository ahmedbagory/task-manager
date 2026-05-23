<?php

namespace App\Filament\Pages;

use App\Services\WhatsApp\BridgeApiClient;
use App\Services\WhatsApp\WhatsappBridgeProcessService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use UnitEnum;

class WhatsAppSession extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $bridgeStatus = null;

    /**
     * @var string|null
     */
    public ?string $qrDataUrl = null;

    /**
     * @var array<string, mixed>
     */
    public array $diagnostics = [];

    /**
     * @var string
     */
    public string $pm2Logs = '';

    public function mount(): void
    {
        $this->refreshBridgeData();
    }

    public function getTitle(): string|HtmlString
    {
        return __('WhatsApp Session');
    }

    public static function getNavigationLabel(): string
    {
        return __('WhatsApp Session');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Settings');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('settings.api.manage') ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Connection Status'))
                    ->description(__('Current WhatsApp Bridge session information.'))
                    ->components([
                        \Filament\Forms\Components\Placeholder::make('session_status')
                            ->label('')
                            ->content(fn (): HtmlString => $this->renderSessionCard()),
                    ]),
                Section::make(__('QR Code'))
                    ->description(__('Scan this QR code from WhatsApp → Linked Devices → Link a Device.'))
                    ->visible(fn (): bool => $this->qrDataUrl !== null)
                    ->components([
                        \Filament\Forms\Components\Placeholder::make('qr_display')
                            ->label('')
                            ->content(fn (): HtmlString => $this->renderQrCode()),
                    ]),
                Section::make(__('Process Diagnostics'))
                    ->description(__('PM2 process and system information.'))
                    ->collapsed()
                    ->components([
                        \Filament\Forms\Components\Placeholder::make('diagnostics_display')
                            ->label('')
                            ->content(fn (): HtmlString => $this->renderDiagnostics()),
                    ]),
                Section::make(__('Bridge Logs'))
                    ->description(__('Last log lines from the WhatsApp bridge process.'))
                    ->collapsed()
                    ->components([
                        \Filament\Forms\Components\Placeholder::make('logs_display')
                            ->label('')
                            ->content(fn (): HtmlString => $this->renderLogs()),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('Refresh'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => $this->refreshBridgeData()),

            Action::make('checkStatus')
                ->label(__('فحص الحالة'))
                ->icon(Heroicon::OutlinedSignal)
                ->color('info')
                ->action(function (): void {
                    $service = app(WhatsappBridgeProcessService::class);
                    $diag = $service->getDiagnostics();

                    $statusText = "PM2: " . ($diag['pm2_found'] ? 'Found' : 'NOT FOUND')
                        . "\nBridge: " . $diag['bridge_status']
                        . "\nNode: " . $diag['node_version']
                        . ($diag['uptime'] ? "\nUptime: " . $diag['uptime'] : '')
                        . ($diag['memory'] ? "\nMemory: " . $diag['memory'] : '')
                        . ($diag['pid'] ? "\nPID: " . $diag['pid'] : '')
                        . "\nAuth: " . ($diag['auth_exists'] ? 'Exists' : 'Missing');

                    Notification::make()
                        ->title(__('Bridge Process Status'))
                        ->body($statusText)
                        ->color($diag['bridge_status'] === 'online' ? 'success' : 'warning')
                        ->duration(10000)
                        ->send();

                    $this->refreshBridgeData();
                }),

            Action::make('restartBridge')
                ->label(__('إعادة تشغيل البريدج'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Restart WhatsApp Bridge'))
                ->modalDescription(__('This will restart the bridge PM2 process. The session (auth) will NOT be deleted. Messages may be briefly interrupted.'))
                ->action(function (): void {
                    $service = app(WhatsappBridgeProcessService::class);

                    if (! $service->pm2Exists()) {
                        Notification::make()
                            ->danger()
                            ->title(__('PM2 Not Found'))
                            ->body(__('PM2 binary not found at: ') . $service->getPm2Bin())
                            ->send();

                        return;
                    }

                    $result = $service->restart();

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title(__('Bridge Restarted'))
                            ->body(__('WhatsApp bridge has been restarted successfully.'))
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title(__('Restart Failed'))
                            ->body($result['error'] ?: __('Unknown error during restart.'))
                            ->send();
                    }

                    sleep(3);
                    $this->refreshBridgeData();
                }),

            Action::make('watchdogRestart')
                ->label(__('طلب إعادة تشغيل من Watchdog'))
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->action(function (): void {
                    $service = app(WhatsappBridgeProcessService::class);
                    $service->createRestartFlag();

                    Notification::make()
                        ->success()
                        ->title(__('Watchdog Restart Requested'))
                        ->body(__('A restart flag has been created. The watchdog cron will pick it up on the next run (within 1 minute).'))
                        ->send();
                }),

            Action::make('reconnectQr')
                ->label(__('إعادة الربط / QR'))
                ->icon(Heroicon::OutlinedQrCode)
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading(__('Reconnect / Show QR'))
                ->modalDescription(__('This will restart the bridge process to trigger a new connection attempt. If not authenticated, a new QR code will appear. Auth will NOT be deleted.'))
                ->action(function (): void {
                    $service = app(WhatsappBridgeProcessService::class);

                    if (! $service->pm2Exists()) {
                        Notification::make()
                            ->danger()
                            ->title(__('PM2 Not Found'))
                            ->body(__('PM2 binary not found at: ') . $service->getPm2Bin())
                            ->send();

                        return;
                    }

                    $result = $service->restart();

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title(__('Bridge Restarted'))
                            ->body(__('Bridge restarted. If a QR code is needed, click Refresh in a few seconds to see it.'))
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title(__('Restart Failed'))
                            ->body($result['error'] ?: __('Could not restart bridge.'))
                            ->send();
                    }

                    sleep(5);
                    $this->refreshBridgeData();
                }),

            Action::make('disconnect')
                ->label(__('Reset Session'))
                ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Reset WhatsApp Session'))
                ->modalDescription(__('WARNING: This will log out the current WhatsApp session, back up the auth folder, and generate a new QR code. You will need to scan the QR code again from WhatsApp. This cannot be undone.'))
                ->action(function (): void {
                    $client = app(BridgeApiClient::class);
                    $result = $client->logout();

                    if ($result && ($result['success'] ?? false)) {
                        Notification::make()
                            ->success()
                            ->title(__('Session Reset'))
                            ->body(__('Session disconnected. A new QR code will appear shortly. Click Refresh to see it.'))
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title(__('Reset Failed'))
                            ->body(__('Could not reach the bridge. Make sure it is running.'))
                            ->send();
                    }

                    sleep(3);
                    $this->refreshBridgeData();
                }),
        ];
    }

    public function refreshBridgeData(): void
    {
        $client = app(BridgeApiClient::class);

        $this->bridgeStatus = $client->getStatus();

        $qrResponse = $client->getQr();
        $this->qrDataUrl = $qrResponse['qr'] ?? null;

        $service = app(WhatsappBridgeProcessService::class);
        $this->diagnostics = $service->getDiagnostics();

        $logResult = $service->getLogs(40);
        $this->pm2Logs = $logResult['output'] ?: ($logResult['error'] ?: '');
    }

    private function renderSessionCard(): HtmlString
    {
        if ($this->bridgeStatus === null) {
            return new HtmlString(
                '<div class="rounded-xl border border-gray-200 dark:border-white/10 p-6 text-center">'
                . '<div class="text-2xl mb-2">⚠️</div>'
                . '<p class="font-semibold text-gray-700 dark:text-gray-300">' . __('Bridge Unreachable') . '</p>'
                . '<p class="text-sm text-gray-500 dark:text-gray-400 mt-1">' . __('The WhatsApp bridge is not running or not responding.') . '</p>'
                . '</div>'
            );
        }

        $state = $this->bridgeStatus['state'] ?? 'disconnected';
        $accountId = $this->bridgeStatus['account_id'] ?? null;
        $accountName = $this->bridgeStatus['account_name'] ?? null;
        $groupsCount = $this->bridgeStatus['groups_count'] ?? 0;

        if ($state === 'connected') {
            $accountDisplay = $accountName ? e($accountName) : '';
            if ($accountId) {
                $accountDisplay .= $accountDisplay ? ' (' . e($accountId) . ')' : e($accountId);
            }

            return new HtmlString(
                '<div class="rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-6">'
                . '<div class="flex items-center gap-3 mb-4">'
                . '<span class="inline-flex h-3 w-3 rounded-full bg-green-500 animate-pulse"></span>'
                . '<span class="font-semibold text-green-700 dark:text-green-400 text-lg">' . __('Connected') . '</span>'
                . '</div>'
                . '<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">'
                . '<div><span class="text-gray-500 dark:text-gray-400">' . __('Account') . ':</span> <span class="font-medium text-gray-900 dark:text-white">' . ($accountDisplay ?: '-') . '</span></div>'
                . '<div><span class="text-gray-500 dark:text-gray-400">' . __('Groups') . ':</span> <span class="font-medium text-gray-900 dark:text-white">' . $groupsCount . '</span></div>'
                . '</div>'
                . '</div>'
            );
        }

        if ($state === 'qr_pending') {
            return new HtmlString(
                '<div class="rounded-xl border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20 p-6">'
                . '<div class="flex items-center gap-3">'
                . '<span class="inline-flex h-3 w-3 rounded-full bg-yellow-500 animate-pulse"></span>'
                . '<span class="font-semibold text-yellow-700 dark:text-yellow-400 text-lg">' . __('Waiting for QR Scan') . '</span>'
                . '</div>'
                . '<p class="text-sm text-gray-500 dark:text-gray-400 mt-2">' . __('Open WhatsApp on your phone → Linked Devices → Link a Device → Scan the QR code below.') . '</p>'
                . '</div>'
            );
        }

        return new HtmlString(
            '<div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-6">'
            . '<div class="flex items-center gap-3">'
            . '<span class="inline-flex h-3 w-3 rounded-full bg-red-500"></span>'
            . '<span class="font-semibold text-red-700 dark:text-red-400 text-lg">' . __('Disconnected') . '</span>'
            . '</div>'
            . '<p class="text-sm text-gray-500 dark:text-gray-400 mt-2">' . __('The bridge is running but not connected to a WhatsApp account.') . '</p>'
            . '</div>'
        );
    }

    private function renderQrCode(): HtmlString
    {
        if (! $this->qrDataUrl) {
            return new HtmlString('');
        }

        return new HtmlString(
            '<div class="flex flex-col items-center gap-4 p-4">'
            . '<img src="' . e($this->qrDataUrl) . '" alt="WhatsApp QR Code" class="rounded-xl shadow-lg" style="width:320px;height:320px;" />'
            . '<p class="text-sm text-gray-500 dark:text-gray-400">' . __('QR codes expire quickly. Click Refresh if the scan fails.') . '</p>'
            . '</div>'
        );
    }

    private function renderDiagnostics(): HtmlString
    {
        $d = $this->diagnostics;

        if (empty($d)) {
            return new HtmlString('<p class="text-gray-500 dark:text-gray-400">' . __('No diagnostics available. Click Refresh.') . '</p>');
        }

        $rows = [
            [__('PM2 Found'), ($d['pm2_found'] ?? false) ? '✓ Yes' : '✗ No'],
            [__('PM2 Binary'), e($d['pm2_bin'] ?? '-')],
            [__('Node Version'), e($d['node_version'] ?? '-')],
            [__('Bridge PM2 Status'), e($d['bridge_status'] ?? '-')],
            [__('PID'), e((string) ($d['pid'] ?? '-'))],
            [__('Restart Count'), e((string) ($d['restart_count'] ?? '-'))],
            [__('Uptime'), e($d['uptime'] ?? '-')],
            [__('Memory'), e($d['memory'] ?? '-')],
            [__('Auth Folder'), ($d['auth_exists'] ?? false) ? '✓ Exists' : '✗ Missing'],
        ];

        $html = '<div class="overflow-x-auto"><table class="w-full text-sm">';
        $html .= '<tbody class="divide-y divide-gray-200 dark:divide-white/10">';

        foreach ($rows as [$label, $value]) {
            $html .= '<tr>'
                . '<td class="py-2 pe-4 font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">' . $label . '</td>'
                . '<td class="py-2 text-gray-600 dark:text-gray-400 font-mono text-xs">' . $value . '</td>'
                . '</tr>';
        }

        $html .= '</tbody></table></div>';

        return new HtmlString($html);
    }

    private function renderLogs(): HtmlString
    {
        if (! $this->pm2Logs) {
            return new HtmlString('<p class="text-gray-500 dark:text-gray-400">' . __('No logs available. The bridge may not be running.') . '</p>');
        }

        $escaped = e($this->pm2Logs);

        return new HtmlString(
            '<pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs overflow-x-auto max-h-96 overflow-y-auto font-mono whitespace-pre-wrap">'
            . $escaped
            . '</pre>'
        );
    }
}
