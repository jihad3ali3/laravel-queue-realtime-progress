<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Support;

class FrontendConfig
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $reverb = config('broadcasting.connections.reverb', []);
        $options = is_array($reverb) ? ($reverb['options'] ?? []) : [];
        $frontend = config('realtime-job-batch.frontend', []);

        $key = $frontend['key'] ?? null;

        if (! is_string($key) || $key === '') {
            $key = is_array($reverb) ? (string) ($reverb['key'] ?? '') : '';
        }

        return [
            'broadcaster' => 'reverb',
            'key' => $key,
            'host' => $frontend['host'] ?? '',
            'port' => $frontend['port'] ?? ($options['port'] ?? 8080),
            'scheme' => $frontend['scheme'] ?? ($options['scheme'] ?? 'http'),
            'serverHost' => $options['host'] ?? '127.0.0.1',
            'serverPort' => (int) ($options['port'] ?? 8080),
            'serverScheme' => $options['scheme'] ?? 'http',
            'globalChannel' => config('realtime-job-batch.channels.global', 'job-batches'),
            'channelPrefix' => config('realtime-job-batch.channels.prefix', 'job-batch'),
            'progressEvent' => config('realtime-job-batch.events.progress', 'progress'),
            'finishedEvent' => config('realtime-job-batch.events.finished', 'finished'),
            'statusBase' => url('/'.trim((string) config('realtime-job-batch.routes.prefix', 'realtime-job-batches'), '/')),
            'sameOrigin' => false,
        ];
    }
}
