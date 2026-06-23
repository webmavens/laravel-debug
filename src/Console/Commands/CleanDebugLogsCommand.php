<?php

namespace Webmavens\DebugMonitor\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Webmavens\DebugMonitor\Models\DebugRuleLog; 

class CleanDebugLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug-monitor:clean 
                            {--days= : Number of days to retain logs (overrides config value)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old debug logs older than configured number of days.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days') ?: config('debug-monitor.log_retention_days', 30);
        $cutoff = now()->subDays($days);

        $count = DebugRuleLog::where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->info("✅ No logs older than {$days} days found.");
            return 0;
        }

        $this->warn("🧹 Deleting {$count} logs older than {$days} days...");

        $deleted = DebugRuleLog::where('created_at', '<', $cutoff)->delete();

        $this->info("✅ Successfully deleted {$deleted} old logs (before {$cutoff->toDateTimeString()}).");

        return 0;
    }
}
