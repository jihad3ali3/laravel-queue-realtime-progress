<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Jobs;

use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Throwable;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Interfaces\RealtimeJobBatchInterface;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Services\RealtimeJobService;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Support\TaskMeta;

class MasterJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public RealtimeJobBatchInterface $repository,
        public string $batchName,
        public ?string $queueName = null,
    ) {
        $this->timeout = (int) config('realtime-job-batch.timeout', 120);
        $this->tries = (int) config('realtime-job-batch.tries', 1);
    }

    public function handle(RealtimeJobService $progress): void
    {
        $trackingId = $this->batch()?->id ?? $this->job?->uuid();

        if (! is_string($trackingId) || $trackingId === '') {
            $trackingId = 'untracked';
        }

        $items = $this->repository->get_all()->values();
        $tasks = TaskMeta::describeAll($this->repository, $items);
        $queue = $this->queueName ?: (string) config('realtime-job-batch.queue', 'default');

        if ($tasks === []) {
            $progress->markStarted($trackingId, $this->batchName, []);
            $progress->markFinished($trackingId);

            return;
        }

        $progress->markStarted($trackingId, $this->batchName, $tasks);

        $jobs = [];

        foreach ($items as $index => $item) {
            $job = BatchJob::make(
                $item,
                $this->repository,
                $trackingId,
                (int) $index,
                (string) $tasks[$index]['key'],
            );
            $job->onQueue($queue);
            $jobs[] = $job;
        }

        $allowFailures = (bool) config('realtime-job-batch.allow_failures', true);

        $pending = Bus::batch($jobs)
            ->name($this->batchName)
            ->onQueue($queue)
            ->finally(function (Batch $batch) use ($trackingId): void {
                $service = app(RealtimeJobService::class);

                if ($batch->cancelled()) {
                    $service->cancel($trackingId);

                    return;
                }

                $service->markFinished($trackingId);
            });

        if ($allowFailures) {
            $pending->allowFailures();
        }

        $workBatch = $pending->dispatch();
        $progress->attachWorkBatch($trackingId, $workBatch->id);
    }

    public function failed(Throwable $exception): void
    {
        $trackingId = $this->batch()?->id;

        if (! is_string($trackingId) || $trackingId === '') {
            return;
        }

        app(RealtimeJobService::class)->markFailed($trackingId, $exception->getMessage());
    }
}
