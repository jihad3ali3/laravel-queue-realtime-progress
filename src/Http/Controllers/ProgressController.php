<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Services\RealtimeJobService;

class ProgressController extends Controller
{
    public function index(RealtimeJobService $progress): JsonResponse
    {
        return response()->json([
            'data' => $progress->active(),
        ]);
    }

    public function show(string $batchId, RealtimeJobService $progress): JsonResponse
    {
        $snapshot = $progress->find($batchId);

        abort_if($snapshot === null, 404);

        return response()->json($snapshot);
    }

    public function cancel(string $batchId, RealtimeJobService $progress): JsonResponse
    {
        $snapshot = $progress->cancel($batchId);

        abort_if($snapshot === null, 404);

        return response()->json($snapshot->toArray());
    }
}
