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
    'direction',
    'from_phone',
    'to_phone',
    'group_id',
    'group_name',
    'business_phone_number_id',
    'message_type',
    'body',
    'media_url',
    'status',
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
}
