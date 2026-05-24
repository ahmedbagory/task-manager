<?php

namespace App\Models;

use App\Enums\WhatsappMessageDirection;
use Database\Factories\WhatsappMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'whatsapp_message_id',
    'contact_id',
    'task_id',
    'sent_by_user_id',
    'direction',
    'from_phone',
    'to_phone',
    'group_id',
    'group_name',
    'business_phone_number_id',
    'message_type',
    'body',
    'media_url',
    'media_type',
    'media_mime',
    'media_path',
    'media_name',
    'media_size',
    'media_rejected',
    'media_reject_reason',
    'status',
    'external_message_id',
    'failed_reason',
    'raw_payload',
    'received_at',
    'sent_at',
])]
class WhatsappMessage extends Model
{
    /** @use HasFactory<WhatsappMessageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'direction' => WhatsappMessageDirection::class,
            'media_size' => 'integer',
            'media_rejected' => 'boolean',
            'raw_payload' => 'array',
            'received_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsappContact::class, 'contact_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function hasMedia(): bool
    {
        return filled($this->media_url) || filled($this->media_path) || filled($this->media_type);
    }

    public function isIncoming(): bool
    {
        return $this->direction === WhatsappMessageDirection::INBOUND;
    }

    public function isOutgoing(): bool
    {
        return $this->direction === WhatsappMessageDirection::OUTBOUND;
    }
}
