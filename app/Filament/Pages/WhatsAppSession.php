<?php

namespace App\Filament\Pages;

use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Services\WhatsApp\BridgeApiClient;
use App\Services\WhatsApp\WhatsappBridgeProcessService;
use App\Services\WhatsApp\WhatsappBridgeStatusService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use UnitEnum;

class WhatsAppSession extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.whatsapp-session';

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
        return __('جلسة واتساب');
    }

    public static function getNavigationLabel(): string
    {
        return __('جلسة واتساب');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('واتساب');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('settings.api.manage') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openInbox')
                ->label(__('فتح المحادثات'))
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('gray')
                ->url(WhatsappMessageResource::getUrl('index')),

            Action::make('refresh')
                ->label(__('تحديث'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => $this->refreshBridgeData()),

            Action::make('checkStatus')
                ->label(__('فحص العملية'))
                ->icon(Heroicon::OutlinedSignal)
                ->color('info')
                ->action(function (): void {
                    $service = app(WhatsappBridgeProcessService::class);
                    $diag = $service->getDiagnostics();

                    $statusText = "PM2: " . (($diag['pm2_found'] ?? false) ? 'متوفر' : 'مفقود')
                        . "\nBridge: " . ($diag['bridge_status'] ?? 'unknown')
                        . "\nNode: " . ($diag['node_version'] ?? 'unknown')
                        . ($diag['uptime'] ? "\nUptime: " . $diag['uptime'] : '')
                        . ($diag['memory'] ? "\nMemory: " . $diag['memory'] : '')
                        . ($diag['pid'] ? "\nPID: " . $diag['pid'] : '')
                        . "\nAuth: " . (($diag['auth_exists'] ?? false) ? 'موجود' : 'مفقود');

                    Notification::make()
                        ->title(__('حالة عملية واتساب'))
                        ->body($statusText)
                        ->color(($diag['bridge_status'] ?? null) === 'online' ? 'success' : 'warning')
                        ->duration(10000)
                        ->send();

                    $this->refreshBridgeData();
                }),

            Action::make('restartBridge')
                ->label(__('إعادة تشغيل البريدج'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('إعادة تشغيل بريدج واتساب'))
                ->modalDescription(__('سيتم إعادة تشغيل عملية البريدج دون حذف الجلسة الحالية.'))
                ->action(function (): void {
                    $service = app(WhatsappBridgeProcessService::class);

                    if (! $service->pm2Exists()) {
                        Notification::make()
                            ->danger()
                            ->title(__('PM2 غير متوفر'))
                            ->body(__('لم يتم العثور على PM2 في المسار: ') . $service->getPm2Bin())
                            ->send();

                        return;
                    }

                    $result = $service->restart();

                    Notification::make()
                        ->title($result['success'] ? __('تمت إعادة التشغيل') : __('فشلت إعادة التشغيل'))
                        ->body($result['success'] ? __('تمت إعادة تشغيل البريدج بنجاح.') : ($result['error'] ?: __('حدث خطأ غير معروف أثناء إعادة التشغيل.')))
                        ->color($result['success'] ? 'success' : 'danger')
                        ->send();

                    sleep(3);
                    $this->refreshBridgeData();
                }),

            Action::make('watchdogRestart')
                ->label(__('إشارة Watchdog'))
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->action(function (): void {
                    $service = app(WhatsappBridgeProcessService::class);
                    $service->createRestartFlag();

                    Notification::make()
                        ->success()
                        ->title(__('تم تسجيل طلب إعادة التشغيل'))
                        ->body(__('سيتعامل معه الـ Watchdog في الدورة التالية.'))
                        ->send();

                    $this->refreshBridgeData();
                }),

            Action::make('reconnectQr')
                ->label(__('إعادة الربط / Reconnect'))
                ->icon(Heroicon::OutlinedQrCode)
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading(__('إعادة الربط أو إظهار QR'))
                ->modalDescription(__('سيتم إعادة تشغيل البريدج ومحاولة إظهار QR جديد إذا كانت الجلسة تحتاج ربطًا.'))
                ->action(function (): void {
                    $service = app(WhatsappBridgeProcessService::class);

                    if (! $service->pm2Exists()) {
                        Notification::make()
                            ->danger()
                            ->title(__('PM2 غير متوفر'))
                            ->body(__('لم يتم العثور على PM2 في المسار: ') . $service->getPm2Bin())
                            ->send();

                        return;
                    }

                    $result = $service->restart();

                    Notification::make()
                        ->title($result['success'] ? __('تمت إعادة تهيئة الجلسة') : __('تعذر إعادة الربط'))
                        ->body($result['success'] ? __('أعد تحميل الصفحة خلال ثوانٍ لعرض QR إذا كان مطلوبًا.') : ($result['error'] ?: __('تعذر إعادة تشغيل البريدج.')))
                        ->color($result['success'] ? 'success' : 'danger')
                        ->send();

                    sleep(5);
                    $this->refreshBridgeData();
                }),

            Action::make('disconnect')
                ->label(__('فصل الجلسة'))
                ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                ->color('danger')
                ->visible(fn (): bool => in_array($this->currentState(), ['connected', 'ready', 'qr_required'], true))
                ->requiresConfirmation()
                ->modalHeading(__('فصل جلسة واتساب'))
                ->modalDescription(__('سيتم تسجيل الخروج من الجلسة الحالية وحذف ملفات الاعتماد لإظهار QR جديد.'))
                ->action(function (): void {
                    $client = app(BridgeApiClient::class);
                    $result = $client->logout();

                    if ($result && ($result['success'] ?? false)) {
                        Notification::make()
                            ->success()
                            ->title(__('تم فصل الجلسة'))
                            ->body(__('سيظهر QR جديد بعد لحظات.'))
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title(__('تعذر فصل الجلسة'))
                            ->body(__('تعذر الوصول إلى البريدج. تأكد من أنه يعمل أولًا.'))
                            ->send();
                    }

                    sleep(3);
                    $this->refreshBridgeData();
                }),
        ];
    }

    public function refreshBridgeData(): void
    {
        $this->bridgeStatus = app(WhatsappBridgeStatusService::class)->current();

        $client = app(BridgeApiClient::class);
        $this->qrDataUrl = ($this->bridgeStatus['qr_available'] ?? false)
            ? ($client->getQr()['qr'] ?? null)
            : null;

        $service = app(WhatsappBridgeProcessService::class);
        $this->diagnostics = $service->getDiagnostics();

        $logResult = $service->getLogs(60);
        $this->pm2Logs = $logResult['output'] ?: ($logResult['error'] ?: '');
    }

    public function currentState(): string
    {
        return (string) ($this->bridgeStatus['state'] ?? 'disconnected');
    }

    public function currentLabel(): string
    {
        return (string) ($this->bridgeStatus['label'] ?? 'غير متصل');
    }

    public function currentBadge(): string
    {
        return (string) ($this->bridgeStatus['badge'] ?? 'gray');
    }

    public function currentHint(): string
    {
        return (string) ($this->bridgeStatus['status_hint'] ?? '');
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public function summaryItems(): array
    {
        $status = $this->bridgeStatus ?? [];

        return [
            ['label' => 'الحساب', 'value' => $this->formatAccount()],
            ['label' => 'المجموعات', 'value' => (string) ((int) ($status['groups_count'] ?? 0))],
            ['label' => 'آخر نبضة', 'value' => $this->formatTimestamp($status['last_heartbeat_at'] ?? null)],
            ['label' => 'آخر رسالة', 'value' => $this->formatTimestamp($status['last_message_at'] ?? null)],
            ['label' => 'PM2', 'value' => (string) ($status['pm2_status'] ?? 'unknown')],
            ['label' => 'الإرسال', 'value' => $this->sendModeLabel()],
        ];
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    public function diagnosticsRows(): array
    {
        $d = $this->diagnostics;

        return [
            ['label' => 'PM2', 'value' => ($d['pm2_found'] ?? false) ? 'متوفر' : 'غير متوفر'],
            ['label' => 'مسار PM2', 'value' => (string) ($d['pm2_bin'] ?? '-')],
            ['label' => 'إصدار Node', 'value' => (string) ($d['node_version'] ?? '-')],
            ['label' => 'حالة العملية', 'value' => (string) ($d['bridge_status'] ?? '-')],
            ['label' => 'PID', 'value' => isset($d['pid']) ? (string) $d['pid'] : '-'],
            ['label' => 'مرات إعادة التشغيل', 'value' => isset($d['restart_count']) ? (string) $d['restart_count'] : '-'],
            ['label' => 'مدة التشغيل', 'value' => (string) ($d['uptime'] ?? '-')],
            ['label' => 'الذاكرة', 'value' => (string) ($d['memory'] ?? '-')],
            ['label' => 'مجلد auth', 'value' => ($d['auth_exists'] ?? false) ? 'موجود' : 'مفقود'],
        ];
    }

    public function canShowQr(): bool
    {
        return filled($this->qrDataUrl);
    }

    public function formattedLogs(): string
    {
        return trim($this->pm2Logs);
    }

    private function formatAccount(): string
    {
        $status = $this->bridgeStatus ?? [];
        $name = trim((string) ($status['account_name'] ?? ''));
        $id = trim((string) ($status['account_id'] ?? ''));

        if ($name !== '' && $id !== '') {
            return $name . ' (' . $id . ')';
        }

        return $name !== '' ? $name : ($id !== '' ? $id : 'غير محدد');
    }

    private function sendModeLabel(): string
    {
        $status = $this->bridgeStatus ?? [];

        if (! ($status['supports_bridge'] ?? false)) {
            return 'ليس البريدج المحلي';
        }

        if (! ($status['outbound_enabled'] ?? false)) {
            return 'معطل';
        }

        if ($status['can_send'] ?? false) {
            return 'متاح';
        }

        if ($status['can_queue'] ?? false) {
            return 'انتظار';
        }

        return 'غير متاح';
    }

    private function formatTimestamp(mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        $date = $value instanceof Carbon ? $value : Carbon::parse((string) $value);

        return $date->timezone(config('app.timezone'))->format('Y-m-d H:i');
    }
}
