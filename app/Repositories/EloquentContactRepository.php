<?php

namespace App\Repositories;

use App\Models\Contact;

final class EloquentContactRepository implements ContactRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contact
    {
        return Contact::query()->create($data);
    }

    public function updateCategory(Contact $contact, string $category): void
    {
        $contact->update(['ai_category' => $category]);
    }
}
