<?php

namespace Webmavens\DebugMonitor\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleSync;

/**
 * Bridges version-controlled rule files and the database.
 *
 * Rules always *execute* from the database. Files are the version-controlled
 * source and stay linked to their DB rule via `debug_rule_syncs` (including a
 * content checksum). Sync applies new files, updates changed files (detected by
 * checksum), and skips files whose name is already taken by an unrelated DB rule
 * (the database wins). Editing or exporting a rule in the UI writes the linked
 * file back to disk so the two stay in step.
 */
class RuleSyncService
{
    /** Fields that make up a rule file (runtime state is excluded). */
    private const RULE_FIELDS = [
        'name',
        'group_name',
        'sql_query',
        'frequency_minutes',
        'importance_level',
        'expected_rows_operator',
        'expected_rows',
        'expected_json',
        'notification_level',
        'status',
    ];

    public function rulesPath(): string
    {
        return rtrim((string) config('debug-monitor.rules_path', base_path('debug-monitor-rules')), '/');
    }

    /**
     * All rule files currently on disk (absolute paths, sorted).
     *
     * @return string[]
     */
    public function files(): array
    {
        if (! File::isDirectory($this->rulesPath())) {
            return [];
        }

        return collect(File::glob($this->rulesPath().'/*.json'))->sort()->values()->all();
    }

    /**
     * IDs of rules whose linked file is still on disk. Only these count as
     * "file" rules, so a manually deleted file immediately drops the badge
     * and makes the rule exportable again.
     *
     * @return int[]
     */
    public function fileRuleIds(): array
    {
        $existingFiles = collect($this->files())->map(fn ($file) => basename($file))->all();

        return DebugRuleSync::whereNotNull('debug_rule_id')
            ->whereIn('rule_file', $existingFiles)
            ->pluck('debug_rule_id')
            ->all();
    }

    /**
     * Number of files that a sync would create or update (new + changed).
     */
    public function pendingCount(): int
    {
        $records = DebugRuleSync::pluck('checksum', 'rule_file');

        return collect($this->files())
            ->filter(function (string $file) use ($records) {
                $base = basename($file);

                return ! $records->has($base) || $records->get($base) !== md5_file($file);
            })
            ->count();
    }

    /**
     * Remove sync records whose file has disappeared from disk (so the rule
     * reverts to a plain DB rule). Returns the names that were unlinked.
     *
     * @return array<int,string>
     */
    public function reconcile(): array
    {
        $existing = collect($this->files())->map(fn (string $file) => basename($file))->all();

        $orphans = DebugRuleSync::when(
            ! empty($existing),
            fn ($query) => $query->whereNotIn('rule_file', $existing)
        )->get();

        return $orphans->each->delete()->pluck('name')->all();
    }

    /**
     * Apply the rules directory to the database.
     *
     * @return array{applied: array<int,string>, updated: array<int,string>, skipped: array<int,array>, errors: array<int,array>, unlinked: array<int,string>}
     */
    public function sync(): array
    {
        $summary = ['applied' => [], 'updated' => [], 'skipped' => [], 'errors' => [], 'unlinked' => []];

        $summary['unlinked'] = $this->reconcile();

        $files = $this->files();

        if (empty($files)) {
            return $summary;
        }

        $batch = $this->nextBatch();

        foreach ($files as $file) {
            $ruleFile = basename($file);

            [$data, $error] = $this->parseFile($file);

            if ($error !== null) {
                // Invalid files are left unrecorded so they can be fixed and re-synced.
                $summary['errors'][] = ['file' => $ruleFile, 'message' => $error];
                continue;
            }

            $checksum = md5_file($file);
            $record = DebugRuleSync::where('rule_file', $ruleFile)->first();

            // Already linked to a live rule → update it only if the file changed.
            if ($record && $record->debug_rule_id && ($rule = $record->rule)) {
                if ($record->checksum === $checksum) {
                    continue;
                }

                $rule->update($data);
                $record->update(['name' => $data['name'], 'checksum' => $checksum, 'batch' => $batch]);
                $summary['updated'][] = $data['name'];
                continue;
            }

            // Not yet linked (new file, or its rule was deleted) → the DB wins on name.
            if (DebugRule::where('name', $data['name'])->exists()) {
                $summary['skipped'][] = [
                    'file' => $ruleFile,
                    'name' => $data['name'],
                    'reason' => 'A rule with this name already exists in the database.',
                ];
                continue;
            }

            $rule = DebugRule::create($data);

            DebugRuleSync::updateOrCreate(
                ['rule_file' => $ruleFile],
                ['name' => $data['name'], 'debug_rule_id' => $rule->id, 'checksum' => $checksum, 'batch' => $batch]
            );

            $summary['applied'][] = $data['name'];
        }

        return $summary;
    }

    /**
     * Export a database rule to a version-controlled file (creating the link),
     * or rewrite its existing file if it already has one.
     *
     * @return array{file: string, created: bool}
     */
    public function export(DebugRule $rule): array
    {
        if (($record = $rule->syncRecord) && $record->rule_file) {
            // If the file was deleted on disk, rewriting it is a re-creation, not an update.
            $missing = ! File::exists($this->rulesPath().'/'.$record->rule_file);

            $this->writeLinkedFile($rule);

            return ['file' => $record->rule_file, 'created' => $missing];
        }

        $ruleFile = $this->uniqueFilename($rule->name);
        $contents = $this->serialize($rule);

        File::ensureDirectoryExists($this->rulesPath());
        File::put($this->rulesPath().'/'.$ruleFile, $contents);

        DebugRuleSync::updateOrCreate(
            ['rule_file' => $ruleFile],
            ['name' => $rule->name, 'debug_rule_id' => $rule->id, 'checksum' => md5($contents), 'batch' => $this->nextBatch()]
        );

        return ['file' => $ruleFile, 'created' => true];
    }

    /**
     * Rewrite the file linked to a rule (used after a UI edit). No-op for
     * rules that have no linked file. Returns the filename written, or null.
     */
    public function writeLinkedFile(DebugRule $rule): ?string
    {
        $record = $rule->syncRecord;

        if (! $record || ! $record->rule_file) {
            return null;
        }

        $contents = $this->serialize($rule);

        File::ensureDirectoryExists($this->rulesPath());
        File::put($this->rulesPath().'/'.$record->rule_file, $contents);

        $record->update(['name' => $rule->name, 'checksum' => md5($contents)]);

        return $record->rule_file;
    }

    /**
     * Delete the file and sync record linked to a rule (used on rule deletion).
     */
    public function deleteLinkedFile(DebugRule $rule): void
    {
        $record = $rule->syncRecord;

        if (! $record) {
            return;
        }

        if ($record->rule_file) {
            $path = $this->rulesPath().'/'.$record->rule_file;

            if (File::exists($path)) {
                File::delete($path);
            }
        }

        $record->delete();
    }

    /**
     * Read and validate a single rule file.
     *
     * @return array{0: array|null, 1: string|null} [data, errorMessage]
     */
    public function parseFile(string $path): array
    {
        $decoded = json_decode(File::get($path), true);

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

        $data = $validator->validated();

        if (array_key_exists('group_name', $data)) {
            $data['group_name'] = trim((string) $data['group_name']) === '' ? null : trim($data['group_name']);
        }

        return [$data, null];
    }

    /** Serialize a rule to pretty-printed JSON file contents. */
    private function serialize(DebugRule $rule): string
    {
        return json_encode($rule->only(self::RULE_FIELDS), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
    }

    /** A slug-based filename for a rule name, unique within the rules directory. */
    private function uniqueFilename(string $name): string
    {
        $slug = Str::slug($name) ?: 'rule';
        $filename = $slug.'.json';
        $i = 1;

        while (File::exists($this->rulesPath().'/'.$filename)) {
            $filename = $slug.'-'.$i++.'.json';
        }

        return $filename;
    }

    private function nextBatch(): int
    {
        return (int) (DebugRuleSync::max('batch') ?? 0) + 1;
    }
}
