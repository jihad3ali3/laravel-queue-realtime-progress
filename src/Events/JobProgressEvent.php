<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Events\Concerns\BroadcastsViaPackageConnection;

class JobProgressEvent implements ShouldBroadcastNow
{
    use BroadcastsViaPackageConnection, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public array $snapshot,
        public ?string $eventName = null,
    ) {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $batchId = (string) ($this->snapshot['batch_id'] ?? 'unknown');
        $global = (string) config('realtime-job-batch.channels.global', 'job-batches');
        $prefix = (string) config('realtime-job-batch.channels.prefix', 'job-batch');

        return [
            new Channel($global),
            new Channel($prefix.'.'.$batchId),
        ];
    }

    public function broadcastAs(): string
    {
        if (is_string($this->eventName) && $this->eventName !== '') {
            return $this->eventName;
        }

        return (string) config('realtime-job-batch.events.progress', 'progress');
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->snapshot;
    }

}
