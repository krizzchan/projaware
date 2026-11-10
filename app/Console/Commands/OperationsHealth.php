<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class OperationsHealth extends Command
{
    protected $signature = 'operations:health {--json : Print safe machine-readable results}';

    protected $description = 'Read-only dependency and SMS backlog checks; sends no messages';

    public function handle(): int
    {
        $checks = ['database' => false, 'cache' => false];
        $metrics = [];
        try {
            DB::select('SELECT 1');
            $checks['database'] = true;
            $metrics = [
                'failed_jobs_24h' => DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count(),
                'stale_queued' => DB::table('sms_recipients')->where('status', 'queued')
                    ->where('created_at', '<', now()->subSeconds(max(60, (int) config('operations.queue_warning_seconds', 300))))->count(),
                'stale_processing' => DB::table('sms_recipients')->where('status', 'processing')
                    ->where('updated_at', '<', now()->subSeconds(180))->count(),
                'needs_review_24h' => DB::table('sms_recipients')->whereIn('status', ['unknown', 'failed', 'rejected'])
                    ->where('updated_at', '>=', now()->subDay())->count(),
            ];
        } catch (Throwable) {
            $checks['database'] = false;
        }
        try {
            Cache::has('illuminate:queue:restart');
            $checks['cache'] = true;
        } catch (Throwable) {
            $checks['cache'] = false;
        }
        $healthy = ! in_array(false, $checks, true) && array_sum($metrics) === 0;
        $result = ['status' => $healthy ? 'ok' : 'attention_required', 'checks' => $checks, 'sms' => $metrics];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Result'], collect($checks)->map(fn (bool $passed, string $name) => [$name, $passed ? 'PASS' : 'FAIL'])->values()->all());
            $this->table(['SMS metric', 'Count'], collect($metrics)->map(fn (int $count, string $name) => [$name, $count])->values()->all());
            $this->line($result['status']);
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
