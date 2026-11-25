<?php

namespace Webmavens\DebugMonitor\View\Composers;

use Illuminate\View\View;
use Webmavens\DebugMonitor\Models\DebugRule;

class RecentFailedRulesComposer
{
    /** How many failing rules the sidebar lists before linking to the full list. */
    private const LIMIT = 8;

    public function compose(View $view): void
    {
        // Active rules whose latest run failed, most recent first (a deactivated
        // rule is paused, not failing). One extra row tells the sidebar whether
        // to show a "View all" link, without a count query.
        $rules = DebugRule::where('status', 'active')
            ->where('last_status', 'failed')
            ->orderByDesc('last_run_at')
            ->limit(self::LIMIT + 1)
            ->get(['id', 'name']);

        $view->with([
            'recentFailedRules' => $rules->take(self::LIMIT),
            'hasMoreFailedRules' => $rules->count() > self::LIMIT,
        ]);
    }
}
