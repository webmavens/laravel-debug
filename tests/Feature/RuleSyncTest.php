<?php

use Webmavens\DebugMonitor\Models\DebugRule;
use Webmavens\DebugMonitor\Models\DebugRuleSync;
use Webmavens\DebugMonitor\Services\RuleSyncService;

/**
 * These tests require the package test harness (Orchestra Testbench + a
 * database migration run). They document the intended sync behaviour and run
 * once the suite is wired up. See tests/Feature/DebugMonitorAccessTest.php.
 */
uses(Tests\TestCase::class);

beforeEach(function () {
    $this->rulesDir = sys_get_temp_dir().'/debug-monitor-rules-'.uniqid();
    mkdir($this->rulesDir);
    config(['debug-monitor.rules_path' => $this->rulesDir]);
});

afterEach(function () {
    array_map('unlink', glob($this->rulesDir.'/*'));
    @rmdir($this->rulesDir);
});

function writeRuleFile(string $dir, string $file, array $data): void
{
    file_put_contents($dir.'/'.$file, json_encode($data));
}

$validRule = [
    'name' => 'Users missing email',
    'sql_query' => 'SELECT * FROM users WHERE email IS NULL',
    'frequency_minutes' => 15,
    'importance_level' => 'high',
    'expected_rows_operator' => '=',
    'expected_rows' => 0,
    'expected_json' => null,
    'notification_level' => 'default',
    'status' => 'active',
];

test('a pending rule file is applied to the database', function () use ($validRule) {
    writeRuleFile($this->rulesDir, 'missing-email.json', $validRule);

    $summary = app(RuleSyncService::class)->sync();

    expect($summary['applied'])->toHaveCount(1);
    expect(DebugRule::where('name', 'Users missing email')->exists())->toBeTrue();
    expect(DebugRuleSync::where('rule_file', 'missing-email.json')->where('status', 'applied')->exists())->toBeTrue();
});

test('an already-applied file is skipped on the next sync', function () use ($validRule) {
    writeRuleFile($this->rulesDir, 'missing-email.json', $validRule);

    $service = app(RuleSyncService::class);
    $service->sync();
    $second = $service->sync();

    expect($second['applied'])->toBeEmpty();
    expect(DebugRule::where('name', 'Users missing email')->count())->toBe(1);
});

test('a file whose name already exists in the DB is skipped (DB wins)', function () use ($validRule) {
    DebugRule::create($validRule);

    writeRuleFile($this->rulesDir, 'missing-email.json', $validRule);

    $summary = app(RuleSyncService::class)->sync();

    expect($summary['applied'])->toBeEmpty();
    expect($summary['skipped'])->toHaveCount(1);
    expect(DebugRule::where('name', 'Users missing email')->count())->toBe(1);
    expect(DebugRuleSync::where('rule_file', 'missing-email.json')->where('status', 'skipped')->exists())->toBeTrue();
});

test('an invalid file is reported as an error and left unrecorded', function () {
    file_put_contents($this->rulesDir.'/broken.json', '{ not valid json');

    $summary = app(RuleSyncService::class)->sync();

    expect($summary['errors'])->toHaveCount(1);
    expect(DebugRuleSync::where('rule_file', 'broken.json')->exists())->toBeFalse();
});
