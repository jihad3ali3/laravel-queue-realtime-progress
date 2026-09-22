<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Throwable;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Events\FinishedJobEvent;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Events\JobProgressEvent;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Events\StatusJobEvent;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Support\ProgressSnapshot;

class RealtimeJobService
{
    private const ACTIVE_KEY = 'realtime-job-batch:active';

    public function markQueued(string $batchId, string $name): ProgressSnapshot
    {
        $snapshot = new ProgressSnapshot($batchId, $name, 'queued');
        $this->store($snapshot);
        $this->broadcast($snapshot);

        return $snapshot;
    }

    /**
     * @param  array<int, array{key: string, label: string, status: string, message: ?string}>  $tasks
     */
    public function markStarted(string $batchId, string $name, array $tasks, ?string $workBatchId = null): ProgressSnapshot
    {
        $snapshot = $this->mutate($batchId, function (ProgressSnapshot $snapshot) use ($name, $tasks, $workBatchId): void {
            $snapshot->name = $name;
            $snapshot->start($tasks, $workBatchId);
        }, $name);

        $this->broadcast($snapshot);

        return $snapshot;
    }

    public function attachWorkBatch(string $batchId, string $workBatchId): void
    {
        $snapshot = $this->findSnapshot($batchId);

        if ($snapshot === null) {
            return;
        }

        $this->mutate($batchId, function (ProgressSnapshot $snapshot) use ($workBatchId): void {
            $snapshot->attachWorkBatch($workBatchId);
        });
    }

    public function markTask(string $batchId, string $key, string $status, ?string $message = null): ?ProgressSnapshot
    {
        $snapshot = $this->findSnapshot($batchId);

        if ($snapshot === null) {
            return null;
        }

        $snapshot = $this->mutate($batchId, function (ProgressSnapshot $snapshot) use ($key, $status, $message): void {
            if ($snapshot->cancelled && $status !== 'cancelled') {
                return;
            }

            $snapshot->markTask($key, $status, $message);
        });

        if ($snapshot instanceof ProgressSnapshot) {
            $this->broadcast($snapshot);
        }

        return $snapshot;
    }

    public function markFinished(string $batchId): ?ProgressSnapshot
    {
        $snapshot = $this->findSnapshot($batchId);

        if ($snapshot === null || $snapshot->cancelled) {
            return $snapshot;
        }

        $snapshot = $this->mutate($batchId, function (ProgressSnapshot $snapshot): void {
            if ($snapshot->cancelled) {
                return;
            }

            if ($snapshot->total === 0) {
                $snapshot->finishEmpty();

                return;
            }

            $snapshot->status = $snapshot->failed > 0 ? 'finished_with_failures' : 'finished';
            $snapshot->recalculate();
        });

        if ($snapshot instanceof ProgressSnapshot) {
            $this->broadcast($snapshot);
        }

        return $snapshot;
    }

    public function markFailed(string $batchId, string $message): ?ProgressSnapshot
    {
        $snapshot = $this->findSnapshot($batchId);

        if ($snapshot === null) {
            return null;
        }

        $snapshot = $this->mutate($batchId, function (ProgressSnapshot $snapshot) use ($message): void {
            $snapshot->markFailed($message);
            $snapshot->recalculate();
        });

        if ($snapshot instanceof ProgressSnapshot) {
            $this->broadcast($snapshot);
        }

        return $snapshot;
    }

    public function markCancelled(string $batchId): ?ProgressSnapshot
    {
        $snapshot = $this->findSnapshot($batchId);

        if ($snapshot === null) {
            return null;
        }

        $snapshot = $this->mutate($batchId, function (ProgressSnapshot $snapshot): void {
            $snapshot->markCancelled();
        });

        if ($snapshot instanceof ProgressSnapshot) {
            $this->broadcast($snapshot);
        }

        return $snapshot;
    }

    public function cancel(string $batchId): ?ProgressSnapshot
    {
        $snapshot = $this->findSnapshot($batchId);

        if ($snapshot === null || $snapshot->finished) {
            return $snapshot;
        }

        if ($snapshot->workBatchId) {
            Bus::findBatch($snapshot->workBatchId)?->cancel();
        }

        Bus::findBatch($batchId)?->cancel();

        return $this->markCancelled($batchId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $batchId): ?array
    {
        return $this->findSnapshot($batchId)?->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function active(): array
    {
        $ids = Cache::get(self::ACTIVE_KEY, []);
        $snapshots = [];

        foreach ($ids as $id) {
            $snapshot = $this->find((string) $id);

            if ($snapshot !== null) {
                $snapshots[] = $snapshot;
            }
        }

        return $snapshots;
    }

    private function mutate(string $batchId, callable $callback, ?string $fallbackName = null): ?ProgressSnapshot
    {
        $run = function () use ($batchId, $callback, $fallbackName): ?ProgressSnapshot {
            $snapshot = $this->findSnapshot($batchId) ?? new ProgressSnapshot(
                $batchId,
                $fallbackName ?? 'Queue batch',
                'queued',
            );

            $callback($snapshot);
            $this->store($snapshot);

            return $snapshot;
        };

        try {
            return Cache::lock($this->lockKey($batchId), 8)->block(5, $run);
        } catch (LockTimeoutException|Throwable) {
            return $run();
        }
    }

    private function findSnapshot(string $batchId): ?ProgressSnapshot
    {
        $payload = Cache::get($this->key($batchId));

        if (! is_array($payload)) {
            return null;
        }

        return ProgressSnapshot::fromArray($payload);
    }

    private function store(ProgressSnapshot $snapshot): void
    {
        Cache::put($this->key($snapshot->batchId), $snapshot->toArray(), now()->addDay());

        $ids = array_values(array_filter(
            Cache::get(self::ACTIVE_KEY, []),
            fn ($id): bool => $id !== $snapshot->batchId
        ));
        array_unshift($ids, $snapshot->batchId);
        Cache::put(self::ACTIVE_KEY, array_slice($ids, 0, 20), now()->addDay());
    }

    private function broadcast(ProgressSnapshot $snapshot): void
    {
        $payload = $snapshot->toArray();

        $this->safely(fn () => event(new JobProgressEvent($payload)));

        if ($snapshot->finished) {
            $finishedEvent = (string) config('realtime-job-batch.events.finished', 'finished');
            $this->safely(fn () => event(new JobProgressEvent($payload, $finishedEvent)));
        }

        if (! config('realtime-job-batch.legacy_channels')) {
            return;
        }

        $this->safely(fn () => event(StatusJobEvent::fromSnapshot($payload)));

        if ($snapshot->finished) {
            $this->safely(fn () => event(new FinishedJobEvent(finished: true)));
        }
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function key(string $batchId): string
    {
        return 'realtime-job-batch:'.$batchId;
    }

    private function lockKey(string $batchId): string
    {
        return 'realtime-job-batch-lock:'.$batchId;
    }
}
