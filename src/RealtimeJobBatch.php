<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress;

use BadMethodCallException;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Interfaces\RealtimeJobBatchInterface;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Jobs\MasterJob;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Services\RealtimeJobService;

class RealtimeJobBatch
{
    protected static ?RealtimeJobBatchInterface $sharedRepository = null;

    protected ?string $queue = null;

    public function __construct(protected RealtimeJobBatchInterface $repository)
    {
    }

    public static function setRepository(RealtimeJobBatchInterface $repository): self
    {
        static::$sharedRepository = $repository;

        return new static($repository);
    }

    public function onQueue(?string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Queue a master job that chunks the repository into a realtime batch.
     *
     * The returned batch id is the id the progress bar subscribes to.
     */
    public function execute(string $name): Batch
    {
        $queue = $this->queue ?: (string) config('realtime-job-batch.queue', 'default');

        $progress = app(RealtimeJobService::class);

        $batch = Bus::batch([
            new MasterJob(
                repository: $this->repository,
                batchName: $name,
                queueName: $queue,
            ),
        ])
            ->name($name)
            ->onQueue($queue)
            ->dispatch();

        // A sync queue finishes the master job inside dispatch(). Don't rewind it.
        if ($progress->find($batch->id) === null) {
            $progress->markQueued($batch->id, $name);
        }

        return $batch;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function snapshot(string $batchId): ?array
    {
        return app(RealtimeJobService::class)->find($batchId);
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        if ($method === 'execute' && static::$sharedRepository instanceof RealtimeJobBatchInterface) {
            return (new static(static::$sharedRepository))->execute(...$arguments);
        }

        throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $method));
    }
}
