<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\ScheduledNotificationLog;
use App\Models\ScheduledNotificationRule;
use App\Models\User;
use App\Support\Rbac;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduledNotificationRunner
{
    public function __construct(
        private readonly FcmNotificationService $fcmNotificationService,
    ) {}

    public function runDueRules(): int
    {
        $rules = ScheduledNotificationRule::query()
            ->where('is_active', true)
            ->where('next_run_at', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('end_at')->orWhere('end_at', '>', now());
            })
            ->orderBy('next_run_at')
            ->get();

        $count = 0;

        foreach ($rules as $rule) {
            $this->executeRule($rule);
            $count++;
        }

        return $count;
    }

    public function executeRule(ScheduledNotificationRule $rule): ScheduledNotificationLog
    {
        $users = $this->resolveTargetUsers($rule);
        $recipientsCount = 0;
        $error = null;

        try {
            foreach ($users as $user) {
                $tokenCount = $this->fcmNotificationService->countTokensForUser($user);

                if ($tokenCount === 0) {
                    continue;
                }

                $delivered = $this->fcmNotificationService->sendNotificationToUser(
                    recipient: $user,
                    title: $rule->title,
                    body: $rule->body,
                    data: [
                        'type' => 'scheduled_notification',
                        'rule_id' => (string) $rule->id,
                        'notification_type' => $rule->type,
                    ],
                );

                if ($delivered > 0) {
                    $recipientsCount++;
                }
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::error('[ScheduledNotification] Rule execution failed.', [
                'rule_id' => $rule->id,
                'error' => $error,
            ]);
        }

        $log = $rule->logs()->create([
            'title' => $rule->title,
            'recipients_count' => $recipientsCount,
            'status' => $error ? 'failed' : 'sent',
            'error' => $error,
            'sent_at' => now(),
        ]);

        DB::transaction(function () use ($rule): void {
            $nextRunAt = $rule->computeNextRunAt(now());

            $rule->forceFill([
                'last_run_at' => now(),
                'next_run_at' => $nextRunAt,
                'is_active' => $rule->frequency === 'once' ? false : $rule->is_active,
            ])->save();
        });

        Log::info('[ScheduledNotification] Rule executed.', [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'recipients' => $recipientsCount,
            'status' => $log->status,
        ]);

        return $log;
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveTargetUsers(ScheduledNotificationRule $rule): Collection
    {
        $payload = $rule->target_payload ?? [];

        return match ($rule->target_type) {
            'all' => $this->eligibleUsersQuery()->get(),
            'users' => $this->eligibleUsersQuery()
                ->whereIn('id', array_map('intval', (array) ($payload['user_ids'] ?? [])))
                ->get(),
            'departments' => $this->eligibleUsersQuery()
                ->whereIn('department_id', array_map('intval', (array) ($payload['department_ids'] ?? [])))
                ->get(),
            'roles' => User::query()
                ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', (array) ($payload['roles'] ?? [])))
                ->whereHas('deviceTokens')
                ->get(),
            default => collect(),
        };
    }

    private function eligibleUsersQuery(): Builder
    {
        return User::query()
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', [
                Rbac::EMPLOYEE,
                Rbac::SUPERVISOR,
            ]));
    }
}
