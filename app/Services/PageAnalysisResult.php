<?php

namespace App\Services;

final readonly class PageAnalysisResult
{
    /**
     * @param  array<int, string>  $reasons
     * @param  array<string, mixed>  $signals
     */
    public function __construct(
        public string $pageStatus,
        public string $summary,
        public int $confidence,
        public array $reasons,
        public array $signals = [],
    ) {}
}
