<?php

namespace App\Console\Commands;

use App\SmsGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckProductionReadiness extends Command
{
    protected $signature = 'operations:check-production';

    protected $description = 'Check production configuration without printing secrets or changing data';

    public function handle(): int
    {
        $queue = config('queue.connections.'.config('queue.default'), []);
        $database = config('database.connections.'.config('database.default'), []);
        $checks = [
            'Production environment' => app()->isProduction(),
            'Debug disabled' => config('app.debug') === false,
            'HTTPS application URL' => parse_url(config('app.url'), PHP_URL_SCHEME) === 'https',
            'Encryption key configured' => filled(config('app.key')),
            'Secure HTTP-only encrypted sessions' => (bool) config('session.secure') && (bool) config('session.http_only') && (bool) config('session.encrypt'),
            'Same-site cookie protection' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'Database account is not root' => filled($database['username'] ?? null) && ($database['username'] ?? null) !== 'root',
            'Real mail transport configured' => ! in_array(config('mail.default'), ['log', 'array'], true),
            'Asynchronous queue configured' => ! in_array($queue['driver'] ?? null, [null, 'sync', 'null'], true),
            'Queue reservation exceeds SMS job timeout' => isset($queue['retry_after']) && (int) $queue['retry_after'] > 35,
            'Live SMS credentials configured or test mode enabled' => app(SmsGateway::class)->dryRun() || app(SmsGateway::class)->ready(),
            'No Vite development hot file' => ! is_file(public_path('hot')),
            'Production asset manifest present' => is_file(public_path('build/manifest.json')),
            'Configuration cached' => app()->configurationIsCached(),
            'Routes cached' => app()->routesAreCached(),
        ];
        try {
            DB::select('SELECT 1');
            $checks['Database connection available'] = true;
        } catch (Throwable) {
            $checks['Database connection available'] = false;
        }
        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'PASS' : 'FAIL').' '.$label);
        }
        $this->warn('These checks do not verify TLS, database grants, backups, supervision, monitoring, or handset delivery.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
