<?php

namespace Webmavens\DebugMonitor\View\Composers;

use Webmavens\DebugMonitor\Models\DebugRuleLog;
use Illuminate\View\View;

class RecentErrorsComposer
{
    public function compose(View $view): void
    {
        $view->with(
            'recentErrors',
            DebugRuleLog::where('status', 'failed')
                ->latest()
                ->limit(5)
                ->get()
        );
    }
}
