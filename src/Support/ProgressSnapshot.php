<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Support;

class ProgressSnapshot
{
    public int $progress = 0;

    public int $processed = 0;

    public int $pending = 0;

    public int $failed = 0;

    public int $total = 0;

    public bool $finished = false;

    /**
     * @param  array<int, array{key: string, label: string, status: string, message: ?string}>  $tasks
     */
    public function __construct(
        public string $batchId,
        public string $name,
        public string $status = 'queued',
        public array $tasks = [],
        public ?array $current = null,
        public ?string $error = null,
        public ?string $workBatchId = null,
        public bool $cancelled = false,
        public ?string $updatedAt = null,
    ) {
        $this->updatedAt ??= gmdate('c');
        $this->recalculate();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $storedAt = isset($data['updated_at']) && is_string($data['updated_at']) ? $data['updated_at'] : null;

        $snapshot = new self(
            batchId: (string) ($data['batch_id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            status: (string) ($data['status'] ?? 'queued'),
            tasks: array_values($data['tasks'] ?? []),
            current: is_array($data['current'] ?? null) ? $data['current'] : null,
            error: isset($data['error']) ? (is_string($data['error']) ? $data['error'] : null) : null,
            workBatchId: isset($data['work_batch_id']) ? (string) $data['work_batch_id'] : null,
            cancelled: (bool) ($data['cancelled'] ?? false),
            updatedAt: $storedAt,
        );

        if ($storedAt !== null) {
            $snapshot->updatedAt = $storedAt;
        }

        return $snapshot;
    }

    public function touch(): self
    {
        $formatted = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)));

        if ($formatted === false) {
            $this->updatedAt = gmdate('c');

            return $this;
        }

        $this->updatedAt = $formatted
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.uP');

        return $this;
    }

    public function recalculate(): self
    {
        $processed = 0;
        $failed = 0;
        $pending = 0;
        $processing = null;
        $lastSettled = null;

        foreach ($this->tasks as $task) {
            $status = $task['status'] ?? 'pending';

            if ($status === 'completed') {
                $processed++;
                $lastSettled = $task;
            } elseif ($status === 'failed') {
                $failed++;
                $lastSettled = $task;
            } elseif ($status === 'processing') {
                $pending++;
                $processing = $task;
            } elseif ($status !== 'cancelled') {
                $pending++;
            }
        }

        $this->processed = $processed;
        $this->failed = $failed;
        $this->pending = $pending;
        $this->total = count($this->tasks);
        $done = $processed + $failed;
        $inFlight = $processing !== null ? 0.45 : 0.0;
        $this->progress = $this->total === 0
            ? (($this->status === 'queued') ? 0 : 100)
            : (int) round((($done + $inFlight) / $this->total) * 100);

        $this->current = $processing ?? $lastSettled;

        if ($this->cancelled) {
            $this->status = 'cancelled';
            $this->finished = true;
            $this->progress = $this->total === 0 ? 0 : $this->progress;
        } elseif ($this->status === 'failed') {
            $this->finished = true;
        } elseif ($this->total === 0 && $this->status === 'finished') {
            $this->finished = true;
            $this->progress = 100;
        } elseif ($pending === 0 && $this->total > 0 && $this->status !== 'queued') {
            $this->finished = true;
            $this->status = $failed > 0 ? 'finished_with_failures' : 'finished';
            $this->progress = 100;
        } elseif ($this->status === 'queued') {
            $this->finished = false;
            $this->progress = 0;
        } else {
            $this->status = 'processing';
            $this->finished = false;
        }

        if (! $this->finished && $this->progress >= 100) {
            $this->progress = 99;
        }

        return $this->touch();
    }

    /**
     * @param  array<int, array{key: string, label: string, status: string, message: ?string}>  $tasks
     */
    public function start(array $tasks, ?string $workBatchId = null): self
    {
        $this->tasks = array_values($tasks);
        $this->workBatchId = $workBatchId ?? $this->workBatchId;
        $this->status = 'processing';
        $this->cancelled = false;
        $this->error = null;

        return $this->recalculate();
    }

    public function attachWorkBatch(string $workBatchId): self
    {
        $this->workBatchId = $workBatchId;

        return $this->touch();
    }

    public function markTask(string $key, string $status, ?string $message = null): self
    {
        foreach ($this->tasks as $index => $task) {
            if (($task['key'] ?? null) !== $key) {
                continue;
            }

            $this->tasks[$index]['status'] = $status;
            $this->tasks[$index]['message'] = $message;
            break;
        }

        if ($this->status === 'queued') {
            $this->status = 'processing';
        }

        return $this->recalculate();
    }

    public function markCancelled(): self
    {
        $this->cancelled = true;

        foreach ($this->tasks as $index => $task) {
            $status = $task['status'] ?? 'pending';

            if (in_array($status, ['pending', 'processing'], true)) {
                $this->tasks[$index]['status'] = 'cancelled';
            }
        }

        return $this->recalculate();
    }

    public function markFailed(string $message): self
    {
        $this->status = 'failed';
        $this->error = $message;
        $this->finished = true;

        return $this->touch();
    }

    public function finishEmpty(): self
    {
        $this->status = 'finished';
        $this->tasks = [];

        return $this->recalculate();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'batch_id' => $this->batchId,
            'name' => $this->name,
            'status' => $this->status,
            'finished' => $this->finished,
            'cancelled' => $this->cancelled,
            'progress' => $this->progress,
            'percentage' => $this->progress,
            'processed' => $this->processed,
            'pending' => $this->pending,
            'failed' => $this->failed,
            'total' => $this->total,
            'tasks' => $this->broadcastTasks(),
            'tasks_truncated' => count($this->tasks) > 80,
            'current' => $this->current,
            'error' => $this->error,
            'work_batch_id' => $this->workBatchId,
            'updated_at' => $this->updatedAt,
            'data' => $this->current,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function broadcastTasks(): array
    {
        if (count($this->tasks) <= 80) {
            return $this->tasks;
        }

        $recent = array_values(array_filter(
            $this->tasks,
            fn (array $task): bool => in_array($task['status'] ?? '', ['processing', 'completed', 'failed'], true)
        ));

        return array_slice($recent, -20);
    }
}
