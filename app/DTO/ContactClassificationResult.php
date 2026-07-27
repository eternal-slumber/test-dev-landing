<?php

namespace App\DTO;

final readonly class ContactClassificationResult
{
    public function __construct(
        public ?string $category,
    ) {}
}
