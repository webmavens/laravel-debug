<?php

namespace Webmavens\DebugMonitor\Models;

use Illuminate\Database\Eloquent\Model;

class DebugRuleLog extends Model
{
    protected $table = 'debug_rule_logs';

    protected $fillable = [
        'debug_rule_id', 'result', 'status', 'duration_ms',
    ];

    protected $casts = [
        'result' => 'array'
    ];

    public function rule()
    {
        return $this->belongsTo(DebugRule::class, 'debug_rule_id');
    }
}
