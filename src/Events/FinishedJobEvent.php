<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Events\Concerns\BroadcastsViaPackageConnection;

class FinishedJobEvent implements ShouldBroadcastNow
{
    use BroadcastsViaPackageConnection, Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public $finished = true,
        public string $channelName = 'channel-job-finish',
        public string $broadcastName = 'broadcast-job-finish'
    ) {
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
        return [
            'finished' => $this->finished,
        ];
    }

}
