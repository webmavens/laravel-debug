<?php

namespace Webmavens\DebugMonitor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

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

    public function logs()
    {
        return $this->hasMany(DebugRuleLog::class, 'debug_rule_id');
    }
}
