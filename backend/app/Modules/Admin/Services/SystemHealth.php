<?php

namespace App\Modules\Admin\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SystemHealth
{
    /** @return array{status: string, checks: array<string, array<string, mixed>>, app: array<string, string>} */
    public function report(): array
    {
        $checks = [
            'database' => $this->timed(function () {
                DB::select('select 1');

                return ['connections' => (int) (DB::selectOne('select count(*) as c from pg_stat_activity where datname = current_database()')->c ?? 0)];
            }),
            'redis' => $this->timed(function () {
                Redis::connection()->ping();

                return [];
            }),
            'queue' => $this->timed(fn () => [
                'pending' => Queue::size(),
                'failed' => DB::table('failed_jobs')->count(),
            ]),
            'storage' => $this->timed(function () {
                $disk = Storage::disk(config('ecosystem.media.disk'));
                $probe = '.health/'.Str::random(12);
                $disk->put($probe, 'ok');
                $disk->delete($probe);

                return ['disk' => config('ecosystem.media.disk')];
            }),
        ];

        $checks['queue']['status'] = $checks['queue']['status'] === 'ok' && ($checks['queue']['failed'] ?? 0) > 0
            ? 'degraded'
            : $checks['queue']['status'];

        $statuses = array_column($checks, 'status');
        $overall = in_array('down', $statuses, true) ? 'down' : (in_array('degraded', $statuses, true) ? 'degraded' : 'ok');

        return [
            'status' => $overall,
            'checks' => $checks,
            'app' => [
                'environment' => app()->environment(),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'checked_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function timed(callable $check): array
    {
        $start = hrtime(true);

        try {
            $details = $check();

            return ['status' => 'ok', 'latency_ms' => round((hrtime(true) - $start) / 1e6, 1), ...$details];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'down', 'latency_ms' => round((hrtime(true) - $start) / 1e6, 1), 'error' => class_basename($e)];
        }
    }
}
