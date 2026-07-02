<?php

namespace Webmavens\DebugMonitor\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleSync;

/**
 * Imports version-controlled rule files into the database — like migrations.
 *
 * Flow is one-directional (file -> DB). Files are never executed directly;
 * once a file is applied it is tracked in `debug_rule_syncs` and skipped on
 * subsequent syncs. If a DB rule with the same name already exists, the file
 * is skipped (DB wins).
 */
class RuleSyncService
{
    public function rulesPath(): string
    {
        return (string) config('debug-monitor.rules_path', base_path('debug-monitor-rules'));
    }

    /**
     * JSON files present on disk that have not been synced yet.
     *
     * @return string[] Absolute file paths.
     */
    public function pending(): array
    {
        $path = $this->rulesPath();

        if (! File::isDirectory($path)) {
            return [];
        }

        $applied = DebugRuleSync::pluck('rule_file')->all();

        return collect(File::glob(rtrim($path, '/').'/*.json'))
            ->reject(fn (string $file) => in_array(basename($file), $applied, true))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Apply all pending rule files to the database.
     *
     * @return array{applied: array<int, string>, skipped: array<int, array>, errors: array<int, array>}
     */
    public function sync(): array
    {
        $summary = ['applied' => [], 'skipped' => [], 'errors' => []];

        $pending = $this->pending();

        if (empty($pending)) {
            return $summary;
        }

        $batch = (int) (DebugRuleSync::max('batch') ?? 0) + 1;

        foreach ($pending as $file) {
            $ruleFile = basename($file);

            [$data, $error] = $this->parseFile($file);

            if ($error !== null) {
                // Invalid files are left unrecorded so they can be fixed and re-synced.
                $summary['errors'][] = ['file' => $ruleFile, 'message' => $error];
                continue;
            }

            if (DebugRule::where('name', $data['name'])->exists()) {
                DebugRuleSync::create([
                    'rule_file' => $ruleFile,
                    'name' => $data['name'],
                    'debug_rule_id' => null,
                    'status' => 'skipped',
                    'batch' => $batch,
                ]);

                $summary['skipped'][] = [
                    'file' => $ruleFile,
                    'name' => $data['name'],
                    'reason' => 'A rule with this name already exists in the database.',
                ];

                continue;
            }

            $rule = DebugRule::create($data);

            DebugRuleSync::create([
                'rule_file' => $ruleFile,
                'name' => $data['name'],
                'debug_rule_id' => $rule->id,
                'status' => 'applied',
                'batch' => $batch,
            ]);

            $summary['applied'][] = $data['name'];
        }

        return $summary;
    }

    /**
     * Read and validate a single rule file.
     *
     * @return array{0: array|null, 1: string|null} [data, errorMessage]
     */
    public function parseFile(string $path): array
    {
        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return [null, 'File does not contain a valid JSON object.'];
        }

        // The `json` validation rule expects a string; encode nested objects so a
        // file may express `expected_json` as either an object or a JSON string.
        if (isset($decoded['expected_json']) && is_array($decoded['expected_json'])) {
            $decoded['expected_json'] = json_encode($decoded['expected_json']);
        }

        $validator = Validator::make($decoded, DebugRule::validationRules());

        if ($validator->fails()) {
            return [null, $validator->errors()->first()];
        }

        return [$validator->validated(), null];
    }
}
