<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Http\Controllers;

use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController extends Controller
{
    public function show(string $file): BinaryFileResponse
    {
        $root = dirname(__DIR__, 3).'/resources';

        $map = [
            'realtime-progress.css' => [$root.'/css/realtime-progress.css', 'text/css; charset=UTF-8'],
            'realtime-progress.js' => [$root.'/js/realtime-progress.js', 'application/javascript; charset=UTF-8'],
            'demo-form.js' => [$root.'/js/demo-form.js', 'application/javascript; charset=UTF-8'],
        ];

        abort_unless(isset($map[$file]) && is_file($map[$file][0]), 404);

        return response()->file($map[$file][0], [
            'Content-Type' => $map[$file][1],
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
