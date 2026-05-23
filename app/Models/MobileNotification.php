<?php

namespace App\Models;

use App\Enums\MobileNotificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MobileNotification extends Model
{
    protected $fillable = [
        'title',
        'body',
        'status',
        'created_by',
        'queued_at',
        'sent_at',
        'failed_at',
        'failure_message',
        'targeted_users_count',
        'targeted_users_with_devices_count',
        'targeted_devices_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => MobileNotificationStatus::class,
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(MobileNotificationTarget::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MobileNotificationRecipient::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MobileNotificationAttachment::class);
    }
}
