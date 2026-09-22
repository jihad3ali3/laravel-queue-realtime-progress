<?php

declare(strict_types=1);

namespace YogaMeleniawan\JobBatchingWithRealtimeProgress\Support;

use YogaMeleniawan\JobBatchingWithRealtimeProgress\Interfaces\RealtimeJobBatchInterface;

class TaskMeta
{
    /**
     * @param  iterable<mixed>  $items
     * @return array<int, array{key: string, label: string, status: string, message: ?string}>
     */
    public static function describeAll(RealtimeJobBatchInterface $repository, iterable $items): array
    {
        $tasks = [];
        $used = [];

        foreach ($items as $index => $item) {
            $index = (int) $index;
            $key = self::uniqueKey(self::key($repository, $item, $index), $index, $used);
            $used[$key] = true;

            $tasks[] = [
                'key' => $key,
                'label' => self::label($repository, $item, $index),
                'status' => 'pending',
                'message' => null,
            ];
        }

        return $tasks;
    }

    public static function key(RealtimeJobBatchInterface $repository, mixed $item, int $index): string
    {
        if (method_exists($repository, 'key')) {
            return (string) $repository->key($item, $index);
        }

        if (is_array($item)) {
            foreach (['key', 'id', 'uuid'] as $field) {
                if (isset($item[$field]) && is_scalar($item[$field])) {
                    return (string) $item[$field];
                }
            }
        }

        if (is_object($item)) {
            foreach (['key', 'id', 'uuid'] as $field) {
                if (isset($item->{$field}) && is_scalar($item->{$field})) {
                    return (string) $item->{$field};
                }
            }
        }

        return (string) $index;
    }

    public static function label(RealtimeJobBatchInterface $repository, mixed $item, int $index): string
    {
        if (method_exists($repository, 'label')) {
            return (string) $repository->label($item, $index);
        }

        if (is_array($item)) {
            foreach (['label', 'name', 'title'] as $field) {
                if (isset($item[$field]) && is_scalar($item[$field])) {
                    return (string) $item[$field];
                }
            }
        }

        if (is_object($item)) {
            foreach (['label', 'name', 'title'] as $field) {
                if (isset($item->{$field}) && is_scalar($item->{$field})) {
                    return (string) $item->{$field};
                }
            }
        }

        return 'Task '.($index + 1);
    }

    /**
     * @param  array<string, bool>  $used
     */
    private static function uniqueKey(string $key, int $index, array $used): string
    {
        if (! isset($used[$key])) {
            return $key;
        }

        return $key.'-'.$index;
    }
}
