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
}
