<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Broadcast connection
    |--------------------------------------------------------------------------
    |
    | Progress events are published on the Reverb connection, even when the rest
    | of the app still uses Pusher. Override only if Reverb is named differently.
    |
    */
    'broadcast_connection' => env('REALTIME_JOB_BROADCAST_CONNECTION', 'reverb'),

    'channels' => [
        'global' => env('REALTIME_JOB_GLOBAL_CHANNEL', 'job-batches'),
        'prefix' => env('REALTIME_JOB_CHANNEL_PREFIX', 'job-batch'),
    ],

    'events' => [
        'progress' => 'progress',
        'finished' => 'finished',
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Pusher channel names
    |--------------------------------------------------------------------------
    |
    | The 1.x release broadcast on channel-job-batching / channel-job-finish.
    | Leave this off unless an existing frontend still listens there.
    |
    */
    'legacy_channels' => (bool) env('REALTIME_JOB_LEGACY_CHANNELS', false),

    'queue' => env('REALTIME_JOB_QUEUE', 'default'),

    'timeout' => (int) env('REALTIME_JOB_TIMEOUT', 120),

    'tries' => (int) env('REALTIME_JOB_TRIES', 1),

    'allow_failures' => (bool) env('REALTIME_JOB_ALLOW_FAILURES', true),

    'routes' => [
        'enabled' => (bool) env('REALTIME_JOB_ROUTES', true),
        'prefix' => env('REALTIME_JOB_ROUTES_PREFIX', 'realtime-job-batches'),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Browser connection
    |--------------------------------------------------------------------------
    |
    | REVERB_HOST / REVERB_PORT are where PHP publishes events (usually
    | 127.0.0.1:8080). The browser often needs a different public host,
    | especially behind a TLS proxy. Leave the public values empty to let the
    | progress bar detect the host from the page URL.
    |
    */
    'frontend' => [
        'key' => env('REVERB_APP_KEY'),
        'host' => env('REVERB_PUBLIC_HOST'),
        'port' => env('REVERB_PUBLIC_PORT'),
        'scheme' => env('REVERB_PUBLIC_SCHEME'),
    ],

    'demo' => [
        'enabled' => (bool) env('REALTIME_JOB_DEMO', false),
    ],

];
