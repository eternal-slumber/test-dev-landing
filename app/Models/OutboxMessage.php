<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contact_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property int $attempts
 * @property Carbon $available_at
 * @property Carbon|null $locked_at
 * @property string|null $locked_by
 * @property Carbon|null $published_at
 * @property Carbon|null $failed_at
 * @property string|null $last_error
 */
#[Fillable([
    'contact_id',
    'type',
    'payload',
    'attempts',
    'available_at',
    'locked_at',
    'locked_by',
    'published_at',
    'failed_at',
    'last_error',
])]
final class OutboxMessage extends Model
{
    public const string TYPE_PROCESS_CONTACT = 'contact.process';

    public const string TYPE_OWNER_EMAIL = 'contact.owner-email';

    public const string TYPE_CONFIRMATION_EMAIL = 'contact.confirmation-email';

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'locked_at' => 'datetime',
            'published_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
