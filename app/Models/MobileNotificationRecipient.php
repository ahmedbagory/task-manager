<?php

namespace App\Models;

use App\Enums\MobileNotificationRecipientStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileNotificationRecipient extends Model
{
    protected $fillable = [
        'mobile_notification_id',
        'user_id',
        'status',
        'device_count',
        'delivered_devices_count',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MobileNotificationRecipientStatus::class,
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function mobileNotification(): BelongsTo
    {
        return $this->belongsTo(MobileNotification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
