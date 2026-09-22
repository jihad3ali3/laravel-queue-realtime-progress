<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Interfaces\RealtimeJobBatchInterface;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Services\RealtimeJobService;

class BatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        private mixed $data,
        private RealtimeJobBatchInterface $repository,
        private string $trackingId,
        private int $index,
        private string $taskKey,
    ) {
        $this->timeout = (int) config('realtime-job-batch.timeout', 120);
        $this->tries = (int) config('realtime-job-batch.tries', 1);
    }

    public function handle(RealtimeJobService $progress): void
    {
        if ($this->batch()?->cancelled()) {
            $progress->markTask($this->trackingId, $this->taskKey, 'cancelled');

            return;
        }

        $progress->markTask($this->trackingId, $this->taskKey, 'processing');

        $this->repository->save($this->data);

        if ($this->batch()?->cancelled()) {
            $progress->markTask($this->trackingId, $this->taskKey, 'cancelled');

            return;
        }

        $progress->markTask($this->trackingId, $this->taskKey, 'completed');
    }

    public function failed(Throwable $exception): void
    {
        app(RealtimeJobService::class)->markTask(
            $this->trackingId,
            $this->taskKey,
            'failed',
            $exception->getMessage()
        );
    }

    public static function make(
        mixed $data,
        RealtimeJobBatchInterface $repository,
        string $trackingId,
        int $index,
        string $taskKey,
    ): self {
        return new self(
            data: $data,
            repository: $repository,
            trackingId: $trackingId,
            index: $index,
            taskKey: $taskKey,
        );
    }
}
