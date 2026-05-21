<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider',
    'status',
    'account_id',
    'account_name',
    'group_id',
    'group_name',
    'last_heartbeat_at',
    'last_message_at',
    'last_error',
    'meta',
])]
class BridgeStatus extends Model
{
    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'last_heartbeat_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }
}
