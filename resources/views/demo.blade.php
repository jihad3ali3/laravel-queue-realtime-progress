<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>مراقب الطابور</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
</head>
<body class="rjb-page">
    <div class="rjb-wrap">
        <header class="rjb-hero">
            <div class="rjb-brand">
                <span class="rjb-seal" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <rect x="6" y="7" width="20" height="3.2" rx="1.6" fill="currentColor"/>
                        <rect x="6" y="14.4" width="14" height="3.2" rx="1.6" fill="currentColor" opacity=".8"/>
                        <rect x="6" y="21.8" width="9" height="3.2" rx="1.6" fill="currentColor" opacity=".55"/>
                    </svg>
                </span>
                <div>
                    <p class="rjb-eyebrow">Laravel Reverb</p>
                    <h1>مراقب الطابور</h1>
                </div>
            </div>
            <p class="rjb-lead">شوف شريط تقدّم العمليات اللي تشتغل في الـ queue. البث يجي من WebSocket تبع Reverb، مو من Pusher.</p>
        </header>

        <main class="rjb-grid">
            <form id="rjb-start-form" class="rjb-form" action="{{ route('realtime-job-batch.demo.start') }}" method="post">
                @csrf
                <h2>عملية جديدة</h2>
                <p>كل عنصر يرجع من <code>get_all()</code> يصير مهمة، و<code>save()</code> يشتغل داخل job. Reverb يبث التقدم للشريط.</p>

                <div class="rjb-field">
                    <label for="rjb-name">اسم العملية</label>
                    <input id="rjb-name" name="name" type="text" value="مطابقة فواتير سبتمبر" maxlength="120" required>
                </div>

                <div class="rjb-field">
                    <label for="rjb-count">عدد المهام <span data-count-out>12</span></label>
                    <input id="rjb-count" name="count" type="range" min="3" max="24" value="12">
                </div>

                <div class="rjb-field">
                    <label for="rjb-pace">سرعة كل مهمة <span data-pace-out>0.7 ث</span></label>
                    <input id="rjb-pace" name="pace_ms" type="range" min="300" max="2000" step="100" value="700">
                </div>

                <label class="rjb-check">
                    <input type="checkbox" name="simulate_failure" value="1">
                    <span>خلّ مهمة واحدة تفشل، حتى تشوف حالة الخطأ على الشريط.</span>
                </label>

                <button class="rjb-start" type="submit">ابدأ المعالجة</button>
                <p class="rjb-note" data-form-note>شغّل <code>reverb:start</code> و <code>queue:work</code> قبل ما تبدأ.</p>

                <div class="rjb-steps">
                    <span>1. الطابور يستلم العملية</span>
                    <span>2. كل job يبث تقدمه</span>
                    <span>3. الشريط يتحرك فوراً</span>
                </div>
            </form>

            <x-realtime-job-panel />
        </main>
    </div>

    <x-realtime-job-taskbar />
    <script src="{{ route('realtime-job-batch.assets', ['file' => 'demo-form.js']) }}" defer></script>
</body>
</html>
