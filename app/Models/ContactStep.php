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
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property string|null $locked_by
 * @property Carbon|null $locked_until
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
#[Fillable([
    'contact_id',
    'type',
    'status',
    'attempts',
    'last_error',
    'locked_by',
    'locked_until',
    'started_at',
    'finished_at',
])]
final class ContactStep extends Model
{
    public const string TYPE_CLASSIFICATION = 'classification';

    public const string TYPE_OWNER_EMAIL = 'owner_email';

    public const string TYPE_CONFIRMATION_EMAIL = 'confirmation_email';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_SUCCEEDED = 'succeeded';

    public const string STATUS_FALLBACK = 'fallback';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_FAILED = 'failed';

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'locked_until' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
