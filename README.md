# مراقب تقدّم الـ Queue عبر Laravel Reverb

شريط تقدّم لعمليات الـ queue. كل مهمة تخلص، Reverb يبث حالتها، والواجهة تحرّك الشريط بدون تحديث الصفحة.

الحزمة ما زالت على نفس الفكرة: repository فيه `get_all()` و `save()`، و `RealtimeJobBatch` يوزّع العناصر على batch. الفرق إن البث صار عبر **Laravel Reverb**، وفي شريط عمليات جاهز للواجهة.

## المتطلبات

- PHP 8.2+
- Laravel 10.48 أو 11 أو 12 أو 13
- [Laravel Reverb](https://laravel.com/docs/reverb)
- Queue worker (`database` أو `redis`). اتصال `sync` يشتغل للتجربة، بس الطلب يبقى مفتوح لين تخلص المهام.

## التثبيت

```bash
php artisan install:broadcasting --reverb
composer require yogameleniawan/realtime-job-batching
php artisan vendor:publish --tag=realtime-job-batch-config
```

في `.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=realtime-job
REVERB_APP_KEY=realtime-job-key
REVERB_APP_SECRET=realtime-job-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# اتركها فاضية محلياً. عبيها لو المتصفح يوصل لـ Reverb من دومين أو بورت مختلف.
REVERB_PUBLIC_HOST=
REVERB_PUBLIC_PORT=
REVERB_PUBLIC_SCHEME=

QUEUE_CONNECTION=database
REALTIME_JOB_DEMO=true
```

`REVERB_HOST` هو عنوان سيرفر PHP وهو يبث. المتصفح ما يتصل على `127.0.0.1` إلا لو الصفحة نفسها مفتوحة محلياً. لو الموقع خلف بروكسي، حط العنوان العام في `REVERB_PUBLIC_*`.

جداول الـ queue، لو مو موجودة:

```bash
php artisan queue:table
php artisan queue:batches-table
php artisan migrate
```

## المستودع

```php
namespace App\Repositories;

use Illuminate\Support\Collection;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Interfaces\RealtimeJobBatchInterface;

class VerificationRepository implements RealtimeJobBatchInterface
{
    public function get_all(): Collection
    {
        return collect([
            ['key' => 'invoice-1', 'label' => 'مطابقة فاتورة #1'],
            ['key' => 'invoice-2', 'label' => 'مطابقة فاتورة #2'],
        ]);
    }

    public function save(mixed $data): void
    {
        // منطق المهمة الواحدة. يشتغل داخل job.
    }

    public function label(mixed $data, int $index): string
    {
        return $data['label'];
    }

    public function key(mixed $data, int $index): string
    {
        return $data['key'];
    }
}
```

`label()` و `key()` اختياريين. بدونهم الشريط يحاول يقرأ `label` أو `name` أو `title`.

## تشغيل الباتش

```php
use App\Repositories\VerificationRepository;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\RealtimeJobBatch;

$batch = RealtimeJobBatch::setRepository(new VerificationRepository())
    ->onQueue('default')
    ->execute('مطابقة فواتير سبتمبر');

return response()->json([
    'batch_id' => $batch->id,
    'progress' => RealtimeJobBatch::snapshot($batch->id),
]);
```

`$batch->id` هو نفسه رقم الشريط. كل عنصر من `get_all()` يصير job، و `save()` يتنفّذ داخله.

## الشريط في الواجهة

حطهم في أي layout. ما يحتاج Vite:

```blade
<x-realtime-job-panel />
<x-realtime-job-taskbar />
```

- اللوحة تعرض النسبة، المنجز، المتبقي، الفاشل، وقائمة المهام.
- الشريط السفلي يثبت عمليات الطابور الشغالة، حتى لو فتحت صفحة ثانية فيها نفس المكوّن.

المكوّن يفتح WebSocket بنفسه على Reverb، على المسار `/app/{REVERB_APP_KEY}`. ما يحمّل Pusher، وما يستخدم اتصال Pusher حتى لو باقي التطبيق لسّه عليه.

أحداث الحزمة تنشر على اتصال `reverb` حتى لو `BROADCAST_CONNECTION=pusher`. عشان كذا شريط التقدم ما يروح لـ Pusher Channels.

الاستماع اليدوي، لو ركّبت Echo على Reverb:

```js
Echo.channel('job-batches').listen('.progress', (data) => {
    console.log(data.percentage, data.processed, data.pending, data.total);
});
```

القنوات:

- `job-batches` لكل العمليات
- `job-batch.{batchId}` لعملية واحدة

القنوات عامة. لا تعرض فيها بيانات حساسة، أو غيّر `routes.middleware` وأضف قناة خاصة في تطبيقك لو التقدم خاص بمستخدم.

## التشغيل

ثلاث عمليات:

```bash
php artisan serve
php artisan reverb:start
php artisan queue:work
```

صفحة المعاينة، بعد `REALTIME_JOB_DEMO=true` وخارج الإنتاج:

```text
/realtime-job-batch/demo
```

تبدأ batch تجريبي وتحرك الشريط. عطّلها في الإنتاج.

## شكل الحدث

```json
{
  "batch_id": "9f0c...",
  "name": "مطابقة فواتير سبتمبر",
  "status": "processing",
  "finished": false,
  "cancelled": false,
  "progress": 42,
  "percentage": 42,
  "processed": 5,
  "pending": 6,
  "failed": 1,
  "total": 12,
  "current": { "key": "invoice-6", "label": "مطابقة فاتورة #6", "status": "processing" },
  "tasks": []
}
```

`progress` نسبة من 0 إلى 100. `pending` عدد المتبقي، مو عدد المنفَّذ.

`status`: `queued` ، `processing` ، `finished` ، `finished_with_failures` ، `failed` ، `cancelled`.

إيقاف عملية:

```http
POST /realtime-job-batches/{batchId}/cancel
```

## إعدادات مهمة

| المفتاح | المعنى |
| --- | --- |
| `REALTIME_JOB_BROADCAST_CONNECTION` | اتصال بث خاص بالحزمة. فاضي = اتصال التطبيق، خلّه `reverb` |
| `REALTIME_JOB_QUEUE` | اسم الطابور |
| `REALTIME_JOB_TIMEOUT` | مهلة المهمة بالثواني |
| `REALTIME_JOB_ALLOW_FAILURES` | مهمة فاشلة ما توقف الباقي |
| `REALTIME_JOB_LEGACY_CHANNELS` | رجّع قنوات الإصدار 1: `channel-job-batching` |
| `REALTIME_JOB_DEMO` | صفحة المعاينة |

لو البث فشل لأن Reverb واقف، المهمة نفسها ما تفشل. الخطأ ينتسجّل ويكمل `save()`.

## English

Same package, but progress is a Reverb WebSocket, not Pusher Channels. The browser opens `/app/{REVERB_APP_KEY}` itself and does not load `pusher-js`. Package events publish on the `reverb` broadcast connection even if the rest of the app still uses Pusher. Implement `RealtimeJobBatchInterface`, then:

```php
$batch = RealtimeJobBatch::setRepository(new VerificationRepository())
    ->execute('September invoices');
```

Set `BROADCAST_CONNECTION=reverb`, run `php artisan reverb:start` and `php artisan queue:work`, and drop the Blade components into a layout:

```blade
<x-realtime-job-panel />
<x-realtime-job-taskbar />
```

The browser subscribes to `job-batches`. Each event carries `percentage`, `processed`, `pending`, `failed`, `total`, and the task list. Enable `REALTIME_JOB_DEMO=true` outside production and open `/realtime-job-batch/demo`.

`examples/preview` is a browser preview of that same bar. It speaks the Reverb WebSocket protocol so the progress UI can be opened without booting a full Laravel app.

## الرخصة

MIT. المؤلف الأصلي: [Yoga Meleniawan Pamungkas](https://github.com/yogameleniawan).
