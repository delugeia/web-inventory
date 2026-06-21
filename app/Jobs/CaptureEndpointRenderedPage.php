<?php

namespace App\Jobs;

use App\Models\Endpoint;
use App\Services\RenderedPageCaptureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CaptureEndpointRenderedPage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $endpointId,
    ) {}

    public function handle(RenderedPageCaptureService $captureService): void
    {
        $endpoint = Endpoint::query()->find($this->endpointId);

        if ($endpoint === null) {
            return;
        }

        $captureService->capture($endpoint);
    }
}
