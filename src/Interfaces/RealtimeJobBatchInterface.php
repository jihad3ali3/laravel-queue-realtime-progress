<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Interfaces;

use Illuminate\Support\Collection;

interface RealtimeJobBatchInterface
{
    /**
     * Data to process. Each item becomes one queued task and one tick on the progress bar.
     *
     * @return Collection<int, mixed>
     */
    public function get_all(): Collection;

    /**
     * Business logic for a single item. Runs inside a queued job.
     *
     * @param  mixed  $data
     */
    public function save($data): void;

    /*
     * Optional, detected with method_exists so existing repositories keep working:
     *
     * public function label(mixed $data, int $index): string;
     * public function key(mixed $data, int $index): string;
     *
     * Without them the bar falls back to label/name/title fields, then "Task N".
     */
}
