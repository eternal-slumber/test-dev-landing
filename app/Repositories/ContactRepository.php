<?php

namespace App\Repositories;

use App\DTO\ContactAnalysisResult;
use App\Models\Contact;

interface ContactRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contact;

    public function findOrFail(int $id): Contact;

    public function updateAnalysis(
        Contact $contact,
        ContactAnalysisResult $analysis,
    ): void;
}
