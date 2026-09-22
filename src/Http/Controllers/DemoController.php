<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Demo\DemoTaskRepository;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\RealtimeJobBatch;

class DemoController extends Controller
{
    /**
     * @var array<int, string>
     */
    private array $labels = [
        'مطابقة فاتورة المورد',
        'إرسال إشعار للعميل',
        'أرشفة مستند العملية',
        'تحديث رصيد المخزون',
        'توليد كشف الحساب',
        'التحقق من عملية الدفع',
        'مزامنة بيانات المنتج',
        'إغلاق أمر الشغل',
        'حساب عمولة المندوب',
        'تصدير تقرير اليوم',
        'مراجعة مرتجع العميل',
        'تحديث حالة الشحنة',
    ];

    public function index(): View
    {
        abort_unless($this->enabled(), 404);

        return view('realtime-job-batch::demo');
    }

    public function start(Request $request): JsonResponse
    {
        abort_unless($this->enabled(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'count' => ['required', 'integer', 'min:1', 'max:40'],
            'pace_ms' => ['required', 'integer', 'min:200', 'max:5000'],
            'simulate_failure' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'اكتب اسم العملية.',
            'count.min' => 'أقل عدد هو مهمة واحدة.',
            'count.max' => 'الحد الأعلى في المعاينة ٤٠ مهمة.',
        ]);

        $count = (int) $data['count'];
        $failAt = $request->boolean('simulate_failure') ? intdiv($count, 2) : -1;
        $items = [];

        for ($index = 0; $index < $count; $index++) {
            $items[] = [
                'key' => $index.'-'.bin2hex(random_bytes(4)),
                'label' => $this->labels[$index % count($this->labels)].' #'.($index + 1),
                'duration_ms' => (int) $data['pace_ms'],
                'should_fail' => $index === $failAt,
                'fail_message' => 'تعذّر إكمال هذه العملية',
            ];
        }

        $batch = RealtimeJobBatch::setRepository(new DemoTaskRepository($items))
            ->execute($data['name']);

        return response()->json([
            'batch_id' => $batch->id,
            'progress' => RealtimeJobBatch::snapshot($batch->id),
        ]);
    }

    private function enabled(): bool
    {
        return (bool) config('realtime-job-batch.demo.enabled')
            && config('app.env') !== 'production';
    }
}
