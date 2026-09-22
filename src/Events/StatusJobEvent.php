<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Events\Concerns\BroadcastsViaPackageConnection;

class StatusJobEvent implements ShouldBroadcastNow
{
    use BroadcastsViaPackageConnection, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public function __construct(
        public $finished = false,
        public $progress = 0,
        public $pending = 0,
        public $total = 0,
        public $data = null,
        public string $channelName = 'channel-job-batching',
        public string $broadcastName = 'broadcast-job-batching',
        public ?array $snapshot = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromSnapshot(array $snapshot): self
    {
        return new self(
            finished: $snapshot['finished'] ?? false,
            progress: $snapshot['progress'] ?? 0,
            pending: $snapshot['pending'] ?? 0,
            total: $snapshot['total'] ?? 0,
            data: $snapshot['current'] ?? null,
            snapshot: $snapshot,
        );
    }

    public function broadcastOn(): Channel
    {
        return new Channel($this->channelName);
    }

    public function broadcastAs(): string
    {
        return $this->broadcastName;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }

        return [
            'finished' => $this->finished,
            'progress' => $this->progress,
            'pending' => $this->pending,
            'total' => $this->total,
            'data' => $this->data,
        ];
    }

}
