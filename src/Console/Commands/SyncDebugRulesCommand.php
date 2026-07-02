<?php

namespace Webmavens\DebugMonitor\Console\Commands;

use Illuminate\Console\Command;
use Webmavens\DebugMonitor\Services\RuleSyncService;

class SyncDebugRulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug-monitor:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import pending rule files from the rules directory into the database.';

    /**
     * Execute the console command.
     */
    public function handle(RuleSyncService $service): int
    {
        $this->info('Syncing debug rules from '.$service->rulesPath().' ...');

        $summary = $service->sync();

        foreach ($summary['applied'] as $name) {
            $this->info("✅ Imported: {$name}");
        }

        foreach ($summary['skipped'] as $skipped) {
            $this->warn("⏭️  Skipped {$skipped['file']}: {$skipped['reason']}");
        }

        foreach ($summary['errors'] as $error) {
            $this->error("❌ {$error['file']}: {$error['message']}");
        }

        $this->info(sprintf(
            'Done: %d imported, %d skipped, %d failed.',
            count($summary['applied']),
            count($summary['skipped']),
            count($summary['errors'])
        ));

        return empty($summary['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
