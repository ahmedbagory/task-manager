<?php

namespace App\Services\WhatsApp;

use App\Models\BridgeStatus;
use App\Services\Inbound\BridgeStatusService;
use App\Services\Settings\ApiSettingsService;

class WhatsappBridgeStatusService
{
    public function __construct(
        private readonly BridgeApiClient $bridgeApiClient,
        private readonly WhatsappBridgeProcessService $processService,
        private readonly ApiSettingsService $apiSettingsService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $settings = $this->apiSettingsService->getWhatsAppSettings();
        $supportsBridge = strtolower((string) ($settings['provider'] ?? 'meta')) === BridgeStatusService::PROVIDER;
        $outboundEnabled = filter_var($settings['outbound_enabled'] ?? false, FILTER_VALIDATE_BOOL) === true;
        $liveStatus = $this->bridgeApiClient->getStatus();
        $storedStatus = BridgeStatus::query()
            ->where('provider', BridgeStatusService::PROVIDER)
            ->latest('id')
            ->first();

        $diagnostics = $this->resolveDiagnostics($liveStatus);
        $state = $this->resolveState($liveStatus, $storedStatus, $diagnostics);
        $canSend = $supportsBridge && $outboundEnabled && in_array($state, ['connected', 'ready'], true);
        $canQueue = $supportsBridge && $outboundEnabled && ! $canSend;

        return [
            'state' => $state,
            'label' => $this->label($state),
            'badge' => $this->badge($state),
            'hex' => $this->hex($state),
            'status_hint' => $this->statusHint(
                state: $state,
                supportsBridge: $supportsBridge,
                outboundEnabled: $outboundEnabled,
                canQueue: $canQueue,
            ),
            'supports_bridge' => $supportsBridge,
            'outbound_enabled' => $outboundEnabled,
            'can_send' => $canSend,
            'can_queue' => $canQueue,
            'raw_state' => strtolower((string) ($liveStatus['state'] ?? $storedStatus?->status ?? '')),
            'account_id' => $liveStatus['account_id'] ?? $storedStatus?->account_id,
            'account_name' => $liveStatus['account_name'] ?? $storedStatus?->account_name,
            'group_name' => $liveStatus['group_name'] ?? $storedStatus?->group_name,
            'groups_count' => (int) ($liveStatus['groups_count'] ?? (is_array($storedStatus?->meta) ? ($storedStatus->meta['groups_count'] ?? 0) : 0)),
            'qr_available' => (bool) ($liveStatus['qr_available'] ?? false),
            'last_heartbeat_at' => $storedStatus?->last_heartbeat_at?->toIso8601String(),
            'last_message_at' => $storedStatus?->last_message_at?->toIso8601String(),
            'last_error' => $storedStatus?->last_error,
            'pm2_status' => strtolower((string) ($diagnostics['bridge_status'] ?? 'unknown')),
            'pm2_found' => (bool) ($diagnostics['pm2_found'] ?? false),
            'auth_exists' => (bool) ($diagnostics['auth_exists'] ?? false),
            'restart_requested' => $this->processService->hasRestartFlag(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $liveStatus
     * @return array<string, mixed>
     */
    private function resolveDiagnostics(?array $liveStatus): array
    {
        $liveState = strtolower((string) ($liveStatus['state'] ?? ''));

        if (is_array($liveStatus) && in_array($liveState, ['connected', 'ready', 'qr_pending', 'qr_required'], true)) {
            return [
                'pm2_found' => $this->processService->pm2Exists(),
                'bridge_status' => 'online',
                'auth_exists' => $this->processService->authFolderExists(),
            ];
        }

        return $this->processService->getDiagnostics();
    }

    /**
     * @param  array<string, mixed>|null  $liveStatus
     * @param  array<string, mixed>  $diagnostics
     */
    private function resolveState(?array $liveStatus, ?BridgeStatus $storedStatus, array $diagnostics): string
    {
        $pm2Status = strtolower((string) ($diagnostics['bridge_status'] ?? 'unknown'));
        $hasAuth = (bool) ($diagnostics['auth_exists'] ?? false);
        $hasQr = (bool) ($liveStatus['qr_available'] ?? false);
        $liveState = strtolower((string) ($liveStatus['state'] ?? ''));
        $lastHeartbeat = $storedStatus?->last_heartbeat_at;
        $isStoredFresh = $lastHeartbeat?->gte(now()->subSeconds(90)) ?? false;
        $storedState = strtolower((string) ($storedStatus?->status ?? ''));

        if ($liveState !== '') {
            return match ($liveState) {
                'connected' => filled($liveStatus['account_id'] ?? null) ? 'ready' : 'connected',
                'ready' => 'ready',
                'qr_pending', 'qr_required' => 'qr_required',
                'starting', 'launching', 'connecting' => 'starting',
                'stopped' => 'stopped',
                'error', 'errored' => 'error',
                'disconnected' => $this->stateFromOfflineSignals($pm2Status, $hasAuth, $hasQr, $storedStatus, $isStoredFresh),
                default => $this->stateFromOfflineSignals($pm2Status, $hasAuth, $hasQr, $storedStatus, $isStoredFresh),
            };
        }

        if ($storedState !== '' && $isStoredFresh) {
            return $this->normalizeStoredState($storedState);
        }

        return $this->stateFromOfflineSignals($pm2Status, $hasAuth, $hasQr, $storedStatus, $isStoredFresh);
    }

    private function stateFromOfflineSignals(
        string $pm2Status,
        bool $hasAuth,
        bool $hasQr,
        ?BridgeStatus $storedStatus,
        bool $isStoredFresh,
    ): string {
        if ($hasQr || ! $hasAuth) {
            return 'qr_required';
        }

        if (in_array($pm2Status, ['launching', 'stopping'], true) || $this->processService->hasRestartFlag()) {
            return 'starting';
        }

        if (in_array($pm2Status, ['stopped', 'unknown'], true)) {
            return 'stopped';
        }

        if (in_array($pm2Status, ['errored', 'one-launch-status'], true)) {
            return 'error';
        }

        if ($pm2Status === 'online' && $isStoredFresh) {
            return $this->normalizeStoredState((string) ($storedStatus?->status ?? 'connected'));
        }

        if ($pm2Status === 'online') {
            return 'starting';
        }

        if (filled($storedStatus?->last_error)) {
            return 'error';
        }

        return 'disconnected';
    }

    private function normalizeStoredState(string $state): string
    {
        return match (strtolower($state)) {
            'connected', 'ready' => 'connected',
            'qr_pending', 'qr_required' => 'qr_required',
            'starting', 'launching', 'connecting' => 'starting',
            'stopped' => 'stopped',
            'error', 'errored', 'failed' => 'error',
            default => 'disconnected',
        };
    }

    private function label(string $state): string
    {
        return match ($state) {
            'connected', 'ready' => 'متصل',
            'qr_required' => 'يحتاج QR',
            'starting' => 'جاري الربط',
            'stopped' => 'متوقف',
            'error' => 'خطأ',
            default => 'غير متصل',
        };
    }

    private function badge(string $state): string
    {
        return match ($state) {
            'connected', 'ready' => 'success',
            'qr_required' => 'warning',
            'starting' => 'info',
            'stopped' => 'gray',
            'error', 'disconnected' => 'danger',
            default => 'gray',
        };
    }

    private function hex(string $state): string
    {
        return match ($state) {
            'connected', 'ready' => '#10b981',
            'qr_required' => '#f59e0b',
            'starting' => '#3b82f6',
            'stopped' => '#6b7280',
            'error', 'disconnected' => '#ef4444',
            default => '#6b7280',
        };
    }

    private function statusHint(string $state, bool $supportsBridge, bool $outboundEnabled, bool $canQueue): string
    {
        if (! $supportsBridge) {
            return 'مزود واتساب الحالي ليس البريدج المحلي.';
        }

        if (! $outboundEnabled) {
            return 'إرسال واتساب معطل من الإعدادات حاليًا.';
        }

        return match ($state) {
            'connected', 'ready' => 'الجلسة جاهزة لإرسال الرسائل واستقبالها.',
            'qr_required' => 'يجب مسح رمز QR أو إعادة الربط قبل الإرسال.',
            'starting' => 'الجلسة قيد التجهيز. قد يتأخر الإرسال لبضع ثوان.',
            'stopped' => 'البريدج متوقف ويحتاج إلى تشغيل أو إعادة ربط.',
            'error' => 'حدث خطأ في جلسة واتساب. راجع السجلات أو أعد التشغيل.',
            default => $canQueue
                ? 'البريدج غير متصل الآن. يمكن وضع الرسائل في الانتظار لحين عودة الجلسة.'
                : 'البريدج غير متصل الآن.',
        };
    }
}
