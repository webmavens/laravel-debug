<?php

namespace Webmavens\DebugMonitor\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Webmavens\DebugMonitor\Support\QueryGuard;

class DebugRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'group_name',
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
        'suppressed_until',
        'last_run_at',
        'last_status',
        'failing_since',
        'last_duration_ms',
        'failure_notified_at',
    ];

    protected $casts = [
        'expected_json' => 'array',
        'last_debug_log' => 'array',
        'suppress' => 'boolean',
        'last_run_at' => 'datetime',
        'failure_notified_at' => 'datetime',
        'suppressed_until' => 'datetime',
        'failing_since' => 'datetime',
        'last_duration_ms' => 'integer',
        // Form input arrives as strings; keep numbers as numbers (e.g. in rule files).
        'frequency_minutes' => 'integer',
        'expected_rows' => 'integer',
    ];

    /**
     * Validation rules shared by the web form and the file-sync importer,
     * so rules created through either path validate identically.
     */
    public static function validationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'group_name' => 'nullable|string|max:100',
            'sql_query' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                // Same check as right before a run, so a rule that could never run isn't saved.
                if ($problem = QueryGuard::problem($value)) {
                    $fail($problem);
                }
            }],
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
    public function isDue(?CarbonInterface $now = null): bool
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

    /**
     * Whether an active rule has missed its schedule by more than the grace
     * period: not run within frequency + grace minutes of its last run, or,
     * if it never ran, within grace minutes of being created. A sign that the
     * scheduler or the queue worker isn't running.
     */
    public function isOverdue(?CarbonInterface $now = null, int $graceMinutes = 10): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $expectedBy = $this->last_run_at
            ? Carbon::parse($this->last_run_at)->addMinutes($this->frequency_minutes)
            : Carbon::parse($this->created_at);

        return $expectedBy->addMinutes($graceMinutes)->lt($now ?? Carbon::now());
    }

    /**
     * Whether alerts are currently held back: suppressed with no end time, or
     * with an end time still in the future.
     */
    public function isSuppressed(?CarbonInterface $now = null): bool
    {
        return (bool) $this->suppress
            && ($this->suppressed_until === null || $this->suppressed_until->gt($now ?? Carbon::now()));
    }

    /** Rules whose suppression is in effect (see isSuppressed()). */
    public function scopeSuppressed(Builder $query): Builder
    {
        return $query->where('suppress', true)->where(fn (Builder $query) => $query
            ->whereNull('suppressed_until')
            ->orWhere('suppressed_until', '>', Carbon::now()));
    }

    /**
     * Turn off suppressions whose end time has passed, so the data matches
     * what isSuppressed() already reports. Returns how many were cleared.
     */
    public static function releaseExpiredSuppressions(): int
    {
        return static::where('suppress', true)
            ->whereNotNull('suppressed_until')
            ->where('suppressed_until', '<=', Carbon::now())
            ->update(['suppress' => false, 'suppress_notes' => null, 'suppressed_until' => null]);
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
