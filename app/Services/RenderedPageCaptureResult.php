<?php

namespace App\Services;

final readonly class RenderedPageCaptureResult
{
    public function __construct(
        public bool $captured,
        public string $status,
        public ?string $error = null,
    ) {}
}
