<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $email
 * @property string $comment
 * @property string|null $ai_category
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'phone', 'email', 'comment', 'ai_category'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;
}
