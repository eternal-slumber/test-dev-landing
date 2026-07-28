<?php

namespace App\DTO;

final readonly class ContactAnalysisResult
{
    public function __construct(
        public ?string $category,
        public ?string $summary,
        public ?string $suggestedReply,
    ) {}

    public function isAvailable(): bool
    {
        return $this->category !== null
            && $this->summary !== null
            && $this->suggestedReply !== null;
    }

    public static function unavailable(): self
    {
        return new self(null, null, null);
    }
}
