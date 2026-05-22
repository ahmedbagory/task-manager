<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\Task;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmNotificationService
{
    private ?string $projectId;

    private ?string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = config('services.firebase.credentials');
        $this->projectId = config('services.firebase.project_id');
    }

    /**
     * Send push notification when a new task is assigned.
     */
    public function notifyNewTaskAssigned(Task $task, User $assignee): void
    {
        $tokens = $this->tokensForUser($assignee);

        if (empty($tokens)) {
            return;
        }

        $title = 'New Task Assigned';
        $body = "Task \"{$task->title}\" has been assigned to you.";

        $this->sendTokens($tokens, $title, $body, [
            'type' => 'new_task',
            'task_id' => (string) $task->id,
            'task_title' => $task->title,
        ]);
    }

    /**
     * Send push notification for task status change.
     */
    public function notifyTaskStatusChanged(Task $task, User $recipient, string $action): void
    {
        $tokens = $this->tokensForUser($recipient);

        if (empty($tokens)) {
            return;
        }

        $actionLabels = [
            'accepted' => 'accepted',
            'started' => 'started',
            'completed' => 'completed',
            'rejected' => 'rejected',
        ];

        $label = $actionLabels[$action] ?? $action;
        $title = 'Task '.ucfirst($label);
        $body = "Task \"{$task->title}\" has been {$label}.";

        $this->sendTokens($tokens, $title, $body, [
            'type' => 'task_update',
            'task_id' => (string) $task->id,
            'action' => $action,
        ]);
    }

    /**
     * Notify all dispatchers/admins about a task event.
     */
    public function notifyDispatchersTaskUpdate(Task $task, string $action, ?User $actor = null): void
    {
        $actionLabels = [
            'accepted' => __('accepted'),
            'started' => __('started'),
            'completed' => __('completed'),
            'rejected' => __('rejected'),
        ];

        $label = $actionLabels[$action] ?? $action;
        $actorName = $actor?->name ?? __('Unknown');

        $title = __('Task')." {$task->task_number} {$label}";
        $body = "{$actorName} {$label} \"{$task->title}\"";

        $data = [
            'type' => 'task_dispatcher_update',
            'task_id' => (string) $task->id,
            'task_number' => $task->task_number ?? '',
            'action' => $action,
        ];

        $dispatchers = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', [Rbac::SUPER_ADMIN, Rbac::ADMIN, Rbac::DISPATCHER]))
            ->where('id', '!=', $actor?->id)
            ->get();

        foreach ($dispatchers as $dispatcher) {
            $tokens = $this->tokensForUser($dispatcher);

            if (! empty($tokens)) {
                $this->sendTokens($tokens, $title, $body, $data);
            }
        }
    }

    /**
     * Send a protected test notification to the selected user's device(s).
     */
    public function sendTestNotification(
        User $recipient,
        string $title,
        string $body,
        array $data = [],
        ?string $deviceId = null,
    ): int {
        return $this->sendNotificationToUser(
            recipient: $recipient,
            title: $title,
            body: $body,
            data: $data,
            deviceId: $deviceId,
        );
    }

    public function countTokensForUser(User $recipient, ?string $deviceId = null): int
    {
        return count($this->tokensForUser($recipient, $deviceId));
    }

    public function sendNotificationToUser(
        User $recipient,
        string $title,
        string $body,
        array $data = [],
        ?string $deviceId = null,
    ): int {
        $tokens = $this->tokensForUser($recipient, $deviceId);

        if (empty($tokens)) {
            return 0;
        }

        $this->sendTokens($tokens, $title, $body, $data);

        return count($tokens);
    }

    /**
     * Send a push notification via FCM HTTP v1 API.
     */
    private function sendPush(string $token, string $title, string $body, array $data = []): void
    {
        $credentialsFile = $this->resolveCredentialsFile();

        if (! $credentialsFile || ! $this->projectId) {
            Log::warning('[FCM] Firebase credentials or project ID not configured.');

            return;
        }

        try {
            $accessToken = $this->getAccessToken($credentialsFile);

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $this->stringifyData([
                            'title' => $title,
                            'body' => $body,
                            ...$data,
                        ]),
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'task_notifications',
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                $error = $response->json();
                Log::error('[FCM] Failed to send push', [
                    'token' => substr($token, 0, 20).'...',
                    'error' => $error,
                ]);

                // Remove invalid tokens
                if ($this->shouldForgetToken($response->status(), $response->body())) {
                    DeviceToken::where('fcm_token', $token)->delete();
                    Log::info('[FCM] Removed stale token');
                }
            }
        } catch (\Throwable $e) {
            Log::error('[FCM] Exception sending push: '.$e->getMessage());
        }
    }

    /**
     * Get OAuth2 access token from service account credentials.
     */
    private function getAccessToken(string $credentialsFile): string
    {
        $credentials = json_decode(file_get_contents($credentialsFile), true, 512, JSON_THROW_ON_ERROR);

        $clientEmail = $credentials['client_email'] ?? null;
        $privateKey = $credentials['private_key'] ?? null;

        if (! is_string($clientEmail) || ! is_string($privateKey) || $clientEmail === '' || $privateKey === '') {
            throw new \RuntimeException('Firebase service account credentials are incomplete.');
        }

        $issuedAt = now()->timestamp;
        $assertion = $this->buildServiceAccountAssertion(
            clientEmail: $clientEmail,
            privateKey: $privateKey,
            issuedAt: $issuedAt,
            expiresAt: $issuedAt + 3600,
        );

        $response = Http::asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ])
            ->throw()
            ->json();

        $accessToken = $response['access_token'] ?? null;

        if (! is_string($accessToken) || trim($accessToken) === '') {
            throw new \RuntimeException('Firebase OAuth token response did not include an access token.');
        }

        return $accessToken;
    }

    /**
     * @return array<int, string>
     */
    private function tokensForUser(User $user, ?string $deviceId = null): array
    {
        $query = DeviceToken::query()->where('user_id', $user->id);

        if ($deviceId !== null) {
            $query->where('device_id', $deviceId);
        }

        return $query
            ->pluck('fcm_token')
            ->filter(fn ($token): bool => filled($token))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function sendTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        foreach ($tokens as $token) {
            $this->sendPush($token, $title, $body, $data);
        }
    }

    private function resolveCredentialsFile(): ?string
    {
        if (! filled($this->credentialsPath)) {
            return null;
        }

        if (is_file((string) $this->credentialsPath)) {
            return (string) $this->credentialsPath;
        }

        $relativePath = base_path((string) $this->credentialsPath);

        return is_file($relativePath) ? $relativePath : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        $payload = [];

        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value instanceof \Stringable) {
                $stringValue = trim((string) $value);

                if ($stringValue !== '') {
                    $payload[(string) $key] = $stringValue;
                }
            }
        }

        return $payload;
    }

    private function shouldForgetToken(int $status, string $body): bool
    {
        return $status === 404
            || str_contains($body, 'UNREGISTERED')
            || str_contains($body, 'registration-token-not-registered');
    }

    private function buildServiceAccountAssertion(
        string $clientEmail,
        string $privateKey,
        int $issuedAt,
        int $expiresAt,
    ): string {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ], JSON_THROW_ON_ERROR));

        $unsignedToken = "{$header}.{$claims}";
        $signature = '';

        if (! openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Unable to sign Firebase service account assertion.');
        }

        return "{$unsignedToken}.{$this->base64UrlEncode($signature)}";
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
