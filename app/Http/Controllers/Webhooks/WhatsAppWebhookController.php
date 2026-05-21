<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\ManualInboundMessageRequest;
use App\Models\WhatsappMessage;
use App\Enums\WhatsappMessageDirection;
use App\Services\Inbound\BridgeStatusService;
use App\Services\Inbound\InboundMessageService;
use App\Services\Inbound\Parsers\Dialog360MessageParser;
use App\Services\Inbound\Parsers\ManualWebhookMessageParser;
use App\Services\Inbound\Parsers\MetaWhatsAppMessageParser;
use App\Services\Inbound\Parsers\TwilioWhatsAppMessageParser;
use App\Services\Settings\ApiSettingsService;
use App\Services\WhatsApp\BridgeOutboundMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, ApiSettingsService $apiSettingsService): Response
    {
        if (! $this->isProviderAccepted($apiSettingsService, 'meta')) {
            return response('Webhook provider is not active.', 403);
        }

        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $verifyToken = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');
        $expectedVerifyToken = (string) $apiSettingsService->getWhatsAppValue('verify_token', '');

        $isValid = $mode === 'subscribe'
            && filled($verifyToken)
            && hash_equals($expectedVerifyToken, (string) $verifyToken)
            && filled($challenge);

        if (! $isValid) {
            return response('Invalid webhook verification request.', 403);
        }

        return response((string) $challenge, 200)
            ->header('Content-Type', 'text/plain');
    }

    public function receive(
        Request $request,
        MetaWhatsAppMessageParser $parser,
        InboundMessageService $inboundMessageService,
        ApiSettingsService $apiSettingsService
    ): JsonResponse {
        if (! $this->isProviderAccepted($apiSettingsService, 'meta')) {
            $this->logProviderIgnored('meta');

            return $this->jsonIgnoredResponse();
        }

        $payload = $this->extractPayload($request);

        try {
            $messages = $parser->parse($payload);
        } catch (Throwable $exception) {
            Log::warning('Inbound meta webhook parse failure.', [
                'provider' => 'meta',
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => true,
                'status' => 'ok',
            ]);
        }

        Log::info('Inbound webhook received.', [
            'provider' => 'meta',
            'messages_count' => count($messages),
            'payload_keys' => array_slice(array_keys($payload), 0, 10),
        ]);

        try {
            foreach ($messages as $message) {
                $inboundMessageService->handle($message);
            }
        } catch (Throwable $exception) {
            Log::error('Inbound meta webhook processing failed.', [
                'provider' => 'meta',
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'status' => 'error',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'status' => 'ok',
        ]);
    }

    public function manual(
        ManualInboundMessageRequest $request,
        ManualWebhookMessageParser $parser,
        InboundMessageService $inboundMessageService,
        ApiSettingsService $apiSettingsService,
        BridgeStatusService $bridgeStatusService,
    ): JsonResponse {
        $payload = $request->validated();
        $provider = strtolower((string) ($payload['provider'] ?? $payload['source'] ?? 'manual'));

        if (! $this->isProviderAccepted($apiSettingsService, $provider)) {
            $this->logProviderIgnored($provider);

            return $this->jsonIgnoredResponse();
        }

        if ($provider === BridgeStatusService::PROVIDER && ! $this->hasValidBridgeToken($request, $apiSettingsService)) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid bridge token.',
            ], 403);
        }

        Log::info('Inbound webhook received.', [
            'provider' => $provider,
            'payload_keys' => array_slice(array_keys($payload), 0, 10),
        ]);

        $messages = $parser->parse($payload);
        $savedMessage = null;

        foreach ($messages as $message) {
            $savedMessage = $inboundMessageService->handle($message);

            if ($message->provider === BridgeStatusService::PROVIDER) {
                $bridgeStatusService->updateFromInboundMessage($message);
            }
        }

        return response()->json([
            'ok' => true,
            'message_id' => $savedMessage?->id,
        ]);
    }

    public function bridgeHeartbeat(
        Request $request,
        ApiSettingsService $apiSettingsService,
        BridgeStatusService $bridgeStatusService
    ): JsonResponse {
        if (! $this->isProviderAccepted($apiSettingsService, BridgeStatusService::PROVIDER)) {
            $this->logProviderIgnored(BridgeStatusService::PROVIDER);

            return $this->jsonIgnoredResponse();
        }

        if (! $this->hasValidBridgeToken($request, $apiSettingsService)) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid bridge token.',
            ], 403);
        }

        $payload = $this->extractPayload($request);

        Log::info('Bridge heartbeat received.', [
            'provider' => $payload['provider'] ?? BridgeStatusService::PROVIDER,
            'payload_keys' => array_slice(array_keys($payload), 0, 10),
        ]);

        $bridgeStatusService->updateHeartbeat($payload);

        return response()->json([
            'ok' => true,
        ]);
    }

    public function bridgeOutboundPull(
        Request $request,
        ApiSettingsService $apiSettingsService,
        BridgeOutboundMessageService $bridgeOutboundMessageService
    ): JsonResponse {
        if (! $this->isBridgeProviderSelected($apiSettingsService)) {
            return response()->json([
                'ok' => true,
                'status' => 'ignored',
                'messages' => [],
            ]);
        }

        if (! $this->hasValidBridgeToken($request, $apiSettingsService)) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid bridge token.',
            ], 403);
        }

        $limit = max(1, min((int) $request->integer('limit', 10), 20));
        $messages = $bridgeOutboundMessageService->claimPendingMessages($limit)
            ->map(fn (WhatsappMessage $message): array => $bridgeOutboundMessageService->toBridgePayload($message))
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'messages' => $messages,
        ]);
    }

    public function bridgeOutboundAcknowledge(
        Request $request,
        WhatsappMessage $message,
        ApiSettingsService $apiSettingsService,
        BridgeOutboundMessageService $bridgeOutboundMessageService
    ): JsonResponse {
        if (! $this->isBridgeProviderSelected($apiSettingsService)) {
            return response()->json([
                'ok' => true,
                'status' => 'ignored',
            ]);
        }

        if (! $this->hasValidBridgeToken($request, $apiSettingsService)) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid bridge token.',
            ], 403);
        }

        if ($message->direction !== WhatsappMessageDirection::OUTBOUND) {
            return response()->json([
                'ok' => false,
                'error' => 'Outbound message not found.',
            ], 404);
        }

        $payload = $request->validate([
            'status' => ['required', 'string', 'in:sent,failed'],
            'provider_message_id' => ['nullable', 'string', 'max:255'],
            'error' => ['nullable', 'string', 'max:1000'],
            'sent_to' => ['nullable', 'string', 'max:255'],
            'response' => ['nullable', 'array'],
        ]);

        if ($payload['status'] === 'sent') {
            $bridgeOutboundMessageService->markSent(
                message: $message,
                providerMessageId: $payload['provider_message_id'] ?? null,
                responsePayload: $payload['response'] ?? null,
                sentTo: $payload['sent_to'] ?? null,
            );
        } else {
            $bridgeOutboundMessageService->markFailed(
                message: $message,
                error: $payload['error'] ?? null,
                responsePayload: $payload['response'] ?? null,
            );
        }

        return response()->json([
            'ok' => true,
        ]);
    }

    public function twilio(
        Request $request,
        TwilioWhatsAppMessageParser $parser,
        InboundMessageService $inboundMessageService,
        ApiSettingsService $apiSettingsService,
    ): Response {
        if (! $this->isProviderAccepted($apiSettingsService, 'twilio')) {
            $this->logProviderIgnored('twilio');

            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        $payload = $request->all();

        if (! is_array($payload)) {
            $payload = [];
        }

        try {
            $messages = $parser->parse($payload);
        } catch (Throwable $exception) {
            Log::warning('Inbound twilio webhook parse failure.', [
                'provider' => 'twilio',
                'error' => $exception->getMessage(),
            ]);

            return response('OK', 200)->header('Content-Type', 'text/plain');
        }

        Log::info('Inbound webhook received.', [
            'provider' => 'twilio',
            'messages_count' => count($messages),
            'payload_keys' => array_slice(array_keys($payload), 0, 10),
        ]);

        try {
            foreach ($messages as $message) {
                $inboundMessageService->handle($message);
            }
        } catch (Throwable $exception) {
            Log::error('Inbound twilio webhook processing failed.', [
                'provider' => 'twilio',
                'error' => $exception->getMessage(),
            ]);

            return response('Server error', 500)->header('Content-Type', 'text/plain');
        }

        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    public function dialog360(
        Request $request,
        Dialog360MessageParser $parser,
        InboundMessageService $inboundMessageService,
        ApiSettingsService $apiSettingsService
    ): JsonResponse {
        if (! $this->isProviderAccepted($apiSettingsService, '360dialog')) {
            $this->logProviderIgnored('360dialog');

            return $this->jsonIgnoredResponse();
        }

        $payload = $this->extractPayload($request);

        try {
            $messages = $parser->parse($payload);
        } catch (Throwable $exception) {
            Log::warning('Inbound 360dialog webhook parse failure.', [
                'provider' => '360dialog',
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['ok' => true]);
        }

        Log::info('Inbound webhook received.', [
            'provider' => '360dialog',
            'messages_count' => count($messages),
            'payload_keys' => array_slice(array_keys($payload), 0, 10),
        ]);

        try {
            foreach ($messages as $message) {
                $inboundMessageService->handle($message);
            }
        } catch (Throwable $exception) {
            Log::error('Inbound 360dialog webhook processing failed.', [
                'provider' => '360dialog',
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(Request $request): array
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            $payload = $request->all();
        }

        return is_array($payload) ? $payload : [];
    }

    private function hasValidBridgeToken(Request $request, ApiSettingsService $apiSettingsService): bool
    {
        $expected = trim((string) $apiSettingsService->getWhatsAppValue('bridge_secret', ''));

        if ($expected === '') {
            $expected = trim((string) config('whatsapp.inbound_bridge_secret', ''));
        }

        if ($expected === '') {
            return true;
        }

        $provided = trim((string) $request->header('X-Bridge-Token', ''));

        return $provided !== '' && hash_equals($expected, $provided);
    }

    private function isProviderAccepted(ApiSettingsService $apiSettingsService, string $incomingProvider): bool
    {
        $enforce = filter_var($apiSettingsService->getWhatsAppValue('enforce_selected_provider', false), FILTER_VALIDATE_BOOL);

        if (! $enforce) {
            return true;
        }

        $selectedProvider = strtolower((string) $apiSettingsService->getWhatsAppValue('provider', 'meta'));

        return $selectedProvider === strtolower($incomingProvider);
    }

    private function isBridgeProviderSelected(ApiSettingsService $apiSettingsService): bool
    {
        $selectedProvider = strtolower((string) $apiSettingsService->getWhatsAppValue('provider', 'meta'));

        return $selectedProvider === BridgeStatusService::PROVIDER;
    }

    private function jsonIgnoredResponse(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'status' => 'ignored',
            'reason' => 'provider_not_active',
        ]);
    }

    private function logProviderIgnored(string $provider): void
    {
        Log::info('Inbound webhook ignored because provider is not active.', [
            'provider' => $provider,
        ]);
    }
}
