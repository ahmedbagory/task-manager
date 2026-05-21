<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\Task;
use App\Models\User;
use Google\Auth\Credentials\ServiceAccountCredentials;
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
        $tokens = DeviceToken::where('user_id', $assignee->id)->pluck('fcm_token')->toArray();

        if (empty($tokens)) {
            return;
        }

        $title = 'New Task Assigned';
        $body = "Task \"{$task->title}\" has been assigned to you.";

        foreach ($tokens as $token) {
            $this->sendPush($token, $title, $body, [
                'type' => 'new_task',
                'task_id' => (string) $task->id,
                'task_title' => $task->title,
            ]);
        }
    }

    /**
     * Send push notification for task status change.
     */
    public function notifyTaskStatusChanged(Task $task, User $recipient, string $action): void
    {
        $tokens = DeviceToken::where('user_id', $recipient->id)->pluck('fcm_token')->toArray();

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
        $title = 'Task ' . ucfirst($label);
        $body = "Task \"{$task->title}\" has been {$label}.";

        foreach ($tokens as $token) {
            $this->sendPush($token, $title, $body, [
                'type' => 'task_update',
                'task_id' => (string) $task->id,
                'action' => $action,
            ]);
        }
    }

    /**
     * Send a push notification via FCM HTTP v1 API.
     */
    private function sendPush(string $token, string $title, string $body, array $data = []): void
    {
        if (! $this->credentialsPath || ! $this->projectId) {
            Log::warning('[FCM] Firebase credentials or project ID not configured.');

            return;
        }

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $data,
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
                    'token' => substr($token, 0, 20) . '...',
                    'error' => $error,
                ]);

                // Remove invalid tokens
                if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
                    DeviceToken::where('fcm_token', $token)->delete();
                    Log::info('[FCM] Removed stale token');
                }
            }
        } catch (\Throwable $e) {
            Log::error('[FCM] Exception sending push: ' . $e->getMessage());
        }
    }

    /**
     * Get OAuth2 access token from service account credentials.
     */
    private function getAccessToken(): string
    {
        $credentialsFile = base_path($this->credentialsPath);

        $credentials = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/firebase.messaging',
            json_decode(file_get_contents($credentialsFile), true),
        );

        $token = $credentials->fetchAuthToken();

        return $token['access_token'];
    }
}
