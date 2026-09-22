@php
    if (! isset($GLOBALS['rjb_assets'])) {
        $GLOBALS['rjb_assets'] = true;
        echo view('realtime-job-batch::partials.assets', ['channel' => $channel])->render();
    }
@endphp

<aside class="rjb-dock" dir="rtl" data-rjb-taskbar>
    <div class="rjb-dock__head">
        <span class="rjb-live" data-rjb-dot></span>
        <div>
            <strong>شريط العمليات</strong>
                <small data-rjb-connection>جاري الاتصال بـ Reverb…</small>
        </div>
        <span class="rjb-dock__count" data-rjb-count>0</span>
    </div>
    <div class="rjb-dock__list" data-rjb-list>
        <p class="rjb-empty">ما في عمليات في الطابور.</p>
    </div>
</aside>
