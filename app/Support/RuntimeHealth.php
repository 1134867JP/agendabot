<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class RuntimeHealth
{
    private const HEARTBEAT_TTL_MINUTES = 10;

    public static function touchWorker(string $name): void
    {
        $name = self::normalizeWorkerName($name);
        $timestamp = now()->timestamp;

        Cache::put(self::workerKey($name), $timestamp, now()->addMinutes(self::HEARTBEAT_TTL_MINUTES));
        Cache::put('queue_worker_last_seen_at', now()->toIso8601String(), now()->addMinutes(self::HEARTBEAT_TTL_MINUTES));
    }

    public static function touchScheduler(): void
    {
        Cache::put(self::schedulerKey(), now()->timestamp, now()->addMinutes(self::HEARTBEAT_TTL_MINUTES));
    }

    public static function status(): array
    {
        $workerMaxAge = (int) config('queue.monitoring.worker_stale_after_seconds', 60);
        $schedulerMaxAge = (int) config('queue.monitoring.scheduler_stale_after_seconds', 150);
        $workers = [];

        foreach ((array) config('queue.monitoring.workers', ['interactive', 'batch']) as $name) {
            $normalizedName = self::normalizeWorkerName((string) $name);
            $workers[$normalizedName] = self::heartbeatStatus(self::workerKey($normalizedName), $workerMaxAge);
        }

        $scheduler = self::heartbeatStatus(self::schedulerKey(), $schedulerMaxAge);
        $ready = $scheduler['status'] === 'ok'
            && collect($workers)->every(fn (array $worker) => $worker['status'] === 'ok');

        return [
            'ready' => $ready,
            'workers' => $workers,
            'scheduler' => $scheduler,
        ];
    }

    private static function heartbeatStatus(string $key, int $maxAge): array
    {
        try {
            $timestamp = Cache::get($key);
        } catch (\Throwable) {
            return ['status' => 'unavailable', 'last_seen_at' => null, 'age_seconds' => null];
        }

        if (! is_numeric($timestamp)) {
            return ['status' => 'missing', 'last_seen_at' => null, 'age_seconds' => null];
        }

        $timestamp = (int) $timestamp;
        $age = max(0, now()->timestamp - $timestamp);

        return [
            'status' => $age <= $maxAge ? 'ok' : 'stale',
            'last_seen_at' => now()->setTimestamp($timestamp)->toIso8601String(),
            'age_seconds' => $age,
        ];
    }

    private static function workerKey(string $name): string
    {
        return "runtime_worker_{$name}_last_seen_at";
    }

    private static function schedulerKey(): string
    {
        return 'runtime_scheduler_last_seen_at';
    }

    private static function normalizeWorkerName(string $name): string
    {
        return Str::slug($name) ?: 'default';
    }
}
