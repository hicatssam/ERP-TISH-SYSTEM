<?php

namespace App\Jobs;

use App\Services\Restaurant\MenuCutoutService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Uses the dedicated database queue, so AI work cannot delay product uploads.
 */
class GenerateMenuCutout implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 390;

    public function __construct(public string $source, public bool $force = false)
    {
    }

    public function backoff(): array
    {
        return [90, 300];
    }

    public function handle(MenuCutoutService $cutouts): void
    {
        $cutouts->generate($this->source, $this->force);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Tish menu image cutout failed; original image is preserved.', [
            'source' => $this->source,
            'error' => $exception?->getMessage(),
        ]);
    }
}
