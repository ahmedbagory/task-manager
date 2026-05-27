<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledNotificationLog extends Model
{
    protected $fillable = [
        'rule_id',
        'title',
        'recipients_count',
        'status',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'recipients_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ScheduledNotificationRule::class, 'rule_id');
    }
}
