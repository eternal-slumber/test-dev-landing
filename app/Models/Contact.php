<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $email
 * @property string $comment
 * @property string|null $ai_category
 * @property string|null $ai_summary
 * @property string|null $ai_suggested_reply
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'phone',
    'email',
    'comment',
    'ai_category',
    'ai_summary',
    'ai_suggested_reply',
    'idempotency_key',
])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /** @return HasMany<ContactStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ContactStep::class);
    }

    /** @return HasMany<OutboxMessage, $this> */
    public function outboxMessages(): HasMany
    {
        return $this->hasMany(OutboxMessage::class);
    }
}
