<?php

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

    public function save($data): void
    {
        // Business logic for one queued task.
    }

    public function label(mixed $data, int $index): string
    {
        return is_array($data) ? (string) ($data['label'] ?? 'مهمة') : 'مهمة '.($index + 1);
    }

    public function key(mixed $data, int $index): string
    {
        return is_array($data) ? (string) ($data['key'] ?? $index) : (string) $index;
    }
}
