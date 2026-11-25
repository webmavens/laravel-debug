<?php

namespace Webmavens\DebugMonitor\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DebugRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sql_query',
        'frequency_minutes',
        'importance_level',
        'expected_rows_operator',
        'expected_rows',
        'expected_json',
        'notification_level',
        'last_debug_log',
        'status',
        'suppress',
        'suppress_notes',
        'last_run_at',
        'last_status',
        'failure_notified_at',
    ];

    protected $casts = [
        'expected_json' => 'array',
        'last_debug_log' => 'array',
        'suppress' => 'boolean',
        'last_run_at' => 'datetime',
        'failure_notified_at' => 'datetime',
    ];

    /**
     * Validation rules shared by the web form and the file-sync importer,
     * so rules created through either path validate identically.
     */
    public static function validationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sql_query' => 'required|string',
            'frequency_minutes' => 'required|integer|min:1',
            'importance_level' => 'required|in:high,medium,low',
            'expected_rows_operator' => 'required|in:=,>,<,>=,<=',
            'expected_rows' => 'nullable|integer|min:0',
            'expected_json' => 'nullable|json',
            'notification_level' => 'required|in:none,default,urgent',
            'status' => 'required|in:active,inactive',
        ];
    }

    public function setExpectedJsonAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['expected_json'] = null;

            return;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        $this->attributes['expected_json'] = json_encode($value);
    }

    /**
     * Whether the rule is due for a scheduled run: never run yet, or at least
     * frequency_minutes since the last run.
     */
    public function isDue(?Carbon $now = null): bool
    {
        if (! $this->last_run_at) {
            return true;
        }

        // Compare whole minutes: a 15-minute rule that ran at 10:00:05 is due at
        // the 10:15:00 tick, not one tick later.
        $lastRun = Carbon::parse($this->last_run_at)->startOfMinute();
        $tick = ($now ?? Carbon::now())->copy()->startOfMinute();

        return $lastRun->diffInMinutes($tick) >= $this->frequency_minutes;
    }

    public function logs()
    {
        return $this->hasMany(DebugRuleLog::class, 'debug_rule_id');
    }

    /**
     * The sync record linking this rule to a version-controlled JSON file
     * (present only for rules that originated from, or were exported to, a file).
     */
    public function syncRecord()
    {
        return $this->hasOne(DebugRuleSync::class, 'debug_rule_id');
    }

    public function isFileRule(): bool
    {
        return $this->syncRecord()->exists();
    }
}
