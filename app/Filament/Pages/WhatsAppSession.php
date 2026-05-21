<?php

namespace App\Filament\Pages;

use App\Services\WhatsApp\BridgeApiClient;
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
            Action::make('disconnect')
                ->label(__('Disconnect & New QR'))
                ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('Disconnect WhatsApp Session'))
                ->modalDescription(__('This will log out the current WhatsApp session and generate a new QR code. You will need to scan the QR code again from WhatsApp.'))
                ->action(function (): void {
                    $client = app(BridgeApiClient::class);
                    $result = $client->logout();

                    if ($result && ($result['success'] ?? false)) {
                        Notification::make()
                            ->success()
                            ->title(__('Session disconnected'))
                            ->body(__('A new QR code will appear shortly. Click Refresh to see it.'))
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title(__('Disconnect failed'))
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
}
