<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Demo;

use Illuminate\Support\Collection;
use RuntimeException;
use YogaMeleniawan\JobBatchingWithRealtimeProgress\Interfaces\RealtimeJobBatchInterface;

class DemoTaskRepository implements RealtimeJobBatchInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(private array $items)
    {
    }

    public function get_all(): Collection
    {
        return collect($this->items);
    }

    public function save($data): void
    {
        $pace = 400;

        if (is_array($data)) {
            $pace = (int) ($data['duration_ms'] ?? 400);

            if (! empty($data['should_fail'])) {
                usleep(max(0, $pace) * 1000);

                throw new RuntimeException((string) ($data['fail_message'] ?? 'Task failed.'));
            }
        }

        usleep(max(0, $pace) * 1000);
    }

    public function label(mixed $data, int $index): string
    {
        if (is_array($data) && isset($data['label'])) {
            return (string) $data['label'];
        }

        return 'Task '.($index + 1);
    }

    public function key(mixed $data, int $index): string
    {
        if (is_array($data) && isset($data['key'])) {
            return (string) $data['key'];
        }

        return (string) $index;
    }
}
