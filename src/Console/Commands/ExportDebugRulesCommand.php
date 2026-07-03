<?php

namespace Webmavens\DebugMonitor\Console\Commands;

use Illuminate\Console\Command;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Services\RuleSyncService;

class ExportDebugRulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug-monitor:export
                            {--id=* : Only export the given rule id(s); defaults to all rules}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export database rules to version-controlled JSON files.';

    /**
     * Execute the console command.
     */
    public function handle(RuleSyncService $service): int
    {
        $ids = $this->option('id');

        $rules = DebugRule::when(! empty($ids), fn ($q) => $q->whereIn('id', $ids))->get();

        if ($rules->isEmpty()) {
            $this->info('No rules to export.');

            return self::SUCCESS;
        }

        foreach ($rules as $rule) {
            $result = $service->export($rule);
            $verb = $result['created'] ? 'Exported' : 'Updated';
            $this->info("✅ {$verb}: {$rule->name} → {$result['file']}");
        }

        $this->info("Done: exported {$rules->count()} rule(s) to ".$service->rulesPath().'.');

        return self::SUCCESS;
    }
}
