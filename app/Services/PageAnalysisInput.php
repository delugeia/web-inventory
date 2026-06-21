<?php

namespace App\Services;

final readonly class PageAnalysisInput
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public bool $shouldAnalyze,
        public ?string $skipReason,
        public array $payload,
        public string $inputText,
        public string $inputHash,
        public int $inputCharacterCount,
    ) {}
}
