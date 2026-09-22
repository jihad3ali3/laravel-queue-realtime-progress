<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Events\Concerns;

trait BroadcastsViaPackageConnection
{
    /**
     * @return array<int, string|null>
     */
    public function broadcastConnections(): array
    {
        $connection = config('realtime-job-batch.broadcast_connection', 'reverb');

        if (! is_string($connection) || $connection === '') {
            $connection = 'reverb';
        }

        return [$connection];
    }
}
