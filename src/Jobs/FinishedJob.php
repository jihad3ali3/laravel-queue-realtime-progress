<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Events\FinishedJobEvent;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Services\RealtimeJobService;

class FinishedJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?string $trackingId = null)
    {
    }

    public function handle(RealtimeJobService $progress): void
    {
        if ($this->trackingId) {
            $progress->markFinished($this->trackingId);

            return;
        }

        event(new FinishedJobEvent(finished: true));
    }
}
