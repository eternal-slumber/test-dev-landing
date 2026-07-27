<?php

namespace App\Repositories;

use App\Models\Contact;

interface ContactRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contact;

    public function updateCategory(Contact $contact, string $category): void;
}
