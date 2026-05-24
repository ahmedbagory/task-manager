<?php

namespace App\Services\WhatsApp;

use App\Services\Settings\ApiSettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BridgeApiClient
{
    private const DEFAULT_PORT = 3001;

    private const TIMEOUT_SECONDS = 20;

    public function __construct(
        private readonly ApiSettingsService $apiSettingsService,
    ) {}

    /**
     * @return array{state:string, account_id:?string, account_name:?string, qr_available:bool, groups_count:int}|null
     */
    public function getStatus(): ?array
    {
        return $this->get('/status');
    }

    /**
     * @return array{state:string, qr:?string}|null
     */
    public function getQr(): ?array
    {
        return $this->get('/qr');
    }

    /**
     * @return array{success:bool, message:string}|null
     */
    public function logout(): ?array
    {
        return $this->post('/logout');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function sendMessage(array $payload): ?array
    {
        return $this->post('/send-message', $payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function get(string $path): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders($this->headers())
                ->get($this->baseUrl() . $path);

            if (! $response->successful()) {
                Log::warning('Bridge API GET request failed.', [
                    'path' => $path,
                    'status' => $response->status(),
                ]);
            }

            return $response->successful() ? $response->json() : null;
        } catch (Throwable $throwable) {
            Log::warning('Bridge API GET request threw an exception.', [
                'path' => $path,
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function post(string $path, array $data = []): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders($this->headers())
                ->post($this->baseUrl() . $path, $data);

            if (! $response->successful()) {
                Log::warning('Bridge API POST request failed.', [
                    'path' => $path,
                    'status' => $response->status(),
                    'payload_keys' => array_keys($data),
                ]);
            }

            $decoded = $response->json();

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $throwable) {
            Log::warning('Bridge API POST request threw an exception.', [
                'path' => $path,
                'payload_keys' => array_keys($data),
                'error' => $throwable->getMessage(),
            ]);

            return null;
        }
    }

    private function baseUrl(): string
    {
        $port = (int) config('whatsapp.bridge_api_port', self::DEFAULT_PORT);

        return 'http://127.0.0.1:' . $port;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $secret = (string) $this->apiSettingsService->getWhatsAppValue('bridge_secret', '');

        $headers = ['Accept' => 'application/json'];

        if ($secret !== '') {
            $headers['X-Bridge-Token'] = $secret;
        }

        return $headers;
    }
}
