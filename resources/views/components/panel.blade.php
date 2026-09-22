@php
    if (! isset($GLOBALS['rjb_assets'])) {
        $GLOBALS['rjb_assets'] = true;
        echo view('realtime-job-batch::partials.assets', ['channel' => null])->render();
    }
@endphp

<section class="rjb-panel" dir="rtl" data-rjb-panel @if($batchId) data-batch-id="{{ $batchId }}" @endif>
    <header class="rjb-panel__head">
        <div>
            <p class="rjb-kicker">العملية الحالية</p>
            <h2 data-rjb-title>بانتظار عملية</h2>
            <p class="rjb-sub" data-rjb-status>ابدأ معالجة حتى يظهر شريط التقدم هنا.</p>
        </div>
        <div class="rjb-percent" data-rjb-percent aria-hidden="true"><b>0</b><span>%</span></div>
    </header>
    <div class="rjb-bar" data-rjb-bar role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="تقدم العملية">
        <span data-rjb-bar-fill></span>
    </div>
    <dl class="rjb-stats">
        <div><dt>منجز</dt><dd data-rjb-processed>0</dd></div>
        <div><dt>متبقي</dt><dd data-rjb-pending>0</dd></div>
        <div><dt>فشل</dt><dd data-rjb-failed>0</dd></div>
        <div><dt>الإجمالي</dt><dd data-rjb-total>0</dd></div>
    </dl>
    <ol class="rjb-tasks" data-rjb-tasks></ol>
    <footer class="rjb-panel__foot">
        <p data-rjb-message>الشريط يتحدث مباشرة من أحداث Reverb.</p>
        <button type="button" class="rjb-stop" data-rjb-cancel hidden>إيقاف</button>
    </footer>
</section>
