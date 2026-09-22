<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Taskbar extends Component
{
    public function __construct(public ?string $channel = null)
    {
    }

    public function render(): View
    {
        return view('realtime-job-batch::components.taskbar');
    }
}
