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
            Log::info('[FCM] Task assignment skipped because the assignee has no registered devices.', [
                'task_id' => $task->id,
                'task_number' => $task->task_number ?? '',
                'assignee_id' => $assignee->id,
            ]);
            return;
        }

        $title = 'New Task Assigned';
        $body = "Task \"{$task->title}\" has been assigned to you.";

        $deliveredCount = $this->sendTokens($tokens, $title, $body, [
            'type' => 'new_task',
            'task_id' => (string) $task->id,
            'task_title' => $task->title,
            'task_number' => $task->task_number ?? '',
            'route' => '/tasks/' . $task->id,
        ]);

        Log::info('[FCM] Task assignment notification processed.', [
            'task_id' => $task->id,
            'task_number' => $task->task_number ?? '',
            'assignee_id' => $assignee->id,
            'token_count' => count($tokens),
            'delivered_count' => $deliveredCount,
            'failed_count' => count($tokens) - $deliveredCount,
        ]);
    }

    /**
     * Send push notification for task status change.
     */
    public function notifyTaskStatusChanged(Task $task, User $recipient, string $action): void
    {
        $tokens = $this->tokensForUser($recipient);

        if (empty($tokens)) {
            Log::info('[FCM] Task status notification skipped because the recipient has no registered devices.', [
                'task_id' => $task->id,
                'task_number' => $task->task_number ?? '',
                'recipient_id' => $recipient->id,
                'action' => $action,
            ]);
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

        $deliveredCount = $this->sendTokens($tokens, $title, $body, [
            'type' => 'task_update',
            'task_id' => (string) $task->id,
            'task_number' => $task->task_number ?? '',
            'action' => $action,
            'route' => '/tasks/' . $task->id,
        ]);

        Log::info('[FCM] Task status notification processed.', [
            'task_id' => $task->id,
            'task_number' => $task->task_number ?? '',
            'recipient_id' => $recipient->id,
            'action' => $action,
            'token_count' => count($tokens),
            'delivered_count' => $deliveredCount,
            'failed_count' => count($tokens) - $deliveredCount,
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

        $targetedUsers = 0;
        $totalTokens = 0;
        $deliveredCount = 0;

        foreach ($dispatchers as $dispatcher) {
            $tokens = $this->tokensForUser($dispatcher);

            if (! empty($tokens)) {
                $targetedUsers++;
                $totalTokens += count($tokens);
                $deliveredCount += $this->sendTokens($tokens, $title, $body, $data);
            }
        }

        Log::info('[FCM] Dispatcher task update notification processed.', [
            'task_id' => $task->id,
            'task_number' => $task->task_number ?? '',
            'action' => $action,
            'actor_id' => $actor?->id,
            'targeted_users_with_devices_count' => $targetedUsers,
            'token_count' => $totalTokens,
            'delivered_count' => $deliveredCount,
            'failed_count' => $totalTokens - $deliveredCount,
        ]);
    }

    /**
     * Send push notification to all resolved task target users.
     *
     * @param  array<int, int>  $resolvedUserIds
     */
    public function notifyTaskTargetsAssigned(Task $task, array $resolvedUserIds): void
    {
        $uniqueUserIds = collect($resolvedUserIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($uniqueUserIds === []) {
            return;
        }

        $users = User::query()->whereIn('id', $uniqueUserIds)->get();

        $title = 'New Task Available';
        $body = "Task \"{$task->title}\" has been assigned to you.";
        $data = [
            'type' => 'new_task',
            'task_id' => (string) $task->id,
            'task_title' => $task->title,
            'task_number' => $task->task_number ?? '',
        ];

        $totalTokens = 0;
        $successCount = 0;
        $failureCount = 0;

        foreach ($users as $user) {
            $tokens = $this->tokensForUser($user);
            $totalTokens += count($tokens);
            $delivered = $this->sendTokens($tokens, $title, $body, $data);
            $successCount += $delivered;
            $failureCount += count($tokens) - $delivered;
        }

        Log::info('[FCM] Task targets notification completed.', [
            'task_id' => $task->id,
            'task_number' => $task->task_number ?? '',
            'resolved_user_ids' => $uniqueUserIds,
            'resolved_user_count' => count($uniqueUserIds),
            'token_count' => $totalTokens,
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ]);
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

        return $this->sendTokens($tokens, $title, $body, $data);
    }

    /**
     * Send a push notification via FCM HTTP v1 API.
     */
    private function sendPush(string $token, string $title, string $body, array $data = []): bool
    {
        $credentialsFile = $this->resolveCredentialsFile();

        if (! $credentialsFile || ! $this->projectId) {
            Log::warning('[FCM] Firebase credentials or project ID not configured.');

            return false;
        }

        try {
            $accessToken = $this->getAccessToken($credentialsFile);

            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->connectTimeout(10)
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
                if ($this->shouldForgetToken($response->status(), $error, $response->body())) {
                    DeviceToken::where('fcm_token', $token)->delete();
                    Log::info('[FCM] Removed stale token');
                }

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('[FCM] Exception sending push: '.$e->getMessage());

            return false;
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
    private function sendTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        $deliveredCount = 0;

        foreach ($tokens as $token) {
            if ($this->sendPush($token, $title, $body, $data)) {
                $deliveredCount++;
            }
        }

        return $deliveredCount;
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

    /**
     * @param  array<string, mixed>|null  $error
     */
    private function shouldForgetToken(int $status, ?array $error, string $body): bool
    {
        $details = data_get($error, 'error.details', []);
        $hasTokenFieldViolation = collect(is_array($details) ? $details : [])
            ->contains(function ($detail): bool {
                $violations = data_get($detail, 'fieldViolations', []);

                if (! is_array($violations)) {
                    return false;
                }

                foreach ($violations as $violation) {
                    if (data_get($violation, 'field') === 'message.token') {
                        return true;
                    }
                }

                return false;
            });

        return $status === 404
            || str_contains($body, 'UNREGISTERED')
            || str_contains($body, 'registration-token-not-registered')
            || ($status === 400
                && str_contains($body, 'INVALID_ARGUMENT')
                && ($hasTokenFieldViolation
                    || str_contains($body, 'message.token')
                    || str_contains($body, 'valid FCM registration token')));
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
