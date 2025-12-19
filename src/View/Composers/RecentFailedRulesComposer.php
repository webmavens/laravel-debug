<?php

namespace Webmavens\DebugMonitor\View\Composers;

use Webmavens\DebugMonitor\Models\DebugRule;
use Illuminate\View\View;

class RecentFailedRulesComposer
{
    public function compose(View $view): void
    {
        $view->with(
            'recentFailedRules',
            DebugRule::whereHas('logs', function ($q) {
                $q->where('status', 'failed');
            })
            ->with(['logs' => function ($q) {
                $q->where('status', 'failed')
                ->latest()
                ->limit(1);
            }])
            ->latest()
            ->get()
        );
    }
}
