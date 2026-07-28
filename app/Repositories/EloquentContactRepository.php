<?php

namespace App\Repositories;

use App\DTO\ContactAnalysisResult;
use App\Models\Contact;
use Illuminate\Support\Arr;
use Override;

final class EloquentContactRepository implements ContactRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Contact
    {
        return Contact::query()->createOrFirst(
            ['idempotency_key' => $data['idempotency_key']],
            Arr::except($data, 'idempotency_key'),
        );
    }

    public function findOrFail(int $id): Contact
    {
        return Contact::query()->findOrFail($id);
    }

    #[Override]
    public function updateAnalysis(
        Contact $contact,
        ContactAnalysisResult $analysis,
    ): void {
        $contact->update([
            'ai_category' => $analysis->category,
            'ai_summary' => $analysis->summary,
            'ai_suggested_reply' => $analysis->suggestedReply,
        ]);
    }
}
