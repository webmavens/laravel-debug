<?php

namespace Webmavens\DebugMonitor\Models;

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
    ];

    protected $casts = [
        'expected_json' => 'array',
        'last_debug_log' => 'array',
        'suppress' => 'boolean',
        'last_run_at' => 'datetime',
    ];

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

    public function logs()
    {
        return $this->hasMany(DebugRuleLog::class, 'debug_rule_id');
    }
}
