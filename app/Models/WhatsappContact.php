<?php

namespace App\Models;

use Database\Factories\WhatsappContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['phone', 'name', 'user_id', 'department_id', 'default_location', 'last_message_at'])]
class WhatsappContact extends Model
{
    /** @use HasFactory<WhatsappContactFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'contact_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(WhatsappMessage::class, 'contact_id')->latestOfMany('id');
    }

    public function displayName(): string
    {
        return trim((string) $this->name) !== '' ? (string) $this->name : 'جهة اتصال غير معروفة';
    }

    public function statusKey(): string
    {
        if ($this->user_id) {
            return 'linked';
        }

        if ($this->last_message_at) {
            return 'active';
        }

        return 'new';
    }

    public function statusLabel(): string
    {
        return match ($this->statusKey()) {
            'linked' => 'مرتبط بموظف',
            'active' => 'نشط',
            default => 'جديد',
        };
    }

    public function statusColor(): string
    {
        return match ($this->statusKey()) {
            'linked' => 'success',
            'active' => 'warning',
            default => 'gray',
        };
    }

    public function latestMessagePreview(): string
    {
        $message = $this->relationLoaded('latestMessage')
            ? $this->latestMessage
            : $this->latestMessage()->first();

        if (! $message) {
            return 'لا توجد رسائل بعد';
        }

        $body = trim((string) $message->body);

        if ($body !== '') {
            return str($body)->limit(120)->toString();
        }

        if ($message->media_rejected) {
            return 'وسيط مرفوض';
        }

        return match ($message->media_type) {
            'image' => 'صورة واتساب',
            'video' => 'فيديو واتساب',
            'audio' => 'رسالة صوتية',
            'document' => 'ملف واتساب',
            'sticker' => 'ملصق واتساب',
            default => 'وسيط واتساب',
        };
    }
}
