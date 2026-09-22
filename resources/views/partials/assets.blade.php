@php
    $rjbConfig = app(\YogaMeleniawan\JobBatchingWithRealtimeProgress\Support\FrontendConfig::class)->toArray();

    if (! empty($channel)) {
        $rjbConfig['globalChannel'] = $channel;
    }
@endphp
<link rel="stylesheet" href="{{ route('realtime-job-batch.assets', ['file' => 'realtime-progress.css']) }}">
<script type="application/json" id="rjb-config">{!! json_encode($rjbConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script src="{{ route('realtime-job-batch.assets', ['file' => 'realtime-progress.js']) }}" defer></script>
