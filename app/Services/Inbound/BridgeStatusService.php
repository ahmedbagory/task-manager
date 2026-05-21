<?php

namespace App\Services\Inbound;

use App\Data\InboundMessageData;
use App\Models\BridgeStatus;
use Illuminate\Support\Arr;

class BridgeStatusService
{
    public const PROVIDER = 'whatsapp_web_bridge';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateHeartbeat(array $payload): BridgeStatus
    {
        $provider = (string) Arr::get($payload, 'provider', self::PROVIDER);

        return $this->upsertStatus($provider, [
            'status' => (string) Arr::get($payload, 'status', 'connected'),
            'account_id' => $this->nullableString(Arr::get($payload, 'account_id')),
            'account_name' => $this->nullableString(Arr::get($payload, 'account_name')),
            'group_id' => $this->nullableString(Arr::get($payload, 'group_id')),
            'group_name' => $this->nullableString(Arr::get($payload, 'group_name')),
            'last_heartbeat_at' => now(),
            'last_error' => null,
            'meta' => is_array(Arr::get($payload, 'meta')) ? Arr::get($payload, 'meta') : null,
        ]);
    }

    public function updateFromInboundMessage(InboundMessageData $data): BridgeStatus
    {
        return $this->upsertStatus(self::PROVIDER, [
            'status' => 'connected',
            'group_id' => $data->groupId,
            'group_name' => $data->groupName,
            'last_message_at' => now(),
            'last_heartbeat_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPanelState(): array
    {
        $record = BridgeStatus::query()
            ->where('provider', self::PROVIDER)
            ->first();

        if (! $record) {
            return [
                'status' => 'unknown',
                'color' => 'gray',
                'last_heartbeat_at' => null,
                'last_message_at' => null,
                'account_id' => null,
                'account_name' => null,
                'group_id' => null,
                'group_name' => null,
                'last_error' => null,
            ];
        }

        $isStale = $record->last_heartbeat_at?->lt(now()->subSeconds(90)) ?? true;
        $resolvedStatus = $isStale ? 'stale' : 'connected';

        return [
            'status' => $resolvedStatus,
            'color' => match ($resolvedStatus) {
                'connected' => 'success',
                'stale' => 'warning',
                'disconnected' => 'danger',
                default => 'gray',
            },
            'last_heartbeat_at' => $record->last_heartbeat_at,
            'last_message_at' => $record->last_message_at,
            'account_id' => $record->account_id,
            'account_name' => $record->account_name,
            'group_id' => $record->group_id,
            'group_name' => $record->group_name,
            'last_error' => $isStale ? $record->last_error : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertStatus(string $provider, array $attributes): BridgeStatus
    {
        $record = BridgeStatus::query()->firstOrNew(['provider' => $provider]);
        $record->fill($attributes);
        $record->save();

        return $record->refresh();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
