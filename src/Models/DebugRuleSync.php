<?php

namespace Webmavens\DebugMonitor\Models;

use Illuminate\Database\Eloquent\Model;

class DebugRuleSync extends Model
{
    protected $table = 'debug_rule_syncs';

    protected $fillable = [
        'rule_file',
        'name',
        'debug_rule_id',
        'status',
        'batch',
    ];

    public function rule()
    {
        return $this->belongsTo(DebugRule::class, 'debug_rule_id');
    }
}
