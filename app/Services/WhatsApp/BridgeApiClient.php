<?php

namespace App\Services\WhatsApp;

use App\Services\Settings\ApiSettingsService;
use Illuminate\Support\Facades\Http;
use Throwable;

class BridgeApiClient
{
    private const DEFAULT_PORT = 3001;

    private const TIMEOUT_SECONDS = 5;

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
     * @return array<string, mixed>|null
     */
    private function get(string $path): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders($this->headers())
                ->get($this->baseUrl() . $path);

            return $response->successful() ? $response->json() : null;
        } catch (Throwable) {
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

            return $response->successful() ? $response->json() : null;
        } catch (Throwable) {
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
